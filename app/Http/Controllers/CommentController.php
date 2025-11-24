<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommentResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use IncadevUns\CoreDomain\Models\Comment as ModelsComment;
use IncadevUns\CoreDomain\Models\Thread as ModelsThread;

class CommentController extends Controller
{
    /**
     * Helper para obtener el user_id del request
     */
    private function getUserId(Request $request): ?int
    {
        $userId = $request->query('user_id') ?? $request->header('X-User-Id');
        return $userId ? (int) $userId : null;
    }

    public function indexByThread(Request $request, $threadId)
    {
        $thread = ModelsThread::findOrFail($threadId);

        // Devolver TODOS los comentarios del thread (el frontend construye el árbol)
        $query = ModelsComment::where('thread_id', $threadId)
                       ->with(['user', 'votes'])
                       ->withCount(['votes']);

        $query->orderBy('created_at', 'asc');

        // Sin paginación para permitir construcción del árbol completo
        $comments = $query->get();

        return CommentResource::collection($comments);
    }

    public function indexByUser(Request $request, $userId)
    {
        $query = ModelsComment::where('user_id', $userId)
                       ->with(['user', 'thread'])
                       ->withCount(['replies', 'votes']);
        
        if ($request->has('thread_id') && $request->thread_id) {
            $query->where('thread_id', $request->thread_id);
        }
        
        $query->orderBy('created_at', 'desc');
        
        $perPage = $request->get('per_page', 15);
        $comments = $query->paginate($perPage);
        
        return CommentResource::collection($comments);
    }

    public function store(Request $request, $threadId): JsonResponse
    {
        $userId = $this->getUserId($request);
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'ID de usuario no proporcionado'
            ], 400);
        }

        $validated = $request->validate([
            'body' => 'required|string|min:1',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        $thread = ModelsThread::findOrFail($threadId);

        // Verificar que el parent_id pertenece al mismo thread
        if (isset($validated['parent_id'])) {
            $parentComment = ModelsComment::findOrFail($validated['parent_id']);
            if ($parentComment->thread_id != $threadId) {
                return response()->json([
                    'message' => 'El comentario padre no pertenece a este hilo'
                ], 422);
            }
        }

        $comment = ModelsComment::create([
            'user_id' => $userId,
            'thread_id' => $thread->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'body' => $validated['body'],
        ]);

        return response()->json(new CommentResource($comment->load(['user'])), 201);
    }

    public function show(ModelsComment $comment): CommentResource
    {
        return new CommentResource($comment->load(['user', 'thread', 'replies.user']));
    }

    public function update(Request $request, ModelsComment $comment): CommentResource
    {
        // $this->authorize('update', $comment);
        
        $validated = $request->validate([
            'body' => 'required|string|min:5',
        ]);

        $comment->update($validated);

        return new CommentResource($comment->load(['user']));
    }

    public function destroy(ModelsComment $comment): JsonResponse
    {
        // $this->authorize('delete', $comment);
        
        $comment->delete();

        return response()->json([
            'message' => 'Comentario eliminado correctamente'
        ], 204);
    }
}