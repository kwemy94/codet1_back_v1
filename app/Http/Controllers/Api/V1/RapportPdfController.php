<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Contribution;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\TypeCarte;
use App\Services\EnteteDocumentService;
use App\Services\JournalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * États financiers au format PDF.
 *
 * Les montants par destination sont ceux réellement encaissés — c'est-à-dire
 * les affectations des paiements validés — et non les parts théoriques du
 * tarif. Un membre qui n'a réglé que la moitié de sa carte apparaît donc pour
 * la moitié de chaque poste, ce qui est la seule lecture utile au trésorier.
 */
class RapportPdfController extends Controller
{
    /** Colonnes fixes de l'état des ventes, dans l'ordre demandé par le bureau. */
    private const COLONNES = ['GROUPEMENT', 'VILLAGE', 'CONGRES'];

    public function __construct(
        private EnteteDocumentService $entete,
        private JournalService $journal,
    ) {}

    /**
     * Historique des ventes de cartes d'un exercice — par défaut celui en cours.
     *
     * Les filtres de l'écran (statut de la carte, type de carte) sont repris
     * tels quels : l'état édité correspond exactement à la liste affichée.
     * Le document rappelle en tête les filtres appliqués, pour qu'une liste
     * partielle ne soit jamais prise pour l'état complet de l'exercice.
     */
    public function ventesCartes(Request $requete, ?Exercice $exercice = null)
    {
        $this->refuserSiNonAdministrateur();

        $filtres = $requete->validate([
            'statut'        => ['nullable', 'in:impayee,partielle,soldee,annulee'],
            'type_carte_id' => ['nullable', 'exists:types_cartes,id'],
        ]);

        $exercice = $exercice?->exists ? $exercice : Exercice::courant();

        if (! $exercice) {
            throw ValidationException::withMessages([
                'exercice' => "Aucun exercice n'est ouvert. Ouvrez-en un avant d'éditer cet état.",
            ]);
        }

        $lignes = $this->lignesVentes($exercice, $filtres);
        $totaux = $this->totaliser($lignes);

        $this->journal->tracer('export_ventes_cartes', $exercice, nouvelleValeur: $filtres);

        $pdf = Pdf::loadView('pdf.ventes-cartes', [
            'mentions' => $this->entete->mentions(),
            'exercice' => $exercice,
            'lignes'   => $lignes,
            'totaux'   => $totaux,
            'colonnes' => self::COLONNES,
            'filtres'  => $this->libellesFiltres($filtres),
        ])->setPaper('a4', 'landscape');

        $this->numeroterLesPages($pdf);

        $suffixe = ! empty($filtres['statut']) ? '-'.$filtres['statut'] : '';

        return $pdf->download("ventes-cartes-{$exercice->annee}{$suffixe}.pdf");
    }

    /**
     * État des contributions et dons d'un exercice.
     *
     * Les dons financiers et les dons en nature ne se totalisent pas ensemble :
     * les premiers entrent en caisse, les seconds ne sont qu'une valeur estimée.
     * Le document les additionne donc séparément.
     */
    public function contributions(Request $requete, ?Exercice $exercice = null)
    {
        $this->refuserSiNonAdministrateur();

        $filtres = $requete->validate([
            'statut' => ['nullable', 'in:attendue,encaissee,recue,annulee'],
            'nature' => ['nullable', 'in:financier,materiel,service'],
        ]);

        $exercice = $exercice?->exists ? $exercice : Exercice::courant();

        if (! $exercice) {
            throw ValidationException::withMessages([
                'exercice' => "Aucun exercice n'est ouvert. Ouvrez-en un avant d'éditer cet état.",
            ]);
        }

        $lignes = Contribution::with('membre', 'donateur', 'type')
            ->where('exercice_id', $exercice->id)
            ->when($filtres['statut'] ?? null, fn ($q, $v) => $q->where('statut', $v))
            ->when($filtres['nature'] ?? null, fn ($q, $v) => $q->where('nature', $v))
            ->orderBy('date_contribution')
            ->get();

        $acquises = $lignes->whereIn('statut', ['encaissee', 'recue']);

        $totaux = [
            'nombre'            => $lignes->count(),
            'financier_acquis'  => (int) $acquises->where('nature', 'financier')->sum('montant'),
            'nature_acquis'     => (int) $acquises->where('nature', '!=', 'financier')->sum('montant'),
            'attendu'           => (int) $lignes->where('statut', 'attendue')->sum('montant'),
            'annule'            => (int) $lignes->where('statut', 'annulee')->sum('montant'),
            'membres'           => $lignes->whereNotNull('membre_id')->count(),
            'externes'          => $lignes->whereNotNull('donateur_id')->count(),
        ];

        $this->journal->tracer('export_contributions', $exercice, nouvelleValeur: $filtres);

        $pdf = Pdf::loadView('pdf.contributions', [
            'mentions' => $this->entete->mentions(),
            'exercice' => $exercice,
            'lignes'   => $lignes,
            'totaux'   => $totaux,
            'filtres'  => $this->libellesFiltresContributions($filtres),
        ])->setPaper('a4', 'landscape');

        $this->numeroterLesPages($pdf);

        $suffixe = collect($filtres)->filter()->implode('-');

        return $pdf->download('contributions-'.$exercice->annee.($suffixe ? "-{$suffixe}" : '').'.pdf');
    }

