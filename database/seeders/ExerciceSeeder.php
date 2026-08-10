<?php

namespace Database\Seeders;

use App\Models\CategorieMembre;
use App\Models\DestinationFonds;
use App\Models\Exercice;
use App\Models\TarifCarte;
use App\Models\TypeCarte;
use Illuminate\Database\Seeder;

/**
 * Exercice courant, types de cartes et tarifs de la Carte Unique de
 * Développement, conformes aux montants du cahier des charges (§5.1).
 *
 * La carte de membre d'honneur illustre une répartition libre : la totalité
 * du montant revient au compte du CODET I, rien au groupement.
 */
class ExerciceSeeder extends Seeder
{
    public function run(): void
    {
        $annee = (int) date('Y');

        $exercice = Exercice::updateOrCreate(['annee' => $annee], [
            'date_debut' => "{$annee}-01-01",
            'date_fin'   => "{$annee}-12-31",
            'statut'     => 'ouvert',
        ]);

        $annuelle = TypeCarte::updateOrCreate(['code' => 'CARTE_ANNUELLE'], [
            'libelle'     => 'Carte unique de développement',
            'description' => 'Carte annuelle due par tout ressortissant. Le montant dépend de la catégorie.',
            'obligatoire' => true,
            'actif'       => true,
        ]);

        $honneur = TypeCarte::updateOrCreate(['code' => 'MEMBRE_HONNEUR'], [
            'libelle'     => "Carte de membre d'honneur",
            'description' => 'Carte facultative, intégralement reversée au compte du CODET I.',
            'obligatoire' => false,
            'actif'       => true,
        ]);

        $destinations = DestinationFonds::pluck('id', 'code');

        // Carte annuelle : montant et répartition par catégorie
        $tarifs = [
            'HC' => [10500, ['VILLAGE' => 5000, 'GROUPEMENT' => 2500, 'CONGRES' => 3000]],
            'FC' => [6500,  ['VILLAGE' => 3000, 'GROUPEMENT' => 1500, 'CONGRES' => 2000]],
            'HV' => [5000,  ['VILLAGE' => 2500, 'GROUPEMENT' => 2500]],
            'FV' => [3500,  ['VILLAGE' => 2000, 'GROUPEMENT' => 1500]],
        ];

        foreach ($tarifs as $code => [$montant, $repartition]) {
            $categorie = CategorieMembre::where('code', $code)->first();

            if (! $categorie) {
                continue;
            }

            $this->enregistrer($exercice, $annuelle->id, $categorie->id, $montant, $repartition, $destinations);
        }

        // Carte d'honneur : aucune catégorie, 100 % au CODET I
        $this->enregistrer($exercice, $honneur->id, null, 100000, ['CODET' => 100000], $destinations);
    }

    private function enregistrer(Exercice $exercice, int $typeId, ?int $categorieId, int $montant, array $repartition, $destinations): void
    {
        $tarif = TarifCarte::updateOrCreate(
            [
                'exercice_id'         => $exercice->id,
                'type_carte_id'       => $typeId,
                'categorie_membre_id' => $categorieId,
                'date_fin_validite'   => null,
            ],
            [
                'montant_minimum'     => $montant,
                'date_debut_validite' => "{$exercice->annee}-01-01",
            ]
        );

        $tarif->repartitions()->delete();

        foreach ($repartition as $code => $part) {
            if (! isset($destinations[$code])) {
                continue;
            }

            $tarif->repartitions()->create([
                'destination_fonds_id' => $destinations[$code],
                'montant'              => $part,
            ]);
        }
    }
}
