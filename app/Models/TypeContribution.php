<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeContribution extends Model
{
    protected $table = 'types_contributions';

    protected $fillable = ['code', 'libelle', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
