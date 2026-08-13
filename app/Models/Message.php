<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Message extends Model
{
    protected $fillable = [
        'membre_id', 'message_parent_id', 'objet', 'contenu', 'categorie',
        'statut', 'date_envoi', 'date_traitement', 'traite_par',
    ];

    protected function casts(): array
    {
        return [
            'date_envoi'      => 'datetime',
            'date_traitement' => 'datetime',
        ];
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_parent_id');
    }

    public function reponses(): HasMany
    {
        return $this->hasMany(Message::class, 'message_parent_id');
    }

    public function piecesJointes(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Laravel sérialise les relations sous leur nom camelCase (`piecesJointes`).
     * On expose `pieces_jointes`, conforme au reste de l'API, pour que
     * l'interface n'ait pas à connaître cette exception.
     */
    public function toArray(): array
    {
        $donnees = parent::toArray();

        if ($this->relationLoaded('piecesJointes')) {
            $donnees['pieces_jointes'] = $this->piecesJointes->map(fn (Document $document) => [
                'id'          => $document->id,
                'nom_fichier' => $document->nom_fichier,
                'type_mime'   => $document->type_mime,
                'taille'      => (int) $document->taille,
            ])->values()->all();

            unset($donnees['pieces_jointes_count']);
        }

        return $donnees;
    }
}
