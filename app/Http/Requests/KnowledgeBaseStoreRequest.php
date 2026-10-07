<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KnowledgeBaseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'software' => ['required', 'string', Rule::in(['home', 'stella'])],
            'title' => ['required', 'string', 'max:255'],
            'file' => [
                'required',
                'file',
                'mimetypes:application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/markdown',
                'extensions:pdf,docx,txt,md',
                'max:20480',
            ],
        ];
    }
}
