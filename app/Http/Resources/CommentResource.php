<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
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
            'body' => $this->body,
            'user_id' => $this->user_id,
            'thread_id' => $this->thread_id,
            'parent_id' => $this->parent_id,
            'attachment_url' => $this->url_img,
            'user' => new UserResource($this->whenLoaded('user')),
            'thread' => new ThreadResource($this->whenLoaded('thread')),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'replies_count' => $this->whenCounted('replies'),
            'votes_count' => $this->whenCounted('votes'),
            'vote_score' => $voteScore,
            'user_vote' => $userVote,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
