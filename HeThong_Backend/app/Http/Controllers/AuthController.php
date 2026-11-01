<?php

namespace App\Http\Controllers;

use App\Http\Requests\DangNhapRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth)
    {
    }

    public function dangNhap(DangNhapRequest $request): JsonResponse
    {
        $taiKhoan = $this->auth->xacThuc(
            $request->validated('Ten_DangNhap'),
            $request->validated('MatKhau')
        );

        if (!$taiKhoan) {
            return response()->json([
                'ok'  => false,
                'loi' => 'Sai tên đăng nhập hoặc mật khẩu, hoặc tài khoản đã bị khóa.',
            ], 422);
        }

        Auth::guard('web')->login($taiKhoan);
        $request->session()->regenerate();

        return response()->json(['ok' => true, 'nguoi_dung' => $this->auth->thongTin($taiKhoan)]);
    }

    public function toi(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'nguoi_dung' => $this->auth->thongTin($request->user())]);
    }

    public function dangXuat(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }
}