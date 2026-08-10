<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donateur extends Model
{
    protected $fillable = ['denomination', 'categorie_donateur', 'telephone', 'email', 'pays', 'adresse'];

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }
}
