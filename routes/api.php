<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\KnowledgeBaseController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/setup/status', [AuthController::class, 'setupStatus']);
    Route::post('/setup', [AuthController::class, 'setup'])->middleware('throttle:login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');

    Route::middleware('auth')->group(function () {
        Route::get('/auth/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::prefix('chats')->group(function () {
            Route::get('/', [ChatController::class, 'index']);
            Route::post('/', [ChatController::class, 'store']);
            Route::get('/{chat}', [ChatController::class, 'show']);
            Route::post('/{chat}/messages', [ChatController::class, 'sendMessage'])->middleware('throttle:ai');
            Route::get('/{chat}/messages/{message}/attachment', [ChatController::class, 'downloadAttachment']);
            Route::post('/{chat}/export-pdf', [ChatController::class, 'exportPdf']);
            Route::delete('/{chat}', [ChatController::class, 'destroy']);
        });

        Route::prefix('documents')->group(function () {
            Route::get('/', [DocumentController::class, 'index']);
            Route::get('/{document}/download', [DocumentController::class, 'download']);
            Route::delete('/{document}', [DocumentController::class, 'destroy']);
        });

        Route::get('/knowledge-bases', [KnowledgeBaseController::class, 'index']);
        Route::post('/knowledge-bases', [KnowledgeBaseController::class, 'store'])->middleware('throttle:uploads');
        Route::post('/knowledge-bases/{document}/retry', [KnowledgeBaseController::class, 'retry'])->middleware('throttle:uploads');
        Route::delete('/knowledge-bases/{document}', [KnowledgeBaseController::class, 'destroy']);
    });
});
