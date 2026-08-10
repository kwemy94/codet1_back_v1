<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DestinationFonds extends Model
{
    protected $table = 'destinations_fonds';

    protected $fillable = ['code', 'libelle', 'taux_reversement', 'couleur', 'actif'];

    protected function casts(): array
    {
        return ['taux_reversement' => 'decimal:2', 'actif' => 'boolean'];
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    public function estReversee(): bool
    {
        return (float) $this->taux_reversement > 0;
    }
}
