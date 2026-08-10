<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RG : 20 % du cumul des affectations « groupement » d'un exercice sont
 * prélevés annuellement et reversés au CODET I. Un seul reversement par exercice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reversements_annuels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exercice_id')->unique()->constrained('exercices')->restrictOnDelete();
            $t->unsignedBigInteger('assiette');             // total des affectations « groupement »
            $t->decimal('taux_applique', 5, 2);             // 20.00 — figé au calcul
            $t->unsignedBigInteger('montant_reverse');
            $t->dateTime('date_calcul');
            $t->dateTime('date_cloture')->nullable();
            $t->enum('statut', ['provisoire', 'cloture'])->default('provisoire');
            // Détail par destination : { code, libelle, assiette, taux, montant }
            $t->json('detail')->nullable();
            $t->foreignId('calcule_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reversements_annuels');
    }
};
