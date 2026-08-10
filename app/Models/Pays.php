<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pays extends Model
{
    protected $table = 'pays';

    protected $fillable = ['code', 'libelle'];

    public function villes(): HasMany
    {
        return $this->hasMany(Ville::class);
    }
}
