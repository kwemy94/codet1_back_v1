<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membres', function (Blueprint $t) {
            $t->id();
            $t->string('matricule', 20)->unique();            // COD26-000125
            $t->string('nom');
            $t->string('prenom')->nullable();
            $t->enum('sexe', ['M', 'F']);
            $t->date('date_naissance')->nullable();
            $t->string('profession')->nullable();
            $t->string('telephone', 30);
            $t->string('email')->nullable();
            $t->foreignId('categorie_membre_id')->constrained('categories_membres')->restrictOnDelete();
            $t->foreignId('ville_id')->nullable()->constrained('villes')->nullOnDelete();
            $t->string('quartier')->nullable();
            $t->string('adresse')->nullable();
            $t->string('photo')->nullable();
            $t->string('contact_urgence_nom')->nullable();
            $t->string('contact_urgence_telephone', 30)->nullable();
            $t->date('date_adhesion');
            $t->enum('statut', ['actif', 'inactif', 'decede'])->default('actif');
            $t->timestamps();
            $t->softDeletes();

            $t->index(['statut', 'categorie_membre_id']);
            $t->index('telephone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membres');
    }
};
