# API CODET I — Version 1

API REST de gestion du **Comité de Développement du Village Tchuelekouet I**, développée
avec Laravel 12 (PHP 8.3+), MySQL 8 et Laravel Sanctum, conformément au cahier des
charges v1.2 et au modèle conceptuel de données.

## Périmètre de la version 1

| Module | Contenu |
|---|---|
| Authentification | Connexion par e-mail **ou** téléphone, jetons Sanctum, rôles et permissions |
| Membres | CRUD, matricule automatique `COD26-000125`, suspension (aucune suppression physique) |
| Exercices et tarifs | Ouverture/clôture d'exercice, tarifs versionnés par catégorie |
| Cartes annuelles | Émission, solde, état des impayés |
| Paiements | Orange Money, MTN MoMo, encaissement manuel, ventilation automatique, reçus |
| Reversement | Calcul et clôture annuels des 20 % revenant au CODET I |
| Contributions | Dons de membres, personnes physiques, entreprises, associations, partenaires |
| Rapports d'AG | Dépôt, publication, consultation par tous les membres, téléchargements tracés |
| Messages | Messages au comité avec pièces jointes, réponses des administrateurs |
| Statistiques | Tableau de bord financier, membres et cotisations |
| Traçabilité | Journal générique de toutes les actions (auteur, date, IP, valeurs) |

Hors périmètre V1 (prévu en V2) : module Projets, tableau de bord avancé, exports PDF/Excel,
réunions, vote électronique, patrimoine et comptabilité.

## Installation

**Important — ce dossier n'est pas un projet Laravel complet.** Il contient
uniquement les fichiers métier (`app/`, `database/`, `routes/`, `config/`, `docs/`).
Le fichier `artisan` appartient au squelette Laravel, qu'il faut créer d'abord :
c'est pour cela qu'un `php artisan` lancé directement ici répond
« Could not open input file: artisan ».

### Option A — script d'installation (recommandé)

Depuis le dossier décompressé :

```bash
bash installer.sh
```

Le script vérifie PHP et Composer, crée le squelette Laravel 12 dans un dossier
voisin, active Sanctum, retire la migration `users` par défaut, copie les fichiers
métier et complète le `.env`. Il vous laisse ensuite renseigner l'accès à la base
et lancer `php artisan migrate --seed`.

### Option B — étapes manuelles

```bash
# 1. Squelette Laravel — c'est lui qui fournit `artisan`
composer create-project laravel/laravel codet1-api-laravel "12.*"
cd codet1-api-laravel
php artisan install:api

# 2. Retirer la migration users livrée par Laravel : ce projet a la sienne,
#    qui rattache le compte à une fiche membre
rm database/migrations/0001_01_01_000000_create_users_table.php

# 3. Copier les fichiers métier DEPUIS le dossier décompressé (notez le « /. »)
cp -R ../codet1-api/app/.      app/
cp -R ../codet1-api/database/. database/
cp -R ../codet1-api/routes/.   routes/
cp -R ../codet1-api/config/.   config/
cp -R ../codet1-api/docs/.     docs/

# 4. Configurer
#    - DB_* dans .env, et créer la base : CREATE DATABASE codet1 CHARACTER SET utf8mb4;
#    - SESSION_DRIVER=file (ce projet ne crée pas la table sessions)
#    - ajouter les variables ADMIN_* et OM_/MOMO_ listées ci-dessous

# 5. Créer le schéma et les données de référence
php artisan migrate --seed
php artisan serve
```

Le compte administrateur initial est créé par `AdministrateurSeeder`
(`ADMIN_EMAIL` / `ADMIN_MOT_DE_PASSE` dans `.env`). **Changez le mot de passe
à la première connexion.**

### En cas d'erreur

