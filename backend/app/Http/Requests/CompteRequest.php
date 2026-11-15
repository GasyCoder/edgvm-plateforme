<?php

namespace App\Http\Requests;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use App\Support\Telephone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Comptes de l'administration et des membres d'équipe. Les comptes doctorants se créent avec leur dossier.
 */
class CompteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $compte = $this->route('compte');

        return $compte instanceof User
            ? $this->user()->can('update', $compte)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'telephone' => Telephone::normaliser($this->input('telephone')),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $compte = $this->route('compte');
        $estDoctorant = $compte instanceof User && $compte->estDoctorant();

        return [
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($compte?->id)],
            'telephone' => ['nullable', 'string', 'regex:'.Telephone::FORMAT],
            'role' => $estDoctorant
                ? ['prohibited']
                : ['required', Rule::in([RoleUtilisateur::SuperAdmin->value, RoleUtilisateur::MembreEad->value])],
            'actif' => ['boolean'],
            'equipes' => ['nullable', 'array', 'required_if:role,'.RoleUtilisateur::MembreEad->value],
            'equipes.*' => ['integer', Rule::exists('equipes_accueil', 'id')],
            'envoyer_invitation' => ['boolean'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $compte = $this->route('compte');

                if (! $compte instanceof User || ! $compte->is($this->user())) {
                    return;
                }

                if ($this->input('role') !== RoleUtilisateur::SuperAdmin->value) {
                    $validator->errors()->add('role', 'Vous ne pouvez pas retirer vos propres droits d\'administration.');
                }

                if ($this->has('actif') && ! $this->boolean('actif')) {
                    $validator->errors()->add('actif', 'Vous ne pouvez pas désactiver votre propre compte.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
            'telephone.regex' => 'Numéro de téléphone non reconnu. Exemple : 034 12 345 67.',
            'equipes.required_if' => "Choisissez au moins une équipe d'accueil.",
            'role.prohibited' => "Le rôle d'un compte doctorant ne se change pas.",
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nom', 'email' => 'adresse e-mail', 'telephone' => 'téléphone', 'role' => 'rôle', 'equipes' => "équipes d'accueil"];
    }
}
