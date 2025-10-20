<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ObserveSesionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'observaciones' => ['required', 'string', 'max:500'],
        ];
    }

    public function observation(): string
    {
        return trim((string) $this->input('observaciones'));
    }
}
