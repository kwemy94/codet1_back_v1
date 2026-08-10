<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\InitierPaiementRequest;
use App\Http\Requests\PaiementManuelRequest;
use App\Http\Resources\PaiementResource;
use App\Models\Paiement;
use App\Services\PaiementService;
use Illuminate\Http\Request;

class PaiementController extends Controller
{
    public function __construct(private PaiementService $paiements) {}

    public function index(Request $requete)
    {
        $paiements = Paiement::with('membre', 'moyenPaiement', 'affectations.destination', 'recu')
            ->when($requete->query('exercice_id'), fn ($q, $v) => $q->where('exercice_id', $v))
            ->when($requete->query('membre_id'), fn ($q, $v) => $q->where('membre_id', $v))
            ->when($requete->query('statut'), fn ($q, $v) => $q->where('statut', $v))
            ->when($requete->query('du'), fn ($q, $v) => $q->whereDate('date_paiement', '>=', $v))
            ->when($requete->query('au'), fn ($q, $v) => $q->whereDate('date_paiement', '<=', $v))
            ->orderByDesc('date_paiement')
            ->paginate((int) $requete->query('par_page', 25));

        return PaiementResource::collection($paiements);
    }

    /** Paiement en ligne déclenché par le membre depuis son espace personnel. */
    public function initier(InitierPaiementRequest $requete)
    {
        $paiement = $this->paiements->initierEnLigne($requete->validated());

        return $this->reponse(
            new PaiementResource($paiement),
            'Demande de paiement envoyée. Validez la transaction sur votre téléphone.',
            201
        );
    }

    /** Saisie par un administrateur d'un encaissement effectué hors ligne. */
    public function enregistrerManuel(PaiementManuelRequest $requete)
    {
        $paiement = $this->paiements->enregistrerManuel($requete->validated());

        return $this->reponse(
            new PaiementResource($paiement->load('affectations.destination', 'recu')),
            'Paiement enregistré et ventilé.',
            201
        );
    }

    public function show(Paiement $paiement)
    {
        return $this->reponse(new PaiementResource(
            $paiement->load('membre', 'moyenPaiement', 'transaction', 'affectations.destination', 'recu', 'carte')
        ));
    }

    /** Vérification manuelle du statut auprès de l'opérateur (bouton « actualiser »). */
    public function statut(Paiement $paiement)
    {
        return $this->reponse([
            'reference'   => $paiement->reference,
            'statut'      => $paiement->statut,
            'transaction' => $paiement->transaction?->only(['statut', 'reference_operateur', 'message_retour']),
        ]);
    }

    public function annuler(Request $requete, Paiement $paiement)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate(['motif' => ['required', 'string', 'max:255']]);
        $paiement = $this->paiements->echouer($paiement, $donnees['motif']);

        return $this->reponse(new PaiementResource($paiement), 'Paiement annulé.');
    }
}