    /** Historique complet d'un membre, tous exercices confondus. */
    public function historiqueMembre(Membre $membre)
    {
        $this->refuserSiNonAdministrateur();

        $membre->load('categorie', 'ville.pays');

        $exercices = $this->historiqueParExercice($membre);
        $impayes   = $exercices->filter(fn ($ligne) => $ligne['solde'] > 0)->values();

        $this->journal->tracer('export_historique_membre', $membre, membreId: $membre->id);

        $pdf = Pdf::loadView('pdf.historique-membre', [
            'mentions'  => $this->entete->mentions(),
            'membre'    => $membre,
            'exercices' => $exercices,
            'impayes'   => $impayes,
            'totaux'    => [
                'du'     => $exercices->sum('montant_du'),
                'regle'  => $exercices->sum('montant_regle'),
                'solde'  => $exercices->sum('solde'),
                'dons'   => (int) $membre->contributions()->whereIn('statut', ['encaissee', 'recue'])->sum('montant'),
            ],
            'colonnes'  => self::COLONNES,
        ])->setPaper('a4', 'portrait');

        $this->numeroterLesPages($pdf);

        return $pdf->download("historique-{$membre->matricule}.pdf");
    }

    /* ------------------------------------------------------------------ */

    private function lignesVentes(Exercice $exercice, array $filtres = [])
    {
        $cartes = DB::table('cartes_developpement as c')
            ->join('membres as m', 'm.id', '=', 'c.membre_id')
            ->leftJoin('villes as v', 'v.id', '=', 'm.ville_id')
            ->leftJoin('pays as p', 'p.id', '=', 'v.pays_id')
            ->leftJoin('types_cartes as t', 't.id', '=', 'c.type_carte_id')
            ->where('c.exercice_id', $exercice->id)
            ->when($filtres['statut'] ?? null, fn ($requete, $statut) => $requete->where('c.statut', $statut))
            ->when($filtres['type_carte_id'] ?? null, fn ($requete, $type) => $requete->where('c.type_carte_id', $type))
            ->orderBy('m.nom')->orderBy('m.prenom')
            ->select([
                'c.id as carte_id', 'c.montant_du', 'c.montant_regle', 'c.statut',
                'm.matricule', 'm.nom', 'm.prenom',
                'v.libelle as ville', 'p.libelle as pays', 't.libelle as type_carte',
            ])
            ->get();

        $ventilation = $this->ventilationParCarte($exercice);

        return $cartes->map(function ($carte) use ($ventilation) {
            $parts = $ventilation[$carte->carte_id] ?? [];

            return [
                'matricule'  => $carte->matricule,
                'nom_complet' => trim($carte->nom.' '.($carte->prenom ?? '')),
                'ville'      => $carte->ville ?: '—',
                'pays'       => $carte->pays,
                'type_carte' => $carte->type_carte,
                'parts'      => collect(self::COLONNES)->mapWithKeys(fn ($code) => [$code => (int) ($parts[$code] ?? 0)])->all(),
                'autres'     => (int) collect($parts)->except(self::COLONNES)->sum(),
                'total'      => (int) collect($parts)->sum(),
                'montant_du' => (int) $carte->montant_du,
                'solde'      => max(0, (int) $carte->montant_du - (int) $carte->montant_regle),
                'statut'     => $carte->statut,
            ];
        });
    }

    /** [ carte_id => [ code_destination => montant encaissé ] ] */
    private function ventilationParCarte(Exercice $exercice): array
    {
        return DB::table('affectations as a')
            ->join('paiements as p', 'p.id', '=', 'a.paiement_id')
            ->join('destinations_fonds as d', 'd.id', '=', 'a.destination_fonds_id')
            ->where('p.exercice_id', $exercice->id)
            ->where('p.statut', 'valide')
            ->whereNotNull('p.carte_developpement_id')
            ->groupBy('p.carte_developpement_id', 'd.code')
            ->selectRaw('p.carte_developpement_id as carte_id, d.code, SUM(a.montant_affecte) as total')
            ->get()
            ->groupBy('carte_id')
            ->map(fn ($lignes) => $lignes->pluck('total', 'code')->map(fn ($v) => (int) $v)->all())
            ->all();
    }

