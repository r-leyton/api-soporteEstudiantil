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
Route::apiResource('forums', ForumController::class);
Route::get('/forums/{forumId}/threads', [ThreadController::class, 'indexByForum']);

// Rutas de Hilos
Route::apiResource('threads', ThreadController::class)->except(['store']);
Route::post('/forums/{forumId}/threads', [ThreadController::class, 'store']);
Route::get('/users/{userId}/threads', [ThreadController::class, 'indexByUser']);

// Rutas de Comentarios
Route::apiResource('comments', CommentController::class)->except(['store']);
Route::get('/threads/{threadId}/comments', [CommentController::class, 'indexByThread']);
Route::post('/threads/{threadId}/comments', [CommentController::class, 'store']);
Route::get('/users/{userId}/comments', [CommentController::class, 'indexByUser']);

// Rutas de Votos
Route::post('/threads/{threadId}/votes', [VoteController::class, 'voteThread']);
Route::post('/comments/{commentId}/votes', [VoteController::class, 'voteComment']);
Route::get('/threads/{threadId}/votes', [VoteController::class, 'getThreadVotes']);
Route::get('/comments/{commentId}/votes', [VoteController::class, 'getCommentVotes']);