<?php

namespace App\Http\Requests;

use App\Enums\NiveauDoctorat;
use App\Enums\StatutInscription;
use App\Models\Doctorant;
use App\Models\Inscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Inscription annuelle. Le type (première inscription ou réinscription) est déduit de l'historique ;
 * après trois années (D1 à D3), une nouvelle inscription est forcément une dérogation.
 */
class InscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inscription = $this->route('inscription');

        return $inscription instanceof Inscription
            ? $this->user()->can('update', $inscription)
            : $this->user()->can('create', [Inscription::class, $this->route('doctorant')]);
    }

    public function doctorant(): Doctorant
    {
        $inscription = $this->route('inscription');

        return $inscription instanceof Inscription ? $inscription->doctorant : $this->route('doctorant');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $inscription = $this->route('inscription');

        return [
            'annee_universitaire_id' => [
                'required', 'integer', Rule::exists('annees_universitaires', 'id'),
                Rule::unique('inscriptions')->where('doctorant_id', $this->doctorant()->id)->ignore($inscription?->id),
            ],
            'niveau' => ['required', Rule::enum(NiveauDoctorat::class)],
            'est_derogation' => ['boolean'],
            'motif_derogation' => ['nullable', 'required_if:est_derogation,true', 'string', 'max:2000'],
            'decision_derogation' => ['nullable', 'string', 'max:190'],
            'date_decision_derogation' => ['nullable', 'required_with:decision_derogation', 'date'],
            'statut' => ['required', Rule::enum(StatutInscription::class)],
            'date_inscription' => ['nullable', 'date'],
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

                $anterieures = $this->doctorant()->inscriptions()
                    ->where('statut', '!=', StatutInscription::Annulee->value)
                    ->when($this->route('inscription'), fn ($requete, Inscription $courante) => $requete->whereKeyNot($courante->id))
                    ->count();

                if (! $this->boolean('est_derogation') && $anterieures >= NiveauDoctorat::DUREE_NORMALE) {
                    $validator->errors()->add('est_derogation', 'Ce doctorant a déjà trois années d\'inscription : la nouvelle inscription doit être une dérogation.');
                }

                if ($this->boolean('est_derogation') && $this->input('niveau') !== NiveauDoctorat::D3->value) {
                    $validator->errors()->add('niveau', 'Une dérogation se fait au niveau D3.');
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
            'annee_universitaire_id.unique' => 'Ce doctorant est déjà inscrit pour cette année universitaire.',
            'motif_derogation.required_if' => 'Indiquez le motif de la dérogation.',
            'date_decision_derogation.required_with' => 'Indiquez la date de la décision.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'annee_universitaire_id' => 'année universitaire',
            'motif_derogation' => 'motif de la dérogation',
            'decision_derogation' => 'référence de la décision',
            'date_decision_derogation' => 'date de la décision',
            'date_inscription' => "date d'inscription",
        ];
    }
}
