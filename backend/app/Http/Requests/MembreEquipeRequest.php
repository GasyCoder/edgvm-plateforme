<?php

namespace App\Http\Requests;

use App\Enums\FonctionMembre;
use App\Models\EquipeAccueil;
use App\Models\MembreEquipe;
use App\Support\Telephone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Personne d'une équipe : responsable, encadrant ou membre. Plusieurs personnes par équipe.
 */
class MembreEquipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->equipe());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['nom', 'titre', 'specialite', 'etablissement'])
            ->filter(fn (string $champ) => is_string($this->input($champ)))
            ->mapWithKeys(fn (string $champ) => [$champ => Str::squish($this->input($champ)) ?: null])
            ->all());

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email'))) ?: null]);
        }

        if ($this->has('telephone')) {
            $this->merge(['telephone' => Telephone::normaliser($this->input('telephone'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'titre' => ['nullable', 'string', 'max:120'],
            'fonction' => ['required', Rule::enum(FonctionMembre::class)],
            'specialite' => ['nullable', 'string', 'max:255'],
            'etablissement' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:190'],
            'telephone' => ['nullable', 'string', 'regex:'.Telephone::FORMAT],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.required' => 'Indiquez le nom de la personne.',
            'fonction.required' => 'Choisissez la fonction dans l\'équipe.',
            'email.email' => 'Adresse e-mail non valide.',
            'telephone.regex' => 'Numéro de téléphone non reconnu. Exemple : 034 12 345 67.',
        ];
    }

    public function equipe(): EquipeAccueil
    {
        $membre = $this->route('membre');

        return $membre instanceof MembreEquipe ? $membre->equipeAccueil : $this->route('equipe');
    }
}
