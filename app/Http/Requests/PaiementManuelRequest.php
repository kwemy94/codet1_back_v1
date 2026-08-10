<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaiementManuelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->estAdministrateur();
    }

    public function rules(): array
    {
        return [
            'carte_developpement_id' => ['required_without:contribution_id', 'nullable', 'exists:cartes_developpement,id'],
            'contribution_id'        => ['required_without:carte_developpement_id', 'nullable', 'exists:contributions,id'],
            'moyen_paiement_id'      => ['required', 'exists:moyens_paiement,id'],
            'montant'                => ['required', 'integer', 'min:1'],
            'observation'            => ['nullable', 'string', 'max:500'],
        ];
    }
}
