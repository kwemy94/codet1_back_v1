<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionMobile extends Model
{
    protected $table = 'transactions_mobiles';

    protected $fillable = [
        'paiement_id', 'operateur', 'reference_operateur', 'numero_telephone',
        'date_initiation', 'date_confirmation', 'statut', 'message_retour', 'payload_retour',
    ];

    protected function casts(): array
    {
        return [
            'date_initiation'   => 'datetime',
            'date_confirmation' => 'datetime',
            'payload_retour'    => 'array',
        ];
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }
}
