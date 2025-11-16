<?php

namespace App\Http\Controllers;

use App\Http\Resources\VoteResource;
use App\Models\Comment;
use App\Models\Thread;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use IncadevUns\CoreDomain\Models\Comment as ModelsComment;
use IncadevUns\CoreDomain\Models\Thread as ModelsThread;
use IncadevUns\CoreDomain\Models\Vote as ModelsVote;

class VoteController extends Controller
{
    public function voteThread(Request $request, $threadId): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required|in:1,-1',
        ]);

        $thread = ModelsThread::findOrFail($threadId);

        $vote = ModelsVote::updateOrCreate(
            [
                
                'votable_id' => $thread->id,
                'votable_type' => ModelsThread::class,
            ],
            [
                'value' => $validated['value'],
            ]
        );

        // Recargar con relaciones
        $vote->load(['user']);

        return response()->json([
            'message' => 'Voto registrado correctamente',
            'vote' => new VoteResource($vote)
        ]);
    }

    public function voteComment(Request $request, $commentId): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required|in:1,-1',
        ]);

        $comment = ModelsComment::findOrFail($commentId);

        $vote = ModelsVote::updateOrCreate(
            [
                'votable_id' => $comment->id,
                'votable_type' => ModelsComment::class,
            ],
            [
                'value' => $validated['value'],
            ]
        );

        // Recargar con relaciones
        $vote->load(['user']);

        return response()->json([
            'message' => 'Voto registrado correctamente',
            'vote' => new VoteResource($vote)
        ]);
    }

    public function getThreadVotes($threadId): JsonResponse
    {
        $thread = ModelsThread::findOrFail($threadId);
        
        $positiveVotes = $thread->votes()->where('value', 1)->count();
        $negativeVotes = $thread->votes()->where('value', -1)->count();
        
        $userVote = null;

        return response()->json([
            'thread_id' => $thread->id,
            'positive_votes' => $positiveVotes,
            'negative_votes' => $negativeVotes,
            'total_score' => $positiveVotes - $negativeVotes,
            'user_vote' => $userVote ? $userVote->value : null,
        ]);
    }

    public function getCommentVotes($commentId): JsonResponse
    {
        $comment = ModelsComment::findOrFail($commentId);
        
        $positiveVotes = $comment->votes()->where('value', 1)->count();
        $negativeVotes = $comment->votes()->where('value', -1)->count();
        
        $userVote = null;

        return response()->json([
            'comment_id' => $comment->id,
            'positive_votes' => $positiveVotes,
            'negative_votes' => $negativeVotes,
            'total_score' => $positiveVotes - $negativeVotes,
            'user_vote' => $userVote ? $userVote->value : null,
        ]);
    }
}