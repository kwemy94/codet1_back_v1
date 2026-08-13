<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Contribution;
use App\Models\Donateur;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Contributions volontaires et dons.
 *
 * Un don peut être financier, matériel ou en services. Un don matériel n'a pas
 * de flux de trésorerie : son montant porte la valeur estimée du bien, utile
 * aux états financiers, et son cycle de vie s'achève sur le statut « reçue »
 * plutôt que « encaissée ».
 */
class ContributionController extends Controller
{
    public function __construct(private JournalService $journal) {}

    public function index(Request $requete)
    {
        $contributions = Contribution::with('membre', 'donateur', 'type', 'exercice')
            ->withSum(['paiements as montant_regle' => fn ($requete) => $requete->where('statut', 'valide')], 'montant')
            ->when($requete->query('exercice_id'), fn ($q, $v) => $q->where('exercice_id', $v))
            ->when($requete->query('membre_id'), fn ($q, $v) => $q->where('membre_id', $v))
            ->when($requete->query('statut'), fn ($q, $v) => $q->where('statut', $v))
            ->when($requete->query('nature'), fn ($q, $v) => $q->where('nature', $v))
            ->orderByDesc('date_contribution')
            ->paginate((int) $requete->query('par_page', 25));

        return $this->reponse($contributions);
    }

    public function store(Request $requete)
    {
        $donnees = $requete->validate([
            'membre_id'            => ['nullable', 'exists:membres,id'],
            'donateur_id'          => ['nullable', 'exists:donateurs,id'],
            'type_contribution_id' => ['required', 'exists:types_contributions,id'],
            'exercice_id'          => ['required', 'exists:exercices,id'],
            'date_contribution'    => ['required', 'date'],
            'nature'               => ['required', 'in:financier,materiel,service'],
            'designation'          => ['nullable', 'string', 'max:255'],
            'montant'              => ['required', 'integer', 'min:1'],
            'motif'                => ['nullable', 'string', 'max:255'],
            'observation'          => ['nullable', 'string'],
        ]);

        if ((bool) ($donnees['membre_id'] ?? null) === (bool) ($donnees['donateur_id'] ?? null)) {
            throw ValidationException::withMessages([
                'membre_id' => 'Indiquez soit un membre, soit un donateur externe, mais pas les deux.',
            ]);
        }

        if ($donnees['nature'] !== 'financier' && empty($donnees['designation'])) {
            throw ValidationException::withMessages([
                'designation' => 'Décrivez le bien ou le service donné (« 5 sacs de ciment », « 2 journées de maçonnerie »).',
            ]);
        }

        $contribution = Contribution::create($donnees + [
            'reference'      => sprintf('CONT-%s-%06d', date('Y'), Contribution::whereYear('created_at', date('Y'))->count() + 1),
            'statut'         => 'attendue',
            'enregistre_par' => $requete->user()->id,
        ]);

        $this->journal->tracer('enregistrement_contribution', $contribution, membreId: $contribution->membre_id);

        return $this->reponse(
            $contribution->load('type', 'membre', 'donateur'),
            'Contribution enregistrée.',
            201
        );
    }

    public function show(Contribution $contribution)
    {
        return $this->reponse($contribution->load('membre', 'donateur', 'type', 'exercice', 'paiements.moyenPaiement', 'justificatifs'));
    }

    /**
     * Mise à jour du statut.
     *
     * Une contribution financière passe à « encaissée » par le paiement qui la
     * règle ; elle ne peut donc pas être marquée reçue à la main. Un don
     * matériel, lui, n'a pas de paiement : c'est le secrétariat qui constate
     * sa réception.
     */
    public function changerStatut(Request $requete, Contribution $contribution)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate([
            'statut'         => ['required', 'in:attendue,encaissee,recue,annulee'],
            'date_reception' => ['nullable', 'date'],
            'observation'    => ['nullable', 'string', 'max:500'],
        ]);

        if ($donnees['statut'] === 'encaissee' && ! $contribution->estMaterielle()) {
            $regle = $contribution->paiements()->where('statut', 'valide')->exists();

            if (! $regle) {
                throw ValidationException::withMessages([
                    'statut' => "Une contribution financière est encaissée par l'enregistrement de son paiement, "
                        .'pas par un changement de statut manuel.',
                ]);
            }
        }

        if ($donnees['statut'] === 'recue' && ! $contribution->estMaterielle()) {
            throw ValidationException::withMessages([
                'statut' => 'Le statut « reçue » ne concerne que les dons matériels ou en services.',
            ]);
        }

        $ancien = $contribution->toArray();

        $contribution->update([
            'statut'         => $donnees['statut'],
            'date_reception' => $donnees['date_reception'] ?? ($donnees['statut'] === 'recue' ? now()->toDateString() : $contribution->date_reception),
            'observation'    => $donnees['observation'] ?? $contribution->observation,
        ]);

        $this->journal->tracer(
            'changement_statut_contribution',
            $contribution,
            ancienneValeur: $ancien,
            nouvelleValeur: $contribution->fresh()->toArray(),
            membreId: $contribution->membre_id,
        );

        return $this->reponse($contribution->fresh('type', 'membre', 'donateur'), 'Statut mis à jour.');
    }

    public function annuler(Request $requete, Contribution $contribution)
    {
        $this->refuserSiNonAdministrateur();

        $contribution->update([
            'statut'      => 'annulee',
            'observation' => $requete->input('motif'),
        ]);

        $this->journal->tracer('annulation_contribution', $contribution);

        return $this->reponse($contribution, 'Contribution annulée.');
    }

    public function donateurs(Request $requete)
    {
        if ($requete->isMethod('post')) {
            $donnees = $requete->validate([
                'denomination'       => ['required', 'string', 'max:255'],
                'categorie_donateur' => ['required', 'in:personne_physique,entreprise,association,partenaire'],
                'telephone'          => ['nullable', 'string', 'max:30'],
                'email'              => ['nullable', 'email'],
                'pays'               => ['nullable', 'string', 'max:100'],
                'adresse'            => ['nullable', 'string', 'max:255'],
            ]);

            return $this->reponse(Donateur::create($donnees), 'Donateur enregistré.', 201);
        }

        return $this->reponse(Donateur::withCount('contributions')->orderBy('denomination')->get());
    }
}
