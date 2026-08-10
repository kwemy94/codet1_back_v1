<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CarteResource;
use App\Http\Resources\MembreResource;
use App\Http\Resources\PaiementResource;
use App\Models\Exercice;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Espace personnel du membre (CDC §8) : le membre n'accède qu'à ses propres
 * données, quel que soit l'identifiant transmis.
 */
class EspaceMembreController extends Controller
{
    public function tableauDeBord(Request $requete)
    {
        $membre   = $this->membre($requete);
        $exercice = Exercice::courant();
        $carte    = $exercice ? $membre->carteDeLExercice($exercice->id) : null;
        $carte?->load('exercice', 'typeCarte', 'tarif.repartitions.destination');

        return $this->reponse([
            'membre'            => new MembreResource($membre->load('categorie', 'ville.pays')),
            'exercice_courant'  => $exercice?->annee,
            'carte_en_cours'    => $carte ? new CarteResource($carte) : null,
            'solde_annuel'      => $carte?->solde ?? 0,
            'total_cotise'      => (int) $membre->paiements()->valides()->sum('montant'),
            'nombre_cartes'     => $membre->cartes()->count(),
        ]);
    }

    public function profil(Request $requete)
    {
        return $this->reponse(new MembreResource($this->membre($requete)->load('categorie', 'ville.pays')));
    }

    /** Le membre ne peut modifier que ses coordonnées, jamais sa catégorie ni son statut. */
    public function modifierProfil(Request $requete)
    {
        $membre  = $this->membre($requete);
        $donnees = $requete->validate([
            'telephone'                 => ['sometimes', 'string', 'max:30'],
            'email'                     => ['sometimes', 'nullable', 'email', 'max:255'],
            'profession'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'ville_id'                  => ['sometimes', 'nullable', 'exists:villes,id'],
            'quartier'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'adresse'                   => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_urgence_nom'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_urgence_telephone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $membre->update($donnees);

        return $this->reponse(new MembreResource($membre->fresh('categorie', 'ville')), 'Profil mis à jour.');
    }

    public function mesCartes(Request $requete)
    {
        return CarteResource::collection(
            $this->membre($requete)->cartes()->with('exercice', 'tarif')->orderByDesc('date_emission')->get()
        );
    }

    public function mesPaiements(Request $requete)
    {
        return PaiementResource::collection(
            $this->membre($requete)->paiements()
                ->with('moyenPaiement', 'recu', 'affectations.destination')
                ->orderByDesc('date_paiement')
                ->paginate(25)
        );
    }

    public function mesContributions(Request $requete)
    {
        return $this->reponse(
            $this->membre($requete)->contributions()->with('type', 'exercice')->orderByDesc('date_contribution')->get()
        );
    }

    private function membre(Request $requete)
    {
        $membre = $requete->user()->membre;

        if (! $membre) {
            throw ValidationException::withMessages([
                'compte' => "Ce compte n'est rattaché à aucune fiche membre.",
            ]);
        }

        return $membre;
    }
}
