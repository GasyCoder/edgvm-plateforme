<?php

namespace App\Http\Requests;

use App\Models\Acte;
use App\Models\ModeleActe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Délivrance d'un acte à un doctorant (« doctorant ») ou à plusieurs (« doctorants »), avec les champs du modèle.
 */
class DelivrerActeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Acte::class);
    }

    public function modele(): ?ModeleActe
    {
        return ModeleActe::query()->find($this->input('modele_acte_id'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $modele = $this->modele();
        $lot = $this->routeIs('actes.lot');

        $regles = [
            'modele_acte_id' => ['required', 'integer', Rule::exists('modeles_actes', 'id')->where('actif', true)],
            'annee_universitaire_id' => [$modele?->exige_inscription ? 'required' : 'nullable', 'integer', Rule::exists('annees_universitaires', 'id')],
            'doctorant' => [$lot ? 'prohibited' : 'required', 'string', Rule::exists('doctorants', 'uuid')],
            'doctorants' => [$lot ? 'required' : 'prohibited', 'array', 'min:1', 'max:300'],
            'doctorants.*' => ['string', 'distinct', Rule::exists('doctorants', 'uuid')],
            'champs' => ['nullable', 'array'],
            'doublons' => ['nullable', Rule::in($lot ? ['confirmer', 'ignorer'] : ['confirmer'])],
        ];

        foreach ($modele?->champs ?? [] as $champ) {
            $regles['champs.'.$champ['cle']] = [
                ($champ['obligatoire'] ?? false) ? 'required' : 'nullable',
                ...match ($champ['type']) {
                    'date' => ['date'],
                    'heure' => ['date_format:H:i'],
                    'choix' => [Rule::in($champ['options'] ?? [])],
                    'texte_long' => ['string', 'max:3000'],
                    default => ['string', 'max:500'],
                },
            ];
        }

        return $regles;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $noms = ['modele_acte_id' => "modèle d'acte", 'annee_universitaire_id' => 'année universitaire', 'doctorants' => 'doctorants'];
        foreach ($this->modele()?->champs ?? [] as $champ) {
            $noms['champs.'.$champ['cle']] = mb_strtolower($champ['libelle']);
        }

        return $noms;
    }
}
