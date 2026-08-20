<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CarteResource;
use App\Models\CarteDeveloppement;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\TypeCarte;
use App\Services\CarteService;
use App\Services\JournalService;
use App\Services\TraitementLotService;
use Illuminate\Http\Request;

class CarteController extends Controller
{
    public function __construct(
        private CarteService $cartes,
        private JournalService $journal,
        private TraitementLotService $lots,
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
            'membre_id' => ['required', 'exists:membres,id'],
            'exercice_id' => ['required', 'exists:exercices,id'],
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

    /**
     * Émission en lot.
     *
     * Chaque membre est traité séparément : un membre déjà titulaire d'une
     * carte de ce type, ou suspendu, n'empêche pas l'émission pour les autres.
     * Le rapport nomme chaque échec et sa raison.
     */
    public function emettreEnLot(Request $requete)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate([
            'membre_ids' => ['required', 'array', 'min:1', 'max:500'],
            'membre_ids.*' => ['integer', 'exists:membres,id'],
            'exercice_id' => ['required', 'exists:exercices,id'],
            'type_carte_id' => ['required', 'exists:types_cartes,id'],
        ]);

        $exercice = Exercice::findOrFail($donnees['exercice_id']);
        $type = TypeCarte::findOrFail($donnees['type_carte_id']);

        $membres = Membre::with('categorie')->whereIn('id', $donnees['membre_ids'])->get();

        $rapport = $this->lots->executer(
            $membres,
            function (Membre $membre) use ($exercice, $type) {
                $carte = $this->cartes->emettre($membre, $exercice, $type);

                return [
                    'carte_id' => $carte->id,
                    'numero_carte' => $carte->numero_carte,
                    'montant_du' => (int) $carte->montant_du,
                ];
            },
            fn (Membre $membre) => [
                'membre_id' => $membre->id,
                'matricule' => $membre->matricule,
                'nom_complet' => $membre->nom_complet,
            ],
        );

        $this->journal->tracer('emission_cartes_lot', $exercice, nouvelleValeur: [
            'type_carte' => $type->libelle,
            'emises' => count($rapport['reussites']),
            'echecs' => count($rapport['echecs']),
        ]);

        return $this->reponse(
            $rapport,
            $this->resumer(count($rapport['reussites']), count($rapport['echecs']), 'carte(s) émise(s)'),
        );
    }

    /** Formule le résumé de l'opération, sans jamais masquer les échecs. */
    private function resumer(int $reussites, int $echecs, string $objet): string
    {
        if ($echecs === 0) {
            return "{$reussites} {$objet}.";
        }

        if ($reussites === 0) {
            return "Aucune opération n'a abouti : {$echecs} échec(s). Voir le détail.";
        }

        return "{$reussites} {$objet}, {$echecs} en échec. Voir le détail.";
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
