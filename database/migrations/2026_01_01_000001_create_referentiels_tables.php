<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables de référence entièrement paramétrables depuis le back-office :
 * ajouter une catégorie, un moyen de paiement ou une destination de fonds
 * ne demande aucune intervention des développeurs (CDC §11.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pays', function (Blueprint $t) {
            $t->id();
            $t->string('code', 3)->unique();
            $t->string('libelle');
            $t->timestamps();
        });

        Schema::create('villes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pays_id')->constrained('pays')->restrictOnDelete();
            $t->string('libelle');
            $t->timestamps();
            $t->unique(['pays_id', 'libelle']);
        });

        Schema::create('categories_membres', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();                 // HV, FV, HC, FC
            $t->string('libelle');
            $t->enum('type_residence', ['villageois', 'citadin_diaspora']);
            $t->enum('sexe_concerne', ['M', 'F']);
            $t->boolean('actif')->default(true);
            $t->timestamps();
        });

        Schema::create('destinations_fonds', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();                 // VILLAGE, GROUPEMENT, CONGRES, CODET
            $t->string('libelle');
            // Part de cette destination reversée au CODET I, en pourcentage.
            // GROUPEMENT = 20, CODET = 100, les autres = 0. Un taux par destination
            // permet d'exprimer une carte intégralement reversée au comité.
            $t->decimal('taux_reversement', 5, 2)->default(0);
            $t->string('couleur', 7)->nullable();             // affichage du ruban de ventilation
            $t->boolean('actif')->default(true);
            $t->timestamps();
        });

        Schema::create('moyens_paiement', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();                 // ORANGE_MONEY, MTN_MOMO, ESPECES...
            $t->string('libelle');
            $t->enum('type', ['mobile_money', 'especes', 'virement', 'autre']);
            $t->string('passerelle')->nullable();             // clé du driver applicatif
            $t->boolean('actif')->default(true);
            $t->timestamps();
        });

        Schema::create('types_contributions', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('libelle');
            $t->boolean('actif')->default(true);
            $t->timestamps();
        });

        Schema::create('parametres', function (Blueprint $t) {
            $t->id();
            $t->string('code', 60)->unique();                 // PREFIXE_MATRICULE, DEVISE...
            $t->string('libelle');
            $t->text('valeur')->nullable();
            $t->enum('type_valeur', ['texte', 'entier', 'decimal', 'booleen', 'json'])->default('texte');
            $t->unsignedBigInteger('modifie_par')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
        Schema::dropIfExists('types_contributions');
        Schema::dropIfExists('moyens_paiement');
        Schema::dropIfExists('destinations_fonds');
        Schema::dropIfExists('categories_membres');
        Schema::dropIfExists('villes');
        Schema::dropIfExists('pays');
    }
};
