<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function observation(): ?string
    {
        $value = trim((string) $this->input('observaciones'));

        return $value === '' ? null : $value;
    }
}
