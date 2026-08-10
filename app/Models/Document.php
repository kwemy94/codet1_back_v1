<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    protected $fillable = [
        'titre', 'nom_fichier', 'chemin_fichier', 'type_mime',
        'taille', 'visibilite', 'documentable_type', 'documentable_id', 'depose_par',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function estPublic(): bool
    {
        return $this->visibilite === 'public';
    }
}
