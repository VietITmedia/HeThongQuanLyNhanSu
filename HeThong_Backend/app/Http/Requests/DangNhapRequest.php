<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DangNhapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $ten = $this->input('Ten_DangNhap');

        if (is_string($ten)) {
            $this->merge([
                'Ten_DangNhap' => mb_strtolower(trim($ten)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'Ten_DangNhap' => ['required', 'string', 'max:80'],
            'MatKhau' => ['required', 'string', 'max:255'],
        ];
    }
}