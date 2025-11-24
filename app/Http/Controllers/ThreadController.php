<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\ThreadResource;
use Illuminate\Http\JsonResponse;
use IncadevUns\CoreDomain\Models\Forum as ModelsForum;
use IncadevUns\CoreDomain\Models\Thread as ModelsThread;

class ThreadController extends Controller
{
    /**
     * Helper para obtener el user_id del request
     */
    private function getUserId(Request $request): ?int
    {
        $userId = $request->query('user_id') ?? $request->header('X-User-Id');
        return $userId ? (int) $userId : null;
    }

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
        $userId = $this->getUserId($request);
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'ID de usuario no proporcionado'
            ], 400);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|min:10',
        ]);

        $forum = ModelsForum::findOrFail($forumId);

        $thread = ModelsThread::create([
            'user_id' => $userId,
            'forum_id' => $forum->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        return response()->json(new ThreadResource($thread->load(['user', 'forum'])), 201);
    }

    public function show(Request $request, ModelsThread $thread): JsonResponse
    {
        $userId = $this->getUserId($request);
        
        $thread->load(['user', 'forum', 'votes']);
        $thread->loadCount(['comments', 'votes']);
        
        // Calcular vote_score y user_vote manualmente
        $voteScore = $thread->votes->sum('value');
        $userVote = null;
        
        if ($userId) {
            $vote = $thread->votes->where('user_id', $userId)->first();
            $userVote = $vote ? $vote->value : null;
        }
        
        $resource = new ThreadResource($thread);
        $data = $resource->toArray($request);
        
        // Sobrescribir con los valores correctos
        $data['vote_score'] = $voteScore;
        $data['user_vote'] = $userVote;
        
        return response()->json(['data' => $data]);
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