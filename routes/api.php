<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\KnowledgeBaseController;
use Illuminate\Support\Facades\Route;

Route::prefix('chats')->group(function () {
    Route::get('/', [ChatController::class, 'index']);
    Route::post('/', [ChatController::class, 'store']);
    Route::get('/{chat}', [ChatController::class, 'show']);
    Route::post('/{chat}/messages', [ChatController::class, 'sendMessage']);
    Route::post('/{chat}/export-pdf', [ChatController::class, 'exportPdf']);
    Route::delete('/{chat}', [ChatController::class, 'destroy']);
});

Route::prefix('documents')->group(function () {
    Route::get('/', [DocumentController::class, 'index']);
    Route::get('/{document}/download', [DocumentController::class, 'download']);
    Route::delete('/{document}', [DocumentController::class, 'destroy']);
});

Route::get('/knowledge-bases', [KnowledgeBaseController::class, 'index']);
Route::post('/knowledge-bases', [KnowledgeBaseController::class, 'store']);
Route::delete('/knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'destroy']);

