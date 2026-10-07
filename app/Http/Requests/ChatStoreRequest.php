<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChatStoreRequest extends FormRequest
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
            'software' => ['nullable', 'string', Rule::in(['home', 'stella'])],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
