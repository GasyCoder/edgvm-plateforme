<?php

namespace App\Http\Requests;

use App\Enums\CategoriePiece;
use App\Models\PieceJointe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PieceJointeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [PieceJointe::class, $this->route('doctorant')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'fichier' => ['required', 'file', 'mimes:'.implode(',', PieceJointe::EXTENSIONS), 'max:'.PieceJointe::TAILLE_MAX_KO],
            'categorie' => ['required', Rule::enum(CategoriePiece::class)],
            'titre' => ['nullable', 'string', 'max:190'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fichier.mimes' => 'Formats acceptés : PDF, JPG, PNG, WEBP, DOC, DOCX.',
            'fichier.max' => 'Le fichier ne doit pas dépasser 10 Mo.',
            'fichier.uploaded' => "Le fichier n'a pas pu être envoyé (il dépasse peut-être 10 Mo).",
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['fichier' => 'fichier', 'categorie' => 'type de document', 'titre' => 'titre'];
    }
}
