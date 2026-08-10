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
}
