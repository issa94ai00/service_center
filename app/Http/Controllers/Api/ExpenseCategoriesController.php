<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseCategoryStoreRequest;
use App\Http\Requests\ExpenseCategoryUpdateRequest;
use App\Http\Resources\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoriesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $categories = ExpenseCategory::query()
            ->when($request->filled('is_active'), fn (Builder $q) => $q->where('is_active', $request->boolean('is_active')))
            ->withCount('expenses')
            // الأكثر استعمالاً أولاً: أزرار الشاشة تُرتَّب بهذا، فيقع ما يُصرف
            // عليه كل يوم تحت الإبهام بدل أن يُبحث عنه أبجدياً.
            ->orderByDesc('expenses_count')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => ExpenseCategoryResource::collection($categories)]);
    }

    public function store(ExpenseCategoryStoreRequest $request): JsonResponse
    {
        $this->authorize('create', ExpenseCategory::class);

        /** @var ExpenseCategory $category */
        $category = ExpenseCategory::query()->create($request->validated());

        return response()->json([
            'message' => __('تم إضافة باب الصرف.'),
            'data' => new ExpenseCategoryResource($category),
        ], 201);
    }

    public function update(ExpenseCategoryUpdateRequest $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $this->authorize('update', $expenseCategory);

        $expenseCategory->update($request->validated());
        $expenseCategory->loadCount('expenses');

        return response()->json([
            'message' => __('تم تعديل باب الصرف.'),
            'data' => new ExpenseCategoryResource($expenseCategory),
        ]);
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        $this->authorize('delete', $expenseCategory);

        // باب صُرف تحته مال لا يُحذف: حذفه يترك سنداتٍ بلا باب ومجاميع لا
        // تُقرأ. التعطيل يُخرجه من الاختيار ويُبقي تاريخه.
        if ($expenseCategory->expenses()->exists()) {
            return response()->json([
                'message' => __('تحت هذا الباب مصاريف مسجّلة، ولا يمكن حذفه. يمكن تعطيله بدلاً من ذلك.'),
            ], 422);
        }

        $expenseCategory->delete();

        return response()->json(['message' => __('تم حذف باب الصرف.')]);
    }
}
