<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartes_developpement', function (Blueprint $t) {
            $t->id();
            $t->string('numero_carte', 30)->unique();       // CARTE-2026-000125
            $t->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $t->foreignId('exercice_id')->constrained('exercices')->restrictOnDelete();
            $t->foreignId('type_carte_id')->constrained('types_cartes')->restrictOnDelete();
            $t->foreignId('tarif_carte_id')->constrained('tarifs_cartes')->restrictOnDelete();
            $t->date('date_emission');
            $t->unsignedInteger('montant_du');              // figé à l'émission
            $t->unsignedInteger('montant_regle')->default(0);
            $t->enum('statut', ['impayee', 'partielle', 'soldee', 'annulee'])->default('impayee');
            $t->timestamps();

            // RG : une seule carte de chaque type par membre et par exercice.
            // Un membre peut donc cumuler sa carte annuelle et une carte d'honneur.
            $t->unique(['membre_id', 'exercice_id', 'type_carte_id'], 'idx_carte_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartes_developpement');
    }
};
