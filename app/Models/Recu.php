<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recu extends Model
{
    protected $fillable = ['paiement_id', 'numero_recu', 'date_emission', 'fichier'];

    protected function casts(): array
    {
        return ['date_emission' => 'datetime'];
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }
}
