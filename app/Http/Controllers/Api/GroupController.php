<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $groups = Group::accessibleBy($request->user())
            ->withCount('events')
            ->orderBy('name')
            ->get();

        return response()->json([
            'groups' => GroupResource::collection($groups),
        ]);
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        $group = Group::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'color' => $request->color ?? '#3b82f6',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Group created successfully',
            'group' => new GroupResource($group),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $group = Group::withCount('events')->findOrFail($id);

        $this->authorize('view', $group);

        return response()->json([
            'group' => new GroupResource($group),
        ]);
    }

    public function update(StoreGroupRequest $request, int $id): JsonResponse
    {
        $group = Group::findOrFail($id);

        $this->authorize('update', $group);

        $group->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'color' => $request->color ?? $group->color,
        ]);

        return response()->json([
            'message' => 'Group updated successfully',
            'group' => new GroupResource($group),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $group = Group::findOrFail($id);

        $this->authorize('delete', $group);

        $group->delete();

        return response()->json([
            'message' => 'Group deleted successfully',
        ]);
    }
}
