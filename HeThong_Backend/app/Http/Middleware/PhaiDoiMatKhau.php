<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PhaiDoiMatKhau
{
    // Tài khoản còn cờ Doi_MatKhau = 1 chỉ được: xem thông tin mình, đăng xuất, và các API đổi mật khẩu.
    // Mọi API nghiệp vụ khác bị chặn ở đây, không dựa vào việc giao diện có chuyển trang hay không.
    public function handle(Request $request, Closure $next): Response
    {
        $taiKhoan = $request->user();

        if ($taiKhoan && (int) $taiKhoan->Doi_MatKhau === 1 && !$request->is('api/toi', 'api/dang-xuat', 'api/doi-mat-khau/*')) {
            return response()->json([
                'ok'  => false,
                'ma'  => 'PHAI_DOI_MAT_KHAU',
                'loi' => 'Bạn phải đổi mật khẩu trước khi sử dụng hệ thống.',
            ], 403);
        }

        return $next($request);
    }
}