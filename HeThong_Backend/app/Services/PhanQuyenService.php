<?php

namespace App\Services;

use App\Models\TaiKhoan;

class PhanQuyenService
{
    // số lớn = phạm vi rộng hơn
    private const DO_RONG = ['tat_ca' => 4, 'cong_ty' => 3, 'cap_duoi' => 2, 'cua_minh' => 1];

    // Phạm vi rộng nhất mà tài khoản có cho quyền này; null = KHÔNG có quyền
    public function phamVi(TaiKhoan $taiKhoan, string $quyen): ?string
    {
        // không dùng config("phan_quyen.$quyen"): dấu chấm trong tên quyền bị hiểu là cấp lồng nhau
        $bang = config('phan_quyen', [])[$quyen] ?? null;
        if (!$bang) {
            return null;            // quyền không có trong file: từ chối
        }

        // vai trò đọc từ database mỗi lần hỏi, chỉ tính vai trò đang hoạt động
        $cacVaiTro = $taiKhoan->vaiTros()
            ->where('VaiTro.Trang_Thai', 1)
            ->pluck('Ma_VaiTro')
            ->push('EMPLOYEE')      // mọi tài khoản đều là nhân viên: luôn có vai trò nền
            ->unique();

        $tot = null;
        foreach ($cacVaiTro as $maVaiTro) {
            $phamVi = $bang[$maVaiTro] ?? null;

            if ($phamVi && (self::DO_RONG[$phamVi] ?? 0) > (self::DO_RONG[$tot ?? ''] ?? 0)) {
                $tot = $phamVi;     // giữ phạm vi rộng nhất
            }
        }

        return $tot;
    }

    public function coQuyen(TaiKhoan $taiKhoan, string $quyen): bool
    {
        return $this->phamVi($taiKhoan, $quyen) !== null;
    }
        // Toàn bộ quyền của tài khoản dạng ['chucvu.xem' => 'tat_ca', ...] (chỉ liệt kê quyền có)
    public function tatCaQuyen(TaiKhoan $taiKhoan): array
    {
        $ketQua = [];

        foreach (array_keys(config('phan_quyen', [])) as $quyen) {
            $phamVi = $this->phamVi($taiKhoan, $quyen);

            if ($phamVi !== null) {
                $ketQua[$quyen] = $phamVi;
            }
        }

        return $ketQua;
    }
}