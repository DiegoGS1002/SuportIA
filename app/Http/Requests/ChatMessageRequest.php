<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // Add this line

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
            'message' => [
                'nullable',
                'string',
                'max:8000',
                // Make message required only if no image is uploaded AND external_search_consent is not true
                Rule::requiredIf(function () {
                    return ! $this->hasFile('image') && ! $this->boolean('external_search_consent');
                }),
            ],
            'image' => ['nullable', 'image', 'max:10240'],
            'external_search_consent' => ['sometimes', 'boolean'],
            'external_search_declined' => ['sometimes', 'boolean'],
        ];
    }
}
