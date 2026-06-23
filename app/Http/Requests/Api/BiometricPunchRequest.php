<?php

namespace App\Http\Requests\Api;

use App\Services\AttendancePunchService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class BiometricPunchRequest extends FormRequest
{
    /**
     * Authorization is handled by the VerifyBiometricWebhookSecret middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the title casing before validation so "time-in"/"TIME-IN" both pass.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('title')) {
            $this->merge(['title' => mb_strtoupper(trim((string) $this->input('title')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required'],
            'title' => ['required', 'string', Rule::in(['SCAN', 'TIME-IN', 'TIME-OUT'])],
            'email' => ['required', 'email'],
            'punched_at' => ['required', 'date'],
        ];
    }

    /**
     * The validated punch, normalised for {@see AttendancePunchService}.
     * The SharePoint id is namespaced and the timestamp is converted to the app's
     * timezone (SharePoint "Created" is UTC).
     *
     * @return array{external_id: string, title: string, email: string, punched_at: Carbon}
     */
    public function toPunch(): array
    {
        return [
            'external_id' => 'sharepoint:'.$this->input('id'),
            'title' => (string) $this->input('title'),
            'email' => (string) $this->input('email'),
            'punched_at' => Carbon::parse($this->input('punched_at'))->setTimezone(config('app.timezone')),
        ];
    }
}
