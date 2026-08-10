<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieMembre extends Model
{
    protected $table = 'categories_membres';

    protected $fillable = ['code', 'libelle', 'type_residence', 'sexe_concerne', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function membres(): HasMany
    {
        return $this->hasMany(Membre::class, 'categorie_membre_id');
    }

    public function tarifs(): HasMany
    {
        return $this->hasMany(TarifCarte::class, 'categorie_membre_id');
    }
}
