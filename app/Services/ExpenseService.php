<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

/**
 * تسجيل المصاريف وإلغاؤها.
 *
 * المصروف لا يمسّ دفتر عميل ولا مورد ولا مخزوناً — لا شيء يُعكس عند إلغائه —
 * فكل ما تملكه هذه الخدمة هو الرقم المتسلسل وقاعدة أن الملغى لا يُمسّ بعد
 * إلغائه.
 */
class ExpenseService
{
    public function __construct(
        private readonly NumberingService $numbers,
        private readonly CurrencyService $currencies,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(array $data, int $userId): Expense
    {
        return DB::transaction(function () use ($data, $userId) {
            /** @var Expense $expense */
            $expense = Expense::query()->create([
                ...$this->payload($data),
                ...$this->currencies->resolve($data),
                'expense_no' => $this->numbers->next('EXPENSE'),
                'status' => Expense::POSTED,
                'recorded_by' => $userId,
            ]);

            return $expense;
        });
    }

    /**
     * تصحيح سند قائم.
     *
     * الرقم والحالة ومَن سجّله لا تُمسّ: التصحيح يصلح ما كُتب خطأً، ولا يحوّل
     * سنداً إلى سند آخر.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, array $data): Expense
    {
        if ($expense->isCancelled()) {
            throw new BusinessRuleException(__('المصروف الملغى لا يُعدَّل.'));
        }

        $expense->fill([
            ...$this->payload($data, partial: true),
            ...$this->currencies->resolve($data, $expense),
        ]);
        $expense->save();

        return $expense->fresh();
    }

    public function cancel(Expense $expense, int $userId, string $reason): Expense
    {
        if ($expense->isCancelled()) {
            throw new BusinessRuleException(__('المصروف ملغى أصلاً.'));
        }

        $expense->forceFill([
            'status' => Expense::CANCELLED,
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        return $expense->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, bool $partial = false): array
    {
        $fields = [
            'expense_category_id' => $data['expense_category_id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'CASH',
            'expense_date' => $data['expense_date'] ?? now()->toDateString(),
            'payee' => $data['payee'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        // في التعديل لا يُلمس إلّا ما أُرسل: حقل غائب عن الطلب يعني «كما هو»،
        // لا «امحه».
        return $partial
            ? array_intersect_key($fields, $data)
            : $fields;
    }
}
