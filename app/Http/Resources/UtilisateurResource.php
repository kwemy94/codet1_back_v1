<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UtilisateurResource extends JsonResource
{
    public function toArray($requete): array
    {
        return [
            'id'                => $this->id,
            'nom_affichage'     => $this->nom_affichage,
            'email'             => $this->email,
            'telephone'         => $this->telephone,
            'statut'            => $this->statut,
            'est_administrateur' => $this->estAdministrateur(),
            'doit_changer_mot_de_passe' => (bool) $this->doit_changer_mot_de_passe,
            'membre'            => $this->whenLoaded('membre', fn () => $this->membre ? new MembreResource($this->membre) : null),
            'roles'             => $this->whenLoaded('roles', fn () => $this->roles->pluck('code')),
            'permissions'       => $this->whenLoaded('roles', fn () => $this->roles->flatMap->permissions->pluck('code')->unique()->values()),
        ];
    }
}
