<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KiemTraVaiTro
{
    public function handle(Request $request, Closure $next, string ...$cacVaiTro): Response
    {
        $taiKhoan = $request->user();

        // phòng hờ: quên đặt auth:sanctum trước thì vẫn từ chối
        if (!$taiKhoan) {
            return response()->json(['ok' => false, 'loi' => 'Chưa đăng nhập.'], 401);
        }

        // vai trò đọc từ database ở MỖI yêu cầu; chỉ tính vai trò đang hoạt động
        $duQuyen = $taiKhoan->vaiTros()
            ->whereIn('Ma_VaiTro', $cacVaiTro)
            ->where('VaiTro.Trang_Thai', 1)
            ->exists();

        if (!$duQuyen) {
            return response()->json(['ok' => false, 'loi' => 'Bạn không đủ quyền thực hiện thao tác này.'], 403);
        }

        return $next($request);
    }
}