<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModifierMembreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->estAdministrateur();
    }

    public function rules(): array
    {
        return [
            'nom'                       => ['sometimes', 'string', 'max:255'],
            'prenom'                    => ['sometimes', 'nullable', 'string', 'max:255'],
            'sexe'                      => ['sometimes', 'in:M,F'],
            'date_naissance'            => ['sometimes', 'nullable', 'date', 'before:today'],
            'profession'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'telephone'                 => ['sometimes', 'string', 'max:30'],
            'email'                     => ['sometimes', 'nullable', 'email', 'max:255'],
            'categorie_membre_id'       => ['sometimes', 'exists:categories_membres,id'],
            'ville_id'                  => ['sometimes', 'nullable', 'exists:villes,id'],
            'quartier'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'adresse'                   => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_urgence_nom'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_urgence_telephone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'statut'                    => ['sometimes', 'in:actif,inactif,decede'],
        ];
    }
}