| Message | Cause | Correction |
|---|---|---|
| `Could not open input file: artisan` | Commande lancée dans le dossier décompressé, pas dans le projet Laravel | Créer le squelette (étape 1) puis se placer dedans |
| `Base table or view already exists: users` | La migration `users` de Laravel n'a pas été retirée | Supprimer `0001_01_01_000000_create_users_table.php` puis `php artisan migrate:fresh --seed` |
| `Table 'sessions' doesn't exist` | Pilote de session en base | Mettre `SESSION_DRIVER=file` dans `.env` |
| `SQLSTATE[HY000] [1049] Unknown database` | Base non créée | `CREATE DATABASE codet1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;` |
| `Class "App\Models\Membre" not found` | Fichiers copiés au mauvais endroit | Vérifier que `app/Models/Membre.php` existe bien dans le projet Laravel |
| `Identifier name '…' is too long` | Nom d'index auto-généré au-delà de 64 caractères (limite MySQL) | Corrigé dans cette version. Si la base est déjà partiellement migrée : `php artisan migrate:fresh --seed` |

## Variables d'environnement spécifiques

```dotenv
ADMIN_EMAIL=admin@codet1.org
ADMIN_TELEPHONE=+237600000000
ADMIN_MOT_DE_PASSE=ChangezMoi2026!

OM_CLIENT_ID=
OM_CLIENT_SECRET=
OM_CLE_MARCHAND=
OM_SECRET_WEBHOOK=

MOMO_UTILISATEUR_API=
MOMO_CLE_API=
MOMO_CLE_ABONNEMENT=
MOMO_ENVIRONNEMENT=mtncameroon
MOMO_IPS_AUTORISEES=
```

## Répartition des fonds et types de cartes

La ventilation n'est pas figée en trois parts. Elle repose sur deux tables :

- **`destinations_fonds`** — village, groupement, congrès, compte du CODET I, et
  toute destination créée ensuite. Chacune porte son **taux de reversement** au
  CODET I : 20 % pour le groupement, 100 % pour le compte du comité, 0 % ailleurs.
- **`repartitions_tarifs`** — la clé de répartition d'un tarif, ligne par ligne.
  Une seule ligne suffit si la totalité revient à une même destination.

Cela permet d'exprimer, sans modifier le code :

| Type de carte | Montant | Répartition | Revient au CODET I |
|---|---|---|---|
| Carte annuelle, homme citadin | 10 500 | 5 000 village · 2 500 groupement · 3 000 congrès | 500 (20 % du groupement) |
| Carte de membre d'honneur | 100 000 | 100 000 compte du CODET I | 100 000 (100 %) |

Un membre peut cumuler plusieurs types de cartes sur un même exercice :
l'unicité porte sur le triplet (membre, exercice, type de carte).

Le reversement additionne, par destination, l'assiette encaissée multipliée par
son taux. Le détail est conservé dans l'enregistrement : modifier un taux plus
tard n'altère jamais les exercices déjà calculés.


## Impression de la carte unique de développement

Le gabarit reproduit la carte physique du groupement, recto-verso, au format
carte d'identité (85,6 × 54 mm) : recto CO.SU.DE.G.BANG portant la part revenant
au groupement, verso Groupement BANGANG portant le montant total et le village
d'origine.

Toutes les mentions — sigle, récépissé, téléphones, e-mail, site, noms du
président et du commissaire aux comptes, village — proviennent des paramètres
`CARTE_*` et se modifient depuis l'écran Paramètres, sans toucher au gabarit.

Deux conditions, vérifiées côté serveur à chaque demande :

1. **La carte doit être intégralement réglée.** Une carte partiellement payée ne
   s'imprime pas : le membre détiendrait sinon un justificatif de paiement qu'il
   n'a pas honoré.
2. **L'exercice doit être ouvert.** Une carte d'exercice clôturé n'a plus valeur
   de titre pour l'année en cours.

Un membre ne peut demander que sa propre carte ; un administrateur peut imprimer
celle de n'importe quel membre. L'interface masque le bouton quand les
conditions ne sont pas réunies, mais c'est `ImpressionCarteController` qui
tranche — le masquage n'est pas une protection.

Chaque impression est journalisée (`impression_carte`).


## Comment un membre se connecte

Le comité ne dispose pas d'un envoi de courriel fiable vers la diaspora : le
parcours ne repose donc sur aucun e-mail automatique.

1. **Le secrétariat crée la fiche du membre** (nom, catégorie, téléphone).
2. **Il ouvre l'accès** depuis la fiche : `POST /v1/membres/{id}/compte`.
   L'API renvoie un **mot de passe provisoire de 8 caractères**, affiché une
   seule fois. Il n'est stocké nulle part en clair et ne pourra pas être
   retrouvé — seulement réinitialisé.
