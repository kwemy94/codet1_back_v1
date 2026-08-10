<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StockerMembreRequest;
use App\Http\Requests\ModifierMembreRequest;
use App\Http\Resources\MembreResource;
use App\Models\Membre;
use App\Services\JournalService;
use App\Services\MatriculeService;
use Illuminate\Http\Request;

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

    /** Suspension : le membre n'est jamais supprimé, son statut passe à « inactif ». */
    public function suspendre(Membre $membre)
    {
        $membre->update(['statut' => 'inactif']);
        $membre->compte?->update(['statut' => 'suspendu']);
        $this->journal->tracer('suspension_membre', $membre, membreId: $membre->id);

        return $this->reponse(new MembreResource($membre), 'Membre suspendu.');
    }

    public function reactiver(Membre $membre)
    {
        $membre->update(['statut' => 'actif']);
        $membre->compte?->update(['statut' => 'actif']);
        $this->journal->tracer('reactivation_membre', $membre, membreId: $membre->id);

        return $this->reponse(new MembreResource($membre), 'Membre réactivé.');
    }
}
