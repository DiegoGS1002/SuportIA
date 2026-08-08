<?php

use App\Models\Document;
use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('KnowledgeBase Upload', function () {
    it('can upload a valid text file', function () {
        $file = UploadedFile::fake()->createWithContent('test.txt', 'Este é um documento de teste válido com conteúdo suficiente.');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'Documento de Teste',
            'file' => $file,
        ]);

        $response->assertStatus(201);
        expect(Document::count())->toBe(1);
    });

    it('can upload a PDF file even if it has no extractable text', function () {
        $file = UploadedFile::fake()->createWithContent('test.pdf', '%PDF-1.4 corrupted content');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'PDF Escaneado',
            'file' => $file,
        ]);

        $response->assertStatus(201);
        expect(Document::count())->toBe(1);
        expect($response->json('warning'))->toBeTruthy();
    });

    it('can upload a large file (above 10MB but under limit)', function () {
        $content = str_repeat('This is test content for a large file. ', 300000);
        $file = UploadedFile::fake()->createWithContent('large.txt', $content);

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'Arquivo Grande',
            'file' => $file,
        ]);

        $response->assertStatus(201);
        expect(Document::count())->toBe(1);
    });

    it('rejects files with invalid mime type', function () {
        $file = UploadedFile::fake()->create('test.exe', 1024);

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'Arquivo Inválido',
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
        expect(Document::count())->toBe(0);
    });

    it('rejects files with very small content', function () {
        $file = UploadedFile::fake()->createWithContent('test.txt', 'abc');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'Arquivo Pequeno',
            'file' => $file,
        ]);

        $response->assertStatus(201);
        expect(Document::count())->toBe(1);
    });

    it('requires authenticated user', function () {
        $this->actingAs(null);

        $file = UploadedFile::fake()->createWithContent('test.txt', 'Conteúdo de teste.');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'Documento',
            'file' => $file,
        ]);

        $response->assertStatus(401);
    });

    it('validates software field', function () {
        $file = UploadedFile::fake()->createWithContent('test.txt', 'Conteúdo de teste.');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'invalid-software',
            'title' => 'Documento',
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('software');
    });

    it('validates title field', function () {
        $file = UploadedFile::fake()->createWithContent('test.txt', 'Conteúdo de teste.');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => '',
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');
    });

    it('stores document with correct metadata', function () {
        $fileName = 'test-document.pdf';
        $file = UploadedFile::fake()->createWithContent($fileName, 'Test content for verification.');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'Documento com Metadados',
            'file' => $file,
        ]);

        $response->assertStatus(201);

        $document = Document::first();
        expect($document->name)->toBe($fileName);
        expect($document->user_id)->toBe($this->user->id);
        expect($document->file_size)->toBeGreaterThan(0);
    });
});
