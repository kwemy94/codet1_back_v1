<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Exercice;
use App\Models\ReversementAnnuel;
use App\Services\ReversementService;

/**
 * Reversement annuel de 20 % au CODET I (CDC §14).
 */
class ReversementController extends Controller
{
    public function __construct(private ReversementService $reversements) {}

    public function index()
    {
        return $this->reponse(
            ReversementAnnuel::with('exercice')->orderByDesc('id')->get()
        );
    }

    /** Simulation à tout moment, sans écriture en base. */
    public function simuler(Exercice $exercice)
    {
        return $this->reponse($this->reversements->simuler($exercice));
    }

    /** Calcul enregistré, réversible tant que l'exercice n'est pas clôturé. */
    public function calculer(Exercice $exercice)
    {
        $this->refuserSiNonAdministrateur();

        $reversement = $this->reversements->calculer($exercice);

        return $this->reponse(
            $reversement->load('exercice'),
            "Reversement de l'exercice {$exercice->annee} calculé : {$reversement->montant_reverse} FCFA."
        );
    }

    public function show(Exercice $exercice)
    {
        return $this->reponse($exercice->reversement?->load('exercice'));
    }
}
