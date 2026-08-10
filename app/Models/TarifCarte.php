<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TarifCarte extends Model
{
    protected $table = 'tarifs_cartes';

    protected $fillable = [
        'exercice_id', 'type_carte_id', 'categorie_membre_id',
        'montant_minimum', 'date_debut_validite', 'date_fin_validite',
    ];

    protected function casts(): array
    {
        return [
            'date_debut_validite' => 'date',
            'date_fin_validite'   => 'date',
        ];
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function typeCarte(): BelongsTo
    {
        return $this->belongsTo(TypeCarte::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieMembre::class, 'categorie_membre_id');
    }

    public function repartitions(): HasMany
    {
        return $this->hasMany(RepartitionTarif::class, 'tarif_carte_id');
    }

    public function scopeActif($query)
    {
        return $query->whereNull('date_fin_validite');
    }

    /** Somme des lignes de répartition : elle doit égaler le montant minimum. */
    public function totalReparti(): int
    {
        return (int) $this->repartitions->sum('montant');
    }

    public function partsCoherentes(): bool
    {
        return $this->totalReparti() === (int) $this->montant_minimum;
    }

    /**
     * Répartit un montant encaissé au prorata de la clé du tarif.
     * Renvoie [ destination_fonds_id => montant ].
     */
    public function repartir(int $montant): array
    {
        $total = max(1, (int) $this->montant_minimum);

        return $this->repartitions
            ->mapWithKeys(fn (RepartitionTarif $ligne) => [
                $ligne->destination_fonds_id => (int) floor($montant * $ligne->montant / $total),
            ])
            ->all();
    }
}
