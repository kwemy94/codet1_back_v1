<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private JournalService $journal) {}

    /** Connexion par e-mail OU par numéro de téléphone (CDC §22.3). */
    public function connexion(Request $requete)
    {
        $donnees = $requete->validate([
            'identifiant' => ['required', 'string'],   // e-mail ou téléphone
            'mot_de_passe' => ['required', 'string'],
        ]);

        $champ = filter_var($donnees['identifiant'], FILTER_VALIDATE_EMAIL) ? 'email' : 'telephone';

        $utilisateur = User::with('membre', 'roles.permissions')
            ->where($champ, $donnees['identifiant'])
            ->first();

        if (! $utilisateur || ! Hash::check($donnees['mot_de_passe'], $utilisateur->password)) {
            throw ValidationException::withMessages([
                'identifiant' => 'Identifiant ou mot de passe incorrect.',
            ]);
        }

        if ($utilisateur->statut !== 'actif') {
            throw ValidationException::withMessages([
                'identifiant' => 'Ce compte est suspendu. Rapprochez-vous du secrétariat du comité.',
            ]);
        }

        $utilisateur->update(['derniere_connexion_at' => now()]);
        $this->journal->tracer('connexion', $utilisateur, membreId: $utilisateur->membre_id);

        return $this->reponse([
            'jeton'       => $utilisateur->createToken('api')->plainTextToken,
            'utilisateur' => new UtilisateurResource($utilisateur),
        ]);
    }

    public function profil(Request $requete)
    {
        return $this->reponse(new UtilisateurResource($requete->user()->load('membre.categorie', 'roles.permissions')));
    }

    public function deconnexion(Request $requete)
    {
        $requete->user()->currentAccessToken()->delete();
        $this->journal->tracer('deconnexion', $requete->user());

        return $this->reponse(message: 'Déconnexion effectuée.');
    }

    public function changerMotDePasse(Request $requete)
    {
        $donnees = $requete->validate([
            'ancien_mot_de_passe'  => ['required', 'string'],
            'nouveau_mot_de_passe' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($donnees['ancien_mot_de_passe'], $requete->user()->password)) {
            throw ValidationException::withMessages([
                'ancien_mot_de_passe' => "L'ancien mot de passe est incorrect.",
            ]);
        }

        $utilisateur = $requete->user();

        $utilisateur->update([
            'password'                  => $donnees['nouveau_mot_de_passe'],
            'doit_changer_mot_de_passe' => false,
        ]);

        // Les autres sessions sont fermées ; celle en cours est renouvelée pour
        // que le membre poursuive sans avoir à se reconnecter.
        $utilisateur->tokens()->delete();
        $this->journal->tracer('changement_mot_de_passe', $utilisateur);

        return $this->reponse([
            'jeton'       => $utilisateur->createToken('api')->plainTextToken,
            'utilisateur' => new UtilisateurResource($utilisateur->fresh('membre', 'roles.permissions')),
        ], 'Mot de passe modifié.');
    }
}
