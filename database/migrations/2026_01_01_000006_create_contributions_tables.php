<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donateurs', function (Blueprint $t) {
            $t->id();
            $t->string('denomination');
            $t->enum('categorie_donateur', ['personne_physique', 'entreprise', 'association', 'partenaire']);
            $t->string('telephone', 30)->nullable();
            $t->string('email')->nullable();
            $t->string('pays')->nullable();
            $t->string('adresse')->nullable();
            $t->timestamps();
        });

        Schema::create('contributions', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();
            // Contrainte d'exclusion : membre_id XOR donateur_id (vérifiée applicativement)
            $t->foreignId('membre_id')->nullable()->constrained('membres')->nullOnDelete();
            $t->foreignId('donateur_id')->nullable()->constrained('donateurs')->nullOnDelete();
            $t->foreignId('type_contribution_id')->constrained('types_contributions')->restrictOnDelete();
            $t->foreignId('exercice_id')->constrained('exercices')->restrictOnDelete();
            $t->date('date_contribution');
            // Un don matériel n'a pas de flux financier : le montant porte alors
            // la valeur estimée du bien, utile aux états financiers.
            $t->enum('nature', ['financier', 'materiel', 'service'])->default('financier');
            $t->string('designation')->nullable();   // « 5 sacs de ciment », « 2 journées de maçonnerie »
            $t->unsignedInteger('montant');
            $t->string('motif')->nullable();
            $t->enum('statut', ['attendue', 'encaissee', 'recue', 'annulee'])->default('attendue');
            $t->date('date_reception')->nullable();
            $t->text('observation')->nullable();
            $t->foreignId('enregistre_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
        Schema::dropIfExists('donateurs');
    }
};
