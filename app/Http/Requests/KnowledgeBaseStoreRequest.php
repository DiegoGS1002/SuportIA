<?php

namespace App\Http\Requests;

use App\Services\DocumentProcessor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KnowledgeBaseStoreRequest extends FormRequest
{
    public function __construct(
        private readonly DocumentProcessor $documentProcessor,
    ) {
        parent::__construct();
    }

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
                'mimes:pdf,doc,docx,txt',
                'max:10240',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $content = $this->documentProcessor->extractText($value);
                        if (!$this->documentProcessor->isValidContent($content)) {
                            $fail('O arquivo não contém texto válido, está vazio, corrompido ou muito pequeno.');
                        }
                    }
                },
            ],
        ];
    }
}

