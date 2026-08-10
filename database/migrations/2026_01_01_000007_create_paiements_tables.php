<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();          // PAY-2026-000001
            $t->foreignId('membre_id')->nullable()->constrained('membres')->nullOnDelete();
            $t->foreignId('moyen_paiement_id')->constrained('moyens_paiement')->restrictOnDelete();
            $t->foreignId('exercice_id')->constrained('exercices')->restrictOnDelete();
            // Exclusion : un paiement règle une carte OU une contribution, jamais les deux
            $t->foreignId('carte_developpement_id')->nullable()->constrained('cartes_developpement')->nullOnDelete();
            $t->foreignId('contribution_id')->nullable()->constrained('contributions')->nullOnDelete();
            $t->dateTime('date_paiement');
            $t->unsignedInteger('montant');
            $t->enum('canal', ['en_ligne', 'manuel'])->default('en_ligne');
            $t->enum('statut', ['initie', 'valide', 'echoue', 'annule'])->default('initie');
            $t->text('observation')->nullable();
            $t->foreignId('enregistre_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['exercice_id', 'statut']);
        });

        Schema::create('transactions_mobiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paiement_id')->unique()->constrained('paiements')->cascadeOnDelete();
            $t->string('operateur', 30);                    // ORANGE_MONEY / MTN_MOMO
            $t->string('reference_operateur')->nullable()->index();
            $t->string('numero_telephone', 30);
            $t->dateTime('date_initiation');
            $t->dateTime('date_confirmation')->nullable();
            $t->enum('statut', ['en_attente', 'confirmee', 'echouee', 'expiree'])->default('en_attente');
            $t->text('message_retour')->nullable();
            $t->json('payload_retour')->nullable();         // trace brute du webhook
            $t->timestamps();
        });

        Schema::create('affectations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paiement_id')->constrained('paiements')->cascadeOnDelete();
            $t->foreignId('destination_fonds_id')->constrained('destinations_fonds')->restrictOnDelete();
            $t->foreignId('exercice_id')->constrained('exercices')->restrictOnDelete();
            $t->unsignedInteger('montant_affecte');
            $t->timestamps();

            $t->index(['exercice_id', 'destination_fonds_id']);
        });

        Schema::create('recus', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paiement_id')->unique()->constrained('paiements')->cascadeOnDelete();
            $t->string('numero_recu', 40)->unique();        // RECU-2026-000001
            $t->dateTime('date_emission');
            $t->string('fichier')->nullable();              // chemin du PDF généré
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recus');
        Schema::dropIfExists('affectations');
        Schema::dropIfExists('transactions_mobiles');
        Schema::dropIfExists('paiements');
    }
};
