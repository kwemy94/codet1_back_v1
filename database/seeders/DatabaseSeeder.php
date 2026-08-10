<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferentielSeeder::class,
            RoleSeeder::class,
            ExerciceSeeder::class,
            AdministrateurSeeder::class,
        ]);
    }
}
