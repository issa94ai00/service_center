<?php

namespace App\Http\Controllers\Api;

use App\Enums\CostMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\MaterialStoreRequest;
use App\Http\Requests\MaterialUpdateRequest;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use App\Models\Setting;
use App\Services\CatalogArchiveService;
use App\Services\MaterialCostService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaterialsController extends Controller
{
    public function __construct(
        protected CatalogArchiveService $archive,
        protected MaterialCostService $costs,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Material::class);

        $archived = $request->input('status') === 'archived';

        $materials = Material::query()
            ->with(['category', 'unit'])
            ->when($archived, fn (Builder $query) => $this->archive->withReferenceFlag($query->onlyTrashed(), Material::class))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->search);
                $latinDigits = strtr($search, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
                $query->where(function (Builder $q) use ($search, $latinDigits) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                    if ($latinDigits !== $search) {
                        $q->orWhere('sku', 'like', "%{$latinDigits}%")
                          ->orWhere('barcode', 'like', "%{$latinDigits}%");
                    }
                });
            })
            ->when($request->filled('category_id'), function (Builder $query) use ($request) {
                $query->where('category_id', $request->category_id);
            })
            ->when($request->boolean('low_stock'), function (Builder $query) {
                $query->whereColumn('stock_quantity', '<=', 'min_stock');
            })
            ->when($request->filled('is_active'), function (Builder $query) use ($request) {
                $query->where('is_active', (bool) $request->is_active);
            })
            ->when($archived, fn (Builder $query) => $query->latest('deleted_at'), fn (Builder $query) => $query->orderBy('name'))
            ->paginate($request->integer('per_page', 15));

        return MaterialResource::collection($materials)->additional([
            'counts' => ['archived' => Material::onlyTrashed()->count()],
        ]);
    }

    /**
     * البحث عن مادة واحدة بالرمز الممسوح.
     *
     * الماسح الضوئي يكتب الرمز ثم Enter، فالبحث هنا مطابقة تامّة لا تشابه:
     * الاستجابة إمّا مادة واحدة أو لا شيء، ولا مجال لعرض قائمة عند الصندوق.
     * يُطابَق الباركود أوّلاً ثم الـ sku، لأن محلات كثيرة تطبع رمزها الداخلي
     * باركوداً على البضاعة غير المرقّمة من مصنعها.
     */
    public function lookup(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Material::class);

        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            return response()->json(['data' => null, 'message' => __('لم يُرسل رمز للبحث.')], 422);
        }

        $latinCode = strtr($code, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);

        $material = Material::query()
            ->with(['category', 'unit'])
            ->where('is_active', true)
            ->where(function (Builder $q) use ($code, $latinCode) {
                $q->where('barcode', $code)
                    ->orWhere('sku', $code)
                    ->orWhere('barcode', $latinCode)
                    ->orWhere('sku', $latinCode);
            })
            // الباركود أوّلاً عند تعارض نادر بين باركود مادة وsku أخرى.
            ->orderByRaw('CASE WHEN barcode = ? OR barcode = ? THEN 0 ELSE 1 END', [$code, $latinCode])
            ->first();

        if (! $material) {
            // A scan of an archived item is a common, fixable surprise: say so
            // instead of "unknown code".
            $archived = Material::onlyTrashed()
                ->where(function (Builder $q) use ($code, $latinCode) {
                    $q->where('barcode', $code)
                        ->orWhere('sku', $code)
                        ->orWhere('barcode', $latinCode)
                        ->orWhere('sku', $latinCode);
                })
                ->value('name');

            return response()->json([
                'data' => null,
                'archived' => $archived !== null,
                'message' => $archived !== null
                    ? __('الرمز :code لمادة مؤرشفة «:name» — استعدها من أرشيف المواد لبيعها.', ['code' => $code, 'name' => $archived])
                    : __('لا توجد مادة فعّالة بالرمز :code.', ['code' => $code]),
            ], 404);
        }

        return response()->json(['data' => new MaterialResource($material)]);
    }

    public function store(MaterialStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Material::class);

        $data = $request->validated();
        $data['cost_method'] ??= Setting::get('inventory_cost_method', CostMethod::MANUAL->value);

        $material = Material::query()->create(array_merge($data, [
            'is_active' => (bool) $request->boolean('is_active', true),
            'stock_quantity' => 0,
        ]));
        $material->load(['category', 'unit']);

        return response()->json([
            'message' => __('Material created successfully.'),
            'data' => new MaterialResource($material),
        ], 201);
    }

    public function show(Material $material): JsonResponse
    {
        $this->authorize('viewAny', Material::class);
        $material->load(['category', 'unit']);

        return response()->json(['data' => new MaterialResource($material)]);
    }

    public function update(MaterialUpdateRequest $request, Material $material): JsonResponse
    {
        $this->authorize('update', $material);
        $material->update($request->validated());
        $this->costs->seedFifoOpening($material);
        $material->load(['category', 'unit']);

        return response()->json([
            'message' => __('Material updated successfully.'),
            'data' => new MaterialResource($material),
        ]);
    }

    /**
     * Archive. The everyday delete: works even with sales and stock history,
     * and can be undone.
     */
    public function destroy(Material $material): JsonResponse
    {
        $this->authorize('delete', $material);

        $this->archive->archive($material);

        return response()->json([
            'message' => __('أُرشفت المادة «:name».', ['name' => $material->name]),
            'data' => ['id' => $material->id],
        ]);
    }

    public function restore(Material $material): JsonResponse
    {
        $this->authorize('delete', $material);

        $this->archive->restore($material);
        $material->load(['category', 'unit']);

        return response()->json([
            'message' => __('استُعيدت المادة «:name».', ['name' => $material->name]),
            'data' => new MaterialResource($material),
        ]);
    }

    /** Permanent removal of an archived material nothing ever referred to. */
    public function forceDelete(Material $material): JsonResponse
    {
        $this->authorize('delete', $material);

        $this->archive->forceDelete($material);

        return response()->json(['message' => __('حُذفت المادة «:name» نهائياً.', ['name' => $material->name])]);
    }
}
