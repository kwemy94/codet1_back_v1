<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Paiement extends Model
{
    protected $fillable = [
        'reference', 'membre_id', 'moyen_paiement_id', 'exercice_id',
        'carte_developpement_id', 'contribution_id', 'date_paiement',
        'montant', 'canal', 'statut', 'observation', 'enregistre_par',
    ];

    protected function casts(): array
    {
        return ['date_paiement' => 'datetime'];
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    public function moyenPaiement(): BelongsTo
    {
        return $this->belongsTo(MoyenPaiement::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function carte(): BelongsTo
    {
        return $this->belongsTo(CarteDeveloppement::class, 'carte_developpement_id');
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(TransactionMobile::class);
    }

    public function recu(): HasOne
    {
        return $this->hasOne(Recu::class);
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    public function estValide(): bool
    {
        return $this->statut === 'valide';
    }

    /** Contrainte d'exclusion : carte OU contribution, jamais les deux, jamais aucune. */
    public function objetValide(): bool
    {
        return ((bool) $this->carte_developpement_id) !== ((bool) $this->contribution_id);
    }

    public function scopeValides($query)
    {
        return $query->where('statut', 'valide');
    }
}
