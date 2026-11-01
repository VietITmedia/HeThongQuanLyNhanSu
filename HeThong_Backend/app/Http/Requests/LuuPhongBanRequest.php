<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LuuPhongBanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // quyền đã kiểm ở middleware quyen
    }

    // làm sạch dữ liệu trước khi kiểm tra
    protected function prepareForValidation(): void
    {
        $ma    = $this->input('Ma_PhongBan');
        $ten   = $this->input('Ten_PhongBan');
        $email = $this->input('Email_PhongBan');

        $this->merge([
            'Ma_PhongBan'    => is_string($ma) ? mb_strtoupper(trim($ma)) : $ma,
            'Ten_PhongBan'   => is_string($ten) ? preg_replace('/\s+/u', ' ', trim($ten)) : $ten,
            'Email_PhongBan' => is_string($email) ? mb_strtolower(trim($email)) : $email,
        ]);
    }

    public function rules(): array
    {
        $them = $this->isMethod('POST');
        $id   = (int) $this->route('id');     // khi sửa: tên của chính phòng ban này không tính là trùng

        // tên chỉ cần khác nhau trong CÙNG một công ty (công ty nào cũng có "Phòng Nhân sự")
        $idCongTy = $them
            ? (int) $this->input('ID_CongTy')
            : (int) DB::table('PhongBan')->where('ID_PhongBan', $id)->value('ID_CongTy');

        $rules = [
            'Ten_PhongBan'   => ['required', 'string', 'max:100',
                Rule::unique('PhongBan', 'Ten_PhongBan')->where(fn ($q) => $q->where('ID_CongTy', $idCongTy))->ignore($id, 'ID_PhongBan')],
            'Email_PhongBan' => ['nullable', 'email', 'max:100'],
            'Ngay_ThanhLap'  => ['nullable', 'date', 'before_or_equal:today'],
        ];

        // mã, công ty và phòng ban cha chỉ nhập lúc thêm, không đổi khi sửa
        if ($them) {
            $rules['Ma_PhongBan']    = ['required', 'string', 'max:12', 'regex:/^[A-Z0-9]+(-[A-Z0-9]+)*$/', Rule::unique('PhongBan', 'Ma_PhongBan')];
            $rules['ID_CongTy']      = ['required', 'integer', Rule::exists('CongTy', 'ID_CongTy')];
            $rules['ID_PhongBanCha'] = ['nullable', 'integer', Rule::exists('PhongBan', 'ID_PhongBan')];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'Ma_PhongBan.required'          => 'Vui lòng nhập mã phòng ban.',
            'Ma_PhongBan.max'               => 'Mã phòng ban tối đa 12 ký tự.',
            'Ma_PhongBan.regex'             => 'Mã phòng ban chỉ gồm chữ hoa, số và dấu gạch ngang (ví dụ VC-MKT).',
            'Ma_PhongBan.unique'            => 'Mã phòng ban đã tồn tại.',
            'Ten_PhongBan.required'         => 'Vui lòng nhập tên phòng ban.',
            'Ten_PhongBan.unique'           => 'Tên phòng ban đã tồn tại trong công ty này.',
            'ID_CongTy.required'            => 'Vui lòng chọn công ty.',
            'ID_CongTy.exists'              => 'Công ty không tồn tại.',
            'ID_PhongBanCha.exists'         => 'Phòng ban cha không tồn tại.',
            'Email_PhongBan.email'          => 'Email không đúng dạng.',
            'Ngay_ThanhLap.date'            => 'Ngày thành lập không đúng dạng.',
            'Ngay_ThanhLap.before_or_equal' => 'Ngày thành lập không được ở tương lai.',
        ];
    }
}