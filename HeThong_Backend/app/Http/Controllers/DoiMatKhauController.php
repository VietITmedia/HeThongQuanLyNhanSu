<?php

namespace App\Http\Controllers;

use App\Http\Requests\YeuCauDoiMatKhauRequest;
use App\Services\DoiMatKhauService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DoiMatKhauController extends Controller
{
    // Khóa trong phiên: thời điểm hết hạn của lần vừa nhập đúng mật khẩu hiện tại
    private const KHOA_PHIEN = 'mk_cu_xac_minh_den';

    public function __construct(private DoiMatKhauService $dichVu)
    {
    }

    // Trạng thái để giao diện hiện đúng bước khi tải lại trang
    public function trangThai(Request $request): JsonResponse
    {
        $tk = $request->user();

        return response()->json(['ok' => true, 'du_lieu' => $this->dichVu->trangThai($tk) + [
            'can_mat_khau_cu' => (int) $tk->Doi_MatKhau !== 1,
            'da_xac_minh_cu'  => $this->daXacMinhMatKhauCu($request),
            'khoa_con_giay'   => $this->dichVu->giayKhoaConLai($tk),
        ]]);
    }

    // Bước nhập mật khẩu hiện tại (người đổi chủ động): đúng thì nhớ trong phiên 15 phút để được đi tiếp
    public function kiemTraCu(Request $request): JsonResponse
    {
        $d = $request->validate(
            ['MatKhau_Cu' => ['required', 'string', 'max:72']],
            ['MatKhau_Cu.required' => 'Vui lòng nhập mật khẩu hiện tại.']
        );

        try {
            $this->dichVu->kiemTraMatKhauCu($request->user(), $d['MatKhau_Cu']);
        } catch (\DomainException $e) {
            if ($e->getCode() === 423) {
                // báo cả số giây còn lại để giao diện hiện đồng hồ đếm ngược
                return response()->json([
                    'ok'            => false,
                    'loi'           => $e->getMessage(),
                    'khoa_con_giay' => $this->dichVu->giayKhoaConLai($request->user()->fresh()),
                ], 423);
            }

            return $this->loi($e);
        }

        $request->session()->put(self::KHOA_PHIEN, now()->addMinutes(15)->timestamp);

        return response()->json(['ok' => true, 'du_lieu' => ['da_xac_minh_cu' => true]]);
    }

    // Nhận mật khẩu mới, gửi OTP
    public function yeuCau(YeuCauDoiMatKhauRequest $request): JsonResponse
    {
        if ($chan = $this->chanNeuChuaXacMinhCu($request)) {
            return $chan;
        }

        try {
            $kq = $this->dichVu->taoYeuCau($request->user(), $request->validated()['MatKhau_Moi']);
        } catch (\DomainException $e) {
            return $this->loi($e);
        }

        return response()->json(['ok' => true, 'du_lieu' => $kq]);
    }

    public function guiLai(Request $request): JsonResponse
    {
        if ($chan = $this->chanNeuChuaXacMinhCu($request)) {
            return $chan;
        }

        try {
            $kq = $this->dichVu->guiLai($request->user());
        } catch (\DomainException $e) {
            return $this->loi($e);
        }

        return response()->json(['ok' => true, 'du_lieu' => $kq]);
    }

    // Nhập OTP; thành công thì đăng xuất và bắt đăng nhập lại bằng mật khẩu mới
    public function xacNhan(Request $request): JsonResponse
    {
        $d = $request->validate(
            ['Ma_OTP' => ['required', 'digits:6']],
            ['Ma_OTP.required' => 'Vui lòng nhập mã xác minh.', 'Ma_OTP.digits' => 'Mã xác minh gồm đúng 6 chữ số.']
        );

        try {
            $this->dichVu->xacNhan($request->user(), $d['Ma_OTP']);
        } catch (\DomainException $e) {
            return $this->loi($e);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'thong_bao' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại bằng mật khẩu mới.']);
    }

    private function daXacMinhMatKhauCu(Request $request): bool
    {
        return (int) $request->session()->get(self::KHOA_PHIEN, 0) > now()->timestamp;
    }

    // Người đổi chủ động (không còn cờ bắt đổi) phải vừa nhập đúng mật khẩu hiện tại mới được đặt mật khẩu mới.
    // Đây là chỗ chặn thật: không dựa vào việc giao diện có hiện ô hay không.
    private function chanNeuChuaXacMinhCu(Request $request): ?JsonResponse
    {
        if ((int) $request->user()->Doi_MatKhau !== 1 && !$this->daXacMinhMatKhauCu($request)) {
            return response()->json([
                'ok'  => false,
                'ma'  => 'CAN_MAT_KHAU_CU',
                'loi' => 'Hãy nhập mật khẩu hiện tại trước khi đặt mật khẩu mới.',
            ], 403);
        }

        return null;
    }

    // 409, 422, 429, 503 giữ nguyên; mã khác coi như 409
    private function loi(\DomainException $e): JsonResponse
    {
        $ma = in_array($e->getCode(), [409, 422, 429, 503], true) ? $e->getCode() : 409;

        return response()->json(['ok' => false, 'loi' => $e->getMessage()], $ma);
    }
}