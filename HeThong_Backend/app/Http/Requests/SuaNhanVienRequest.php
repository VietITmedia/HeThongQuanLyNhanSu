<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuaNhanVienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // quyền đã kiểm ở middleware quyen
    }

    // làm sạch dữ liệu trước khi kiểm tra
    protected function prepareForValidation(): void
    {
        $hoTen = $this->input('Ho_Ten');
        $email = $this->input('Email_NhanVien');
        $sdt   = $this->input('SDT_NhanVien');

        $this->merge([
            'Ho_Ten'         => is_string($hoTen) ? preg_replace('/\s+/u', ' ', trim($hoTen)) : $hoTen,
            'Email_NhanVien' => is_string($email) ? mb_strtolower(trim($email)) : $email,
            'SDT_NhanVien'   => is_string($sdt) ? preg_replace('/\s+/', '', $sdt) : $sdt,
        ]);
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');   // người đang được sửa: email, SĐT của chính họ không tính là trùng

        return [
            'Ho_Ten'         => ['required', 'string', 'max:100'],
            'Gioi_Tinh'      => ['required', 'integer', 'in:0,1'],
            'Ngay_Sinh'      => ['required', 'date', 'before:today'],
            'SDT_NhanVien'   => ['required', 'string', 'regex:/^(0|\+84)\d{9}$/', Rule::unique('NhanVien', 'SDT_NhanVien')->ignore($id, 'ID_NhanVien')],
            'Email_NhanVien' => ['required', 'email', 'max:100', Rule::unique('NhanVien', 'Email_NhanVien')->ignore($id, 'ID_NhanVien')],
            'Dia_Chi'        => ['required', 'string', 'max:255'],
            'Ngay_VaoLam'    => ['required', 'date'],
            'ID_PhongBan'    => ['required', 'integer', Rule::exists('PhongBan', 'ID_PhongBan')],
            'ID_ChucVu'      => ['required', 'integer', Rule::exists('ChucVu', 'ID_ChucVu')],
            // không gửi khóa này = giữ nguyên người quản lý; gửi null = bỏ người quản lý
            'ID_QuanLy'      => ['sometimes', 'nullable', 'integer', Rule::exists('NhanVien', 'ID_NhanVien')],
        ];
    }

    public function messages(): array
    {
        return [
            'Ho_Ten.required'         => 'Vui lòng nhập họ tên.',
            'Gioi_Tinh.required'      => 'Vui lòng chọn giới tính.',
            'Ngay_Sinh.required'      => 'Vui lòng nhập ngày sinh.',
            'Ngay_Sinh.before'        => 'Ngày sinh phải trước hôm nay.',
            'SDT_NhanVien.required'   => 'Vui lòng nhập số điện thoại.',
            'SDT_NhanVien.regex'      => 'Số điện thoại không đúng dạng (10 số bắt đầu bằng 0, hoặc +84 và 9 số).',
            'SDT_NhanVien.unique'     => 'Số điện thoại đã được dùng cho nhân viên khác.',
            'Email_NhanVien.required' => 'Vui lòng nhập email.',
            'Email_NhanVien.email'    => 'Email không đúng dạng.',
            'Email_NhanVien.unique'   => 'Email đã được dùng cho nhân viên khác.',
            'Dia_Chi.required'        => 'Vui lòng nhập địa chỉ.',
            'Ngay_VaoLam.required'    => 'Vui lòng nhập ngày vào làm.',
            'ID_PhongBan.required'    => 'Vui lòng chọn phòng ban.',
            'ID_ChucVu.required'      => 'Vui lòng chọn chức vụ.',
        ];
    }
}