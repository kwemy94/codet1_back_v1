<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RapportAg extends Model
{
    protected $table = 'rapports_ag';

    protected $fillable = [
        'exercice_id', 'intitule', 'date_ag', 'lieu_ag', 'type_rapport',
        'resume', 'statut', 'date_publication', 'publie_par',
    ];

    protected function casts(): array
    {
        return [
            'date_ag'          => 'date',
            'date_publication' => 'datetime',
        ];
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function estPublie(): bool
    {
        return $this->statut === 'publie';
    }

    public function scopePublies($query)
    {
        return $query->where('statut', 'publie');
    }
}
