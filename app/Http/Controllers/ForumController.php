<?php

namespace App\Http\Controllers;

use App\Http\Resources\ForumResource;
use IncadevUns\CoreDomain\CoreDomain\Models\Forum;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use IncadevUns\CoreDomain\Models\Forum as ModelsForum;

class ForumController extends Controller
{
    public function index(Request $request)
    {
        $query = ModelsForum::query()->withCount('threads');
        
        // Filtro por búsqueda
        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
        }
        
        // Orden por defecto
        $query->orderBy('created_at', 'desc');
        
        // Paginación
        $perPage = $request->get('per_page', 15);
        $forums = $query->paginate($perPage);
        
        return ForumResource::collection($forums);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:forums,name',
            'description' => 'required|string|min:10',
        ]);

        $forum = ModelsForum::create([$validated]);

        return response()->json(new ForumResource($forum), 201);
    }

    public function show(ModelsForum $forum): ForumResource
    {
        return new ForumResource($forum);
    }

    public function update(Request $request, ModelsForum $forum): ForumResource
    {
        // Autorización: solo el propietario o moderador puede actualizar
        // $this->authorize('update', $forum);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:forums,name,' . $forum->id,
            'description' => 'sometimes|string|min:10',
        ]);

        $forum->update($validated);

        return new ForumResource($forum->fresh());
    }

    public function destroy(ModelsForum $forum): JsonResponse
    {
        // Autorización: solo el propietario puede eliminar
        // $this->authorize('delete', $forum);
        
        $forum->delete();

        return response()->json([
            'message' => 'Foro eliminado correctamente'
        ], 204);
    }
}