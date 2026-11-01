<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LuuCongTyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // quyền đã kiểm ở middleware quyen
    }

    // làm sạch dữ liệu trước khi kiểm tra
    protected function prepareForValidation(): void
    {
        $ma    = $this->input('Ma_CongTy');
        $ten   = $this->input('Ten_CongTy');
        $email = $this->input('Email_CongTy');

        $this->merge([
            'Ma_CongTy'    => is_string($ma) ? mb_strtoupper(trim($ma)) : $ma,
            'Ten_CongTy'   => is_string($ten) ? preg_replace('/\s+/u', ' ', trim($ten)) : $ten,
            'Email_CongTy' => is_string($email) ? mb_strtolower(trim($email)) : $email,
        ]);
    }

    public function rules(): array
    {
        $them = $this->isMethod('POST');
        $id   = (int) $this->route('id');     // khi sửa: tên của chính công ty này không tính là trùng

        $rules = [
            'Ten_CongTy'    => ['required', 'string', 'max:100', Rule::unique('CongTy', 'Ten_CongTy')->ignore($id, 'ID_CongTy')],
            'Dia_Diem'      => ['required', 'string', 'max:255'],
            'SDT_CongTy'    => ['required', 'string', 'regex:/^[0-9+\-\s().]{8,20}$/'],
            'Email_CongTy'  => ['required', 'email', 'max:100'],
            'Ngay_ThanhLap' => ['nullable', 'date', 'before_or_equal:today'],
        ];

        // mã và công ty cha chỉ nhập lúc thêm, không đổi khi sửa
        if ($them) {
            $rules['Ma_CongTy']    = ['required', 'string', 'max:15', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('CongTy', 'Ma_CongTy')];
            $rules['ID_CongTyCha'] = ['required', 'integer', Rule::exists('CongTy', 'ID_CongTy')];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'Ma_CongTy.required'       => 'Vui lòng nhập mã công ty.',
            'Ma_CongTy.regex'          => 'Mã công ty chỉ gồm chữ hoa, số, dấu gạch ngang hoặc gạch dưới.',
            'Ma_CongTy.unique'         => 'Mã công ty đã tồn tại.',
            'Ma_CongTy.max'            => 'Mã công ty tối đa 15 ký tự.',
            'Ten_CongTy.required'      => 'Vui lòng nhập tên công ty.',
            'Ten_CongTy.unique'        => 'Tên công ty đã tồn tại.',
            'Dia_Diem.required'        => 'Vui lòng nhập địa điểm.',
            'SDT_CongTy.required'      => 'Vui lòng nhập số điện thoại.',
            'SDT_CongTy.regex'         => 'Số điện thoại không đúng dạng (8 đến 20 ký tự gồm số, dấu cách, + - ( ) .).',
            'Email_CongTy.required'    => 'Vui lòng nhập email.',
            'Email_CongTy.email'       => 'Email không đúng dạng.',
            'Ngay_ThanhLap.date'       => 'Ngày thành lập không đúng dạng.',
            'Ngay_ThanhLap.before_or_equal' => 'Ngày thành lập không được ở tương lai.',
            'ID_CongTyCha.required'    => 'Vui lòng chọn công ty cha.',
            'ID_CongTyCha.exists'      => 'Công ty cha không tồn tại.',
        ];
    }
}