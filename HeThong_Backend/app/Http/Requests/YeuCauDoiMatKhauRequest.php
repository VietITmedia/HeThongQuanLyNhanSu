<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class YeuCauDoiMatKhauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // cần thêm trường MatKhau_Moi_confirmation (nhập lại) để rule "confirmed" so sánh
            'MatKhau_Moi' => ['required', 'string', 'min:8', 'max:72', 'confirmed', function ($thuocTinh, $giaTri, $fail) {
                if (!preg_match('/[A-Z]/', $giaTri)) {
                    $fail('Mật khẩu phải có ít nhất một chữ hoa.');
                }
                if (!preg_match('/[a-z]/', $giaTri)) {
                    $fail('Mật khẩu phải có ít nhất một chữ thường.');
                }
                if (!preg_match('/\d/', $giaTri)) {
                    $fail('Mật khẩu phải có ít nhất một chữ số.');
                }
                if (!preg_match('/[^A-Za-z0-9]/', $giaTri)) {
                    $fail('Mật khẩu phải có ít nhất một ký tự đặc biệt (ví dụ @ # ! $).');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'MatKhau_Moi.required'  => 'Vui lòng nhập mật khẩu mới.',
            'MatKhau_Moi.min'       => 'Mật khẩu tối thiểu 8 ký tự.',
            'MatKhau_Moi.max'       => 'Mật khẩu tối đa 72 ký tự.',
            'MatKhau_Moi.confirmed' => 'Mật khẩu nhập lại không khớp.',
        ];
    }
}