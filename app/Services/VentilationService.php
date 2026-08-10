<?php

namespace App\Services;

use App\Models\Affectation;
use App\Models\DestinationFonds;
use App\Models\Paiement;

/**
 * Ventile tout paiement validé entre les destinations de fonds.
 *
 * La clé de répartition provient du tarif figé sur la carte : elle peut donc
 * comporter autant de destinations que nécessaire, y compris une seule à 100 %.
 * Règle invariante : la somme des affectations est strictement égale au montant
 * du paiement, le dernier poste absorbant le reliquat d'arrondi.
 */
class VentilationService
{
    public function ventiler(Paiement $paiement): void
    {
        $paiement->affectations()->delete();

        $repartition = $paiement->carte_developpement_id
            ? $this->repartitionCarte($paiement)
            : $this->repartitionContribution($paiement);

        $repartition = array_filter($repartition, fn ($montant) => $montant > 0);

        if (! $repartition) {
            return;
        }

        $cumul = 0;
        $identifiants = array_keys($repartition);
        $dernier = end($identifiants);

        foreach ($repartition as $destinationId => $montant) {
            $montantFinal = $destinationId === $dernier
                ? (int) $paiement->montant - $cumul
                : (int) $montant;

            if ($montantFinal <= 0) {
                continue;
            }

            Affectation::create([
                'paiement_id'          => $paiement->id,
                'destination_fonds_id' => $destinationId,
                'exercice_id'          => $paiement->exercice_id,
                'montant_affecte'      => $montantFinal,
            ]);

            $cumul += $montantFinal;
        }
    }

    /** Au prorata de la clé du tarif figé sur la carte. */
    private function repartitionCarte(Paiement $paiement): array
    {
        return $paiement->carte->tarif->loadMissing('repartitions')->repartir((int) $paiement->montant);
    }

    /** Une contribution volontaire est affectée en totalité au village. */
    private function repartitionContribution(Paiement $paiement): array
    {
        $village = DestinationFonds::where('code', 'VILLAGE')->first()
            ?? DestinationFonds::where('actif', true)->first();

        return $village ? [$village->id => (int) $paiement->montant] : [];
    }
}
