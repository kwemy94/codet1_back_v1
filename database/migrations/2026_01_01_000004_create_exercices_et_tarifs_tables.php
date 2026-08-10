<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'exercice est le pivot de toute l'historisation annuelle.
 *
 * La répartition d'un tarif n'est plus figée en trois colonnes : elle est
 * portée par la table `repartitions_tarifs`, ce qui permet de créer un type de
 * carte dont la totalité revient au CODET I, ou toute autre clé de répartition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercices', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('annee')->unique();
            $t->date('date_debut');
            $t->date('date_fin');
            $t->enum('statut', ['ouvert', 'cloture'])->default('ouvert');
            $t->timestamp('date_cloture')->nullable();
            $t->timestamps();
        });

        Schema::create('types_cartes', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();          // CARTE_ANNUELLE, MEMBRE_HONNEUR...
            $t->string('libelle');
            $t->string('description')->nullable();
            // Une carte obligatoire est due par tout ressortissant et son tarif
            // dépend de sa catégorie. Une carte facultative (honneur, soutien)
            // s'applique indifféremment à toutes les catégories.
            $t->boolean('obligatoire')->default(false);
            $t->boolean('actif')->default(true);
            $t->timestamps();
        });

        Schema::create('tarifs_cartes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $t->foreignId('type_carte_id')->constrained('types_cartes')->restrictOnDelete();
            // Nul pour les cartes non liées à une catégorie (carte d'honneur…)
            $t->foreignId('categorie_membre_id')->nullable()->constrained('categories_membres')->restrictOnDelete();
            $t->unsignedInteger('montant_minimum');
            $t->date('date_debut_validite');
            $t->date('date_fin_validite')->nullable();   // null = version courante
            $t->timestamps();

            $t->index(['exercice_id', 'type_carte_id', 'categorie_membre_id', 'date_fin_validite'], 'idx_tarif_actif');
        });

        Schema::create('repartitions_tarifs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tarif_carte_id')->constrained('tarifs_cartes')->cascadeOnDelete();
            $t->foreignId('destination_fonds_id')->constrained('destinations_fonds')->restrictOnDelete();
            $t->unsignedInteger('montant');
            $t->timestamps();

            $t->unique(['tarif_carte_id', 'destination_fonds_id'], 'idx_repartition_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repartitions_tarifs');
        Schema::dropIfExists('tarifs_cartes');
        Schema::dropIfExists('types_cartes');
        Schema::dropIfExists('exercices');
    }
};
