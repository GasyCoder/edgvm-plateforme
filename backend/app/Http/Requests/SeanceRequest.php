<?php

namespace App\Http\Requests;

use App\Enums\TypeSeance;
use App\Models\Seance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création et modification d'une séance de communication.
 */
class SeanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seance = $this->route('seance');

        return $seance instanceof Seance ? $this->user()->can('update', $seance) : $this->user()->can('create', Seance::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TypeSeance::class)],
            'titre' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:3000'],
            'lieu' => ['nullable', 'string', 'max:190'],
            'date' => ['required', 'date_format:Y-m-d'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'duree_passage' => ['required', 'integer', 'min:5', 'max:480'],
            'pause' => ['nullable', 'integer', 'min:0', 'max:120'],
            'annee_universitaire_id' => ['nullable', 'integer', Rule::exists('annees_universitaires', 'id')],
            'exige_paiement_solde' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titre.required' => 'Donnez un titre à la séance.',
            'date.required' => 'Choisissez la date.',
            'heure_debut.required' => "Indiquez l'heure de début.",
            'heure_debut.date_format' => 'Heure au format 08:30.',
            'duree_passage.min' => 'Un passage dure au moins 5 minutes.',
            'duree_passage.max' => 'Un passage dure au plus 8 heures.',
        ];
    }
}
