<?php

namespace App\Http\Controllers;

use App\Http\Resources\VoteResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use IncadevUns\CoreDomain\Models\Comment as ModelsComment;
use IncadevUns\CoreDomain\Models\Thread as ModelsThread;
use IncadevUns\CoreDomain\Models\Vote as ModelsVote;

class VoteController extends Controller
{
    /**
     * Helper para obtener el user_id del request
     */
    private function getUserId(Request $request): ?int
    {
        $userId = $request->query('user_id') ?? $request->header('X-User-Id');
        return $userId ? (int) $userId : null;
    }

    public function voteThread(Request $request, $threadId): JsonResponse
    {
        $userId = $this->getUserId($request);
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'ID de usuario no proporcionado'
            ], 400);
        }

        $validated = $request->validate([
            'value' => 'required|in:1,-1',
        ]);

        $thread = ModelsThread::findOrFail($threadId);

        // Buscar voto existente
        $existingVote = ModelsVote::where([
            'user_id' => $userId,
            'votable_id' => $thread->id,
            'votable_type' => ModelsThread::class,
        ])->first();

        // Si existe y es el mismo valor, eliminarlo (toggle off)
        if ($existingVote && $existingVote->value == $validated['value']) {
            $existingVote->delete();
            return response()->json([
                'message' => 'Voto eliminado correctamente',
                'vote' => null
            ]);
        }

        // Si no existe o es diferente valor, crear/actualizar
        $vote = ModelsVote::updateOrCreate(
            [
                'user_id' => $userId,
                'votable_id' => $thread->id,
                'votable_type' => ModelsThread::class,
            ],
            [
                'value' => $validated['value'],
            ]
        );

        $vote->load(['user']);

        return response()->json([
            'message' => 'Voto registrado correctamente',
            'vote' => new VoteResource($vote)
        ]);
    }

    public function voteComment(Request $request, $commentId): JsonResponse
    {
        $userId = $this->getUserId($request);
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'ID de usuario no proporcionado'
            ], 400);
        }

        $validated = $request->validate([
            'value' => 'required|in:1,-1',
        ]);

        $comment = ModelsComment::findOrFail($commentId);

        // Buscar voto existente
        $existingVote = ModelsVote::where([
            'user_id' => $userId,
            'votable_id' => $comment->id,
            'votable_type' => ModelsComment::class,
        ])->first();

        // Si existe y es el mismo valor, eliminarlo (toggle off)
        if ($existingVote && $existingVote->value == $validated['value']) {
            $existingVote->delete();
            return response()->json([
                'message' => 'Voto eliminado correctamente',
                'vote' => null
            ]);
        }

        // Si no existe o es diferente valor, crear/actualizar
        $vote = ModelsVote::updateOrCreate(
            [
                'user_id' => $userId,
                'votable_id' => $comment->id,
                'votable_type' => ModelsComment::class,
            ],
            [
                'value' => $validated['value'],
            ]
        );

        $vote->load(['user']);

        return response()->json([
            'message' => 'Voto registrado correctamente',
            'vote' => new VoteResource($vote)
        ]);
    }

    public function getThreadVotes(Request $request, $threadId): JsonResponse
    {
        $thread = ModelsThread::findOrFail($threadId);
        $userId = $this->getUserId($request);

        // Calcular score como SUM(value)
        $totalScore = $thread->votes()->sum('value');
        $positiveVotes = $thread->votes()->where('value', 1)->count();
        $negativeVotes = $thread->votes()->where('value', -1)->count();

        $userVote = $userId
            ? $thread->votes()->where('user_id', $userId)->first()
            : null;

        return response()->json([
            'thread_id' => $thread->id,
            'positive_votes' => $positiveVotes,
            'negative_votes' => $negativeVotes,
            'total_score' => $totalScore,
            'user_vote' => $userVote ? $userVote->value : null,
        ]);
    }

    public function getCommentVotes(Request $request, $commentId): JsonResponse
    {
        $comment = ModelsComment::findOrFail($commentId);
        $userId = $this->getUserId($request);

        // Calcular score como SUM(value)
        $totalScore = $comment->votes()->sum('value');
        $positiveVotes = $comment->votes()->where('value', 1)->count();
        $negativeVotes = $comment->votes()->where('value', -1)->count();

        $userVote = $userId
            ? $comment->votes()->where('user_id', $userId)->first()
            : null;

        return response()->json([
            'comment_id' => $comment->id,
            'positive_votes' => $positiveVotes,
            'negative_votes' => $negativeVotes,
            'total_score' => $totalScore,
            'user_vote' => $userVote ? $userVote->value : null,
        ]);
    }
}