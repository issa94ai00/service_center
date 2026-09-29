<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaterialStoreRequest;
use App\Http\Requests\MaterialUpdateRequest;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MaterialsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Material::class);

        $materials = Material::query()
            ->with(['category', 'unit'])
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $query->where(function (Builder $q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                        ->orWhere('sku', 'like', "%{$request->search}%")
                        ->orWhere('barcode', 'like', "%{$request->search}%");
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
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return MaterialResource::collection($materials);
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

        $material = Material::query()
            ->with(['category', 'unit'])
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->where('barcode', $code)->orWhere('sku', $code))
            // الباركود أوّلاً عند تعارض نادر بين باركود مادة وsku أخرى.
            ->orderByRaw('CASE WHEN barcode = ? THEN 0 ELSE 1 END', [$code])
            ->first();

        if (! $material) {
            return response()->json([
                'data' => null,
                'message' => __('لا توجد مادة فعّالة بالرمز :code.', ['code' => $code]),
            ], 404);
        }

        return response()->json(['data' => new MaterialResource($material)]);
    }

    public function store(MaterialStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Material::class);

        $material = Material::query()->create(array_merge($request->validated(), [
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
        $material->load(['category', 'unit']);

        return response()->json([
            'message' => __('Material updated successfully.'),
            'data' => new MaterialResource($material),
        ]);
    }

    public function destroy(Material $material): JsonResponse
    {
        $this->authorize('delete', $material);

        $referenced = DB::table('stock_movements')->where('material_id', $material->id)->exists()
            || DB::table('sales_invoice_items')->where('material_id', $material->id)->exists()
            || DB::table('purchase_invoice_items')->where('material_id', $material->id)->exists();

        if ($referenced) {
            return response()->json([
                'message' => __('This material has stock or invoice history and cannot be deleted.'),
            ], 422);
        }

        $material->delete();

        return response()->json(['message' => __('Material deleted successfully.')]);
    }
}
