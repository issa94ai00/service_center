<?php

namespace App\Http\Requests;

use App\Services\StatementService;
use Illuminate\Foundation\Http\FormRequest;

class StatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('statements.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.StatementService::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{from: ?string, to: ?string, per_page: ?int}
     */
    public function filters(): array
    {
        return [
            'from' => $this->input('from'),
            'to' => $this->input('to'),
            'per_page' => $this->integer('per_page') ?: null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية أو مساويًا له.',
        ];
    }
}
