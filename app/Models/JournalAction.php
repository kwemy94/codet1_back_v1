<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalAction extends Model
{
    protected $table = 'journal_actions';

    protected $fillable = [
        'type_action', 'entite_concernee', 'identifiant_enregistrement', 'user_id',
        'membre_id', 'date_heure', 'adresse_ip', 'ancienne_valeur', 'nouvelle_valeur',
    ];

    protected function casts(): array
    {
        return [
            'date_heure'      => 'datetime',
            'ancienne_valeur' => 'array',
            'nouvelle_valeur' => 'array',
        ];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }
}
