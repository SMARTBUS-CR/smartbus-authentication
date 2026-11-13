<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UpdatePassengerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::PASSENGER) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:100', Rule::unique('users', 'email')->ignore($this->user())],
            'current_password' => [
                'required_with:email',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Hash::check($value, $this->user()->getAuthPassword())) {
                        $fail(__('validation.current_password'));
                    }
                },
            ],
        ];
    }
}
