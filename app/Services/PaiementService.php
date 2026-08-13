<?php

namespace App\Services;

use App\Models\CarteDeveloppement;
use App\Models\Contribution;
use App\Models\MoyenPaiement;
use App\Models\Paiement;
use App\Models\Recu;
use App\Services\Paiement\PasserelleFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cycle de vie d'un paiement :
 *   initier() → statut « initie »  (paiement en ligne, en attente de l'opérateur)
 *   valider() → statut « valide »  (webhook confirmé ou encaissement manuel)
 *               ventilation + reçu + mise à jour du solde de la carte
 *   echouer() → statut « echoue »
 */
class PaiementService
{
    public function __construct(
        private VentilationService $ventilation,
        private PasserelleFactory $passerelles,
        private JournalService $journal,
    ) {}

    /** Paiement en ligne : crée le paiement puis délègue à la passerelle mobile money. */
    public function initierEnLigne(array $donnees): Paiement
    {
        $moyen = MoyenPaiement::where('code', $donnees['moyen_paiement'])->where('actif', true)->firstOrFail();

        if (! $moyen->estMobileMoney()) {
            throw ValidationException::withMessages([
                'moyen_paiement' => 'Ce moyen de paiement ne peut pas être utilisé en ligne.',
            ]);
        }

        return DB::transaction(function () use ($donnees, $moyen) {
            $paiement = $this->creer($donnees + ['moyen_paiement_id' => $moyen->id, 'canal' => 'en_ligne']);

            $this->passerelles->pour($moyen)->initier($paiement, $donnees['numero_telephone']);

            return $paiement->fresh(['transaction']);
        });
    }

    /** Paiement encaissé hors ligne et saisi par un administrateur. */
    public function enregistrerManuel(array $donnees): Paiement
    {
        return DB::transaction(function () use ($donnees) {
            $paiement = $this->creer($donnees + ['canal' => 'manuel']);

            return $this->valider($paiement);
        });
    }

    public function valider(Paiement $paiement): Paiement
    {
        if ($paiement->estValide()) {
            return $paiement;
        }

        return DB::transaction(function () use ($paiement) {
            $paiement->update(['statut' => 'valide', 'date_paiement' => now()]);

            $this->ventilation->ventiler($paiement);
            $this->emettreRecu($paiement);

            if ($paiement->carte_developpement_id) {
                $paiement->carte->rafraichirSolde();
            }

            if ($paiement->contribution_id) {
                // Un versement partiel ne solde pas la contribution : elle reste
                // « attendue » tant que la totalité n'est pas entrée en caisse.
                $contribution = $paiement->contribution->fresh();

                if ($contribution->estCouverte()) {
                    $contribution->update(['statut' => 'encaissee']);
                }
            }

            $this->journal->tracer('paiement_valide', $paiement, membreId: $paiement->membre_id);

            return $paiement->fresh(['affectations', 'recu']);
        });
    }

    public function echouer(Paiement $paiement, ?string $motif = null): Paiement
    {
        $paiement->update(['statut' => 'echoue', 'observation' => $motif]);
        $this->journal->tracer('paiement_echoue', $paiement, membreId: $paiement->membre_id);

        return $paiement;
    }

    private function creer(array $donnees): Paiement
    {
        $objet = $this->resoudreObjet($donnees);

        $paiement = new Paiement([
            'reference'              => $this->reference(),
            'membre_id'              => $donnees['membre_id'] ?? $objet['membre_id'],
            'moyen_paiement_id'      => $donnees['moyen_paiement_id'],
            'exercice_id'            => $objet['exercice_id'],
            'carte_developpement_id' => $objet['carte_id'],
            'contribution_id'        => $objet['contribution_id'],
            'date_paiement'          => now(),
            'montant'                => $donnees['montant'],
            'canal'                  => $donnees['canal'],
            'statut'                 => 'initie',
            'observation'            => $donnees['observation'] ?? null,
            'enregistre_par'         => auth()->id(),
        ]);

        if (! $paiement->objetValide()) {
            throw ValidationException::withMessages([
                'objet' => 'Un paiement doit régler soit une carte annuelle, soit une contribution, mais pas les deux.',
            ]);
        }

        $paiement->save();

        return $paiement;
    }

    /** Résout l'objet réglé et contrôle que l'exercice concerné est bien ouvert. */
    private function resoudreObjet(array $donnees): array
    {
        if (! empty($donnees['carte_developpement_id'])) {
            $carte = CarteDeveloppement::with('exercice')->findOrFail($donnees['carte_developpement_id']);

            if (! $carte->exercice->estOuvert()) {
                throw ValidationException::withMessages([
                    'carte_developpement_id' => "L'exercice {$carte->exercice->annee} est clôturé.",
                ]);
            }

            // Un encaissement supérieur au solde rendrait montant_regle > montant_du,
            // ce qui fausse l'état des impayés. Le surplus doit être saisi comme
            // contribution volontaire, pas comme règlement de carte.
            if ((int) $donnees['montant'] > $carte->solde) {
                throw ValidationException::withMessages([
                    'montant' => "Le solde de cette carte est de {$carte->solde} FCFA. "
                        ."Enregistrez le surplus comme contribution volontaire.",
                ]);
            }

            return [
                'carte_id'        => $carte->id,
                'contribution_id' => null,
                'exercice_id'     => $carte->exercice_id,
                'membre_id'       => $carte->membre_id,
            ];
        }

        $contribution = Contribution::with('exercice')->findOrFail($donnees['contribution_id']);

        if (! $contribution->exercice->estOuvert()) {
            throw ValidationException::withMessages([
                'contribution_id' => "L'exercice {$contribution->exercice->annee} est clôturé.",
            ]);
        }

        if ($contribution->statut === 'annulee') {
            throw ValidationException::withMessages([
                'contribution_id' => 'Cette contribution a été annulée : elle ne peut plus être encaissée.',
            ]);
        }

        if ($contribution->estMaterielle()) {
            throw ValidationException::withMessages([
                'contribution_id' => "Un don en nature ou en services n'a pas de flux financier. "
                    .'Constatez sa réception plutôt que d\'enregistrer un paiement.',
            ]);
        }

        if ((int) $donnees['montant'] > $contribution->solde) {
            throw ValidationException::withMessages([
                'montant' => "Il reste {$contribution->solde} FCFA à encaisser sur cette contribution.",
            ]);
        }

        return [
            'carte_id'        => null,
            'contribution_id' => $contribution->id,
            'exercice_id'     => $contribution->exercice_id,
            'membre_id'       => $contribution->membre_id,
        ];
    }

    private function emettreRecu(Paiement $paiement): Recu
    {
        return $paiement->recu ?: Recu::create([
            'paiement_id'   => $paiement->id,
            'numero_recu'   => str_replace('PAY', 'RECU', $paiement->reference),
            'date_emission' => now(),
        ]);
    }

    private function reference(): string
    {
        $annee = date('Y');
        $rang  = Paiement::whereYear('created_at', $annee)->count() + 1;

        return sprintf('PAY-%s-%06d', $annee, $rang);
    }
}
