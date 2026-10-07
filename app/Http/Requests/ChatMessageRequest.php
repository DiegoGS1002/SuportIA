<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                Rule::requiredIf(function () {
                    return ! $this->hasFile('image')
                        && ! $this->boolean('external_search_consent')
                        && ! $this->boolean('external_search_declined');
                }),
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'external_search_consent' => [
                'sometimes',
                'boolean',
                Rule::prohibitedIf(fn () => $this->boolean('external_search_declined')),
            ],
            'external_search_declined' => [
                'sometimes',
                'boolean',
                Rule::prohibitedIf(fn () => $this->boolean('external_search_consent')),
            ],
            'client_message_id' => ['nullable', 'uuid'],
        ];
    }
}
