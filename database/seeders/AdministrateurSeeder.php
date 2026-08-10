<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdministrateurSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@codet1.org')],
            [
                'nom_affichage' => 'Administrateur CODET I',
                'telephone'     => env('ADMIN_TELEPHONE', '+237600000000'),
                'password'      => env('ADMIN_MOT_DE_PASSE', 'ChangezMoi2026!'),
                'statut'        => 'actif',
            ]
        );

        $role = Role::where('code', 'SUPER_ADMIN')->first();

        if ($role) {
            $admin->roles()->syncWithoutDetaching([$role->id => ['date_attribution' => now()->toDateString()]]);
        }

        $this->command?->warn('Compte administrateur créé — modifiez le mot de passe dès la première connexion.');
    }
}
