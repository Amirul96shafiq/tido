<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class GuestRestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return User::query()->doesntExist();
    }

    /**
     * @return array<string, list<string|ValidationRule|File>>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) config('backup.backup.restore.max_upload_kilobytes', 51200);

        return [
            'token' => ['required', 'string', 'min:8', 'max:128'],
            'backup' => [
                'required',
                'file',
                File::types(['zip'])
                    ->max($maxKilobytes),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMegabytes = (int) ceil(((int) config('backup.backup.restore.max_upload_kilobytes', 51200)) / 1024);

        return [
            'backup.required' => 'Choose a backup zip file to restore.',
            'backup.max' => "The backup file may not be greater than {$maxMegabytes} MB.",
            'token.required' => 'Enter the restore token from your backup kit.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('backup');

            if ($file === null) {
                return;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());

            if ($extension !== 'zip') {
                $validator->errors()->add('backup', 'Only .zip backup files are allowed.');
            }
        });
    }
}
