<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitChunkUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'total_size' => ['required', 'integer', 'min:1', 'max:10995116277760'], // Max 10TB
            'chunk_size' => ['required', 'integer', 'min:1048576', 'max:104857600'], // 1MB to 100MB
        ];
    }

    public function messages(): array
    {
        return [
            'filename.required' => 'Filename is required.',
            'total_size.required' => 'Total file size is required.',
            'total_size.max' => 'File size cannot exceed 10TB.',
            'chunk_size.required' => 'Chunk size is required.',
            'chunk_size.min' => 'Chunk size must be at least 1MB.',
            'chunk_size.max' => 'Chunk size cannot exceed 100MB.',
        ];
    }
}
