<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CarteResource extends JsonResource
{
    public function toArray($requete): array
    {
        return [
            'id'            => $this->id,
            'numero_carte'  => $this->numero_carte,
            'exercice'      => $this->whenLoaded('exercice', fn () => $this->exercice->annee),
            'membre'        => $this->whenLoaded('membre', fn () => [
                'id'          => $this->membre->id,
                'matricule'   => $this->membre->matricule,
                'nom_complet' => $this->membre->nom_complet,
            ]),
            'date_emission' => $this->date_emission?->toDateString(),
            'montant_du'    => (int) $this->montant_du,
            'montant_regle' => (int) $this->montant_regle,
            'solde'         => $this->solde,
            'statut'        => $this->statut,
            // L'interface s'en sert pour afficher le bouton ; le serveur revérifie
            // systématiquement au moment de l'impression.
            'imprimable'    => $this->statut === 'soldee'
                && $this->relationLoaded('exercice')
                && $this->exercice?->statut === 'ouvert',
            'type_carte'    => $this->whenLoaded('typeCarte', fn () => [
                'id'      => $this->typeCarte->id,
                'code'    => $this->typeCarte->code,
                'libelle' => $this->typeCarte->libelle,
            ]),
            'repartition'   => $this->whenLoaded('tarif', fn () => $this->tarif->relationLoaded('repartitions')
                ? $this->tarif->repartitions->mapWithKeys(fn ($ligne) => [
                    $ligne->relationLoaded('destination') ? $ligne->destination->code : (string) $ligne->destination_fonds_id => (int) $ligne->montant,
                ])
                : null),
            'paiements'     => PaiementResource::collection($this->whenLoaded('paiements')),
        ];
    }
}
