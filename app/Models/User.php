<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'membre_id', 'nom_affichage', 'email', 'telephone', 'password', 'statut',
        'doit_changer_mot_de_passe',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'derniere_connexion_at' => 'datetime',
            'password'              => 'hashed',
            'doit_changer_mot_de_passe' => 'boolean',
        ];
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot('date_attribution');
    }

    public function aLeRole(string ...$codes): bool
    {
        return $this->roles->whereIn('code', $codes)->isNotEmpty();
    }

    public function aLaPermission(string $code): bool
    {
        return $this->roles->contains(
            fn (Role $role) => $role->permissions->contains('code', $code)
        );
    }

    public function estAdministrateur(): bool
    {
        return $this->aLeRole('SUPER_ADMIN', 'TRESORIER', 'SECRETAIRE');
    }
}
