<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contribution extends Model
{
    protected $fillable = [
        'reference', 'membre_id', 'donateur_id', 'type_contribution_id', 'exercice_id',
        'date_contribution', 'nature', 'designation', 'montant', 'motif', 'statut',
        'date_reception', 'observation', 'enregistre_par',
    ];

    protected function casts(): array
    {
        return ['date_contribution' => 'date', 'date_reception' => 'date'];
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    public function donateur(): BelongsTo
    {
        return $this->belongsTo(Donateur::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeContribution::class, 'type_contribution_id');
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function justificatifs(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function estMaterielle(): bool
    {
        return $this->nature !== 'financier';
    }

    /** Statut qui clôt la contribution selon sa nature. */
    public function statutDeCloture(): string
    {
        return $this->estMaterielle() ? 'recue' : 'encaissee';
    }

    /** Exclusion : membre OU donateur externe, jamais les deux. */
    public function origineValide(): bool
    {
        return ((bool) $this->membre_id) !== ((bool) $this->donateur_id);
    }
}
