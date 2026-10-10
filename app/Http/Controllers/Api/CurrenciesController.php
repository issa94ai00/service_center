<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CurrencyRequest;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Expense;
use App\Models\Payment;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * العملات المُدارة وأسعار صرفها مقابل الدولار.
 *
 * الدولار هو العملة الأساسية: سعره 1 دائماً ولا يُعطَّل ولا يُحذف. بقية
 * العملات تُضاف وتُسعَّر هنا، وكل تغيير لسعر يُسجَّل في سجلّه.
 */
class CurrenciesController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    /** The active currencies, for the pickers on the payment and expense forms. */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => CurrencyResource::collection(Currency::query()->active()->ordered()->get()),
        ]);
    }

    public function index(): JsonResponse
    {
        $currencies = Currency::query()
            ->ordered()
            ->with(['rates' => fn ($q) => $q->limit(1)])
            ->get()
            ->each(fn (Currency $c) => $c->setAttribute('usage_count', $this->usage($c)));

        return response()->json(['data' => CurrencyResource::collection($currencies)]);
    }

    public function store(CurrencyRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var Currency $currency */
        $currency = Currency::query()->create([
            ...$data,
            'decimals' => $data['decimals'] ?? 2,
            'is_base' => false,
        ]);

        CurrencyRate::query()->create([
            'currency_id' => $currency->id,
            'previous_rate' => null,
            'exchange_rate' => $currency->exchange_rate,
            'changed_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => __('تمت إضافة العملة.'),
            'data' => new CurrencyResource($currency->load(['rates' => fn ($q) => $q->limit(1)])),
        ], 201);
    }

    public function update(CurrencyRequest $request, Currency $currency): JsonResponse
    {
        $data = $request->validated();

        if ($currency->is_base) {
            // The books are kept in it: its rate is 1 by definition, and
            // switching it off would leave every amount without a unit.
            throw ValidationException::withMessages([
                'code' => __('الدولار هو العملة الأساسية للنظام ولا يُعدَّل.'),
            ]);
        }

        if (array_key_exists('exchange_rate', $data)) {
            $this->currencies->updateRate($currency, (string) $data['exchange_rate'], auth()->id());
            unset($data['exchange_rate']);
        }

        $currency->update($data);

        return response()->json([
            'message' => __('تم حفظ العملة.'),
            'data' => new CurrencyResource($currency->fresh()->load(['rates' => fn ($q) => $q->limit(1)])),
        ]);
    }

    public function destroy(Currency $currency): JsonResponse
    {
        if ($currency->is_base) {
            throw ValidationException::withMessages(['code' => __('الدولار هو العملة الأساسية للنظام ولا يُحذف.')]);
        }

        if ($this->usage($currency) > 0) {
            throw ValidationException::withMessages([
                'code' => __('العملة مستعملة في سندات مسجّلة — عطّلها بدلاً من حذفها.'),
            ]);
        }

        $currency->delete();

        return response()->json(['message' => __('تم حذف العملة.')]);
    }

    public function rates(Currency $currency): JsonResponse
    {
        $rates = $currency->rates()->with('changer:id,name')->limit(50)->get();

        return response()->json([
            'data' => $rates->map(fn (CurrencyRate $r) => [
                'id' => $r->id,
                'previous_rate' => $r->previous_rate,
                'exchange_rate' => $r->exchange_rate,
                'changed_by_name' => $r->changer?->name,
                'created_at' => $r->created_at?->toISOString(),
            ]),
        ]);
    }

    private function usage(Currency $currency): int
    {
        return Payment::query()->where('currency_code', $currency->code)->count()
            + Expense::query()->where('currency_code', $currency->code)->count();
    }
}
