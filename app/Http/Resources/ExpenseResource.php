<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Expense */
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_no' => $this->expense_no,
            'expense_category_id' => $this->expense_category_id,
            'category' => ExpenseCategoryResource::make($this->whenLoaded('category')),
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'amount' => $this->amount,
            // `amount` is always USD; the three below say what actually changed hands.
            'currency_code' => $this->currency_code ?? 'USD',
            'exchange_rate' => $this->exchange_rate ?? '1.000000',
            'currency_amount' => $this->currency_amount ?? $this->amount,
            'payment_method' => $this->payment_method,
            'expense_date' => $this->expense_date?->format('Y-m-d'),
            'payee' => $this->payee,
            'notes' => $this->notes,
            'status' => $this->status,
            'recorded_by' => $this->recorded_by,
            'recorded_by_name' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
            'cancelled_by' => $this->cancelled_by,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at,
        ];
    }
}
