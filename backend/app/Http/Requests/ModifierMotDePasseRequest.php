<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ModifierMotDePasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mot_de_passe_actuel' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:mot_de_passe_actuel', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['mot_de_passe_actuel' => 'mot de passe actuel', 'password' => 'nouveau mot de passe'];
    }
}
