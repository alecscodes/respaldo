<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'upload_id' => ['required', 'string', 'size:32'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:104857600'], // Max 100MB per chunk
        ];
    }

    public function messages(): array
    {
        return [
            'upload_id.required' => 'Upload ID is required.',
            'upload_id.size' => 'Invalid upload ID format.',
            'chunk_index.required' => 'Chunk index is required.',
            'chunk_index.min' => 'Chunk index must be non-negative.',
            'chunk.required' => 'Chunk file is required.',
            'chunk.max' => 'Chunk size cannot exceed 100MB.',
        ];
    }
}