    /** Traduit les filtres en une phrase lisible par le trésorier. */
    private function libellesFiltres(array $filtres): array
    {
        $libelles = [];

        $statuts = [
            'impayee'   => 'cartes impayées uniquement',
            'partielle' => 'cartes partiellement réglées uniquement',
            'soldee'    => 'cartes soldées uniquement',
            'annulee'   => 'cartes annulées uniquement',
        ];

        if (! empty($filtres['statut'])) {
            $libelles[] = $statuts[$filtres['statut']];
        }

        if (! empty($filtres['type_carte_id'])) {
            $type = TypeCarte::find($filtres['type_carte_id']);

            if ($type) {
                $libelles[] = "type « {$type->libelle} » uniquement";
            }
        }

        return $libelles;
    }

    private function libellesFiltresContributions(array $filtres): array
    {
        $statuts = [
            'attendue'  => 'contributions attendues uniquement',
            'encaissee' => 'contributions encaissées uniquement',
            'recue'     => 'dons reçus uniquement',
            'annulee'   => 'contributions annulées uniquement',
        ];

        $natures = [
            'financier' => 'dons financiers uniquement',
            'materiel'  => 'dons matériels uniquement',
            'service'   => 'dons en services uniquement',
        ];

        return collect([
            $statuts[$filtres['statut'] ?? ''] ?? null,
            $natures[$filtres['nature'] ?? ''] ?? null,
        ])->filter()->values()->all();
    }

    private function totaliser($lignes): array
    {
        return [
            'cartes'  => $lignes->count(),
            'parts'   => collect(self::COLONNES)
                ->mapWithKeys(fn ($code) => [$code => (int) $lignes->sum(fn ($l) => $l['parts'][$code])])
                ->all(),
            'autres'  => (int) $lignes->sum('autres'),
            'total'   => (int) $lignes->sum('total'),
            'attendu' => (int) $lignes->sum('montant_du'),
            'solde'   => (int) $lignes->sum('solde'),
            'soldees' => $lignes->where('statut', 'soldee')->count(),
        ];
    }

    private function historiqueParExercice(Membre $membre)
    {
        $cartes = DB::table('cartes_developpement as c')
            ->join('exercices as e', 'e.id', '=', 'c.exercice_id')
            ->leftJoin('types_cartes as t', 't.id', '=', 'c.type_carte_id')
            ->where('c.membre_id', $membre->id)
            ->orderByDesc('e.annee')
            ->select([
                'c.id as carte_id', 'c.numero_carte', 'c.montant_du', 'c.montant_regle', 'c.statut',
                'e.annee', 'e.statut as statut_exercice', 't.libelle as type_carte',
            ])
            ->get();

        $ventilation = DB::table('affectations as a')
            ->join('paiements as p', 'p.id', '=', 'a.paiement_id')
            ->join('destinations_fonds as d', 'd.id', '=', 'a.destination_fonds_id')
            ->where('p.membre_id', $membre->id)
            ->where('p.statut', 'valide')
            ->whereNotNull('p.carte_developpement_id')
            ->groupBy('p.carte_developpement_id', 'd.code')
            ->selectRaw('p.carte_developpement_id as carte_id, d.code, SUM(a.montant_affecte) as total')
            ->get()
            ->groupBy('carte_id')
            ->map(fn ($lignes) => $lignes->pluck('total', 'code')->map(fn ($v) => (int) $v)->all());

        return $cartes->map(function ($carte) use ($ventilation) {
            $parts = $ventilation[$carte->carte_id] ?? [];

            return [
                'annee'           => (int) $carte->annee,
                'statut_exercice' => $carte->statut_exercice,
                'numero_carte'    => $carte->numero_carte,
                'type_carte'      => $carte->type_carte,
                'parts'           => collect(self::COLONNES)->mapWithKeys(fn ($code) => [$code => (int) ($parts[$code] ?? 0)])->all(),
                'autres'          => (int) collect($parts)->except(self::COLONNES)->sum(),
                'montant_du'      => (int) $carte->montant_du,
                'montant_regle'   => (int) $carte->montant_regle,
                'solde'           => max(0, (int) $carte->montant_du - (int) $carte->montant_regle),
                'statut'          => $carte->statut,
            ];
        });
    }

    /**
     * Numérotation des pages. Passe par le canevas plutôt que par un script
     * dans le gabarit : cela évite d'activer l'exécution de PHP dans dompdf.
     */
    private function numeroterLesPages($pdf): void
    {
        try {
            $domPdf = $pdf->getDomPDF();
            $police = $domPdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
            $domPdf->getCanvas()->page_text(
                $domPdf->getPaperSize()[2] - 110, $domPdf->getPaperSize()[3] - 28,
                'Page {PAGE_NUM} / {PAGE_COUNT}', $police, 8, [0.45, 0.45, 0.45],
            );
        } catch (\Throwable) {
            // La numérotation est un confort : son échec ne doit pas priver
            // le trésorier de son document.
        }
    }
}
