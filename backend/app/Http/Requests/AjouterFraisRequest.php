<?php

namespace App\Http\Requests;

use App\Models\Paiement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Application d'un frais à des doctorants choisis, ou à tout un groupe (inscrits d'une année, d'un niveau, d'une équipe).
 */
class AjouterFraisRequest extends FormRequest
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
            'type_frais_id' => ['required_without:libelle', 'nullable', 'integer', Rule::exists('types_frais', 'id')],
            'libelle' => ['required_without:type_frais_id', 'nullable', 'string', 'max:150'],
            'montant' => ['required_without:type_frais_id', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'annee_universitaire_id' => ['nullable', 'integer', Rule::exists('annees_universitaires', 'id')],
            'echeance' => ['nullable', 'date'],
            'doctorants' => ['required_without:cible', 'array', 'max:1000'],
            'doctorants.*' => ['string', Rule::exists('doctorants', 'uuid')],
            'cible' => ['required_without:doctorants', 'nullable', Rule::in(['inscrits', 'actifs'])],
            'niveau' => ['nullable', Rule::in(['D1', 'D2', 'D3', 'derogation'])],
            'equipe' => ['nullable', 'integer', Rule::exists('equipes_accueil', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type_frais_id.required_without' => 'Choisissez un frais du catalogue ou saisissez un libellé.',
            'montant.required_without' => 'Indiquez le montant.',
            'doctorants.required_without' => 'Choisissez les doctorants concernés.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['type_frais_id' => 'frais', 'libelle' => 'libellé', 'echeance' => 'échéance', 'annee_universitaire_id' => 'année universitaire'];
    }
}
