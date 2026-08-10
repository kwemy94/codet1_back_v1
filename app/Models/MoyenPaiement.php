<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoyenPaiement extends Model
{
    protected $table = 'moyens_paiement';

    protected $fillable = ['code', 'libelle', 'type', 'passerelle', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function estMobileMoney(): bool
    {
        return $this->type === 'mobile_money';
    }
}
