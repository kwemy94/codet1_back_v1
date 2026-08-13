<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité de la suspension.
 *
 * Une suspension sans motif ni date est ingérable : six mois plus tard,
 * personne ne sait plus pourquoi tel ressortissant a été écarté ni qui l'a
 * décidé. Le journal des actions le conserve déjà, mais l'information doit
 * être lisible directement sur la fiche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membres', function (Blueprint $t) {
            $t->string('motif_statut')->nullable()->after('statut');
            $t->date('date_changement_statut')->nullable()->after('motif_statut');
        });
    }

    public function down(): void
    {
        Schema::table('membres', function (Blueprint $t) {
            $t->dropColumn(['motif_statut', 'date_changement_statut']);
        });
    }
};
