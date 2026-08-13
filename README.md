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


## Cycle de vie d'une contribution

| Nature | Passage à l'état final | Qui agit |
|---|---|---|
| Financière | *Encaissée* **automatiquement**, dès que les paiements validés couvrent le montant | Personne : c'est l'enregistrement du paiement qui décide |
| Matérielle ou en services | *Reçue*, par constat explicite | L'administrateur, à la réception du bien ou du service |
| Toute nature | *Annulée*, tant qu'elle est *Attendue* | L'administrateur |

L'API refuse un passage manuel à *Encaissée* sans paiement validé : aucun montant
ne peut figurer aux recettes sans trace d'encaissement correspondante. Elle refuse
aussi d'enregistrer un paiement sur un don en nature, qui n'a pas de flux financier.

Un versement partiel ne solde pas la contribution : elle reste *Attendue*, l'écran
affiche le reste à encaisser et propose « Compléter ». Elle bascule en *Encaissée*
au dernier franc.

## Statut d'un membre

Un membre n'est **jamais supprimé**. Ses cotisations, ses dons et ses cartes
restent aux comptes du comité : les effacer rendrait faux les états financiers
des exercices passés, y compris ceux déjà présentés en assemblée.

| Statut | Ce qu'il produit | Réversible |
|---|---|---|
| **Actif** | Situation normale | — |
| **Inactif** (suspendu) | Sort des listes actives, accès fermé et sessions révoquées, plus d'appel à cotisation, aucune carte émissible | Oui, à tout moment |
| **Décédé** | Retiré des envois et des appels à cotisation, fiche et historique conservés | Non — corriger la fiche en cas d'erreur |

La suspension exige un **motif**, conservé sur la fiche avec la date : six mois
plus tard, personne ne se souvient pourquoi tel ressortissant a été écarté. Le
motif s'affiche dans la liste et en bandeau sur la fiche, et la réactivation le
rappelle avant de lever la décision.

Le décès est volontairement distinct : ce n'est pas une sanction, il ne se lève
pas, et le confondre avec une suspension conduirait à relancer une famille en
deuil pour une cotisation.


## Courriels aux membres

L'administration écrit à un membre en particulier, ou à une sélection : tous les
actifs, une catégorie, un sexe, une ville, un pays, ou une **situation de
cotisation** — à jour, en retard, sans carte pour l'exercice. C'est ce dernier
critère qui rend la fonction utile : « tous les membres en retard sur 2026 »
est l'appel que le trésorier envoie avant chaque assemblée.

**Beaucoup de ressortissants n'ont qu'un numéro de téléphone.** Chaque sélection
annonce donc deux nombres : les membres qui seront atteints, et ceux qui ne le
seront pas faute d'adresse, avec leur téléphone pour être joints autrement.
L'aperçu et l'envoi partagent le même code de sélection : le nombre annoncé
avant envoi est exactement celui obtenu.

Les envois passent par la **file d'attente**, un travail par destinataire. Un
collectif de trois cents membres ne bloque pas la requête du secrétaire, et
l'échec d'une adresse n'interrompt pas les suivantes. Chaque destinataire
conserve son statut — en attente, envoyé, échoué — avec le motif d'échec.

Configuration nécessaire dans `.env` :

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="codet1@bangang.info"
MAIL_FROM_NAME="CODET I"
```

Et un consommateur de file en service :

```bash
php artisan queue:work --tries=3
```

Sans ce processus, les envois restent en attente indéfiniment. En production,
placez-le sous supervision (systemd ou supervisor) pour qu'il redémarre seul.

Chaque campagne est conservée avec ses destinataires : le comité doit pouvoir
dire qui a été convoqué et quand. Une convocation d'assemblée est un acte
administratif, pas un simple courriel.


## États PDF

Deux documents sont éditables par l'administration, avec en-tête du comité
(sigle, nom complet, village, groupement, récépissé, contacts) tiré des
paramètres :

| Document | Contenu |
|---|---|
| **Ventes de cartes** d'un exercice | Une ligne par carte : noms et prénoms, matricule, ville de résidence, type de carte, montants encaissés pour le groupement, le village et le congrès, total réglé, reste dû. Ligne de totaux et encadré de rapprochement. |
| **Historique d'un membre** | Une ligne par exercice cotisé, avec ventilation, montant dû, réglé et reste dû. Se termine par la situation : exercices avec impayé et total, ou mention « à jour ». |
| **Contributions et dons** d'un exercice | Une ligne par contribution : référence, origine, nature, objet, montant, date, statut. Récapitulatif séparant les dons financiers encaissés de la valeur estimée des dons en nature. |

**Les montants affichés sont ceux réellement encaissés**, c'est-à-dire les
affectations des paiements validés — pas les parts théoriques du tarif. Un
membre qui n'a réglé que la moitié de sa carte apparaît donc pour la moitié de
chaque poste. C'est la seule lecture exploitable par le trésorier, et elle
garantit que la somme des trois colonnes égale le total encaissé.

Les dons financiers et les dons en nature ne se totalisent jamais ensemble :
les premiers entrent en caisse, les seconds ne sont qu'une valeur estimée
portée aux comptes. L'état le rappelle explicitement sous le récapitulatif.

Les états reprennent **les filtres de l'écran** (exercice, statut, nature, type de carte) : le document correspond exactement à la liste affichée.
Dès qu'un filtre est actif, un bandeau en tête précise lequel et rappelle que
les totaux ne portent que sur les lignes retenues — sans quoi une liste
partielle pourrait être présentée en assemblée comme l'état complet.

La génération repose sur `barryvdh/laravel-dompdf`, installé par le script
d'installation. Si vous avez installé le projet à la main :

```bash
composer require barryvdh/laravel-dompdf
```

Les gabarits sont dans `resources/views/pdf/`. La numérotation des pages passe
par le canevas dompdf plutôt que par un script dans le gabarit, ce qui évite
d'activer l'exécution de PHP dans le moteur de rendu.


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
