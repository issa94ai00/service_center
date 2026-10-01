<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceStoreRequest;
use App\Http\Requests\ServiceUpdateRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\CatalogArchiveService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServicesController extends Controller
{
    public function __construct(protected CatalogArchiveService $archive) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Service::class);

        $archived = $request->input('status') === 'archived';

        $services = Service::query()
            ->with('unit')
            ->when($archived, fn (Builder $query) => $this->archive->withReferenceFlag($query->onlyTrashed(), Service::class))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $query->where('name', 'like', "%{$request->search}%");
            })
            ->when($request->filled('type'), function (Builder $query) use ($request) {
                $query->where('type', $request->type);
            })
            ->when($request->filled('is_active'), function (Builder $query) use ($request) {
                $query->where('is_active', (bool) $request->is_active);
            })
            ->when($archived, fn (Builder $query) => $query->latest('deleted_at'), fn (Builder $query) => $query->orderBy('name'))
            ->paginate($request->integer('per_page', 15));

        return ServiceResource::collection($services)->additional([
            'counts' => ['archived' => Service::onlyTrashed()->count()],
        ]);
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

    /**
     * Archive. The everyday delete: works even when invoices use the service,
     * and can be undone.
     */
    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $this->archive->archive($service);

        return response()->json([
            'message' => __('أُرشفت الخدمة «:name».', ['name' => $service->name]),
            'data' => ['id' => $service->id],
        ]);
    }

    public function restore(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $this->archive->restore($service);
        $service->load('unit');

        return response()->json([
            'message' => __('استُعيدت الخدمة «:name».', ['name' => $service->name]),
            'data' => new ServiceResource($service),
        ]);
    }

    /** Permanent removal of an archived service no invoice ever used. */
    public function forceDelete(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $this->archive->forceDelete($service);

        return response()->json(['message' => __('حُذفت الخدمة «:name» نهائياً.', ['name' => $service->name])]);
    }
}
