<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MotDePasseOublieRequest extends FormRequest
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
        return ['email' => ['required', 'string', 'email']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'adresse e-mail'];
    }
}
