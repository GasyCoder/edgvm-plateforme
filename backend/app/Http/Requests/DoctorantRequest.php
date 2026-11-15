<?php

namespace App\Http\Requests;

use App\Enums\NiveauDoctorat;
use App\Enums\StatutDoctorant;
use App\Models\Doctorant;
use App\Support\Telephone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création et modification d'un dossier doctorant (avec son compte et, à la création, sa première inscription).
 */
class DoctorantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $doctorant = $this->route('doctorant');

        return $doctorant instanceof Doctorant
            ? $this->user()->can('update', $doctorant)
            : $this->user()->can('create', Doctorant::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'telephone' => Telephone::normaliser($this->input('telephone')),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'nom' => is_string($this->input('nom')) ? trim($this->input('nom')) : $this->input('nom'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $doctorant = $this->route('doctorant');
        $regles = self::regles($doctorant instanceof Doctorant ? $doctorant : null);
        $etape = $this->input('etape');

        if (! $doctorant instanceof Doctorant && is_string($etape) && isset(self::ETAPES[$etape])) {
            return collect($regles)
                ->filter(fn (array $regle, string $champ) => in_array(strtok($champ, '.'), self::ETAPES[$etape], true))
                ->all();
        }

        return $regles;
    }

    /**
     * Étapes de l'assistant de création : « etape » ne vérifie que les champs de l'étape, sans rien enregistrer.
     */
    public const ETAPES = [
        'identite' => ['nom', 'prenoms', 'genre', 'date_naissance', 'lieu_naissance', 'nationalite', 'numero_piece', 'adresse'],
        'contact' => ['email', 'telephone', 'envoyer_invitation'],
        'these' => ['equipe_accueil_id', 'sujet_these'],
        'inscription' => ['inscription', 'matricule', 'visible_annuaire', 'observation'],
    ];

    public function estVerificationEtape(): bool
    {
        return is_string($this->input('etape')) && isset(self::ETAPES[$this->input('etape')]);
    }

    /**
     * Règles partagées par le formulaire et l'import (création si $doctorant est null).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function regles(?Doctorant $doctorant = null): array
    {
        $creation = $doctorant === null;

        return [
            'nom' => ['required', 'string', 'max:120'],
            'prenoms' => ['nullable', 'string', 'max:160'],
            'genre' => ['nullable', Rule::in(['M', 'F'])],
            'date_naissance' => ['nullable', 'date', 'before:-15 years'],
            'lieu_naissance' => ['nullable', 'string', 'max:120'],
            'nationalite' => ['nullable', 'string', 'max:60'],
            'numero_piece' => ['nullable', 'string', 'max:40'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($doctorant?->user_id)],
            'telephone' => ['nullable', 'string', 'regex:'.Telephone::FORMAT],
            'matricule' => ['nullable', 'string', 'max:30', Rule::unique('doctorants', 'matricule')->ignore($doctorant?->id)],
            'equipe_accueil_id' => ['required', 'integer', Rule::exists('equipes_accueil', 'id')],
            'sujet_these' => ['nullable', 'string', 'max:500'],
            'statut' => [$creation ? 'nullable' : 'required', Rule::enum(StatutDoctorant::class)],
            'visible_annuaire' => ['boolean'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'envoyer_invitation' => ['boolean'],

            'inscription' => [$creation ? 'nullable' : 'prohibited', 'array'],
            'inscription.annee_universitaire_id' => ['required_with:inscription', 'integer', Rule::exists('annees_universitaires', 'id')],
            'inscription.niveau' => ['required_with:inscription', Rule::enum(NiveauDoctorat::class)],
            'inscription.montant_du' => ['required_with:inscription', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::messagesValidation();
    }

    /**
     * @return array<string, string>
     */
    public static function messagesValidation(): array
    {
        return [
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
            'matricule.unique' => 'Ce matricule est déjà attribué.',
            'telephone.regex' => 'Numéro de téléphone non reconnu. Exemple : 034 12 345 67.',
            'date_naissance.before' => 'La date de naissance semble incorrecte.',
            'equipe_accueil_id.required' => "Choisissez l'équipe d'accueil.",
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::nomsDesChamps();
    }

    /**
     * @return array<string, string>
     */
    public static function nomsDesChamps(): array
    {
        return [
            'prenoms' => 'prénoms',
            'date_naissance' => 'date de naissance',
            'lieu_naissance' => 'lieu de naissance',
            'nationalite' => 'nationalité',
            'numero_piece' => "numéro de pièce d'identité",
            'email' => 'adresse e-mail',
            'telephone' => 'téléphone',
            'equipe_accueil_id' => "équipe d'accueil",
            'sujet_these' => 'sujet de thèse',
            'inscription.annee_universitaire_id' => 'année universitaire',
            'inscription.niveau' => 'niveau',
            'inscription.montant_du' => 'montant des droits',
        ];
    }
}
