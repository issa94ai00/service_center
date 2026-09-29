<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaterialCategoryStoreRequest;
use App\Http\Requests\MaterialCategoryUpdateRequest;
use App\Http\Resources\MaterialCategoryResource;
use App\Models\MaterialCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaterialCategoriesController extends Controller
{
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MaterialCategory::class);

        return MaterialCategoryResource::collection(
            MaterialCategory::query()->where('is_active', true)->orderBy('name')->get()
        );
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MaterialCategory::class);

        $categories = MaterialCategory::query()
            ->withCount('materials')
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $query->where('name', 'like', "%{$request->search}%");
            })
            ->when($request->filled('is_active'), function (Builder $query) use ($request) {
                $query->where('is_active', (bool) $request->is_active);
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return MaterialCategoryResource::collection($categories);
    }

    public function store(MaterialCategoryStoreRequest $request): JsonResponse
    {
        $this->authorize('create', MaterialCategory::class);

        $category = MaterialCategory::query()->create(array_merge($request->validated(), [
            'is_active' => (bool) $request->boolean('is_active', true),
        ]));
        $category->loadCount('materials');

        return response()->json([
            'message' => __('Category created successfully.'),
            'data' => new MaterialCategoryResource($category),
        ], 201);
    }

    public function show(MaterialCategory $material_category): JsonResponse
    {
        $this->authorize('viewAny', MaterialCategory::class);
        $material_category->loadCount('materials');

        return response()->json(['data' => new MaterialCategoryResource($material_category)]);
    }

    public function update(MaterialCategoryUpdateRequest $request, MaterialCategory $material_category): JsonResponse
    {
        $this->authorize('update', $material_category);
        $material_category->update($request->validated());
        $material_category->loadCount('materials');

        return response()->json([
            'message' => __('Category updated successfully.'),
            'data' => new MaterialCategoryResource($material_category),
        ]);
    }

    public function destroy(MaterialCategory $material_category): JsonResponse
    {
        $this->authorize('delete', $material_category);

        if ($material_category->materials()->exists() || $material_category->children()->exists()) {
            return response()->json([
                'message' => __('This category contains materials or sub categories and cannot be deleted.'),
            ], 422);
        }

        $material_category->delete();

        return response()->json(['message' => __('Category deleted successfully.')]);
    }
}
