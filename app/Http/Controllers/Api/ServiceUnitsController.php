<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceUnitStoreRequest;
use App\Http\Requests\ServiceUnitUpdateRequest;
use App\Http\Resources\ServiceUnitResource;
use App\Models\ServiceUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceUnitsController extends Controller
{
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ServiceUnit::class);

        return ServiceUnitResource::collection(
            ServiceUnit::query()->where('is_active', true)->orderBy('name_ar')->get()
        );
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ServiceUnit::class);

        $units = ServiceUnit::query()
            ->withCount('services', 'materials')
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $query->where(function (Builder $q) use ($request) {
                    $q->where('name_ar', 'like', "%{$request->search}%")
                        ->orWhere('name_en', 'like', "%{$request->search}%")
                        ->orWhere('symbol', 'like', "%{$request->search}%");
                });
            })
            ->when($request->filled('is_active'), function (Builder $query) use ($request) {
                $query->where('is_active', (bool) $request->is_active);
            })
            ->orderBy('name_ar')
            ->paginate($request->integer('per_page', 15));

        return ServiceUnitResource::collection($units);
    }

    public function store(ServiceUnitStoreRequest $request): JsonResponse
    {
        $this->authorize('create', ServiceUnit::class);

        $unit = ServiceUnit::query()->create(array_merge($request->validated(), [
            'is_measurable' => (bool) $request->boolean('is_measurable', true),
            'is_active' => (bool) $request->boolean('is_active', true),
        ]));
        $unit->loadCount('services', 'materials');

        return response()->json([
            'message' => __('Service unit created successfully.'),
            'data' => new ServiceUnitResource($unit),
        ], 201);
    }

    public function show(ServiceUnit $service_unit): JsonResponse
    {
        $this->authorize('viewAny', ServiceUnit::class);
        $service_unit->loadCount('services', 'materials');

        return response()->json(['data' => new ServiceUnitResource($service_unit)]);
    }

    public function update(ServiceUnitUpdateRequest $request, ServiceUnit $service_unit): JsonResponse
    {
        $this->authorize('update', $service_unit);
        $service_unit->update($request->validated());
        $service_unit->loadCount('services', 'materials');

        return response()->json([
            'message' => __('Service unit updated successfully.'),
            'data' => new ServiceUnitResource($service_unit),
        ]);
    }

    public function destroy(ServiceUnit $service_unit): JsonResponse
    {
        $this->authorize('delete', $service_unit);

        if ($service_unit->services()->exists() || $service_unit->materials()->exists()) {
            return response()->json([
                'message' => __('This unit is used by services or materials and cannot be deleted.'),
            ], 422);
        }

        $service_unit->delete();

        return response()->json(['message' => __('Service unit deleted successfully.')]);
    }
}
