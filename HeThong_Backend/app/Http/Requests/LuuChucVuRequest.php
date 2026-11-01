<?php

namespace App\Http\Requests;

use App\Models\ChucVu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LuuChucVuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // quyền đã kiểm ở middleware quyen
    }

    protected function prepareForValidation(): void
    {
        $ma = $this->input('Ma_ChucVu');
        if (is_string($ma)) {
            $this->merge(['Ma_ChucVu' => mb_strtoupper(trim($ma))]);   // mã luôn chữ hoa
        }
    }

    public function rules(): array
    {
        // khi sửa: bỏ qua chính dòng đang sửa lúc kiểm tra trùng mã
        $hienTai = $this->route('chuc_vu');
        $id = $hienTai instanceof ChucVu ? $hienTai->getKey() : $hienTai;

        return [
            'Ma_ChucVu'  => ['required', 'string', 'max:20',
                             Rule::unique('ChucVu', 'Ma_ChucVu')->ignore($id, 'ID_ChucVu')],
            'Ten_ChucVu' => ['required', 'string', 'max:100'],
            'Mo_Ta'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'Ma_ChucVu.required'  => 'Vui lòng nhập mã chức vụ.',
            'Ma_ChucVu.unique'    => 'Mã chức vụ đã tồn tại.',
            'Ten_ChucVu.required' => 'Vui lòng nhập tên chức vụ.',
        ];
    }
}