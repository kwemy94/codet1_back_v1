<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestinataireCourriel extends Model
{
    protected $table = 'destinataires_courriels';

    protected $fillable = [
        'campagne_courriel_id', 'membre_id', 'adresse',
        'statut', 'date_traitement', 'message_erreur',
    ];

    protected function casts(): array
    {
        return ['date_traitement' => 'datetime'];
    }

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagneCourriel::class, 'campagne_courriel_id');
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }
}
