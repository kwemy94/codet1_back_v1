<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MembreResource extends JsonResource
{
    public function toArray($requete): array
    {
        return [
            'id'             => $this->id,
            'matricule'      => $this->matricule,
            'nom'            => $this->nom,
            'prenom'         => $this->prenom,
            'nom_complet'    => $this->nom_complet,
            'sexe'           => $this->sexe,
            'date_naissance' => $this->date_naissance?->toDateString(),
            'profession'     => $this->profession,
            'telephone'      => $this->telephone,
            'email'          => $this->email,
            'categorie'      => $this->whenLoaded('categorie', fn () => [
                'id'      => $this->categorie->id,
                'code'    => $this->categorie->code,
                'libelle' => $this->categorie->libelle,
            ]),
            'localisation'   => [
                'ville'    => $this->whenLoaded('ville', fn () => $this->ville->libelle),
                'pays'     => $this->whenLoaded('ville', fn () => $this->ville->relationLoaded('pays') ? $this->ville->pays->libelle : null),
                'quartier' => $this->quartier,
                'adresse'  => $this->adresse,
            ],
            'photo'          => $this->photo,
            'date_adhesion'  => $this->date_adhesion?->toDateString(),
            'statut'         => $this->statut,
            'motif_statut'   => $this->motif_statut,
            'date_changement_statut' => $this->date_changement_statut?->toDateString(),
            'cartes'         => CarteResource::collection($this->whenLoaded('cartes')),
            'contributions'  => $this->whenLoaded('contributions', fn () => $this->contributions->map(fn ($contribution) => [
                'id'                => $contribution->id,
                'reference'         => $contribution->reference,
                'nature'            => $contribution->nature,
                'designation'       => $contribution->designation,
                'montant'           => (int) $contribution->montant,
                'motif'             => $contribution->motif,
                'statut'            => $contribution->statut,
                'date_contribution' => $contribution->date_contribution?->toDateString(),
                'exercice'          => $contribution->relationLoaded('exercice') ? $contribution->exercice?->annee : null,
                'type'              => $contribution->relationLoaded('type') ? $contribution->type?->libelle : null,
            ])),
            'total_dons'     => $this->whenLoaded('contributions', fn () => (int) $this->contributions
                ->whereIn('statut', ['encaissee', 'recue'])
                ->sum('montant')),
            'a_un_compte'    => $this->whenLoaded('compte', fn () => (bool) $this->compte),
            'acces'          => $this->whenLoaded('compte', fn () => $this->compte ? [
                'statut'                    => $this->compte->statut,
                'doit_changer_mot_de_passe' => (bool) $this->compte->doit_changer_mot_de_passe,
                'derniere_connexion'        => $this->compte->derniere_connexion_at?->toDateTimeString(),
            ] : null),
        ];
    }
}
