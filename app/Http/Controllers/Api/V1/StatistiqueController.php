<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Affectation;
use App\Models\CarteDeveloppement;
use App\Models\Contribution;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\Paiement;
use App\Services\ReversementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs du tableau de bord (CDC §21) — périmètre V1 :
 * finances, membres et cotisations. Les indicateurs « projets » relèvent de la V2.
 */
class StatistiqueController extends Controller
{
    public function __construct(private ReversementService $reversements) {}

    public function tableauDeBord(Request $requete)
    {
        $exercice = $requete->query('exercice_id')
            ? Exercice::findOrFail($requete->query('exercice_id'))
            : Exercice::courant();

        abort_if(! $exercice, 404, 'Aucun exercice ouvert.');

        $cartes = CarteDeveloppement::where('exercice_id', $exercice->id);

        return $this->reponse([
            'exercice'   => $exercice->annee,
            'finances'   => [
                'total_cotisations'   => (int) Paiement::valides()->where('exercice_id', $exercice->id)->whereNotNull('carte_developpement_id')->sum('montant'),
                'total_dons'          => (int) Paiement::valides()->where('exercice_id', $exercice->id)->whereNotNull('contribution_id')->sum('montant'),
                'total_recettes'      => (int) Paiement::valides()->where('exercice_id', $exercice->id)->sum('montant'),
                'par_destination'     => $this->parDestination($exercice),
                'reversement_estime'  => $this->reversements->simuler($exercice),
            ],
            'membres'    => [
                'total'           => Membre::count(),
                'actifs'          => Membre::actifs()->count(),
                'inactifs'        => Membre::where('statut', 'inactif')->count(),
                'nouveaux_annee'  => Membre::whereYear('date_adhesion', $exercice->annee)->count(),
                'par_sexe'        => Membre::select('sexe', DB::raw('count(*) as total'))->groupBy('sexe')->pluck('total', 'sexe'),
                'par_categorie'   => $this->parCategorie(),
                'par_pays'        => $this->parPays(),
            ],
            'cotisations' => [
                'cartes_emises'       => (clone $cartes)->count(),
                'membres_a_jour'      => (clone $cartes)->where('statut', 'soldee')->count(),
                'membres_en_retard'   => (clone $cartes)->whereIn('statut', ['impayee', 'partielle'])->count(),
                'reste_a_recouvrer'   => (int) (clone $cartes)->selectRaw($this->resteARecouvrer())->value('reste'),
                'taux_paiement'       => $this->tauxPaiement($exercice),
            ],
        ]);
    }

    /** Évolution des recettes sur les N derniers exercices (graphique du tableau de bord). */
    public function evolutionRecettes(Request $requete)
    {
        $donnees = Paiement::valides()
            ->join('exercices', 'exercices.id', '=', 'paiements.exercice_id')
            ->select('exercices.annee', DB::raw('sum(paiements.montant) as total'))
            ->groupBy('exercices.annee')
            ->orderBy('exercices.annee')
            ->get();

        return $this->reponse($donnees);
    }

    /**
     * Reste à recouvrer.
     *
     * Les deux colonnes sont UNSIGNED : en MySQL, leur soustraction reste
     * UNSIGNED et déborde dès qu'une carte est réglée au-delà de son montant
     * (paiement excédentaire, correction de tarif à la baisse). On convertit
     * donc en SIGNED, et on plafonne à zéro : un trop-perçu ne doit pas venir
     * diminuer l'impayé des autres membres.
     */
    private function resteARecouvrer(): string
    {
        return 'COALESCE(SUM(GREATEST(CAST(montant_du AS SIGNED) - CAST(montant_regle AS SIGNED), 0)), 0) as reste';
    }

    private function parDestination(Exercice $exercice)
    {
        return Affectation::where('affectations.exercice_id', $exercice->id)
            ->join('destinations_fonds', 'destinations_fonds.id', '=', 'affectations.destination_fonds_id')
            ->join('paiements', 'paiements.id', '=', 'affectations.paiement_id')
            ->where('paiements.statut', 'valide')
            ->select('destinations_fonds.code', DB::raw('sum(affectations.montant_affecte) as total'))
            ->groupBy('destinations_fonds.code')
            ->pluck('total', 'code');
    }

    private function parCategorie()
    {
        return Membre::join('categories_membres', 'categories_membres.id', '=', 'membres.categorie_membre_id')
            ->select('categories_membres.libelle', DB::raw('count(*) as total'))
            ->groupBy('categories_membres.libelle')
            ->pluck('total', 'libelle');
    }

    private function parPays()
    {
        return Membre::join('villes', 'villes.id', '=', 'membres.ville_id')
            ->join('pays', 'pays.id', '=', 'villes.pays_id')
            ->select('pays.libelle', DB::raw('count(*) as total'))
            ->groupBy('pays.libelle')
            ->pluck('total', 'libelle');
    }

    private function tauxPaiement(Exercice $exercice): float
    {
        $total = CarteDeveloppement::where('exercice_id', $exercice->id)->count();

        if ($total === 0) {
            return 0.0;
        }

        $soldees = CarteDeveloppement::where('exercice_id', $exercice->id)->where('statut', 'soldee')->count();

        return round($soldees * 100 / $total, 2);
    }
}
