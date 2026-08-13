<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampagneCourriel extends Model
{
    protected $table = 'campagnes_courriels';

    protected $fillable = [
        'objet', 'contenu', 'portee', 'criteres', 'nombre_destinataires',
        'nombre_sans_adresse', 'statut', 'date_envoi', 'date_fin', 'envoye_par',
    ];

    protected function casts(): array
    {
        return [
            'criteres'   => 'array',
            'date_envoi' => 'datetime',
            'date_fin'   => 'datetime',
        ];
    }

    public function destinataires(): HasMany
    {
        return $this->hasMany(DestinataireCourriel::class, 'campagne_courriel_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'envoye_par');
    }

    /** Clôt la campagne dès qu'aucun destinataire n'est plus en attente. */
    public function actualiserStatut(): void
    {
        if ($this->destinataires()->where('statut', 'en_attente')->exists()) {
            return;
        }

        $echecs = $this->destinataires()->where('statut', 'echoue')->count();

        $this->update([
            'statut'   => $echecs === $this->nombre_destinataires ? 'echouee' : 'terminee',
            'date_fin' => now(),
        ]);
    }
}
