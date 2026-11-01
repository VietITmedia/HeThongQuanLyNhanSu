<?php

namespace App\Http\Middleware;

use App\Services\PhanQuyenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KiemTraQuyen
{
    public function __construct(private PhanQuyenService $phanQuyen)
    {
    }

    // cần có ÍT NHẤT MỘT trong các quyền ghi ở route
    public function handle(Request $request, Closure $next, string ...$cacQuyen): Response
    {
        $taiKhoan = $request->user();

        if (!$taiKhoan) {
            return response()->json(['ok' => false, 'loi' => 'Chưa đăng nhập.'], 401);
        }

        foreach ($cacQuyen as $quyen) {
            if ($this->phanQuyen->coQuyen($taiKhoan, $quyen)) {
                return $next($request);
            }
        }

        return response()->json(['ok' => false, 'loi' => 'Bạn không đủ quyền thực hiện thao tác này.'], 403);
    }
}