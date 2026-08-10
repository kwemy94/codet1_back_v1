<?php

namespace App\Services;

use App\Models\Membre;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création et réinitialisation des accès des membres.
 *
 * Le comité ne dispose pas d'un envoi de courriel fiable vers la diaspora : le
 * secrétariat crée l'accès, lit le mot de passe provisoire une seule fois à
 * l'écran, et le transmet au membre de vive voix, par SMS ou par WhatsApp.
 * Le membre est ensuite obligé de le remplacer à sa première connexion.
 */
class CompteMembreService
{
    /** Alphabet sans caractères ambigus : ni O/0, ni I/l/1. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(private JournalService $journal) {}

    public function creer(Membre $membre): array
    {
        if ($membre->compte) {
            throw ValidationException::withMessages([
                'membre' => "{$membre->nom_complet} possède déjà un accès. Utilisez la réinitialisation du mot de passe.",
            ]);
        }

        if ($membre->statut !== 'actif') {
            throw ValidationException::withMessages([
                'membre' => "Seul un membre actif peut recevoir un accès.",
            ]);
        }

        $this->verifierIdentifiantsLibres($membre);

        $motDePasse = $this->motDePasseProvisoire();

        $compte = DB::transaction(function () use ($membre, $motDePasse) {
            $compte = User::create([
                'membre_id'                 => $membre->id,
                'nom_affichage'             => $membre->nom_complet,
                'email'                     => $membre->email,
                'telephone'                 => $membre->telephone,
                'password'                  => $motDePasse,
                'statut'                    => 'actif',
                'doit_changer_mot_de_passe' => true,
            ]);

            $role = Role::where('code', 'MEMBRE')->first();

            if ($role) {
                $compte->roles()->attach($role->id, ['date_attribution' => now()->toDateString()]);
            }

            return $compte;
        });

        $this->journal->tracer('creation_acces_membre', $compte, membreId: $membre->id);

        return [
            'compte'              => $compte,
            'mot_de_passe_provisoire' => $motDePasse,
            'identifiants'        => array_values(array_filter([$membre->telephone, $membre->email])),
        ];
    }

    public function reinitialiser(Membre $membre): array
    {
        $compte = $membre->compte;

        if (! $compte) {
            throw ValidationException::withMessages([
                'membre' => "{$membre->nom_complet} n'a pas encore d'accès. Créez-le d'abord.",
            ]);
        }

        $motDePasse = $this->motDePasseProvisoire();

        DB::transaction(function () use ($compte, $motDePasse) {
            $compte->update([
                'password'                  => $motDePasse,
                'statut'                    => 'actif',
                'doit_changer_mot_de_passe' => true,
            ]);

            // Toute session ouverte avec l'ancien mot de passe est révoquée.
            $compte->tokens()->delete();
        });

        $this->journal->tracer('reinitialisation_mot_de_passe', $compte, membreId: $membre->id);

        return [
            'compte'                  => $compte->fresh(),
            'mot_de_passe_provisoire' => $motDePasse,
            'identifiants'            => array_values(array_filter([$compte->telephone, $compte->email])),
        ];
    }

    /**
     * Le téléphone et l'e-mail servent d'identifiants de connexion : ils doivent
     * donc être uniques. Deux membres d'un même foyer partageant un numéro,
     * le cas est fréquent et mérite un message explicite.
     */
    private function verifierIdentifiantsLibres(Membre $membre): void
    {
        if (! $membre->telephone && ! $membre->email) {
            throw ValidationException::withMessages([
                'membre' => "Renseignez un téléphone ou un e-mail sur la fiche avant de créer l'accès.",
            ]);
        }

        if ($membre->telephone && User::where('telephone', $membre->telephone)->exists()) {
            throw ValidationException::withMessages([
                'telephone' => "Le numéro {$membre->telephone} est déjà utilisé par un autre compte. "
                    ."Corrigez la fiche du membre avec un numéro qui lui est propre.",
            ]);
        }

        if ($membre->email && User::where('email', $membre->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => "L'adresse {$membre->email} est déjà utilisée par un autre compte.",
            ]);
        }
    }

    private function motDePasseProvisoire(int $longueur = 8): string
    {
        $motDePasse = '';

        for ($i = 0; $i < $longueur; $i++) {
            $motDePasse .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $motDePasse;
    }
}
