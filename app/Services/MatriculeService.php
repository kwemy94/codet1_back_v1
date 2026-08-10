<?php

namespace App\Services;

use App\Models\Membre;
use App\Models\Parametre;
use Illuminate\Support\Facades\DB;

/**
 * Génère le matricule unique du membre au format COD{AA}-{séquence sur 6 chiffres},
 * par exemple COD26-000125 (CDC §4.2). La séquence est annuelle et le verrou
 * transactionnel évite toute collision en cas de créations simultanées.
 */
class MatriculeService
{
    public function genererPour(?int $annee = null): string
    {
        $annee  = $annee ?: (int) date('Y');
        $suffixe = substr((string) $annee, -2);
        $prefixe = Parametre::valeur('PREFIXE_MATRICULE', 'COD').$suffixe;

        return DB::transaction(function () use ($prefixe) {
            $dernier = Membre::withTrashed()
                ->where('matricule', 'like', $prefixe.'-%')
                ->lockForUpdate()
                ->orderByDesc('matricule')
                ->value('matricule');

            $sequence = $dernier ? ((int) substr($dernier, -6)) + 1 : 1;

            return sprintf('%s-%06d', $prefixe, $sequence);
        });
    }
}
