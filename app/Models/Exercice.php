<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Exercice extends Model
{
    protected $fillable = ['annee', 'date_debut', 'date_fin', 'statut', 'date_cloture'];

    protected function casts(): array
    {
        return [
            'date_debut'   => 'date',
            'date_fin'     => 'date',
            'date_cloture' => 'datetime',
        ];
    }

    public function tarifs(): HasMany
    {
        return $this->hasMany(TarifCarte::class);
    }

    public function cartes(): HasMany
    {
        return $this->hasMany(CarteDeveloppement::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    public function reversement(): HasOne
    {
        return $this->hasOne(ReversementAnnuel::class);
    }

    public function estOuvert(): bool
    {
        return $this->statut === 'ouvert';
    }

    public static function courant(): ?self
    {
        return static::where('statut', 'ouvert')->orderByDesc('annee')->first();
    }
}
