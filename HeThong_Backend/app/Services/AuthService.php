<?php

namespace App\Services;

use App\Models\TaiKhoan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthService
{
    private const TOI_DA_SAI = 5;

    // MỚI: Laravel tự đưa PhanQuyenService vào
    public function __construct(private PhanQuyenService $phanQuyen)
    {
    }

    public function xacThuc(string $tenDangNhap, string $matKhau): ?TaiKhoan
    {
        // 1. Tìm tài khoản kèm hồ sơ nhân viên
        $taiKhoan = TaiKhoan::with('nhanVien')->where('Ten_DangNhap', $tenDangNhap)->first();

        // 2. Luôn băm một lần dù có tài khoản hay không, để thời gian trả lời như nhau
        $matKhauDung = Hash::check($matKhau, $taiKhoan?->MatKhau_hash ?? $this->hashGia());

        // 3. Không tìm thấy tài khoản
        if (!$taiKhoan) {
            return null;
        }

        // 4. Tài khoản bị khóa, hoặc nhân viên đã nghỉ việc
                if (!$taiKhoan->Trang_Thai || (int) $taiKhoan->nhanVien?->Trang_Thai !== 1) {
            return null;
        }

        // 5. Đã nhập sai quá số lần
        if ($taiKhoan->SoLan_NhapSai >= self::TOI_DA_SAI) {
            return null;
        }

        // 6. Sai mật khẩu
        if (!$matKhauDung) {
            $taiKhoan->increment('SoLan_NhapSai');
            Log::info('Đăng nhập sai mật khẩu', ['id_tai_khoan' => $taiKhoan->ID_TaiKhoan]);
            return null;
        }

        // 7. Đăng nhập đúng: đặt lại số lần sai
        $taiKhoan->forceFill([
            'SoLan_NhapSai'    => 0,
            'LanDangNhap_Cuoi' => now(),
        ])->save();

        return $taiKhoan;
    }

    // Thông tin gửi cho giao diện sau khi đăng nhập
    public function thongTin(TaiKhoan $taiKhoan): array
    {
        $nv = $taiKhoan->nhanVien()->with(['congTy', 'phongBan', 'chucVu'])->firstOrFail();

        return [
            'ma_nhan_vien'      => $nv->Ma_NhanVien,
            'ho_ten'            => $nv->Ho_Ten,
            'ten_dang_nhap'     => $taiKhoan->Ten_DangNhap,
            'cong_ty'           => $nv->congTy?->Ten_CongTy,
            'phong_ban'         => $nv->phongBan?->Ten_PhongBan,
            'chuc_vu'           => $nv->chucVu?->Ten_ChucVu,
            'vai_tro'           => $taiKhoan->vaiTros()->pluck('Ma_VaiTro')->all(),
            'quyen'             => $this->phanQuyen->tatCaQuyen($taiKhoan),   // MỚI
            'phai_doi_mat_khau' => (bool) $taiKhoan->Doi_MatKhau,
        ];
    }

    // Chuỗi băm giả dùng khi không có tài khoản (tạo một lần cho cả quá trình chạy)
    private function hashGia(): string
    {
        static $hash = null;
        return $hash ??= Hash::make(Str::random(32));
    }
}