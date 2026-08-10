<?php

namespace App\Services;

use App\Models\JournalAction;
use Illuminate\Database\Eloquent\Model;

/**
 * Journalisation générique : en référençant l'entité et l'identifiant concernés,
 * elle couvre automatiquement les modules ajoutés ultérieurement (CDC §12).
 */
class JournalService
{
    public function tracer(
        string $typeAction,
        ?Model $enregistrement = null,
        ?array $ancienneValeur = null,
        ?array $nouvelleValeur = null,
        ?int $membreId = null,
    ): JournalAction {
        return JournalAction::create([
            'type_action'                => $typeAction,
            'entite_concernee'           => $enregistrement ? class_basename($enregistrement) : null,
            'identifiant_enregistrement' => $enregistrement?->getKey(),
            'user_id'                    => auth()->id(),
            'membre_id'                  => $membreId,
            'date_heure'                 => now(),
            'adresse_ip'                 => request()->ip(),
            'ancienne_valeur'            => $ancienneValeur,
            'nouvelle_valeur'            => $nouvelleValeur,
        ]);
    }
}
