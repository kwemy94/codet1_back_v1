<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaiementResource extends JsonResource
{
    public function toArray($requete): array
    {
        return [
            'id'             => $this->id,
            'reference'      => $this->reference,
            'montant'        => (int) $this->montant,
            'date_paiement'  => $this->date_paiement?->toDateTimeString(),
            'canal'          => $this->canal,
            'statut'         => $this->statut,
            'moyen_paiement' => $this->whenLoaded('moyenPaiement', fn () => $this->moyenPaiement->libelle),
            'membre'         => $this->whenLoaded('membre', fn () => [
                'matricule'   => $this->membre?->matricule,
                'nom_complet' => $this->membre?->nom_complet,
            ]),
            'objet'          => $this->carte_developpement_id ? 'carte_annuelle' : 'contribution',
            'transaction'    => $this->whenLoaded('transaction', fn () => [
                'operateur'           => $this->transaction?->operateur,
                'statut'              => $this->transaction?->statut,
                'reference_operateur' => $this->transaction?->reference_operateur,
            ]),
            'affectations'   => $this->whenLoaded('affectations', fn () => $this->affectations->map(fn ($a) => [
                'destination' => $a->relationLoaded('destination') ? $a->destination->code : null,
                'montant'     => (int) $a->montant_affecte,
            ])),
            'recu'           => $this->whenLoaded('recu', fn () => [
                'numero'        => $this->recu?->numero_recu,
                'date_emission' => $this->recu?->date_emission?->toDateTimeString(),
            ]),
        ];
    }
}
