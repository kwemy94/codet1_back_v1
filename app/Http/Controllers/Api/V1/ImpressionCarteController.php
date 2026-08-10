<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CarteDeveloppement;
use App\Models\Parametre;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Données d'impression de la carte unique de développement.
 *
 * Deux garde-fous, qui sont la raison d'être de ce point d'entrée :
 *   — une carte non intégralement réglée ne s'imprime pas, sinon le membre
 *     détiendrait un justificatif de paiement qu'il n'a pas honoré ;
 *   — une carte d'exercice clôturé non plus, une carte périmée n'ayant plus
 *     valeur de titre pour l'année en cours.
 *
 * Le contrôle est ici, côté serveur : l'interface se contente de masquer le
 * bouton, ce qui n'est pas une protection.
 */
class ImpressionCarteController extends Controller
{
    /** Mentions figurant sur le gabarit, toutes modifiables dans les paramètres. */
    private const MENTIONS = [
        'sigle'         => 'CARTE_SIGLE',
        'comite'        => 'CARTE_COMITE',
        'groupement'    => 'CARTE_GROUPEMENT',
        'village'       => 'CARTE_VILLAGE',
        'recepisse'     => 'CARTE_RECEPISSE',
        'tel_president' => 'CARTE_TEL_PRESIDENT',
        'tel_tresorier' => 'CARTE_TEL_TRESORIER',
        'email'         => 'CARTE_EMAIL',
        'site'          => 'CARTE_SITE',
        'slogan'        => 'CARTE_SLOGAN',
        'president'     => 'CARTE_PRESIDENT',
        'commissaire'   => 'CARTE_COMMISSAIRE',
    ];

    public function __construct(private JournalService $journal) {}

    public function __invoke(Request $requete, CarteDeveloppement $carte)
    {
        $carte->load('membre.categorie', 'membre.ville.pays', 'exercice', 'typeCarte', 'tarif.repartitions.destination');

        $this->verifierAcces($requete, $carte);
        $this->verifierImprimable($carte);

        $this->journal->tracer('impression_carte', $carte, membreId: $carte->membre_id);

        $repartition = $carte->tarif->repartitions
            ->mapWithKeys(fn ($ligne) => [$ligne->destination?->code ?? (string) $ligne->destination_fonds_id => (int) $ligne->montant])
            ->all();

        return $this->reponse([
            'carte' => [
                'numero'        => $carte->numero_carte,
                'type'          => $carte->typeCarte?->libelle,
                'exercice'      => $carte->exercice->annee,
                'date_emission' => $carte->date_emission?->toDateString(),
                'montant_total' => (int) $carte->montant_du,
                // Le recto porte la part revenant au groupement, le verso le montant total.
                'montant_groupement' => $repartition['GROUPEMENT'] ?? 0,
                'repartition'   => $repartition,
            ],
            'membre' => [
                'matricule'  => $carte->membre->matricule,
                'nom'        => $carte->membre->nom,
                'prenom'     => $carte->membre->prenom,
                'telephone'  => $carte->membre->telephone,
                'sexe'       => $carte->membre->sexe,
                'categorie'  => $carte->membre->categorie?->libelle,
                'ville'      => $carte->membre->ville?->libelle,
                'region'     => $carte->membre->ville?->pays?->libelle,
            ],
            'mentions' => collect(self::MENTIONS)
                ->map(fn (string $code) => Parametre::valeur($code, ''))
                ->all(),
        ]);
    }

    private function verifierAcces(Request $requete, CarteDeveloppement $carte): void
    {
        $utilisateur = $requete->user();

        abort_if(
            ! $utilisateur->estAdministrateur() && $utilisateur->membre_id !== $carte->membre_id,
            403,
            "Vous ne pouvez imprimer que votre propre carte.",
        );
    }

    private function verifierImprimable(CarteDeveloppement $carte): void
    {
        if ($carte->statut !== 'soldee') {
            throw ValidationException::withMessages([
                'carte' => "La carte n'est pas intégralement réglée : il reste {$carte->solde} FCFA à payer. "
                    ."Elle sera imprimable dès le solde acquitté.",
            ]);
        }

        if (! $carte->exercice->estOuvert()) {
            throw ValidationException::withMessages([
                'carte' => "L'exercice {$carte->exercice->annee} est clôturé : cette carte ne peut plus être imprimée.",
            ]);
        }
    }
}
