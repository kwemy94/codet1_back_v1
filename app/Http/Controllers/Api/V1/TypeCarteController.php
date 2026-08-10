<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TypeCarte;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Types de cartes : la carte annuelle obligatoire, mais aussi tout type créé
 * par le comité (membre d'honneur, carte de soutien…) avec sa propre clé de
 * répartition définie ensuite dans les tarifs.
 */
class TypeCarteController extends Controller
{
    public function __construct(private JournalService $journal) {}

    public function index(Request $requete)
    {
        return $this->reponse(
            TypeCarte::withCount('cartes')
                ->when($requete->boolean('actifs_seulement'), fn ($q) => $q->actifs())
                ->orderByDesc('obligatoire')
                ->orderBy('libelle')
                ->get()
        );
    }

    public function store(Request $requete)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate([
            'libelle'     => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'obligatoire' => ['boolean'],
        ]);

        $type = TypeCarte::create([
            'code'        => $this->code($donnees['libelle']),
            'libelle'     => $donnees['libelle'],
            'description' => $donnees['description'] ?? null,
            'obligatoire' => $requete->boolean('obligatoire'),
            'actif'       => true,
        ]);

        $this->journal->tracer('creation_type_carte', $type, nouvelleValeur: $type->toArray());

        return $this->reponse($type, "Type de carte « {$type->libelle} » créé. Définissez maintenant son tarif.", 201);
    }

    public function update(Request $requete, TypeCarte $type)
    {
        $this->refuserSiNonAdministrateur();

        $donnees = $requete->validate([
            'libelle'     => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'actif'       => ['sometimes', 'boolean'],
        ]);

        $ancien = $type->toArray();
        $type->update($donnees);
        $this->journal->tracer('modification_type_carte', $type, ancienneValeur: $ancien, nouvelleValeur: $type->fresh()->toArray());

        return $this->reponse($type->fresh(), 'Type de carte mis à jour.');
    }

    /** Génère un code stable à partir du libellé : « Membre d'honneur » → MEMBRE_HONNEUR. */
    private function code(string $libelle): string
    {
        $base = Str::upper(Str::slug($libelle, '_'));
        $base = Str::limit($base, 24, '');
        $code = $base;
        $suffixe = 1;

        while (TypeCarte::where('code', $code)->exists()) {
            $code = $base.'_'.(++$suffixe);
        }

        return $code;
    }
}
