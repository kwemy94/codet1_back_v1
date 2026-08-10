<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Exercice;
use App\Services\JournalService;
use App\Services\ReversementService;
use Illuminate\Http\Request;

class ExerciceController extends Controller
{
    public function __construct(
        private ReversementService $reversements,
        private JournalService $journal,
    ) {}

    public function index()
    {
        return $this->reponse(Exercice::withCount('cartes')->orderByDesc('annee')->get());
    }

    public function store(Request $requete)
    {
        $donnees = $requete->validate([
            'annee'      => ['required', 'integer', 'min:2000', 'max:2100', 'unique:exercices,annee'],
            'date_debut' => ['required', 'date'],
            'date_fin'   => ['required', 'date', 'after:date_debut'],
        ]);

        $exercice = Exercice::create($donnees + ['statut' => 'ouvert']);
        $this->journal->tracer('creation_exercice', $exercice);

        return $this->reponse($exercice, 'Exercice créé.', 201);
    }

    public function show(Exercice $exercice)
    {
        return $this->reponse($exercice->load('tarifs.categorie', 'reversement'));
    }

    public function courant()
    {
        return $this->reponse(Exercice::courant()?->load('tarifs.categorie'));
    }

    /**
     * Clôture de l'exercice : calcule et fige le reversement des 20 %,
     * puis interdit toute nouvelle écriture sur l'année concernée.
     */
    public function cloturer(Exercice $exercice)
    {
        $this->refuserSiNonAdministrateur();

        $reversement = $this->reversements->cloturer($exercice);

        return $this->reponse([
            'exercice'    => $exercice->fresh(),
            'reversement' => $reversement,
        ], "Exercice {$exercice->annee} clôturé.");
    }
}
