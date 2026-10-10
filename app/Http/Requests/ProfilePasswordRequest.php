<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Changing one's own password: the current password is re-checked so a
 * walked-away-from session cannot be hijacked into a new password.
 */
class ProfilePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)],
            'logout_others' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password' => 'كلمة المرور الحالية',
            'password' => 'كلمة المرور الجديدة',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
            'password.different' => 'كلمة المرور الجديدة يجب أن تختلف عن الحالية.',
            'password.confirmed' => 'تأكيد كلمة المرور لا يطابق كلمة المرور الجديدة.',
        ];
    }
}
