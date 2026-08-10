<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Membre;
use App\Services\CompteMembreService;

class CompteMembreController extends Controller
{
    public function __construct(private CompteMembreService $comptes) {}

    /**
     * Ouvre l'accès d'un membre. Le mot de passe provisoire n'est renvoyé
     * qu'ici, une seule fois : il n'est stocké nulle part en clair.
     */
    public function store(Membre $membre)
    {
        $this->refuserSiNonAdministrateur();

        $resultat = $this->comptes->creer($membre);

        return $this->reponse([
            'identifiants'            => $resultat['identifiants'],
            'mot_de_passe_provisoire' => $resultat['mot_de_passe_provisoire'],
        ], "Accès créé pour {$membre->nom_complet}. Notez le mot de passe : il ne sera plus affiché.", 201);
    }

    public function reinitialiser(Membre $membre)
    {
        $this->refuserSiNonAdministrateur();

        $resultat = $this->comptes->reinitialiser($membre);

        return $this->reponse([
            'identifiants'            => $resultat['identifiants'],
            'mot_de_passe_provisoire' => $resultat['mot_de_passe_provisoire'],
        ], 'Mot de passe réinitialisé. Les sessions ouvertes ont été fermées.');
    }

    public function suspendre(Membre $membre)
    {
        $this->refuserSiNonAdministrateur();

        $membre->compte?->update(['statut' => 'suspendu']);
        $membre->compte?->tokens()->delete();

        return $this->reponse(message: "Accès suspendu pour {$membre->nom_complet}.");
    }
}
