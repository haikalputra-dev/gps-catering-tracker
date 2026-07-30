<?php

declare(strict_types=1);

namespace App\Http\Requests\Device;

use App\Domain\Identity\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');

        return $user !== null && $user->role === UserRole::Owner;
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');

        $this->merge([
            'notes' => $notes === null ? null : trim((string) $notes),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'courier_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query): void {
                    $query
                        ->where('role', UserRole::Courier->value)
                        ->where('is_active', true);
                }),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Fetch the target courier User for the controller.
     */
    public function courier(): User
    {
        /** @var User $user */
        $user = User::query()->findOrFail((int) $this->validated('courier_id'));

        return $user;
    }
}
