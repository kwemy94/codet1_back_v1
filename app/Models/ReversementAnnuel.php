<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReversementAnnuel extends Model
{
    protected $table = 'reversements_annuels';

    protected $fillable = [
        'exercice_id', 'assiette', 'taux_applique', 'montant_reverse', 'detail',
        'date_calcul', 'date_cloture', 'statut', 'calcule_par',
    ];

    protected function casts(): array
    {
        return [
            'date_calcul'   => 'datetime',
            'date_cloture'  => 'datetime',
            'taux_applique' => 'decimal:2',
            'detail'        => 'array',
        ];
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function estCloture(): bool
    {
        return $this->statut === 'cloture';
    }
}
