<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Affectation extends Model
{
    protected $fillable = ['paiement_id', 'destination_fonds_id', 'exercice_id', 'montant_affecte'];

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(DestinationFonds::class, 'destination_fonds_id');
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }
}
