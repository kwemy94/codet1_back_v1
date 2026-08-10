<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'membres'      => ['membres.consulter', 'membres.creer', 'membres.modifier', 'membres.suspendre'],
            'cotisations'  => ['cartes.emettre', 'tarifs.parametrer', 'exercices.gerer'],
            'finances'     => ['paiements.consulter', 'paiements.enregistrer', 'paiements.annuler', 'reversement.calculer'],
            'documents'    => ['rapports.deposer', 'rapports.publier'],
            'communication'=> ['messages.consulter', 'messages.repondre'],
            'systeme'      => ['parametres.modifier', 'journal.consulter'],
        ];

        foreach ($permissions as $module => $codes) {
            foreach ($codes as $code) {
                Permission::updateOrCreate(['code' => $code], [
                    'libelle' => ucfirst(str_replace(['.', '_'], ' ', $code)),
                    'module'  => $module,
                ]);
            }
        }

        $roles = [
            'SUPER_ADMIN' => ['libelle' => 'Administrateur général', 'permissions' => Permission::pluck('code')->all()],
            'TRESORIER'   => ['libelle' => 'Trésorier', 'permissions' => [
                'membres.consulter', 'cartes.emettre', 'paiements.consulter',
                'paiements.enregistrer', 'paiements.annuler', 'reversement.calculer',
            ]],
            'SECRETAIRE'  => ['libelle' => 'Secrétaire', 'permissions' => [
                'membres.consulter', 'membres.creer', 'membres.modifier',
                'rapports.deposer', 'rapports.publier', 'messages.consulter', 'messages.repondre',
            ]],
            'MEMBRE'      => ['libelle' => 'Membre', 'permissions' => []],
        ];

        foreach ($roles as $code => $donnees) {
            $role = Role::updateOrCreate(['code' => $code], ['libelle' => $donnees['libelle']]);
            $role->permissions()->sync(Permission::whereIn('code', $donnees['permissions'])->pluck('id'));
        }
    }
}
