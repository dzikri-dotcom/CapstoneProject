<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Kelola pos jaga. Perubahan hanya berlaku untuk jadwal yang di-generate setelahnya.
 */
class PostController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PostResource::collection(Post::query()->orderBy('id')->get());
    }

    public function store(PostRequest $request): JsonResponse
    {
        $post = Post::query()->create($request->validated());

        return (new PostResource($post->refresh()))
            ->additional(['message' => "{$post->name} berhasil ditambahkan."])
            ->response()
            ->setStatusCode(201);
    }

    public function update(PostRequest $request, Post $post): PostResource
    {
        $post->update($request->validated());

        return (new PostResource($post->refresh()))->additional(['message' => "{$post->name} berhasil diperbarui."]);
    }

    public function destroy(Post $post): JsonResponse
    {
        $post->delete();

        return response()->json(['message' => "{$post->name} berhasil dihapus."]);
    }
}
