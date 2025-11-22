<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\VoteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'message' => '¡API funcionando correctamente!',
        'status' => 'success',
        'timestamp' => now()
    ]);
});

// Rutas públicas (solo lectura)
Route::get('/forums', [ForumController::class, 'index']);
Route::get('/forums/{forum}', [ForumController::class, 'show']);
Route::get('/forums/{forumId}/threads', [ThreadController::class, 'indexByForum']);
Route::get('/threads/{thread}', [ThreadController::class, 'show']);
Route::get('/threads/{threadId}/comments', [CommentController::class, 'indexByThread']);
Route::get('/threads/{threadId}/votes', [VoteController::class, 'getThreadVotes']);
Route::get('/comments/{commentId}/votes', [VoteController::class, 'getCommentVotes']);

// Rutas protegidas (requieren autenticación)
Route::middleware('auth:sanctum')->group(function () {
    // Forums (crear, editar, eliminar)
    Route::post('/forums', [ForumController::class, 'store']);
    Route::put('/forums/{forum}', [ForumController::class, 'update']);
    Route::delete('/forums/{forum}', [ForumController::class, 'destroy']);

    // Threads (crear, editar, eliminar)
    Route::post('/forums/{forumId}/threads', [ThreadController::class, 'store']);
    Route::put('/threads/{thread}', [ThreadController::class, 'update']);
    Route::delete('/threads/{thread}', [ThreadController::class, 'destroy']);
    Route::get('/users/{userId}/threads', [ThreadController::class, 'indexByUser']);

    // Comments (crear, editar, eliminar)
    Route::post('/threads/{threadId}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
    Route::get('/users/{userId}/comments', [CommentController::class, 'indexByUser']);

    // Votes (crear/toggle)
    Route::post('/threads/{threadId}/votes', [VoteController::class, 'voteThread']);
    Route::post('/comments/{commentId}/votes', [VoteController::class, 'voteComment']);
});