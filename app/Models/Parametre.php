<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Parametre extends Model
{
    protected $table = 'parametres';

    protected $fillable = ['code', 'libelle', 'valeur', 'type_valeur', 'modifie_par'];

    public static function valeur(string $code, mixed $defaut = null): mixed
    {
        $parametre = Cache::remember("parametre.{$code}", 3600, fn () => static::where('code', $code)->first());

        if (! $parametre) {
            return $defaut;
        }

        return match ($parametre->type_valeur) {
            'entier'  => (int) $parametre->valeur,
            'decimal' => (float) $parametre->valeur,
            'booleen' => filter_var($parametre->valeur, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($parametre->valeur, true),
            default   => $parametre->valeur,
        };
    }

    protected static function booted(): void
    {
        static::saved(fn (self $p) => Cache::forget("parametre.{$p->code}"));
        static::deleted(fn (self $p) => Cache::forget("parametre.{$p->code}"));
    }
}
