<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\PassengerAccountDeletionService;
use Illuminate\Foundation\Http\FormRequest;

class DeletePassengerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && app(PassengerAccountDeletionService::class)->canDelete($user);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => [
                'bail',
                'required',
                'string',
                'current_password:sanctum',
            ],
            'confirm_deletion' => [
                'required',
                'boolean',
                'accepted',
            ],
        ];
    }

    /**
     * Validate deletion credentials exclusively from the JSON request body.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return $this->json()->all();
    }
}
