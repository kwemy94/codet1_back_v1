<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CarteController;
use App\Http\Controllers\Api\V1\CompteMembreController;
use App\Http\Controllers\Api\V1\ContributionController;
use App\Http\Controllers\Api\V1\EspaceMembreController;
use App\Http\Controllers\Api\V1\ExerciceController;
use App\Http\Controllers\Api\V1\ImpressionCarteController;
use App\Http\Controllers\Api\V1\JournalController;
use App\Http\Controllers\Api\V1\MembreController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\PaiementController;
use App\Http\Controllers\Api\V1\RapportAgController;
use App\Http\Controllers\Api\V1\ReferentielController;
use App\Http\Controllers\Api\V1\ReversementController;
use App\Http\Controllers\Api\V1\StatistiqueController;
use App\Http\Controllers\Api\V1\TarifCarteController;
use App\Http\Controllers\Api\V1\TypeCarteController;
use App\Http\Controllers\Api\V1\WebhookPaiementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API CODET I — version 1
|--------------------------------------------------------------------------
| Toutes les routes sont préfixées par /api/v1. L'API est versionnée afin
| d'assurer la compatibilité des évolutions (CDC §23).
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    /* ---------------------------------------------------------- Public */
    Route::post('connexion', [AuthController::class, 'connexion'])->name('connexion');

    // Notifications des opérateurs mobile money (protégées par signature)
    Route::prefix('webhooks')->name('webhooks.')->group(function () {
        Route::post('orange-money', [WebhookPaiementController::class, 'orangeMoney'])->name('orange-money');
        Route::post('mtn-momo', [WebhookPaiementController::class, 'mtnMomo'])->name('mtn-momo');
    });

    /* ------------------------------------------------------ Authentifié */
    Route::middleware('auth:sanctum')->group(function () {

        // --- Compte
        Route::get('profil', [AuthController::class, 'profil'])->name('profil');
        Route::post('deconnexion', [AuthController::class, 'deconnexion'])->name('deconnexion');
        Route::post('mot-de-passe', [AuthController::class, 'changerMotDePasse'])->name('mot-de-passe');

        // --- Espace personnel du membre (CDC §8)
        Route::prefix('mon-espace')->name('mon-espace.')->group(function () {
            Route::get('tableau-de-bord', [EspaceMembreController::class, 'tableauDeBord'])->name('tableau-de-bord');
            Route::get('profil', [EspaceMembreController::class, 'profil'])->name('profil');
            Route::patch('profil', [EspaceMembreController::class, 'modifierProfil'])->name('profil.modifier');
            Route::get('cartes', [EspaceMembreController::class, 'mesCartes'])->name('cartes');
            Route::get('paiements', [EspaceMembreController::class, 'mesPaiements'])->name('paiements');
            Route::get('contributions', [EspaceMembreController::class, 'mesContributions'])->name('contributions');
        });

        // --- Référentiels
        Route::get('referentiels', [ReferentielController::class, 'tout'])->name('referentiels');
        Route::get('referentiels/villes', [ReferentielController::class, 'villes'])->name('referentiels.villes');
        Route::get('parametres', [ReferentielController::class, 'parametres'])->name('parametres.index');
        Route::patch('parametres/{parametre}', [ReferentielController::class, 'modifierParametre'])->name('parametres.modifier');

        // --- Membres
        Route::apiResource('membres', MembreController::class)->except('destroy');
        Route::post('membres/{membre}/suspendre', [MembreController::class, 'suspendre'])->name('membres.suspendre');
        Route::post('membres/{membre}/reactiver', [MembreController::class, 'reactiver'])->name('membres.reactiver');

        // --- Accès des membres à leur espace personnel
        Route::post('membres/{membre}/compte', [CompteMembreController::class, 'store'])->name('membres.compte.creer');
        Route::post('membres/{membre}/compte/reinitialiser', [CompteMembreController::class, 'reinitialiser'])->name('membres.compte.reinitialiser');
        Route::post('membres/{membre}/compte/suspendre', [CompteMembreController::class, 'suspendre'])->name('membres.compte.suspendre');

        // --- Exercices et tarifs
        Route::get('exercices/courant', [ExerciceController::class, 'courant'])->name('exercices.courant');
        Route::apiResource('exercices', ExerciceController::class)->only(['index', 'store', 'show']);
        Route::post('exercices/{exercice}/cloturer', [ExerciceController::class, 'cloturer'])->name('exercices.cloturer');
        Route::get('tarifs', [TarifCarteController::class, 'index'])->name('tarifs.index');
        Route::post('tarifs', [TarifCarteController::class, 'store'])->name('tarifs.store');
        Route::get('tarifs/historique', [TarifCarteController::class, 'historique'])->name('tarifs.historique');

        // --- Types de cartes et destinations des fonds
        Route::get('types-cartes', [TypeCarteController::class, 'index'])->name('types-cartes.index');
        Route::post('types-cartes', [TypeCarteController::class, 'store'])->name('types-cartes.store');
        Route::patch('types-cartes/{type}', [TypeCarteController::class, 'update'])->name('types-cartes.update');
        Route::get('destinations-fonds', [TarifCarteController::class, 'destinations'])->name('destinations.index');
        Route::patch('destinations-fonds/{destination}', [TarifCarteController::class, 'modifierDestination'])->name('destinations.modifier');

        // --- Cartes annuelles de développement
        Route::get('cartes/impayes', [CarteController::class, 'impayes'])->name('cartes.impayes');
        Route::get('cartes/{carte}/impression', ImpressionCarteController::class)->name('cartes.impression');
        Route::apiResource('cartes', CarteController::class)->only(['index', 'store', 'show']);

        // --- Paiements
        Route::get('paiements', [PaiementController::class, 'index'])->name('paiements.index');
        Route::post('paiements/initier', [PaiementController::class, 'initier'])->name('paiements.initier');
        Route::post('paiements/manuel', [PaiementController::class, 'enregistrerManuel'])->name('paiements.manuel');
        Route::get('paiements/{paiement}', [PaiementController::class, 'show'])->name('paiements.show');
        Route::get('paiements/{paiement}/statut', [PaiementController::class, 'statut'])->name('paiements.statut');
        Route::post('paiements/{paiement}/annuler', [PaiementController::class, 'annuler'])->name('paiements.annuler');

        // --- Contributions et dons
        Route::apiResource('contributions', ContributionController::class)->only(['index', 'store', 'show']);
        Route::patch('contributions/{contribution}/statut', [ContributionController::class, 'changerStatut'])->name('contributions.statut');
        Route::post('contributions/{contribution}/annuler', [ContributionController::class, 'annuler'])->name('contributions.annuler');
        Route::match(['get', 'post'], 'donateurs', [ContributionController::class, 'donateurs'])->name('donateurs');

        // --- Reversement annuel au CODET I (20 %)
        Route::get('reversements', [ReversementController::class, 'index'])->name('reversements.index');
        Route::get('exercices/{exercice}/reversement', [ReversementController::class, 'show'])->name('reversements.show');
        Route::get('exercices/{exercice}/reversement/simulation', [ReversementController::class, 'simuler'])->name('reversements.simuler');
        Route::post('exercices/{exercice}/reversement/calculer', [ReversementController::class, 'calculer'])->name('reversements.calculer');

        // --- Rapports d'Assemblée Générale (CDC §10)
        Route::get('rapports-ag', [RapportAgController::class, 'index'])->name('rapports-ag.index');
        Route::post('rapports-ag', [RapportAgController::class, 'store'])->name('rapports-ag.store');
        Route::get('rapports-ag/{rapport}', [RapportAgController::class, 'show'])->name('rapports-ag.show');
        Route::post('rapports-ag/{rapport}/publier', [RapportAgController::class, 'publier'])->name('rapports-ag.publier');
        Route::post('rapports-ag/{rapport}/depublier', [RapportAgController::class, 'depublier'])->name('rapports-ag.depublier');
        Route::get('rapports-ag/{rapport}/documents/{document}', [RapportAgController::class, 'telecharger'])->name('rapports-ag.telecharger');

        // --- Messages
        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('messages', [MessageController::class, 'store'])->name('messages.store');
        Route::post('messages/{message}/repondre', [MessageController::class, 'repondre'])->name('messages.repondre');
        Route::post('messages/{message}/traite', [MessageController::class, 'marquerTraite'])->name('messages.traite');

        // --- Tableau de bord et statistiques
        Route::get('statistiques/tableau-de-bord', [StatistiqueController::class, 'tableauDeBord'])->name('statistiques.tableau-de-bord');
        Route::get('statistiques/evolution-recettes', [StatistiqueController::class, 'evolutionRecettes'])->name('statistiques.evolution-recettes');

        // --- Journal des actions
        Route::get('journal', [JournalController::class, 'index'])->name('journal.index');
    });
});
