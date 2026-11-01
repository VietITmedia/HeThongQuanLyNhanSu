<?php

namespace App\Http\Requests;

// Đổi mật khẩu chủ động: dùng lại luật mật khẩu mới của YeuCauDoiMatKhauRequest, thêm ô mật khẩu hiện tại
class DoiMatKhauCaNhanRequest extends YeuCauDoiMatKhauRequest
{
    public function rules(): array
    {
        return ['MatKhau_Cu' => ['required', 'string', 'max:72']] + parent::rules();
    }

    public function messages(): array
    {
        return ['MatKhau_Cu.required' => 'Vui lòng nhập mật khẩu hiện tại.'] + parent::messages();
    }
}
