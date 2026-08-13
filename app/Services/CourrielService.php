<?php

namespace App\Services;

use App\Jobs\EnvoyerCourrielMembre;
use App\Models\CampagneCourriel;
use App\Models\Exercice;
use App\Models\Membre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Envoi de courriels aux membres.
 *
 * Beaucoup de ressortissants n'ont qu'un numéro de téléphone : la sélection
 * distingue donc toujours les membres retenus de ceux qui n'ont pas d'adresse.
 * Le secrétariat sait ainsi qui il devra joindre autrement.
 */
class CourrielService
{
    public function __construct(private JournalService $journal) {}

    /**
     * Construit la sélection sans rien envoyer : sert à l'aperçu comme à
     * l'envoi, de sorte que le nombre annoncé soit toujours celui obtenu.
     */
    public function selection(array $criteres): array
    {
        $requete = Membre::query()
            ->with('categorie')
            ->when(
                ! empty($criteres['membre_ids']),
                fn (Builder $q) => $q->whereIn('id', $criteres['membre_ids']),
                fn (Builder $q) => $this->appliquerCriteres($q, $criteres),
            );

        $membres = $requete->orderBy('nom')->get();

        $avecAdresse = $membres->filter(fn (Membre $m) => filter_var($m->email, FILTER_VALIDATE_EMAIL));
        $sansAdresse = $membres->reject(fn (Membre $m) => filter_var($m->email, FILTER_VALIDATE_EMAIL));

        return [
            'retenus'      => $avecAdresse->values(),
            'sans_adresse' => $sansAdresse->values(),
        ];
    }

    /** Crée la campagne et met un envoi en file par destinataire. */
    public function envoyer(array $donnees): CampagneCourriel
    {
        $selection = $this->selection($donnees['criteres'] ?? []);

        if ($selection['retenus']->isEmpty()) {
            throw ValidationException::withMessages([
                'destinataires' => $selection['sans_adresse']->isEmpty()
                    ? 'Aucun membre ne correspond à cette sélection.'
                    : "Aucun des {$selection['sans_adresse']->count()} membres retenus n'a d'adresse e-mail enregistrée.",
            ]);
        }

        $campagne = DB::transaction(function () use ($donnees, $selection) {
            $campagne = CampagneCourriel::create([
                'objet'                => $donnees['objet'],
                'contenu'              => $donnees['contenu'],
                'portee'               => $selection['retenus']->count() > 1 ? 'collectif' : 'individuel',
                'criteres'             => $donnees['criteres'] ?? [],
                'nombre_destinataires' => $selection['retenus']->count(),
                'nombre_sans_adresse'  => $selection['sans_adresse']->count(),
                'statut'               => 'en_cours',
                'date_envoi'           => now(),
                'envoye_par'           => auth()->id(),
            ]);

            $campagne->destinataires()->createMany(
                $selection['retenus']->map(fn (Membre $membre) => [
                    'membre_id' => $membre->id,
                    'adresse'   => $membre->email,
                    'statut'    => 'en_attente',
                ])->all()
            );

            return $campagne;
        });

        // Les envois partent après la transaction : la campagne est déjà écrite
        // en base quand la file commence à la traiter.
        $campagne->destinataires->each(fn ($destinataire) => EnvoyerCourrielMembre::dispatch($destinataire));

        $this->journal->tracer('envoi_courriel', $campagne, nouvelleValeur: [
            'objet'        => $campagne->objet,
            'destinataires' => $campagne->nombre_destinataires,
        ]);

        return $campagne->load('destinataires');
    }

    private function appliquerCriteres(Builder $requete, array $criteres): Builder
    {
        return $requete
            // Par défaut, seuls les membres actifs : on n'écrit ni à un défunt
            // ni à un membre suspendu. Le statut « inactif » reste sélectionnable
            // explicitement, pour une relance ciblée par exemple.
            ->where('statut', $criteres['statut'] ?? 'actif')
            ->when($criteres['categorie_id'] ?? null, fn ($q, $v) => $q->where('categorie_membre_id', $v))
            ->when($criteres['sexe'] ?? null, fn ($q, $v) => $q->where('sexe', $v))
            ->when($criteres['ville_id'] ?? null, fn ($q, $v) => $q->where('ville_id', $v))
            ->when($criteres['pays_id'] ?? null, fn ($q, $v) => $q->whereHas('ville', fn ($r) => $r->where('pays_id', $v)))
            ->when(
                $criteres['situation'] ?? null,
                fn ($q, $situation) => $this->filtrerParSituation($q, $situation, $criteres['exercice_id'] ?? null),
            );
    }

    /**
     * Filtre sur la situation de cotisation. « En retard » englobe les membres
     * sans carte du tout : ne pas avoir pris sa carte est aussi un retard.
     */
    private function filtrerParSituation(Builder $requete, string $situation, ?int $exerciceId): Builder
    {
        $exerciceId = $exerciceId ?: Exercice::courant()?->id;

        if (! $exerciceId) {
            return $requete;
        }

        $soldee = fn ($r) => $r->where('exercice_id', $exerciceId)->where('statut', 'soldee');

        return match ($situation) {
            'a_jour'   => $requete->whereHas('cartes', $soldee),
            'en_retard' => $requete->whereDoesntHave('cartes', $soldee),
            'sans_carte' => $requete->whereDoesntHave('cartes', fn ($r) => $r->where('exercice_id', $exerciceId)),
            default    => $requete,
        };
    }
}
