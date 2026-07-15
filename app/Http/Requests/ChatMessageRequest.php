<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatMessageRequest extends FormRequest
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
            'message' => ['nullable', 'string', 'required_without:image', 'max:8000'],
            'image' => ['nullable', 'image', 'max:10240'],
            'external_search_consent' => ['sometimes', 'boolean'],
            'external_search_declined' => ['sometimes', 'boolean'],
        ];
    }
}

