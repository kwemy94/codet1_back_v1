<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeCarte extends Model
{
    protected $table = 'types_cartes';

    protected $fillable = ['code', 'libelle', 'description', 'obligatoire', 'actif'];

    protected function casts(): array
    {
        return ['obligatoire' => 'boolean', 'actif' => 'boolean'];
    }

    public function tarifs(): HasMany
    {
        return $this->hasMany(TarifCarte::class);
    }

    public function cartes(): HasMany
    {
        return $this->hasMany(CarteDeveloppement::class);
    }

    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }
}
