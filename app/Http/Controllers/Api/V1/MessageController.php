<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Document;
use App\Models\Message;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function __construct(private JournalService $journal) {}

    public function index(Request $requete)
    {
        $estAdmin = $requete->user()->estAdministrateur();

        $messages = Message::with('membre', 'piecesJointes', 'reponses.piecesJointes')
            ->whereNull('message_parent_id')
            ->when(! $estAdmin, fn ($q) => $q->where('membre_id', $requete->user()->membre_id))
            ->when($requete->query('statut'), fn ($q, $v) => $q->where('statut', $v))
            ->orderByDesc('date_envoi')
            ->paginate((int) $requete->query('par_page', 20));

        return $this->reponse($messages);
    }

    public function store(Request $requete)
    {
        $donnees = $requete->validate([
            'objet'      => ['required', 'string', 'max:255'],
            'contenu'    => ['required', 'string'],
            'categorie'  => ['nullable', 'string', 'max:40'],
            'fichiers'   => ['nullable', 'array', 'max:5'],
            'fichiers.*' => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ]);

        $message = Message::create([
            'membre_id'  => $requete->user()->membre_id,
            'objet'      => $donnees['objet'],
            'contenu'    => $donnees['contenu'],
            'categorie'  => $donnees['categorie'] ?? null,
            'statut'     => 'nouveau',
            'date_envoi' => now(),
        ]);

        foreach ($requete->file('fichiers', []) as $fichier) {
            $message->piecesJointes()->create([
                'titre'          => $fichier->getClientOriginalName(),
                'nom_fichier'    => $fichier->getClientOriginalName(),
                'chemin_fichier' => $fichier->store('messages', 'local'),
                'type_mime'      => $fichier->getClientMimeType(),
                'taille'         => $fichier->getSize(),
                'visibilite'     => 'prive',
                'depose_par'     => $requete->user()->id,
            ]);
        }

        return $this->reponse($message->load('piecesJointes'), 'Message envoyé au comité.', 201);
    }

    /**
     * Téléchargement d'une pièce jointe.
     *
     * Une pièce de message n'est jamais publique : seul l'auteur du message et
     * les administrateurs y accèdent. Le contrôle porte sur le message parent,
     * pas sur le document, pour qu'un identifiant deviné ne donne rien.
     */
    public function telecharger(Request $requete, Message $message, Document $document)
    {
        abort_if(
            $document->documentable_type !== Message::class || $document->documentable_id !== $message->id,
            404,
        );

        $utilisateur = $requete->user();

        abort_if(
            ! $utilisateur->estAdministrateur() && $utilisateur->membre_id !== $message->membre_id,
            403,
            "Cette pièce jointe appartient au message d'un autre membre.",
        );

        abort_unless(Storage::disk('local')->exists($document->chemin_fichier), 404, 'Fichier introuvable.');

        $document->consultations()->create([
            'membre_id'  => $utilisateur->membre_id,
            'user_id'    => $utilisateur->id,
            'date_heure' => now(),
            'adresse_ip' => $requete->ip(),
            'action'     => 'telechargement',
        ]);

        return Storage::disk('local')->download($document->chemin_fichier, $document->nom_fichier);
    }

    public function repondre(Request $requete, Message $message)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate([
            'contenu'    => ['required', 'string'],
            'fichiers'   => ['nullable', 'array', 'max:5'],
            'fichiers.*' => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ]);

        $reponse = Message::create([
            'membre_id'         => $message->membre_id,
            'message_parent_id' => $message->id,
            'objet'             => 'RE : '.$message->objet,
            'contenu'           => $donnees['contenu'],
            'statut'            => 'traite',
            'date_envoi'        => now(),
            'traite_par'        => $requete->user()->id,
        ]);

        foreach ($requete->file('fichiers', []) as $fichier) {
            $reponse->piecesJointes()->create([
                'titre'          => $fichier->getClientOriginalName(),
                'nom_fichier'    => $fichier->getClientOriginalName(),
                'chemin_fichier' => $fichier->store('messages', 'local'),
                'type_mime'      => $fichier->getClientMimeType(),
                'taille'         => $fichier->getSize(),
                'visibilite'     => 'prive',
                'depose_par'     => $requete->user()->id,
            ]);
        }

        $message->update([
            'statut'          => 'traite',
            'date_traitement' => now(),
            'traite_par'      => $requete->user()->id,
        ]);

        return $this->reponse($reponse->load('piecesJointes'), 'Réponse enregistrée.', 201);
    }

    public function marquerTraite(Request $requete, Message $message)
    {
        $this->refuserSiNonAdministrateur();

        $message->update([
            'statut'          => 'traite',
            'date_traitement' => now(),
            'traite_par'      => $requete->user()->id,
        ]);

        return $this->reponse($message, 'Message marqué comme traité.');
    }
}
