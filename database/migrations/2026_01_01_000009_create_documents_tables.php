<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOCUMENT est mutualisé (relation polymorphe) : pièces jointes de messages,
 * justificatifs de contributions, rapports d'AG, et plus tard galerie des projets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->string('titre');
            $t->string('nom_fichier');
            $t->string('chemin_fichier');
            $t->string('type_mime', 100);
            $t->unsignedBigInteger('taille');
            $t->enum('visibilite', ['public', 'prive'])->default('prive');
            $t->nullableMorphs('documentable');            // documentable_type / documentable_id
            $t->foreignId('depose_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('rapports_ag', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exercice_id')->constrained('exercices')->restrictOnDelete();
            $t->string('intitule');
            $t->date('date_ag');
            $t->string('lieu_ag')->nullable();
            $t->enum('type_rapport', ['proces_verbal', 'rapport_moral', 'rapport_financier', 'resolutions', 'annexe']);
            $t->text('resume')->nullable();
            $t->enum('statut', ['brouillon', 'publie', 'archive'])->default('brouillon');
            $t->dateTime('date_publication')->nullable();
            $t->foreignId('publie_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['exercice_id', 'statut']);
        });

        Schema::create('consultations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $t->foreignId('membre_id')->nullable()->constrained('membres')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('date_heure');
            $t->string('adresse_ip', 45)->nullable();
            $t->enum('action', ['consultation', 'telechargement']);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
        Schema::dropIfExists('rapports_ag');
        Schema::dropIfExists('documents');
    }
};
