<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('users.view') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.name' => ['sometimes', 'string', 'max:100'],
            'filter.email' => ['sometimes', 'string', 'max:100'],
            'filter.role' => ['sometimes', Rule::enum(UserRole::class)],
            'page' => ['sometimes', 'array'],
            'page.number' => ['sometimes', 'integer', 'min:1'],
            'page.size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string'],
            'include' => ['sometimes', 'string'],
        ];
    }
}
