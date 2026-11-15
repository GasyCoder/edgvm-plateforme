<?php

namespace App\Http\Requests;

use App\Models\EquipeAccueil;
use Illuminate\Foundation\Http\FormRequest;
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sigle' => ['required', 'string', 'max:30', Rule::unique('equipes_accueil', 'sigle')->ignore($this->route('equipe')?->id)],
            'nom' => ['required', 'string', 'max:255'],
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
