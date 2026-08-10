<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CategorieMembre;
use App\Models\DestinationFonds;
use App\Models\MoyenPaiement;
use App\Models\Parametre;
use App\Models\Pays;
use App\Models\TypeContribution;
use App\Models\Ville;
use App\Services\JournalService;
use Illuminate\Http\Request;

/**
 * Référentiels paramétrables : ajouter une catégorie, un moyen de paiement ou
 * modifier le taux de reversement ne demande aucune intervention technique.
 */
class ReferentielController extends Controller
{
    public function __construct(private JournalService $journal) {}

    public function tout()
    {
        return $this->reponse([
            'categories_membres'  => CategorieMembre::where('actif', true)->get(),
            'moyens_paiement'     => MoyenPaiement::where('actif', true)->get(),
            'destinations_fonds'  => DestinationFonds::where('actif', true)->get(),
            'types_contributions' => TypeContribution::where('actif', true)->get(),
            'pays'                => Pays::orderBy('libelle')->get(),
        ]);
    }

    public function villes(Request $requete)
    {
        return $this->reponse(
            Ville::when($requete->query('pays_id'), fn ($q, $v) => $q->where('pays_id', $v))
                ->orderBy('libelle')
                ->get()
        );
    }

    public function parametres()
    {
        $this->refuserSiNonAdministrateur();

        return $this->reponse(Parametre::orderBy('code')->get());
    }

    public function modifierParametre(Request $requete, Parametre $parametre)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate(['valeur' => ['required']]);
        $ancien  = $parametre->toArray();

        $parametre->update([
            'valeur'      => (string) $donnees['valeur'],
            'modifie_par' => $requete->user()->id,
        ]);

        $this->journal->tracer(
            'modification_parametre',
            $parametre,
            ancienneValeur: $ancien,
            nouvelleValeur: $parametre->fresh()->toArray(),
        );

        return $this->reponse($parametre->fresh(), 'Paramètre mis à jour.');
    }
}
