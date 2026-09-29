<?php

namespace App\Http\Requests;

use App\Enums\MovementType;
use App\Services\ReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('reports.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'technician_id' => ['nullable', 'integer', 'exists:technicians,id'],
            'only_active' => ['nullable', 'boolean'],
            'type' => ['nullable', Rule::enum(MovementType::class)],
            'only_outstanding' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ReportService::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * A period is only mandatory for the summary reports, which have no
     * sensible unbounded default.
     *
     * @return array<string, mixed>
     */
    public function periodRules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'from' => $this->input('from'),
            'to' => $this->input('to'),
            'as_of' => $this->input('as_of'),
            'search' => $this->input('search'),
            'material_id' => $this->integer('material_id') ?: null,
            'technician_id' => $this->integer('technician_id') ?: null,
            'only_active' => $this->boolean('only_active'),
            'type' => $this->input('type'),
            'only_outstanding' => $this->has('only_outstanding')
                ? $this->boolean('only_outstanding')
                : true,
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
            'from.required' => 'تاريخ البداية مطلوب.',
            'to.required' => 'تاريخ النهاية مطلوب.',
        ];
    }
}
