<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitierPaiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'carte_developpement_id' => ['required_without:contribution_id', 'nullable', 'exists:cartes_developpement,id'],
            'contribution_id'        => ['required_without:carte_developpement_id', 'nullable', 'exists:contributions,id'],
            'montant'                => ['required', 'integer', 'min:100'],
            'moyen_paiement'         => ['required', 'string', 'exists:moyens_paiement,code'],
            'numero_telephone'       => ['required', 'string', 'regex:/^[0-9+ ]{8,20}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'montant.min'              => 'Le montant minimum d\'un paiement en ligne est de 100 FCFA.',
            'numero_telephone.regex'   => 'Le numéro de téléphone n\'est pas valide.',
        ];
    }
}
