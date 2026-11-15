<?php

namespace App\Http\Requests;

use App\Models\AnneeUniversitaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnneeUniversitaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        $annee = $this->route('annee');

        return $annee instanceof AnneeUniversitaire
            ? $this->user()->can('update', $annee)
            : $this->user()->can('create', AnneeUniversitaire::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/', Rule::unique('annees_universitaires', 'libelle')->ignore($this->route('annee')?->id)],
            'debut' => ['required', 'date'],
            'fin' => ['required', 'date', 'after:debut'],
            'est_courante' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'libelle.regex' => 'Format attendu : 2026-2027.',
            'libelle.unique' => 'Cette année universitaire existe déjà.',
            'fin.after' => 'La fin doit être après le début.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['libelle' => 'année', 'debut' => 'date de début', 'fin' => 'date de fin'];
    }
}
