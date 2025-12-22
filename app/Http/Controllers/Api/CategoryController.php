<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::accessibleBy($request->user())
            ->withCount('events')
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'color' => $request->color ?? '#3b82f6',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => new CategoryResource($category),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $category = Category::withCount('events')->findOrFail($id);

        $this->authorize('view', $category);

        return response()->json([
            'category' => new CategoryResource($category),
        ]);
    }

    public function update(StoreCategoryRequest $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $this->authorize('update', $category);

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'color' => $request->color ?? $category->color,
        ]);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => new CategoryResource($category),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $this->authorize('delete', $category);

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
