<?php

namespace Database\Seeders;

use App\Models\CategorieMembre;
use App\Models\DestinationFonds;
use App\Models\MoyenPaiement;
use App\Models\Parametre;
use App\Models\Pays;
use App\Models\TypeContribution;
use App\Models\Ville;
use Illuminate\Database\Seeder;

class ReferentielSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'HC', 'libelle' => 'Homme citadin ou diaspora', 'type_residence' => 'citadin_diaspora', 'sexe_concerne' => 'M'],
            ['code' => 'FC', 'libelle' => 'Femme citadine ou diaspora', 'type_residence' => 'citadin_diaspora', 'sexe_concerne' => 'F'],
            ['code' => 'HV', 'libelle' => 'Homme villageois',          'type_residence' => 'villageois',       'sexe_concerne' => 'M'],
            ['code' => 'FV', 'libelle' => 'Femme villageoise',         'type_residence' => 'villageois',       'sexe_concerne' => 'F'],
        ];

        foreach ($categories as $categorie) {
            CategorieMembre::updateOrCreate(['code' => $categorie['code']], $categorie);
        }

        // Le taux porté par la destination détermine ce qui revient au CODET I.
        $destinations = [
            ['code' => 'VILLAGE',    'libelle' => 'Développement du village',        'taux_reversement' => 0,   'couleur' => '#0F5C4A'],
            ['code' => 'GROUPEMENT', 'libelle' => 'Développement du groupement',     'taux_reversement' => 20,  'couleur' => '#A76F12'],
            ['code' => 'CONGRES',    'libelle' => 'Organisation du congrès annuel',  'taux_reversement' => 0,   'couleur' => '#4A6F8A'],
            ['code' => 'CODET',      'libelle' => 'Compte du CODET I',               'taux_reversement' => 100, 'couleur' => '#7A4E9C'],
        ];

        foreach ($destinations as $destination) {
            DestinationFonds::updateOrCreate(['code' => $destination['code']], $destination);
        }

        $moyens = [
            ['code' => 'ORANGE_MONEY', 'libelle' => 'Orange Money',      'type' => 'mobile_money', 'passerelle' => 'orange_money'],
            ['code' => 'MTN_MOMO',     'libelle' => 'MTN Mobile Money',  'type' => 'mobile_money', 'passerelle' => 'mtn_momo'],
            ['code' => 'ESPECES',      'libelle' => 'Espèces',           'type' => 'especes',      'passerelle' => null],
            ['code' => 'VIREMENT',     'libelle' => 'Virement bancaire', 'type' => 'virement',     'passerelle' => null],
        ];

        foreach ($moyens as $moyen) {
            MoyenPaiement::updateOrCreate(['code' => $moyen['code']], $moyen);
        }

        $types = [
            ['code' => 'DON_MEMBRE',      'libelle' => "Don volontaire d'un membre"],
            ['code' => 'DON_PHYSIQUE',    'libelle' => "Don d'une personne physique"],
            ['code' => 'DON_ENTREPRISE',  'libelle' => "Don d'une entreprise"],
            ['code' => 'DON_ASSOCIATION', 'libelle' => "Don d'une association"],
            ['code' => 'DON_PARTENAIRE',  'libelle' => "Don d'un partenaire"],
            ['code' => 'DON_MATERIEL',    'libelle' => 'Don en nature (matériel)'],
            ['code' => 'DON_SERVICE',     'libelle' => 'Don en services'],
        ];

        foreach ($types as $type) {
            TypeContribution::updateOrCreate(['code' => $type['code']], $type);
        }

        $cameroun = Pays::updateOrCreate(['code' => 'CMR'], ['libelle' => 'Cameroun']);
        foreach (['Tchuelekouet', 'Bafoussam', 'Douala', 'Yaoundé', 'Bandjoun'] as $ville) {
            Ville::updateOrCreate(['pays_id' => $cameroun->id, 'libelle' => $ville]);
        }

        foreach ([['FRA', 'France'], ['USA', 'États-Unis'], ['CAN', 'Canada'], ['DEU', 'Allemagne']] as [$code, $libelle]) {
            Pays::updateOrCreate(['code' => $code], ['libelle' => $libelle]);
        }

        $parametres = [
            ['code' => 'PREFIXE_MATRICULE', 'libelle' => 'Préfixe du matricule', 'valeur' => 'COD', 'type_valeur' => 'texte'],
            ['code' => 'NOM_COMITE',        'libelle' => 'Nom du comité',        'valeur' => 'CODET I', 'type_valeur' => 'texte'],
            ['code' => 'DEVISE',            'libelle' => 'Devise',               'valeur' => 'FCFA', 'type_valeur' => 'texte'],

            // En-tête des états PDF édités par le comité
            ['code' => 'COMITE_NOM_COMPLET', 'libelle' => 'Documents — nom complet du comité', 'valeur' => 'Comité de Développement du Village Tchuelekouet I', 'type_valeur' => 'texte'],
            ['code' => 'COMITE_TELEPHONE',   'libelle' => 'Documents — téléphone',             'valeur' => '(237) 695 43 95 02', 'type_valeur' => 'texte'],
            ['code' => 'COMITE_EMAIL',       'libelle' => 'Documents — adresse e-mail',        'valeur' => 'codet1@bangang.info', 'type_valeur' => 'texte'],

            // Mentions imprimées sur la carte unique de développement.
            // Elles se modifient depuis l'écran Paramètres, sans toucher au gabarit.
            ['code' => 'CARTE_SIGLE',        'libelle' => 'Carte — sigle du comité supérieur', 'valeur' => 'CO.SU.DE.G.BANG', 'type_valeur' => 'texte'],
            ['code' => 'CARTE_COMITE',       'libelle' => 'Carte — comité supérieur',          'valeur' => 'COMITÉ SUPÉRIEUR DE DÉVELOPPEMENT DU GROUPEMENT BANGANG', 'type_valeur' => 'texte'],
            ['code' => 'CARTE_GROUPEMENT',   'libelle' => 'Carte — groupement',                'valeur' => 'BANGANG', 'type_valeur' => 'texte'],
            ['code' => 'CARTE_VILLAGE',      'libelle' => "Carte — village d'origine",         'valeur' => 'Tchuelekouet I', 'type_valeur' => 'texte'],
            ['code' => 'CARTE_RECEPISSE',    'libelle' => 'Carte — récépissé',                 'valeur' => 'N°012/RDA/F31/SAAJP du 08 Septembre 2016', 'type_valeur' => 'texte'],
            ['code' => 'CARTE_TEL_PRESIDENT','libelle' => 'Carte — téléphones du président',   'valeur' => '(237) 695 43 95 02 / 675 08 00 71', 'type_valeur' => 'texte'],
            ['code' => 'CARTE_TEL_TRESORIER','libelle' => 'Carte — téléphone du trésorier',    'valeur' => '670 59 02 64', 'type_valeur' => 'texte'],
            ['code' => 'CARTE_EMAIL',        'libelle' => 'Carte — adresse e-mail',            'valeur' => 'cosudegbang@gmail.com', 'type_valeur' => 'texte'],
            ['code' => 'CARTE_SITE',         'libelle' => 'Carte — site web',                  'valeur' => 'www.bangang.info', 'type_valeur' => 'texte'],
            ['code' => 'CARTE_SLOGAN',       'libelle' => 'Carte — devise du comité',          'valeur' => "Esprit d'équipe ; transparence !", 'type_valeur' => 'texte'],
            ['code' => 'CARTE_PRESIDENT',    'libelle' => 'Carte — nom du président',          'valeur' => 'NGUEDJIO Blaise TASHIE', 'type_valeur' => 'texte'],
            ['code' => 'CARTE_COMMISSAIRE',  'libelle' => 'Carte — commissaire aux comptes',   'valeur' => 'YEFOU Simon', 'type_valeur' => 'texte'],
        ];

        foreach ($parametres as $parametre) {
            Parametre::updateOrCreate(['code' => $parametre['code']], $parametre);
        }
    }
}
