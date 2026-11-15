<?php

namespace App\Http\Requests;

use App\Enums\ModePaiement;
use App\Models\Paiement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Paiement::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'montant' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'date_paiement' => ['required', 'date', 'before_or_equal:today'],
            'mode' => ['required', Rule::enum(ModePaiement::class)],
            'reference' => [Rule::requiredIf(fn () => in_array($this->input('mode'), ['virement', 'mobile_money', 'cheque'], true)), 'nullable', 'string', 'max:120'],
            'observation' => ['nullable', 'string', 'max:500'],
            'justificatif' => ['nullable', 'file', 'mimes:'.implode(',', Paiement::EXTENSIONS_JUSTIFICATIF), 'max:10240'],
            'imputations' => ['nullable', 'array'],
            'imputations.*.frais' => ['required', 'integer'],
            'imputations.*.montant' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference.required' => 'Indiquez la référence (numéro de bordereau, de transaction ou de chèque).',
            'date_paiement.before_or_equal' => 'La date du paiement ne peut pas être dans le futur.',
            'justificatif.mimes' => 'Le reçu de versement doit être une photo (JPG, PNG, WEBP) ou un PDF.',
            'justificatif.max' => 'Le reçu de versement ne doit pas dépasser 10 Mo.',
            'justificatif.uploaded' => "Le reçu de versement n'a pas pu être envoyé (il dépasse peut-être 10 Mo).",
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['date_paiement' => 'date du paiement', 'mode' => 'mode de paiement', 'reference' => 'référence', 'justificatif' => 'reçu de versement'];
    }
}
