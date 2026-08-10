<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le compte utilisateur est distinct du membre : un administrateur peut ne pas être
 * membre, un membre peut ne pas avoir de compte (MCD, association DISPOSER 0,1 - 1,1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('membre_id')->nullable()->unique()->constrained('membres')->nullOnDelete();
            $t->string('nom_affichage');
            $t->string('email')->nullable()->unique();
            $t->string('telephone', 30)->nullable()->unique();   // connexion par e-mail OU téléphone
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->enum('statut', ['actif', 'suspendu'])->default('actif');
            // Vrai tant que le membre n'a pas remplacé le mot de passe provisoire
            // remis par le secrétariat lors de la création de son accès.
            $t->boolean('doit_changer_mot_de_passe')->default(false);
            $t->timestamp('derniere_connexion_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();        // SUPER_ADMIN, TRESORIER, SECRETAIRE, MEMBRE
            $t->string('libelle');
            $t->string('description')->nullable();
            $t->timestamps();
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('code', 60)->unique();        // membres.creer, paiements.valider...
            $t->string('libelle');
            $t->string('module', 40);
            $t->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->date('date_attribution')->nullable();
            $t->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
