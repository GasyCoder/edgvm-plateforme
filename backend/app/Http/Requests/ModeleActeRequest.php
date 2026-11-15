<?php

namespace App\Http\Requests;

use App\Models\ModeleActe;
use App\Support\GabaritActe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Modèle d'acte : texte avec variables connues et champs complémentaires bien formés.
 */
class ModeleActeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $modele = $this->route('modele');

        return $modele instanceof ModeleActe
            ? $this->user()->can('update', $modele)
            : $this->user()->can('create', ModeleActe::class);
    }

    protected function prepareForValidation(): void
    {
        $champs = collect($this->input('champs', []))->map(function (mixed $champ): mixed {
            if (! is_array($champ)) {
                return $champ;
            }
            $champ['cle'] = Str::of($champ['cle'] ?? $champ['libelle'] ?? '')->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

            return $champ;
        })->all();

        $this->merge(['champs' => $champs]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:120'],
            'titre' => ['required', 'string', 'max:190'],
            'corps' => ['required', 'string', 'max:60000'],
            'exige_inscription' => ['boolean'],
            'inclure_signature' => ['boolean'],
            'actif' => ['boolean'],
            'champs' => ['array', 'max:20'],
            'champs.*.cle' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/', 'max:40', 'distinct', Rule::notIn(array_keys(GabaritActe::VARIABLES))],
            'champs.*.libelle' => ['required', 'string', 'max:120'],
            'champs.*.type' => ['required', Rule::in(ModeleActe::TYPES_CHAMPS)],
            'champs.*.options' => ['nullable', 'array', 'required_if:champs.*.type,choix'],
            'champs.*.options.*' => ['string', 'max:120'],
            'champs.*.obligatoire' => ['boolean'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $champs = collect($this->input('champs', []))->pluck('cle')->all();
                foreach (['titre', 'corps'] as $cle) {
                    foreach (GabaritActe::variablesInconnues((string) $this->input($cle), $champs) as $inconnue) {
                        $validator->errors()->add($cle, "Variable inconnue : {{{$inconnue}}}. Ajoutez un champ complémentaire « {$inconnue} » ou corrigez le nom.");
                    }
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
            'champs.*.cle.not_in' => 'Ce nom de champ est déjà une variable du système : choisissez un autre libellé.',
            'champs.*.cle.distinct' => 'Deux champs ont le même nom.',
            'champs.*.cle.regex' => 'Le libellé du champ doit commencer par une lettre.',
            'champs.*.options.required_if' => 'Indiquez les choix possibles.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['corps' => 'texte du modèle', 'champs.*.libelle' => 'libellé du champ', 'champs.*.type' => 'type du champ'];
    }
}
