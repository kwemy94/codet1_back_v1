<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarteDeveloppement extends Model
{
    protected $table = 'cartes_developpement';

    protected $fillable = [
        'numero_carte', 'membre_id', 'exercice_id', 'type_carte_id', 'tarif_carte_id',
        'date_emission', 'montant_du', 'montant_regle', 'statut',
    ];

    protected function casts(): array
    {
        return ['date_emission' => 'date'];
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(TarifCarte::class, 'tarif_carte_id');
    }

    public function typeCarte(): BelongsTo
    {
        return $this->belongsTo(TypeCarte::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function getSoldeAttribute(): int
    {
        return max(0, (int) $this->montant_du - (int) $this->montant_regle);
    }

    public function estSoldee(): bool
    {
        return $this->solde === 0;
    }

    /** Recalcule le montant réglé et le statut à partir des paiements validés. */
    public function rafraichirSolde(): void
    {
        $regle = (int) $this->paiements()->where('statut', 'valide')->sum('montant');

        $this->montant_regle = $regle;
        $this->statut = match (true) {
            $regle <= 0                       => 'impayee',
            $regle < (int) $this->montant_du  => 'partielle',
            default                           => 'soldee',
        };
        $this->save();
    }
}
