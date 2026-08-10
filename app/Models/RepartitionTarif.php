<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne de la clé de répartition d'un tarif : une destination, un montant. */
class RepartitionTarif extends Model
{
    protected $table = 'repartitions_tarifs';

    protected $fillable = ['tarif_carte_id', 'destination_fonds_id', 'montant'];

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(TarifCarte::class, 'tarif_carte_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(DestinationFonds::class, 'destination_fonds_id');
    }
}
