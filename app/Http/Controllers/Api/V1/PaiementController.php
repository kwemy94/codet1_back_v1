<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\InitierPaiementRequest;
use App\Http\Requests\PaiementManuelRequest;
use App\Http\Resources\PaiementResource;
use App\Models\CarteDeveloppement;
use App\Models\Paiement;
use App\Services\PaiementService;
use App\Services\TraitementLotService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaiementController extends Controller
{
    public function __construct(
        private PaiementService $paiements,
        private TraitementLotService $lots,
    ) {}

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

    /**
     * Encaissement en lot de plusieurs cartes.
     *
     * Le mode « solde » règle chaque carte à hauteur de ce qu'il lui reste :
     * c'est la situation courante quand le trésorier revient du village avec
     * les espèces collectées. Le mode « montant » applique la même somme à
     * chacune, plafonnée au solde — un versement uniforme, par exemple lors
     * d'une collecte au congrès.
     */
    public function encaisserEnLot(Request $requete)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate([
            'carte_ids' => ['required', 'array', 'min:1', 'max:500'],
            'carte_ids.*' => ['integer', 'exists:cartes_developpement,id'],
            'moyen_paiement_id' => ['required', 'exists:moyens_paiement,id'],
            'mode' => ['required', 'in:solde,montant'],
            'montant' => ['required_if:mode,montant', 'nullable', 'integer', 'min:1'],
            'observation' => ['nullable', 'string', 'max:500'],
        ]);

        $cartes = CarteDeveloppement::with('membre', 'exercice')
            ->whereIn('id', $donnees['carte_ids'])
            ->get();

        $rapport = $this->lots->executer(
            $cartes,
            function (CarteDeveloppement $carte) use ($donnees) {
                $montant = $donnees['mode'] === 'solde'
                    ? $carte->solde
                    : min((int) $donnees['montant'], $carte->solde);

                if ($montant <= 0) {
                    throw ValidationException::withMessages([
                        'montant' => 'Cette carte est déjà soldée.',
                    ]);
                }

                $paiement = $this->paiements->enregistrerManuel([
                    'carte_developpement_id' => $carte->id,
                    'moyen_paiement_id' => $donnees['moyen_paiement_id'],
                    'montant' => $montant,
                    'observation' => $donnees['observation'] ?? null,
                ]);

                return [
                    'paiement_id' => $paiement->id,
                    'reference' => $paiement->reference,
                    'montant' => $montant,
                    'solde' => $carte->fresh()->solde,
                ];
            },
            fn (CarteDeveloppement $carte) => [
                'carte_id' => $carte->id,
                'numero_carte' => $carte->numero_carte,
                'nom_complet' => $carte->membre?->nom_complet,
                'matricule' => $carte->membre?->matricule,
            ],
        );

        $encaisse = array_sum(array_column($rapport['reussites'], 'montant'));
        $rapport['montant_encaisse'] = $encaisse;

        $reussites = count($rapport['reussites']);
        $echecs = count($rapport['echecs']);

        $message = $echecs === 0
            ? "{$reussites} encaissement(s) enregistré(s), soit ".number_format($encaisse, 0, ',', ' ').' FCFA.'
            : ($reussites === 0
                ? "Aucun encaissement n'a abouti : {$echecs} échec(s). Voir le détail."
                : "{$reussites} encaissement(s) pour ".number_format($encaisse, 0, ',', ' ')." FCFA, {$echecs} en échec.");

        return $this->reponse($rapport, $message);
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
            'reference' => $paiement->reference,
            'statut' => $paiement->statut,
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
