<?php

namespace App\Services;

use App\Models\Parametre;

/**
 * En-tête commun aux documents édités par le comité (PDF).
 *
 * Toutes les mentions viennent des paramètres : le jour où le bureau change ou
 * qu'un numéro de téléphone évolue, aucun gabarit n'est à reprendre.
 */
class EnteteDocumentService
{
    public function mentions(): array
    {
        return [
            'sigle'        => Parametre::valeur('NOM_COMITE', 'CODET I'),
            'nom_complet'  => Parametre::valeur('COMITE_NOM_COMPLET', 'Comité de Développement du Village Tchuelekouet I'),
            'village'      => Parametre::valeur('CARTE_VILLAGE', 'Tchuelekouet I'),
            'groupement'   => Parametre::valeur('CARTE_GROUPEMENT', 'Bangang'),
            'recepisse'    => Parametre::valeur('CARTE_RECEPISSE', ''),
            'telephone'    => Parametre::valeur('COMITE_TELEPHONE', Parametre::valeur('CARTE_TEL_PRESIDENT', '')),
            'email'        => Parametre::valeur('COMITE_EMAIL', Parametre::valeur('CARTE_EMAIL', '')),
            'site'         => Parametre::valeur('CARTE_SITE', ''),
            'president'    => Parametre::valeur('CARTE_PRESIDENT', ''),
            'devise'       => Parametre::valeur('DEVISE', 'FCFA'),
        ];
    }
}
