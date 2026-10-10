<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-]+$/', Rule::unique('roles', 'slug')],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الدور مطلوب.',
            'slug.unique' => 'المعرف البرمجي (Slug) مستخدم بالفعل لدور آخر.',
            'slug.regex' => 'المعرف البرمجي يجب أن يحتوي على أحرف إنجليزية وأرقام وشرطات فقط.',
            'permission_ids.*.exists' => 'إحدى الصلاحيات المحددة غير صالحة.',
        ];
    }
}
