<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Membre extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'matricule', 'nom', 'prenom', 'sexe', 'date_naissance', 'profession',
        'telephone', 'email', 'categorie_membre_id', 'ville_id', 'quartier',
        'adresse', 'photo', 'contact_urgence_nom', 'contact_urgence_telephone',
        'date_adhesion', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_adhesion'  => 'date',
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieMembre::class, 'categorie_membre_id');
    }

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class);
    }

    public function compte(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function cartes(): HasMany
    {
        return $this->hasMany(CarteDeveloppement::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getNomCompletAttribute(): string
    {
        return trim($this->nom.' '.$this->prenom);
    }

    /** Carte de l'exercice demandé (ou de l'exercice courant). */
    public function carteDeLExercice(int $exerciceId): ?CarteDeveloppement
    {
        return $this->cartes()->where('exercice_id', $exerciceId)->first();
    }

    public function scopeActifs($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeRecherche($query, ?string $terme)
    {
        if (! $terme) {
            return $query;
        }

        return $query->where(function ($q) use ($terme) {
            $q->where('matricule', 'like', "%{$terme}%")
              ->orWhere('nom', 'like', "%{$terme}%")
              ->orWhere('prenom', 'like', "%{$terme}%")
              ->orWhere('telephone', 'like', "%{$terme}%")
              ->orWhere('email', 'like', "%{$terme}%");
        });
    }
}
