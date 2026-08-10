<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    protected $fillable = ['document_id', 'membre_id', 'user_id', 'date_heure', 'adresse_ip', 'action'];

    protected function casts(): array
    {
        return ['date_heure' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }
}
