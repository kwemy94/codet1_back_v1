<?php

namespace App\Services;

use App\Models\CarteDeveloppement;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\TarifCarte;
use App\Models\TypeCarte;
use Illuminate\Validation\ValidationException;

/**
 * Émission d'une carte.
 *
 * Le tarif en vigueur au moment de l'émission est figé sur la carte, de sorte
 * qu'une révision ultérieure des montants n'altère jamais les cartes émises.
 * Un membre peut cumuler plusieurs types de cartes sur un même exercice :
 * la carte annuelle obligatoire et, par exemple, une carte de membre d'honneur.
 */
class CarteService
{
    public function emettre(Membre $membre, Exercice $exercice, TypeCarte $type): CarteDeveloppement
    {
        if (! $exercice->estOuvert()) {
            throw ValidationException::withMessages([
                'exercice_id' => "L'exercice {$exercice->annee} est clôturé : aucune carte ne peut y être émise.",
            ]);
        }

        if ($membre->statut !== 'actif') {
            throw ValidationException::withMessages([
                'membre_id' => "{$membre->nom_complet} n'est pas actif : "
                    .'réactivez-le avant de lui émettre une carte.',
            ]);
        }

        $existante = $membre->cartes()
            ->where('exercice_id', $exercice->id)
            ->where('type_carte_id', $type->id)
            ->first();

        if ($existante) {
            throw ValidationException::withMessages([
                'membre_id' => "{$membre->nom_complet} possède déjà une carte « {$type->libelle} » pour l'exercice {$exercice->annee}.",
            ]);
        }

        $tarif = $this->tarifApplicable($membre, $exercice, $type);

        return CarteDeveloppement::create([
            'numero_carte'   => $this->numeroCarte($exercice, $membre, $type),
            'membre_id'      => $membre->id,
            'exercice_id'    => $exercice->id,
            'type_carte_id'  => $type->id,
            'tarif_carte_id' => $tarif->id,
            'date_emission'  => now(),
            'montant_du'     => $tarif->montant_minimum,
            'montant_regle'  => 0,
            'statut'         => 'impayee',
        ]);
    }

    /**
     * Un tarif propre à la catégorie du membre l'emporte ; à défaut, on retient
     * le tarif commun du type de carte (catégorie nulle).
     */
    private function tarifApplicable(Membre $membre, Exercice $exercice, TypeCarte $type): TarifCarte
    {
        $tarif = TarifCarte::actif()
            ->with('repartitions')
            ->where('exercice_id', $exercice->id)
            ->where('type_carte_id', $type->id)
            ->where(function ($requete) use ($membre) {
                $requete->where('categorie_membre_id', $membre->categorie_membre_id)
                    ->orWhereNull('categorie_membre_id');
            })
            ->orderByRaw('categorie_membre_id IS NULL')  // la catégorie précise d'abord
            ->first();

        if (! $tarif) {
            throw ValidationException::withMessages([
                'tarif' => "Aucun tarif « {$type->libelle} » n'est défini pour la catégorie "
                    ."{$membre->categorie->libelle} sur l'exercice {$exercice->annee}.",
            ]);
        }

        return $tarif;
    }

    private function numeroCarte(Exercice $exercice, Membre $membre, TypeCarte $type): string
    {
        $prefixe = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $type->code), 0, 4)) ?: 'CART';

        return sprintf('%s-%d-%s', $prefixe, $exercice->annee, substr($membre->matricule, -6));
    }
}
