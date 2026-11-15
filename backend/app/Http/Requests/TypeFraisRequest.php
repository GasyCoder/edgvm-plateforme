<?php

namespace App\Http\Requests;

use App\Models\Paiement;
use App\Models\TypeFrais;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Paramètre d'un frais : montant unique ou montant par niveau (D1, D2, D3, dérogation).
 */
class TypeFraisRequest extends FormRequest
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
            'libelle' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'par_niveau' => ['boolean'],
            'montant' => ['required_unless:par_niveau,true', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'montants_niveaux' => ['required_if:par_niveau,true', 'nullable', 'array'],
            ...collect(TypeFrais::NIVEAUX)->mapWithKeys(fn (string $niveau) => ["montants_niveaux.{$niveau}" => ['required_if:par_niveau,true', 'nullable', 'integer', 'min:0', 'max:100000000']])->all(),
            'obligatoire' => ['boolean'],
            'echeance' => ['sometimes', Rule::in([TypeFrais::ECHEANCE_FIN_ANNEE, TypeFrais::ECHEANCE_DELAI])],
            'delai_jours' => ['required', 'integer', 'min:0', 'max:365'],
            'tranches' => ['sometimes', 'integer', 'min:1', 'max:'.TypeFrais::TRANCHES_MAX],
            'actif' => ['boolean'],
            'appliquer_aux_frais' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function donnees(): array
    {
        $parNiveau = $this->boolean('par_niveau');
        $niveaux = $parNiveau ? collect(TypeFrais::NIVEAUX)->mapWithKeys(fn (string $n) => [$n => (int) $this->input("montants_niveaux.{$n}", 0)])->all() : null;

        return [
            'libelle' => $this->string('libelle')->trim()->toString(),
            'description' => $this->input('description'),
            'montant' => $parNiveau ? (int) ($niveaux['D1'] ?? 0) : (int) $this->input('montant', 0),
            'montants_niveaux' => $niveaux,
            'obligatoire' => $this->boolean('obligatoire'),
            'echeance' => $this->input('echeance', $this->route('type')?->echeance ?? TypeFrais::ECHEANCE_DELAI),
            'delai_jours' => $this->integer('delai_jours'),
            'tranches' => $this->integer('tranches', $this->route('type')?->tranches ?? 1),
            'actif' => $this->boolean('actif', true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'montant.required_unless' => 'Indiquez le montant.',
            'montants_niveaux.*.required_if' => 'Indiquez le montant pour chaque niveau.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['libelle' => 'libellé', 'echeance' => 'échéance', 'tranches' => 'nombre de tranches', 'delai_jours' => 'délai de paiement', 'montants_niveaux.D1' => 'montant D1', 'montants_niveaux.D2' => 'montant D2', 'montants_niveaux.D3' => 'montant D3', 'montants_niveaux.derogation' => 'montant dérogation'];
    }
}
