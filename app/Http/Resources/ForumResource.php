<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Buscar el creador si existe user_create
        $creator = null;
        if ($this->user_create) {
            $user = User::find($this->user_create);
            if ($user) {
                $creator = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => $this->url_img,
            'user_id' => $this->user_create,
            'user' => $creator,
            'threads_count' => $this->whenCounted('threads'),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
