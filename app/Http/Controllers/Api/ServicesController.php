<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceStoreRequest;
use App\Http\Requests\ServiceUpdateRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServicesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Service::class);

        $services = Service::query()
            ->with('unit')
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $query->where('name', 'like', "%{$request->search}%");
            })
            ->when($request->filled('type'), function (Builder $query) use ($request) {
                $query->where('type', $request->type);
            })
            ->when($request->filled('is_active'), function (Builder $query) use ($request) {
                $query->where('is_active', (bool) $request->is_active);
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return ServiceResource::collection($services);
    }

    public function store(ServiceStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Service::class);

        $service = Service::query()->create(array_merge($request->validated(), [
            'is_active' => (bool) $request->boolean('is_active', true),
        ]));
        $service->load('unit');

        return response()->json([
            'message' => __('Service created successfully.'),
            'data' => new ServiceResource($service),
        ], 201);
    }

    public function show(Service $service): JsonResponse
    {
        $this->authorize('viewAny', Service::class);
        $service->load('unit');

        return response()->json(['data' => new ServiceResource($service)]);
    }

    public function update(ServiceUpdateRequest $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);
        $service->update($request->validated());
        $service->load('unit');

        return response()->json([
            'message' => __('Service updated successfully.'),
            'data' => new ServiceResource($service),
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        if ($service->invoiceItems()->exists()) {
            return response()->json([
                'message' => __('This service is used in invoices and cannot be deleted.'),
            ], 422);
        }

        $service->delete();

        return response()->json(['message' => __('Service deleted successfully.')]);
    }
}
