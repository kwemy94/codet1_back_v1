<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CarteResource;
use App\Models\CarteDeveloppement;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\TypeCarte;
use App\Services\CarteService;
use App\Services\JournalService;
use Illuminate\Http\Request;

class CarteController extends Controller
{
    public function __construct(
        private CarteService $cartes,
        private JournalService $journal,
    ) {}

    public function index(Request $requete)
    {
        $cartes = CarteDeveloppement::with('membre.categorie', 'exercice', 'typeCarte')
            ->when($requete->query('exercice_id'), fn ($q, $v) => $q->where('exercice_id', $v))
            ->when($requete->query('membre_id'), fn ($q, $v) => $q->where('membre_id', $v))
            ->when($requete->query('statut'), fn ($q, $v) => $q->where('statut', $v))
            ->when($requete->query('type_carte_id'), fn ($q, $v) => $q->where('type_carte_id', $v))
            ->orderByDesc('date_emission')
            ->paginate((int) $requete->query('par_page', 25));

        return CarteResource::collection($cartes);
    }

    public function store(Request $requete)
    {
        $donnees = $requete->validate([
            'membre_id'     => ['required', 'exists:membres,id'],
            'exercice_id'   => ['required', 'exists:exercices,id'],
            'type_carte_id' => ['required', 'exists:types_cartes,id'],
        ]);

        $carte = $this->cartes->emettre(
            Membre::with('categorie')->findOrFail($donnees['membre_id']),
            Exercice::findOrFail($donnees['exercice_id']),
            TypeCarte::findOrFail($donnees['type_carte_id']),
        );

        $this->journal->tracer('emission_carte', $carte, membreId: $carte->membre_id);

        return $this->reponse(
            new CarteResource($carte->load('membre', 'exercice', 'typeCarte', 'tarif.repartitions.destination')),
            "Carte « {$carte->typeCarte->libelle} » émise.",
            201
        );
    }

    public function show(CarteDeveloppement $carte)
    {
        return $this->reponse(new CarteResource(
            $carte->load('membre.categorie', 'exercice', 'typeCarte', 'tarif.repartitions.destination', 'paiements.moyenPaiement')
        ));
    }

    /** État des impayés de l'exercice (CDC §13). */
    public function impayes(Request $requete)
    {
        $exerciceId = $requete->query('exercice_id') ?: Exercice::courant()?->id;

        $cartes = CarteDeveloppement::with('membre.categorie', 'typeCarte')
            ->where('exercice_id', $exerciceId)
            ->whereIn('statut', ['impayee', 'partielle'])
            ->orderBy('statut')
            ->paginate((int) $requete->query('par_page', 50));

        return CarteResource::collection($cartes);
    }
}
