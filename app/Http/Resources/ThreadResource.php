<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Usar sanctum guard (API) con fallback a default
        $userId = auth('sanctum')->id() ?? auth()->id();
        $userVote = null;
        $voteScore = 0;

        if ($this->relationLoaded('votes')) {
            // Calcular vote_score como SUM(value), no COUNT
            $voteScore = $this->votes->sum('value');

            // Obtener voto del usuario actual
            if ($userId) {
                $vote = $this->votes->where('user_id', $userId)->first();
                $userVote = $vote ? $vote->value : null;
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'user_id' => $this->user_id,
            'forum_id' => $this->forum_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'forum' => new ForumResource($this->whenLoaded('forum')),
            'comments_count' => $this->whenCounted('comments'),
            'votes_count' => $this->whenCounted('votes'),
            'vote_score' => $voteScore,
            'user_vote' => $userVote,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
