<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            // A length policy belongs on password *creation*; enforcing it here
            // just locks out accounts whose password predates the rule.
            'password' => ['required', 'string'],
            // Remember me: keep the session cookie across browser restarts.
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
