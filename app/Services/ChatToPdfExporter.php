<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Document;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatToPdfExporter
{
    public function export(Chat $chat, string $documentName, ?int $userId = null): Document
    {
        // Gerar PDF
        $html = $this->generateHtml($chat);
        $pdf = Pdf::loadHTML($html);

        // Salvar arquivo
        $filename = $this->sanitizeFilename($documentName).'-'.Str::uuid().'.pdf';
        $path = 'documents/'.now()->format('Y/m').'/'.$filename;

        $pdfContent = $pdf->output();
        if (! Storage::disk('local')->put($path, $pdfContent)) {
            throw new \RuntimeException('Nao foi possivel armazenar o PDF exportado.');
        }

        // Criar registro no banco
        $document = Document::create([
            'user_id' => $userId,
            'chat_id' => $chat->id,
            'type' => Document::TYPE_CHAT_EXPORT,
            'name' => $this->sanitizeFilename($documentName).'.pdf',
            'file_path' => $path,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'status' => 'indexed',
            'file_size' => strlen($pdfContent),
        ]);

        return $document;
    }

    private function generateHtml(Chat $chat): string
    {
        $messages = $chat->messages()->get();
        $softwareLabel = match ($chat->software) {
            'stella' => 'Stella',
            'home' => 'Home',
            default => 'Geral',
        };

        $messagesHtml = $messages->map(function ($message) {
            $roleLabel = $message->role === 'user' ? 'Usuário' : 'Assistente';
            $roleClass = $message->role === 'user' ? 'user-message' : 'assistant-message';

            $content = nl2br(e((string) $message->content));

            return <<<HTML
                <div class="message $roleClass">
                    <strong class="role-label">$roleLabel:</strong>
                    <p class="message-content">$content</p>
                </div>
            HTML;
        })->join("\n");

        $createdAt = $chat->created_at->format('d/m/Y H:i:s');
        $exportedAt = now()->format('d/m/Y H:i:s');
        $title = e($chat->title ?: 'Atendimento');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>$title</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }

        .container {
            max-width: 800px;
            margin: 0;
            padding: 20px;
            background: white;
        }

        .header {
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header h1 {
            color: #1e40af;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .header .info {
            font-size: 12px;
            color: #666;
        }

        .info-row {
            margin: 5px 0;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
        }

        .messages {
            margin-top: 20px;
        }

        .message {
            margin-bottom: 15px;
            padding: 12px;
            border-radius: 6px;
        }

        .user-message {
            background: #f0f7ff;
            border-left: 4px solid #2563eb;
        }

        .assistant-message {
            background: #f0fdf4;
            border-left: 4px solid #16a34a;
        }

        .role-label {
            color: #1e40af;
            font-size: 11px;
            text-transform: uppercase;
        }

        .assistant-message .role-label {
            color: #15803d;
        }

        .message-content {
            margin-top: 5px;
            word-wrap: break-word;
            font-size: 13px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 11px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>$title</h1>
            <div class="info">
                <div class="info-row">
                    <span class="info-label">Software:</span>
                    <span>$softwareLabel</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Data:</span>
                    <span>$createdAt</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Mensagens:</span>
                    <span>{$messages->count()}</span>
                </div>
            </div>
        </div>

        <div class="messages">
            $messagesHtml
        </div>

        <div class="footer">
            <p>Documento gerado automaticamente pelo Sistema SuportIA</p>
            <p>Data da exportação: $exportedAt</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function sanitizeFilename(string $filename): string
    {
        // Remove caracteres inválidos
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        // Remove múltiplos underscores
        $filename = preg_replace('/_+/', '_', $filename);

        // Limita o comprimento
        return substr($filename ?: 'atendimento', 0, 100);
    }
}
