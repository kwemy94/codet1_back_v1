<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ville extends Model
{
    protected $fillable = ['pays_id', 'libelle'];

    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class);
    }
}
