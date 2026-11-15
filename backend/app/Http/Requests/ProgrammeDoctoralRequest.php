<?php

namespace App\Http\Requests;

use App\Models\EquipeAccueil;
use App\Models\ProgrammeDoctoral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Programme doctoral d'une équipe : nom unique dans l'équipe.
 */
class ProgrammeDoctoralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->equipe());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nom'))) {
            $this->merge(['nom' => Str::squish($this->input('nom'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255', Rule::unique('programmes_doctoraux', 'nom')
                ->where('equipe_accueil_id', $this->equipe()->id)
                ->ignore($this->route('programme')?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.required' => 'Indiquez le nom du programme.',
            'nom.unique' => 'Cette équipe a déjà un programme de ce nom.',
        ];
    }

    public function equipe(): EquipeAccueil
    {
        $programme = $this->route('programme');

        return $programme instanceof ProgrammeDoctoral ? $programme->equipeAccueil : $this->route('equipe');
    }
}
