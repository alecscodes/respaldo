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
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert GB to bytes before validation
        if ($this->has('storage_size')) {
            $this->merge([
                'storage_size_bytes' => StorageConverter::gbToBytes((float) $this->storage_size),
            ]);
        }
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
