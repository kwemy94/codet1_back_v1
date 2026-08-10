<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Document;
use App\Models\RapportAg;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Rapports d'Assemblée Générale (CDC §10) : publiés par les administrateurs,
 * consultables par tous les membres dès que leur statut est « publié ».
 */
class RapportAgController extends Controller
{
    public function __construct(private JournalService $journal) {}

    public function index(Request $requete)
    {
        $estAdmin = $requete->user()?->estAdministrateur();

        $rapports = RapportAg::with('exercice', 'documents')
            ->when(! $estAdmin, fn ($q) => $q->publies())
            ->when($requete->query('exercice_id'), fn ($q, $v) => $q->where('exercice_id', $v))
            ->when($requete->query('type_rapport'), fn ($q, $v) => $q->where('type_rapport', $v))
            ->when($requete->query('recherche'), fn ($q, $v) => $q->where('intitule', 'like', "%{$v}%"))
            ->orderByDesc('date_ag')
            ->paginate((int) $requete->query('par_page', 20));

        return $this->reponse($rapports);
    }

    public function store(Request $requete)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate([
            'exercice_id'  => ['required', 'exists:exercices,id'],
            'intitule'     => ['required', 'string', 'max:255'],
            'date_ag'      => ['required', 'date'],
            'lieu_ag'      => ['nullable', 'string', 'max:255'],
            'type_rapport' => ['required', 'in:proces_verbal,rapport_moral,rapport_financier,resolutions,annexe'],
            'resume'       => ['nullable', 'string'],
            'fichiers'     => ['required', 'array', 'min:1'],
            'fichiers.*'   => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:20480'],
        ]);

        $rapport = RapportAg::create(collect($donnees)->except('fichiers')->toArray() + ['statut' => 'brouillon']);

        foreach ($requete->file('fichiers') as $fichier) {
            $chemin = $fichier->store("rapports-ag/{$rapport->exercice_id}", 'local');

            $rapport->documents()->create([
                'titre'          => $fichier->getClientOriginalName(),
                'nom_fichier'    => $fichier->getClientOriginalName(),
                'chemin_fichier' => $chemin,
                'type_mime'      => $fichier->getClientMimeType(),
                'taille'         => $fichier->getSize(),
                'visibilite'     => 'prive',          // devient public à la publication
                'depose_par'     => $requete->user()->id,
            ]);
        }

        $this->journal->tracer('depot_rapport_ag', $rapport);

        return $this->reponse($rapport->load('documents'), 'Rapport déposé en brouillon.', 201);
    }

    public function publier(Request $requete, RapportAg $rapport)
    {
        $this->refuserSiNonAdministrateur();

        $rapport->update([
            'statut'           => 'publie',
            'date_publication' => now(),
            'publie_par'       => $requete->user()->id,
        ]);

        $rapport->documents()->update(['visibilite' => 'public']);
        $this->journal->tracer('publication_rapport_ag', $rapport);

        // TODO : déclencher la notification e-mail/SMS aux membres (CDC §10.4)

        return $this->reponse($rapport->fresh('documents'), 'Rapport publié : il est désormais visible par tous les membres.');
    }

    public function depublier(RapportAg $rapport)
    {
        $this->refuserSiNonAdministrateur();

        $rapport->update(['statut' => 'archive']);
        $rapport->documents()->update(['visibilite' => 'prive']);
        $this->journal->tracer('depublication_rapport_ag', $rapport);

        return $this->reponse($rapport->fresh(), 'Rapport retiré de la consultation publique.');
    }

    public function show(Request $requete, RapportAg $rapport)
    {
        abort_if(! $rapport->estPublie() && ! $requete->user()->estAdministrateur(), 403);

        return $this->reponse($rapport->load('exercice', 'documents'));
    }

    /** Téléchargement tracé d'une pièce du rapport (CDC §10.5). */
    public function telecharger(Request $requete, RapportAg $rapport, Document $document)
    {
        abort_if($document->documentable_id !== $rapport->id, 404);
        abort_if(! $document->estPublic() && ! $requete->user()->estAdministrateur(), 403);

        $document->consultations()->create([
            'membre_id'  => $requete->user()->membre_id,
            'user_id'    => $requete->user()->id,
            'date_heure' => now(),
            'adresse_ip' => $requete->ip(),
            'action'     => 'telechargement',
        ]);

        return Storage::disk('local')->download($document->chemin_fichier, $document->nom_fichier);
    }
}
