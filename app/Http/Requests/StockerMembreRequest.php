<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockerMembreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->estAdministrateur();
    }

    public function rules(): array
    {
        return [
            'nom'                       => ['required', 'string', 'max:255'],
            'prenom'                    => ['nullable', 'string', 'max:255'],
            'sexe'                      => ['required', 'in:M,F'],
            'date_naissance'            => ['nullable', 'date', 'before:today'],
            'profession'                => ['nullable', 'string', 'max:255'],
            'telephone'                 => ['required', 'string', 'max:30'],
            'email'                     => ['nullable', 'email', 'max:255'],
            'categorie_membre_id'       => ['required', 'exists:categories_membres,id'],
            'ville_id'                  => ['nullable', 'exists:villes,id'],
            'quartier'                  => ['nullable', 'string', 'max:255'],
            'adresse'                   => ['nullable', 'string', 'max:255'],
            'contact_urgence_nom'       => ['nullable', 'string', 'max:255'],
            'contact_urgence_telephone' => ['nullable', 'string', 'max:30'],
            'date_adhesion'             => ['nullable', 'date'],
            'statut'                    => ['nullable', 'in:actif,inactif,decede'],
        ];
    }

    public function messages(): array
    {
        return [
            'categorie_membre_id.required' => 'La catégorie du membre est obligatoire.',
            'telephone.required'           => 'Le numéro de téléphone est obligatoire.',
        ];
    }
}
