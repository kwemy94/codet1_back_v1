<?php

namespace App\Services;

use App\Models\Affectation;
use App\Models\Exercice;
use App\Models\ReversementAnnuel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reversement annuel au CODET I.
 *
 * Chaque destination de fonds porte son propre taux de reversement :
 * 20 % pour le groupement, 100 % pour une carte intégralement destinée au
 * comité, 0 % pour le village et le congrès. Le calcul additionne donc, par
 * destination, l'assiette encaissée multipliée par son taux. Le détail est
 * conservé dans l'enregistrement : une modification ultérieure d'un taux
 * n'altère jamais les exercices déjà calculés.
 */
class ReversementService
{
    public function __construct(private JournalService $journal) {}

    public function calculer(Exercice $exercice): ReversementAnnuel
    {
        $existant = $exercice->reversement;

        if ($existant && $existant->estCloture()) {
            throw ValidationException::withMessages([
                'exercice_id' => "Le reversement de l'exercice {$exercice->annee} est déjà clôturé.",
            ]);
        }

        $calcul = $this->simuler($exercice);

        return DB::transaction(function () use ($exercice, $calcul) {
            $reversement = ReversementAnnuel::updateOrCreate(
                ['exercice_id' => $exercice->id],
                [
                    'assiette'        => $calcul['assiette'],
                    'taux_applique'   => $calcul['taux_applique'],
                    'montant_reverse' => $calcul['montant_reverse'],
                    'detail'          => $calcul['detail'],
                    'date_calcul'     => now(),
                    'statut'          => 'provisoire',
                    'calcule_par'     => auth()->id(),
                ]
            );

            $this->journal->tracer('reversement_calcule', $reversement);

            return $reversement;
        });
    }

    public function cloturer(Exercice $exercice): ReversementAnnuel
    {
        $reversement = $this->calculer($exercice);

        return DB::transaction(function () use ($exercice, $reversement) {
            $reversement->update(['statut' => 'cloture', 'date_cloture' => now()]);
            $exercice->update(['statut' => 'cloture', 'date_cloture' => now()]);

            $this->journal->tracer('reversement_cloture', $reversement);

            return $reversement;
        });
    }

    /** Simulation sans écriture : utilisable à tout moment de l'année. */
    public function simuler(Exercice $exercice): array
    {
        $lignes = Affectation::query()
            ->where('affectations.exercice_id', $exercice->id)
            ->join('destinations_fonds', 'destinations_fonds.id', '=', 'affectations.destination_fonds_id')
            ->join('paiements', 'paiements.id', '=', 'affectations.paiement_id')
            ->where('paiements.statut', 'valide')
            ->where('destinations_fonds.taux_reversement', '>', 0)
            ->groupBy('destinations_fonds.code', 'destinations_fonds.libelle', 'destinations_fonds.taux_reversement')
            ->selectRaw('destinations_fonds.code, destinations_fonds.libelle, destinations_fonds.taux_reversement as taux, SUM(affectations.montant_affecte) as assiette')
            ->get();

        $detail = $lignes->map(fn ($ligne) => [
            'code'     => $ligne->code,
            'libelle'  => $ligne->libelle,
            'assiette' => (int) $ligne->assiette,
            'taux'     => (float) $ligne->taux,
            'montant'  => (int) round($ligne->assiette * $ligne->taux / 100),
        ])->values()->all();

        $assiette = array_sum(array_column($detail, 'assiette'));
        $montant  = array_sum(array_column($detail, 'montant'));

        return [
            'exercice'        => $exercice->annee,
            'assiette'        => $assiette,
            // Taux effectif : rapport du montant reversé à l'assiette retenue.
            'taux_applique'   => $assiette > 0 ? round($montant * 100 / $assiette, 2) : 0,
            'montant_reverse' => $montant,
            'detail'          => $detail,
        ];
    }

    public function assiette(Exercice $exercice): int
    {
        return $this->simuler($exercice)['assiette'];
    }
}
