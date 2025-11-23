<?php

namespace App\Http\Requests;

use App\Services\StorageConverter;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'storage_size' => ['required', 'numeric', 'min:0.1'],
            'backup_period' => ['nullable', 'string', 'in:daily,weekly,monthly'],
            'backup_days' => ['nullable', 'required_if:backup_period,weekly', 'array'],
            'backup_days.*' => ['string', 'in:M,T,W,R,F,S,U'],
            'retention_days' => ['nullable', 'integer', 'min:1'],
            'retention_count' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get validated data with storage_size in bytes.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        if (isset($validated['storage_size'])) {
            $validated['storage_size'] = StorageConverter::gbToBytes((float) $validated['storage_size']);
        }

        return $validated;
    }
}
