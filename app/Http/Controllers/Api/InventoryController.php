<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryAdjustmentRequest;
use App\Http\Resources\InventoryAdjustmentResource;
use App\Models\InventoryAdjustment;
use App\Models\Material;
use App\Services\InventoryService;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventory,
        protected ReportService $reports,
    ) {}

    /**
     * Current stock levels, with the value on hand at cost.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,low,out,available'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $status = $request->input('status', 'all');

        $materials = Material::query()
            ->with(['unit:id,name_ar', 'category:id,name'])
            ->where('is_active', true)
            ->when($request->filled('search'), fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$request->search}%")
                    ->orWhere('sku', 'like', "%{$request->search}%")
            ))
            ->when($status === 'low', fn (Builder $q) => $q
                ->whereColumn('stock_quantity', '<=', 'min_stock')
                ->where('stock_quantity', '>', 0))
            ->when($status === 'out', fn (Builder $q) => $q->where('stock_quantity', '<=', 0))
            ->when($status === 'available', fn (Builder $q) => $q->whereColumn('stock_quantity', '>', 'min_stock'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => collect($materials->items())->map(fn (Material $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'sku' => $m->sku,
                'category' => $m->category?->name,
                'unit' => $m->unit?->name_ar,
                'stock_quantity' => $m->stock_quantity,
                'min_stock' => $m->min_stock,
                'cost_price' => $m->cost_price,
                'stock_value' => Money::fromDecimal($m->cost_price)
                    ->multiplyBy((float) $m->stock_quantity)->toDecimal(),
                'is_low' => $m->is_low,
                'is_out_of_stock' => (float) $m->stock_quantity <= 0,
            ]),
            'meta' => $this->meta($materials),
            'totals' => $this->stockTotals(),
        ]);
    }

    /**
     * One material with its movement history.
     */
    public function show(Request $request, Material $material): JsonResponse
    {
        $material->load(['unit:id,name_ar', 'category:id,name']);

        ['rows' => $rows, 'paginator' => $paginator] = $this->reports->stockMovements([
            'material_id' => $material->id,
            'per_page' => $request->integer('per_page', 50),
            'page' => $request->integer('page', 1),
        ]);

        return response()->json([
            'data' => [
                'material' => [
                    'id' => $material->id,
                    'name' => $material->name,
                    'sku' => $material->sku,
                    'unit' => $material->unit?->name_ar,
                    'category' => $material->category?->name,
                    'stock_quantity' => $material->stock_quantity,
                    'min_stock' => $material->min_stock,
                    'cost_price' => $material->cost_price,
                    'is_low' => $material->is_low,
                ],
                'movements' => $rows,
            ],
            'meta' => $this->meta($paginator),
        ]);
    }

    /**
     * Adjustment history.
     */
    public function adjustments(Request $request): JsonResponse
    {
        $adjustments = InventoryAdjustment::query()
            ->with(['items.material:id,name,unit_id', 'items.material.unit:id,name_ar', 'adjuster:id,name'])
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => InventoryAdjustmentResource::collection($adjustments->items()),
            'meta' => $this->meta($adjustments),
        ]);
    }

    /**
     * Record a manual stock correction. It posts immediately.
     */
    public function store(InventoryAdjustmentRequest $request): JsonResponse
    {
        $adjustment = $this->inventory->adjust($request->validated(), $request->user()->id);

        return response()->json([
            'message' => __('تم تسجيل تسوية المخزون.'),
            'data' => new InventoryAdjustmentResource($adjustment),
        ], 201);
    }

    /**
     * Reverse a posted adjustment.
     */
    public function cancel(Request $request, InventoryAdjustment $adjustment): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], ['reason.required' => 'سبب الإلغاء مطلوب.']);

        $cancelled = $this->inventory->cancel($adjustment, $request->user()->id, $validated['reason']);

        return response()->json([
            'message' => __('تم إلغاء التسوية وإرجاع الكميات.'),
            'data' => new InventoryAdjustmentResource($cancelled),
        ]);
    }

    // ---------------------------------------------------------------- internals

    /**
     * @return array<string, string>
     */
    protected function stockTotals(): array
    {
        $row = Material::query()
            ->where('is_active', true)
            ->selectRaw('COUNT(*) as materials')
            ->selectRaw('COALESCE(SUM(stock_quantity * cost_price), 0) as value')
            ->selectRaw('SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock')
            ->selectRaw('SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= min_stock THEN 1 ELSE 0 END) as low')
            ->first();

        return [
            'materials' => (string) (int) ($row?->materials ?? 0),
            'stock_value' => Money::fromDecimal($row?->value)->toDecimal(),
            'out_of_stock' => (string) (int) ($row?->out_of_stock ?? 0),
            'low' => (string) (int) ($row?->low ?? 0),
        ];
    }

    /**
     * @return array<string, int|null>
     */
    protected function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
