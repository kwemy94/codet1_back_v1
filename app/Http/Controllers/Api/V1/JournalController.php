<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\JournalAction;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    public function index(Request $requete)
    {
        $this->refuserSiNonAdministrateur();

        $actions = JournalAction::with('auteur:id,nom_affichage', 'membre:id,matricule,nom,prenom')
            ->when($requete->query('type_action'), fn ($q, $v) => $q->where('type_action', $v))
            ->when($requete->query('entite'), fn ($q, $v) => $q->where('entite_concernee', $v))
            ->when($requete->query('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($requete->query('du'), fn ($q, $v) => $q->whereDate('date_heure', '>=', $v))
            ->when($requete->query('au'), fn ($q, $v) => $q->whereDate('date_heure', '<=', $v))
            ->orderByDesc('date_heure')
            ->paginate((int) $requete->query('par_page', 50));

        return $this->reponse($actions);
    }
}
