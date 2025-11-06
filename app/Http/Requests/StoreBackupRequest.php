<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        $fail('Please select a file to upload.');

                        return;
                    }

                    $extension = strtolower($value->getClientOriginalExtension());
                    $filename = strtolower($value->getClientOriginalName());

                    $allowedExtensions = ['zip', 'tar', 'gz', 'img', 'iso', 'dmg', 'pkg', 'rar', '7z'];
                    $isTarGz = str_ends_with($filename, '.tar.gz');

                    if (! $isTarGz && ! in_array($extension, $allowedExtensions, true)) {
                        $fail('The file must be one of: zip, tar, gz, tar.gz, img, iso, dmg, pkg, rar, 7z');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded file is invalid.',
        ];
    }
}
