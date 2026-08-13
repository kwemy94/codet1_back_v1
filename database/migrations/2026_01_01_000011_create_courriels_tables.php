<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courriels adressés aux membres par le comité.
 *
 * Chaque envoi est conservé : le comité doit pouvoir dire qui a été convoqué,
 * quand, et si le message est bien parti. Une convocation d'assemblée est un
 * acte administratif, pas un simple courriel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campagnes_courriels', function (Blueprint $t) {
            $t->id();
            $t->string('objet');
            $t->text('contenu');
            $t->enum('portee', ['individuel', 'collectif'])->default('individuel');
            // Critères de sélection retenus, conservés pour justifier la liste
            $t->json('criteres')->nullable();
            $t->unsignedInteger('nombre_destinataires')->default(0);
            $t->unsignedInteger('nombre_sans_adresse')->default(0);
            $t->enum('statut', ['en_cours', 'terminee', 'echouee'])->default('en_cours');
            $t->dateTime('date_envoi');
            $t->dateTime('date_fin')->nullable();
            $t->foreignId('envoye_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('destinataires_courriels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campagne_courriel_id')->constrained('campagnes_courriels')->cascadeOnDelete();
            $t->foreignId('membre_id')->constrained('membres')->cascadeOnDelete();
            $t->string('adresse');
            $t->enum('statut', ['en_attente', 'envoye', 'echoue'])->default('en_attente');
            $t->dateTime('date_traitement')->nullable();
            $t->text('message_erreur')->nullable();
            $t->timestamps();

            $t->index(['campagne_courriel_id', 'statut'], 'idx_destinataire_statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destinataires_courriels');
        Schema::dropIfExists('campagnes_courriels');
    }
};
