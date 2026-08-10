<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\DestinationFonds;
use App\Models\Exercice;
use App\Models\TarifCarte;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TarifCarteController extends Controller
{
    public function __construct(private JournalService $journal) {}

    public function index(Request $requete)
    {
        $tarifs = TarifCarte::with('categorie', 'exercice', 'typeCarte', 'repartitions.destination')
            ->when($requete->query('exercice_id'), fn ($q, $v) => $q->where('exercice_id', $v))
            ->when($requete->query('type_carte_id'), fn ($q, $v) => $q->where('type_carte_id', $v))
            ->when($requete->boolean('actifs_seulement', true), fn ($q) => $q->actif())
            ->get();

        return $this->reponse($tarifs);
    }

    /**
     * Toute modification crée une NOUVELLE version : l'ancienne est clôturée
     * par sa date de fin de validité, ce qui préserve les cartes déjà émises.
     *
     * La répartition est libre : autant de destinations que nécessaire, une
     * seule à 100 % si la totalité revient au CODET I. Seule contrainte, la
     * somme des lignes doit égaler le montant minimum.
     */
    public function store(Request $requete)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate([
            'exercice_id'                        => ['required', 'exists:exercices,id'],
            'type_carte_id'                      => ['required', 'exists:types_cartes,id'],
            'categorie_membre_id'                => ['nullable', 'exists:categories_membres,id'],
            'montant_minimum'                    => ['required', 'integer', 'min:1'],
            'repartitions'                       => ['required', 'array', 'min:1'],
            'repartitions.*.destination_fonds_id' => ['required', 'exists:destinations_fonds,id'],
            'repartitions.*.montant'             => ['required', 'integer', 'min:0'],
        ]);

        $somme = collect($donnees['repartitions'])->sum('montant');

        if ((int) $somme !== (int) $donnees['montant_minimum']) {
            throw ValidationException::withMessages([
                'repartitions' => "La répartition totalise {$somme} FCFA : elle doit être égale au montant de {$donnees['montant_minimum']} FCFA.",
            ]);
        }

        $destinations = collect($donnees['repartitions'])->pluck('destination_fonds_id');

        if ($destinations->count() !== $destinations->unique()->count()) {
            throw ValidationException::withMessages([
                'repartitions' => 'Une même destination ne peut apparaître deux fois dans la répartition.',
            ]);
        }

        $exercice = Exercice::findOrFail($donnees['exercice_id']);

        if (! $exercice->estOuvert()) {
            throw ValidationException::withMessages([
                'exercice_id' => "L'exercice {$exercice->annee} est clôturé : ses tarifs ne peuvent plus être modifiés.",
            ]);
        }

        $tarif = DB::transaction(function () use ($donnees) {
            TarifCarte::actif()
                ->where('exercice_id', $donnees['exercice_id'])
                ->where('type_carte_id', $donnees['type_carte_id'])
                ->where('categorie_membre_id', $donnees['categorie_membre_id'] ?? null)
                ->update(['date_fin_validite' => now()->toDateString()]);

            $tarif = TarifCarte::create([
                'exercice_id'         => $donnees['exercice_id'],
                'type_carte_id'       => $donnees['type_carte_id'],
                'categorie_membre_id' => $donnees['categorie_membre_id'] ?? null,
                'montant_minimum'     => $donnees['montant_minimum'],
                'date_debut_validite' => now()->toDateString(),
            ]);

            foreach ($donnees['repartitions'] as $ligne) {
                if ((int) $ligne['montant'] <= 0) {
                    continue;
                }

                $tarif->repartitions()->create([
                    'destination_fonds_id' => $ligne['destination_fonds_id'],
                    'montant'              => $ligne['montant'],
                ]);
            }

            return $tarif;
        });

        $this->journal->tracer('creation_tarif', $tarif, nouvelleValeur: $tarif->toArray());

        return $this->reponse(
            $tarif->load('categorie', 'typeCarte', 'repartitions.destination'),
            'Nouvelle version du tarif enregistrée.',
            201
        );
    }

    public function historique(Request $requete)
    {
        $donnees = $requete->validate([
            'type_carte_id'       => ['nullable', 'exists:types_cartes,id'],
            'categorie_membre_id' => ['nullable', 'exists:categories_membres,id'],
        ]);

        return $this->reponse(
            TarifCarte::with('exercice', 'typeCarte', 'categorie', 'repartitions.destination')
                ->when($donnees['type_carte_id'] ?? null, fn ($q, $v) => $q->where('type_carte_id', $v))
                ->when($donnees['categorie_membre_id'] ?? null, fn ($q, $v) => $q->where('categorie_membre_id', $v))
                ->orderByDesc('date_debut_validite')
                ->get()
        );
    }

    /** Destinations disponibles et leur taux de reversement au CODET I. */
    public function destinations()
    {
        return $this->reponse(DestinationFonds::orderBy('libelle')->get());
    }

    public function modifierDestination(Request $requete, DestinationFonds $destination)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate([
            'libelle'          => ['sometimes', 'string', 'max:255'],
            'taux_reversement' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'actif'            => ['sometimes', 'boolean'],
        ]);

        $ancien = $destination->toArray();
        $destination->update($donnees);

        $this->journal->tracer(
            'modification_destination',
            $destination,
            ancienneValeur: $ancien,
            nouvelleValeur: $destination->fresh()->toArray(),
        );

        return $this->reponse($destination->fresh(), 'Destination mise à jour. Les exercices déjà calculés ne sont pas modifiés.');
    }
}
