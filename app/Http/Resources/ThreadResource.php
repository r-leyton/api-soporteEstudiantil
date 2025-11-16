<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'vote_score' => $this->votes_count ? 
                ($this->votes->where('value', 1)->count() - $this->votes->where('value', -1)->count()) : 0,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
