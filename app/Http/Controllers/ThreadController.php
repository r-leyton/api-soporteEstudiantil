<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\ThreadResource;
use Illuminate\Http\JsonResponse;
use IncadevUns\CoreDomain\Models\Forum as ModelsForum;
use IncadevUns\CoreDomain\Models\Thread as ModelsThread;

class ThreadController extends Controller
{
    public function indexByForum(Request $request, $forumId)
    {
        $forum = ModelsForum::findOrFail($forumId);
        
        $query = ModelsThread::where('forum_id', $forumId)
                      ->with(['user', 'forum'])
                      ->withCount(['comments', 'votes']);
        
        // Ordenamiento
        if ($request->has('sort') && $request->sort === 'votes') {
            // Ordenar por score de votos (esto es simplificado)
            $query->withCount(['votes as positive_votes' => function($q) {
                $q->where('value', 1);
            }])->withCount(['votes as negative_votes' => function($q) {
                $q->where('value', -1);
            }])->orderByRaw('(positive_votes - negative_votes) DESC');
        } else {
            $query->orderBy('created_at', 'desc');
        }
        
        $perPage = $request->get('per_page', 15);
        $threads = $query->paginate($perPage);
        
        return ThreadResource::collection($threads);
    }

    public function indexByUser(Request $request, $userId)
    {
        $query = ModelsThread::where('user_id', $userId)
                      ->with(['user', 'forum', 'votes'])
                      ->withCount(['comments', 'votes']);

        if ($request->has('forum_id') && $request->forum_id) {
            $query->where('forum_id', $request->forum_id);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $threads = $query->paginate($perPage);

        return ThreadResource::collection($threads);
    }

    public function store(Request $request, $forumId): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|min:10',
        ]);

        $forum = ModelsForum::findOrFail($forumId);

        $thread = ModelsThread::create([
            'user_id' => auth()->id(),
            'forum_id' => $forum->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        return response()->json(new ThreadResource($thread->load(['user', 'forum'])), 201);
    }

    public function show(ModelsThread $thread): ThreadResource
    {
        return new ThreadResource($thread->load(['user', 'forum', 'votes']));
    }

    public function update(Request $request, ModelsThread $thread): ThreadResource
    {
        // $this->authorize('update', $thread);
        
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string|min:10',
        ]);

        $thread->update($validated);

        return new ThreadResource($thread->load(['user', 'forum']));
    }

    public function destroy(ModelsThread $thread): JsonResponse
    {
        // $this->authorize('delete', $thread);
        
        $thread->delete();

        return response()->json([
            'message' => 'Hilo eliminado correctamente'
        ], 204);
    }
}