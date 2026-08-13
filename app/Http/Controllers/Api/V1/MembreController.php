<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StockerMembreRequest;
use App\Http\Requests\ModifierMembreRequest;
use App\Http\Resources\MembreResource;
use App\Models\Membre;
use App\Services\JournalService;
use App\Services\MatriculeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MembreController extends Controller
{
    public function __construct(
        private MatriculeService $matricules,
        private JournalService $journal,
    ) {}

    public function index(Request $requete)
    {
        $membres = Membre::with('categorie', 'ville.pays')
            ->recherche($requete->query('recherche'))
            ->when($requete->query('statut'), fn ($q, $v) => $q->where('statut', $v))
            ->when($requete->query('categorie_id'), fn ($q, $v) => $q->where('categorie_membre_id', $v))
            ->when($requete->query('sexe'), fn ($q, $v) => $q->where('sexe', $v))
            ->orderBy('nom')
            ->paginate((int) $requete->query('par_page', 25));

        return MembreResource::collection($membres);
    }

    public function store(StockerMembreRequest $requete)
    {
        $donnees = $requete->validated();
        $donnees['matricule']     = $this->matricules->genererPour();
        $donnees['date_adhesion'] = $donnees['date_adhesion'] ?? now()->toDateString();

        $membre = Membre::create($donnees);
        $this->journal->tracer('creation_membre', $membre, nouvelleValeur: $membre->toArray(), membreId: $membre->id);

        return $this->reponse(new MembreResource($membre->load('categorie', 'ville')), 'Membre enregistré.', 201);
    }

    public function show(Membre $membre)
    {
        return $this->reponse(new MembreResource(
            $membre->load(
                'categorie',
                'ville.pays',
                'cartes.exercice',
                'cartes.typeCarte',
                'cartes.tarif.repartitions.destination',
                'contributions.type',
                'contributions.exercice',
                'compte',
            )
        ));
    }

    public function update(ModifierMembreRequest $requete, Membre $membre)
    {
        $ancien = $membre->toArray();
        $membre->update($requete->validated());

        $this->journal->tracer(
            'modification_membre',
            $membre,
            ancienneValeur: $ancien,
            nouvelleValeur: $membre->fresh()->toArray(),
            membreId: $membre->id,
        );

        return $this->reponse(new MembreResource($membre->fresh('categorie', 'ville')), 'Membre mis à jour.');
    }

    /**
     * Suspension d'un membre.
     *
     * Un membre n'est jamais supprimé : ses cotisations, ses dons et ses cartes
     * restent aux comptes du comité, sans quoi les états financiers des années
     * passées deviendraient faux. La suspension est **réversible** — elle sort
     * le membre des listes actives et ferme son accès, rien de plus.
     */
    public function suspendre(Request $requete, Membre $membre)
    {
        $donnees = $requete->validate([
            'motif' => ['required', 'string', 'max:255'],
        ]);

        if ($membre->statut === 'decede') {
            throw ValidationException::withMessages([
                'statut' => 'Ce membre est enregistré comme décédé.',
            ]);
        }

        $ancien = $membre->only(['statut', 'motif_statut']);

        $membre->update([
            'statut'                 => 'inactif',
            'motif_statut'           => $donnees['motif'],
            'date_changement_statut' => now()->toDateString(),
        ]);

        // L'accès est fermé et les sessions ouvertes révoquées.
        $membre->compte?->update(['statut' => 'suspendu']);
        $membre->compte?->tokens()->delete();

        $this->journal->tracer(
            'suspension_membre',
            $membre,
            ancienneValeur: $ancien,
            nouvelleValeur: $membre->only(['statut', 'motif_statut']),
            membreId: $membre->id,
        );

        return $this->reponse(new MembreResource($membre->fresh()), "{$membre->nom_complet} est suspendu.");
    }

    /** Réactivation : le membre retrouve sa place et son accès. */
    public function reactiver(Request $requete, Membre $membre)
    {
        if ($membre->statut === 'decede') {
            throw ValidationException::withMessages([
                'statut' => "Un membre enregistré comme décédé ne peut pas être réactivé. "
                    .'Corrigez son statut depuis la modification de sa fiche si le constat était erroné.',
            ]);
        }

        $ancien = $membre->only(['statut', 'motif_statut']);

        $membre->update([
            'statut'                 => 'actif',
            'motif_statut'           => $requete->input('motif'),
            'date_changement_statut' => now()->toDateString(),
        ]);

        $membre->compte?->update(['statut' => 'actif']);

        $this->journal->tracer(
            'reactivation_membre',
            $membre,
            ancienneValeur: $ancien,
            nouvelleValeur: $membre->only(['statut', 'motif_statut']),
            membreId: $membre->id,
        );

        return $this->reponse(new MembreResource($membre->fresh()), "{$membre->nom_complet} est réactivé.");
    }

    /**
     * Constat de décès. Distinct de la suspension : il n'est pas motivé par un
     * manquement, il n'est pas destiné à être levé, et il retire définitivement
     * le membre des envois et des appels à cotisation.
     */
    public function declarerDecede(Request $requete, Membre $membre)
    {
        $donnees = $requete->validate([
            'date_deces' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $ancien = $membre->only(['statut', 'motif_statut']);

        $membre->update([
            'statut'                 => 'decede',
            'motif_statut'           => 'Décès constaté',
            'date_changement_statut' => $donnees['date_deces'] ?? now()->toDateString(),
        ]);

        $membre->compte?->update(['statut' => 'suspendu']);
        $membre->compte?->tokens()->delete();

        $this->journal->tracer(
            'deces_membre',
            $membre,
            ancienneValeur: $ancien,
            nouvelleValeur: $membre->only(['statut', 'date_changement_statut']),
            membreId: $membre->id,
        );

        return $this->reponse(new MembreResource($membre->fresh()), 'Statut enregistré.');
    }
}
