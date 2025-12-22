<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('categories')
            ->orderBy('name');

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'users' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'is_active' => $request->is_active ?? true,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => new UserResource($user),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with('categories')->findOrFail($id);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function update(StoreUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->has('is_active')) {
            $data['is_active'] = $request->is_active;
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => new UserResource($user->load('categories')),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Don't delete, just deactivate
        $user->update(['is_active' => false]);

        return response()->json([
            'message' => 'User deactivated successfully',
        ]);
    }

    public function assignCategories(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.permission' => 'required|in:view,edit,manage',
        ]);

        $syncData = [];
        foreach ($request->categories as $category) {
            $syncData[$category['id']] = ['permission' => $category['permission']];
        }

        $user->categories()->sync($syncData);

        return response()->json([
            'message' => 'Categories assigned successfully',
            'user' => new UserResource($user->load('categories')),
        ]);
    }

    public function toggleActive(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $user->update(['is_active' => !$user->is_active]);

        if (!$user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => $user->is_active ? 'User activated' : 'User deactivated',
            'user' => new UserResource($user),
        ]);
    }
}
