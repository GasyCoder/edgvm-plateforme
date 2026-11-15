<?php

namespace App\Http\Requests;

use App\Models\EquipeAccueil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EquipeAccueilRequest extends FormRequest
{
    public function authorize(): bool
    {
        $equipe = $this->route('equipe');

        return $equipe instanceof EquipeAccueil
            ? $this->user()->can('update', $equipe)
            : $this->user()->can('create', EquipeAccueil::class);
    }

    /**
     * Sigle et nom sur une seule ligne, sans espaces en trop (un nom long collé depuis un document garde ses retours à la ligne).
     */
    protected function prepareForValidation(): void
    {
        $this->merge(collect(['sigle', 'nom', 'domaine'])
            ->filter(fn (string $champ) => is_string($this->input($champ)))
            ->mapWithKeys(fn (string $champ) => [$champ => Str::squish($this->input($champ))])
            ->all());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sigle' => ['required', 'string', 'max:30', Rule::unique('equipes_accueil', 'sigle')->ignore($this->route('equipe')?->id)],
            'nom' => ['required', 'string', 'max:255'],
            'domaine' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['sigle.unique' => 'Une équipe porte déjà ce sigle.'];
    }
}