3. **Le secrétariat transmet ce mot de passe** au membre de vive voix, par SMS
   ou par WhatsApp, avec son identifiant : **son numéro de téléphone ou son e-mail**.
4. **Le membre se connecte** et se voit immédiatement demander de choisir son
   propre mot de passe. Tant qu'il ne l'a pas fait, toute navigation est
   renvoyée vers cet écran.
5. **Mot de passe oublié** : le secrétariat le réinitialise
   (`POST /v1/membres/{id}/compte/reinitialiser`), ce qui ferme au passage les
   sessions ouvertes.

L'alphabet du mot de passe provisoire exclut les caractères ambigus (ni `O`/`0`,
ni `I`/`l`/`1`) : il est fait pour être dicté au téléphone sans erreur.

Le compte est distinct de la fiche membre : un administrateur peut ne pas être
membre, et un membre peut ne jamais demander d'accès.


## Documentation de l'API

La spécification complète est dans `docs/openapi.yaml` (26 points d'entrée).
Elle s'ouvre dans n'importe quel client Swagger / Redoc / Postman.

Toutes les routes sont préfixées par `/api/v1` et renvoient une enveloppe uniforme :

```json
{ "donnees": { }, "message": "Paiement enregistré et ventilé." }
```

## Langue des messages

Les messages de validation sont traduits dans `lang/fr/`. Ils ne s'appliquent que
si la locale est active — vérifiez `.env` :

```dotenv
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
```

Sans cela, l'interface affiche les messages par défaut de Laravel en anglais
(« The selected membre id is invalid »).

## Points d'attention pour l'intégrateur

1. **Passerelles de paiement** — `OrangeMoneyPasserelle` et `MtnMomoPasserelle` suivent la
   structure documentée par les opérateurs, mais les URL, formats de charge utile et
   mécanismes de signature **doivent être alignés sur le contrat marchand effectivement
   signé**. Toute la logique spécifique est isolée derrière l'interface `Passerelle` :
   ajouter un opérateur consiste à écrire une classe et une ligne dans `moyens_paiement`.

2. **Sécurité des webhooks** — Orange Money est vérifié par signature HMAC, MTN par liste
   blanche d'adresses IP (MTN ne signe pas ses rappels). Ne mettez jamais ces routes en
   production sans avoir renseigné `OM_SECRET_WEBHOOK` et `MOMO_IPS_AUTORISEES`.

3. **Ventilation et arrondis** — `VentilationService` garantit que la somme des affectations
   est strictement égale au montant du paiement : le dernier poste absorbe le reliquat
   d'arrondi. Cette règle est indispensable à l'exactitude de l'assiette du reversement.

4. **Exercices clôturés** — un exercice clôturé refuse toute nouvelle carte, tout paiement
   et toute modification de tarif. Le taux de reversement appliqué est figé dans
   l'enregistrement, de sorte qu'une modification ultérieure du paramètre n'altère jamais
   les années passées.

5. **Génération PDF des reçus** — la table `recus` prévoit le champ `fichier` mais la
   génération du PDF n'est pas incluse : à brancher sur `dompdf` ou `laravel-snappy`
   dans `PaiementService::emettreRecu()`.

6. **Notifications** — l'envoi e-mail/SMS à la publication d'un rapport d'AG est signalé
   par un `TODO` dans `RapportAgController::publier()` ; il doit passer par une file
   d'attente (`Laravel Queue`) pour ne pas bloquer la requête.

## Tests recommandés avant recette

- Émission d'une carte pour chaque catégorie et vérification des montants.
- Paiement partiel puis complémentaire : le statut doit passer `impayee → partielle → soldee`.
- Somme des affectations = montant du paiement, sur des montants non divisibles (ex. 7 333 FCFA).
- Assiette du reversement = somme des affectations « groupement » des paiements validés.
- Un membre ne doit jamais pouvoir consulter les données d'un autre membre.
- Un rapport d'AG en brouillon ne doit pas apparaître dans la liste côté membre.
