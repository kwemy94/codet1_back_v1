<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Message;
use App\Services\JournalService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private JournalService $journal) {}

    public function index(Request $requete)
    {
        $estAdmin = $requete->user()->estAdministrateur();

        $messages = Message::with('membre', 'piecesJointes', 'reponses')
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

    public function repondre(Request $requete, Message $message)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate(['contenu' => ['required', 'string']]);

        $reponse = Message::create([
            'membre_id'         => $message->membre_id,
            'message_parent_id' => $message->id,
            'objet'             => 'RE : '.$message->objet,
            'contenu'           => $donnees['contenu'],
            'statut'            => 'traite',
            'date_envoi'        => now(),
            'traite_par'        => $requete->user()->id,
        ]);

        $message->update([
            'statut'          => 'traite',
            'date_traitement' => now(),
            'traite_par'      => $requete->user()->id,
        ]);

        return $this->reponse($reponse, 'Réponse enregistrée.', 201);
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
