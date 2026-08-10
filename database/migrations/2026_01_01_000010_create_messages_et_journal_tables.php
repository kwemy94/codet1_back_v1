<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('membre_id')->nullable()->constrained('membres')->nullOnDelete();
            $t->foreignId('message_parent_id')->nullable()->constrained('messages')->nullOnDelete();
            $t->string('objet');
            $t->text('contenu');
            $t->string('categorie', 40)->nullable();
            $t->enum('statut', ['nouveau', 'en_cours', 'traite'])->default('nouveau');
            $t->dateTime('date_envoi');
            $t->dateTime('date_traitement')->nullable();
            $t->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('journal_actions', function (Blueprint $t) {
            $t->id();
            $t->string('type_action', 40);                  // creation, modification, suppression, connexion...
            $t->string('entite_concernee', 60)->nullable();
            $t->unsignedBigInteger('identifiant_enregistrement')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();     // auteur
            $t->foreignId('membre_id')->nullable()->constrained('membres')->nullOnDelete(); // concerné
            $t->dateTime('date_heure');
            $t->string('adresse_ip', 45)->nullable();
            $t->json('ancienne_valeur')->nullable();
            $t->json('nouvelle_valeur')->nullable();
            $t->timestamps();

            // Nom d'index explicite : la convention Laravel produirait ici un
            // identifiant de 65 caractères, au-delà de la limite MySQL (64).
            $t->index(['entite_concernee', 'identifiant_enregistrement'], 'idx_journal_entite');
            $t->index('date_heure', 'idx_journal_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_actions');
        Schema::dropIfExists('messages');
    }
};
