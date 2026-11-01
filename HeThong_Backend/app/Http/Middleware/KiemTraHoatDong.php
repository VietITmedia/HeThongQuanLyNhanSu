<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class KiemTraHoatDong
{
    // Mỗi yêu cầu: tài khoản và nhân viên phải còn hoạt động (đang làm việc).
    // Không thì hủy phiên và trả 401, để giao diện đưa người dùng về trang đăng nhập.
    public function handle(Request $request, Closure $next): Response
    {
        $taiKhoan = $request->user();

        if ($taiKhoan && !$this->conHoatDong($taiKhoan)) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'ok'  => false,
                'loi' => 'Tài khoản đã bị khóa hoặc nhân viên không còn làm việc.',
            ], 401);
        }

        return $next($request);
    }

    private function conHoatDong($taiKhoan): bool
    {
        // chỉ trạng thái 1 mới được dùng: tài khoản mở VÀ nhân viên đang làm việc (2 = tạm nghỉ, 0 = nghỉ việc đều bị chặn)
        return (int) $taiKhoan->Trang_Thai === 1
            && (int) $taiKhoan->nhanVien?->Trang_Thai === 1;
    }
}