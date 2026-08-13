<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CampagneCourriel;
use App\Services\CourrielService;
use Illuminate\Http\Request;

/**
 * Courriels adressés aux membres : à un seul destinataire ou à une sélection.
 */
class CourrielController extends Controller
{
    public function __construct(private CourrielService $courriels) {}

    private const REGLES_CRITERES = [
        'criteres'              => ['array'],
        'criteres.membre_ids'   => ['nullable', 'array'],
        'criteres.membre_ids.*' => ['integer', 'exists:membres,id'],
        'criteres.categorie_id' => ['nullable', 'exists:categories_membres,id'],
        'criteres.ville_id'     => ['nullable', 'exists:villes,id'],
        'criteres.pays_id'      => ['nullable', 'exists:pays,id'],
        'criteres.sexe'         => ['nullable', 'in:M,F'],
        'criteres.statut'       => ['nullable', 'in:actif,inactif'],
        'criteres.situation'    => ['nullable', 'in:a_jour,en_retard,sans_carte'],
        'criteres.exercice_id'  => ['nullable', 'exists:exercices,id'],
    ];

    /**
     * Aperçu de la sélection avant envoi.
     *
     * Renvoie qui sera atteint et qui ne le sera pas, faute d'adresse : le
     * secrétariat doit savoir combien de membres il devra joindre autrement.
     */
    public function apercu(Request $requete)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate(self::REGLES_CRITERES);
        $selection = $this->courriels->selection($donnees['criteres'] ?? []);

        return $this->reponse([
            'nombre_retenus'      => $selection['retenus']->count(),
            'nombre_sans_adresse' => $selection['sans_adresse']->count(),
            'retenus'             => $selection['retenus']->take(50)->map(fn ($membre) => [
                'id'          => $membre->id,
                'matricule'   => $membre->matricule,
                'nom_complet' => $membre->nom_complet,
                'email'       => $membre->email,
            ])->values(),
            'sans_adresse'        => $selection['sans_adresse']->take(50)->map(fn ($membre) => [
                'id'          => $membre->id,
                'matricule'   => $membre->matricule,
                'nom_complet' => $membre->nom_complet,
                'telephone'   => $membre->telephone,
            ])->values(),
        ]);
    }

    public function envoyer(Request $requete)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate(self::REGLES_CRITERES + [
            'objet'   => ['required', 'string', 'max:255'],
            'contenu' => ['required', 'string', 'max:20000'],
        ]);

        $campagne = $this->courriels->envoyer($donnees);

        $message = "Envoi lancé vers {$campagne->nombre_destinataires} membre(s).";

        if ($campagne->nombre_sans_adresse > 0) {
            $message .= " {$campagne->nombre_sans_adresse} membre(s) n'ont pas d'adresse e-mail "
                .'et devront être joints autrement.';
        }

        return $this->reponse($campagne, $message, 201);
    }

    /** Historique des envois : qui a été destinataire, quand, avec quel résultat. */
    public function index(Request $requete)
    {
        $this->refuserSiNonAdministrateur();

        return $this->reponse(
            CampagneCourriel::with('auteur:id,nom_affichage')
                ->withCount([
                    'destinataires as envoyes' => fn ($q) => $q->where('statut', 'envoye'),
                    'destinataires as echecs'  => fn ($q) => $q->where('statut', 'echoue'),
                ])
                ->orderByDesc('date_envoi')
                ->paginate((int) $requete->query('par_page', 20))
        );
    }

    public function show(CampagneCourriel $campagne)
    {
        $this->refuserSiNonAdministrateur();

        return $this->reponse(
            $campagne->load('auteur:id,nom_affichage', 'destinataires.membre:id,matricule,nom,prenom')
        );
    }
}
