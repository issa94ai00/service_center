<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseStoreRequest;
use App\Http\Requests\ExpenseUpdateRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class ExpensesController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Expense::class);

        $expenses = $this->filtered($request)
            ->with(['category:id,name', 'recorder:id,name'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        // المجاميع تُحسب على الفلتر نفسه لا على الصفحة: صفحة من خمس عشرة
        // حركة لا تقول كم صُرف، والرقم الذي يهمّ هو مجموع ما رشّحه المستخدم.
        return ExpenseResource::collection($expenses)
            ->additional(['summary' => $this->summary($request)]);
    }

    public function store(ExpenseStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $expense = $this->expenses->record($request->validated(), auth()->id());
        $expense->load(['category:id,name', 'recorder:id,name']);

        return response()->json([
            'message' => __('تم تسجيل المصروف :no.', ['no' => $expense->expense_no]),
            'data' => new ExpenseResource($expense),
        ], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        $expense->load(['category:id,name', 'recorder:id,name', 'canceller:id,name']);

        return response()->json(['data' => new ExpenseResource($expense)]);
    }

    public function update(ExpenseUpdateRequest $request, Expense $expense): JsonResponse
    {
        $this->authorize('update', $expense);

        $expense = $this->expenses->update($expense, $request->validated());
        $expense->load(['category:id,name', 'recorder:id,name']);

        return response()->json([
            'message' => __('تم تعديل المصروف.'),
            'data' => new ExpenseResource($expense),
        ]);
    }

    public function cancel(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('cancel', $expense);

        $request->validate(
            ['reason' => ['required', 'string', 'min:3', 'max:255']],
            ['reason.required' => 'الإلغاء يتطلب سبباً مكتوباً.', 'reason.min' => 'الإلغاء يتطلب سبباً واضحاً.']
        );

        $expense = $this->expenses->cancel($expense, auth()->id(), $request->string('reason')->trim()->value());
        $expense->load(['category:id,name', 'recorder:id,name']);

        return response()->json([
            'message' => __('تم إلغاء المصروف.'),
            'data' => new ExpenseResource($expense),
        ]);
    }

    // ---------------------------------------------------------------- internals

    /**
     * الترشيح المشترك بين القائمة ومجاميعها، فلا يفترقان أبداً.
     */
    private function filtered(Request $request): Builder
    {
        return Expense::query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $term = "%{$request->search}%";
                $query->where(function (Builder $q) use ($term) {
                    $q->where('expense_no', 'like', $term)
                        ->orWhere('payee', 'like', $term)
                        ->orWhere('notes', 'like', $term);
                });
            })
            ->when($request->filled('expense_category_id'), fn (Builder $q) => $q->where('expense_category_id', $request->integer('expense_category_id')))
            ->when($request->filled('payment_method'), fn (Builder $q) => $q->where('payment_method', $request->payment_method))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->status))
            ->when($request->filled('from'), fn (Builder $q) => $q->where('expense_date', '>=', DateRange::start($request->from)))
            ->when($request->filled('to'), fn (Builder $q) => $q->where('expense_date', '<=', DateRange::end($request->to)));
    }

    /**
     * ثلاثة أرقام يحتاجها من يقف عند الدرج: ما صُرف اليوم، وما صُرف هذا الشهر،
     * ومجموع ما يراه على الشاشة الآن — ثم أين ذهب، باباً باباً.
     *
     * الملغى خارج كل مجموع: سند أُلغي هو ورقة في السجل لا مال خرج.
     *
     * @return array<string, mixed>
     */
    private function summary(Request $request): array
    {
        $filtered = $this->filtered($request)->counted()
            ->selectRaw('COUNT(*) as entries, COALESCE(SUM(amount), 0) as amount')
            ->first();

        $today = Carbon::today()->format('Y-m-d');

        return [
            'filtered' => [
                'amount' => Money::fromDecimal($filtered?->amount)->toDecimal(),
                'count' => (int) ($filtered?->entries ?? 0),
            ],
            'today' => $this->totalBetween($today, $today),
            'month' => $this->totalBetween(
                Carbon::today()->startOfMonth()->format('Y-m-d'),
                Carbon::today()->endOfMonth()->format('Y-m-d'),
            ),
            'by_category' => $this->byCategory($request),
        ];
    }

    private function totalBetween(string $from, string $to): string
    {
        $amount = Expense::query()->counted()
            ->whereBetween('expense_date', DateRange::between($from, $to))
            ->sum('amount');

        return Money::fromDecimal($amount)->toDecimal();
    }

    /**
     * أثقل الأبواب في ما رُشِّح، لا كلّها: القائمة هنا للقراءة السريعة، ومن
     * أراد التفصيل رشّح بالباب نفسه.
     *
     * @return array<int, array<string, mixed>>
     */
    private function byCategory(Request $request): array
    {
        return $this->filtered($request)->counted()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->selectRaw('expense_categories.id, expense_categories.name')
            ->selectRaw('COUNT(*) as entries, COALESCE(SUM(expenses.amount), 0) as amount')
            ->orderByDesc('amount')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'count' => (int) $row->entries,
                'amount' => Money::fromDecimal($row->amount)->toDecimal(),
            ])
            ->all();
    }
}
