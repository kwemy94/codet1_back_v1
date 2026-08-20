<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Exécution d'une opération sur un lot d'éléments.
 *
 * Chaque élément est traité dans sa propre transaction : une carte déjà émise
 * ou un tarif manquant fait échouer cette ligne seulement, jamais le lot. Le
 * rapport final nomme chaque échec et sa raison, de sorte que le secrétariat
 * sache exactement quoi reprendre — un « 12 sur 40 » sans détail serait
 * inexploitable.
 */
class TraitementLotService
{
    /**
     * @param  iterable  $elements   Éléments à traiter
     * @param  callable  $traitement fn($element) => array  Description de la réussite
     * @param  callable  $decrire    fn($element) => array  Identité de l'élément, pour le rapport
     */
    public function executer(iterable $elements, callable $traitement, callable $decrire): array
    {
        $reussites = [];
        $echecs = [];

        foreach ($elements as $element) {
            $identite = $decrire($element);

            try {
                $resultat = DB::transaction(fn () => $traitement($element));
                $reussites[] = $identite + ($resultat ?? []);
            } catch (ValidationException $erreur) {
                // Refus métier attendu : carte déjà émise, exercice clôturé,
                // montant supérieur au solde. Le message est déjà lisible.
                $echecs[] = $identite + ['motif' => $this->premierMessage($erreur)];
            } catch (Throwable $erreur) {
                Log::error('Échec dans un traitement en lot', [
                    'element' => $identite,
                    'erreur'  => $erreur->getMessage(),
                ]);

                $echecs[] = $identite + ['motif' => "Une erreur technique est survenue sur cette ligne."];
            }
        }

        return [
            'traites'   => count($reussites) + count($echecs),
            'reussites' => $reussites,
            'echecs'    => $echecs,
        ];
    }

    private function premierMessage(ValidationException $erreur): string
    {
        $messages = $erreur->validator->errors()->all();

        return $messages[0] ?? 'Opération refusée.';
    }
}
