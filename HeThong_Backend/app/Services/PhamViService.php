<?php

namespace App\Services;

use App\Models\NhanVien;
use App\Models\TaiKhoan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PhamViService
{
    // Thu hẹp một truy vấn NhanVien theo phạm vi.
    // phamVi = null nghĩa là KHÔNG có quyền: không thấy ai (từ chối mặc định)
    public function ap(Builder $truyVan, TaiKhoan $taiKhoan, ?string $phamVi): Builder
    {
        if ($phamVi === 'tat_ca') {
            return $truyVan;
        }

        $nv = $taiKhoan->nhanVien;          // hồ sơ nhân viên của tài khoản này
        if (!$nv) {
            return $truyVan->whereRaw('1 = 0');
        }

        return match ($phamVi) {
            'cong_ty'  => $truyVan->whereIn('NhanVien.ID_CongTy', $this->congTyTrongNhom((int) $nv->ID_CongTy)),
            'cap_duoi' => $truyVan->whereIn('NhanVien.ID_NhanVien', $this->capDuoiCua((int) $nv->ID_NhanVien)),
            'cua_minh' => $truyVan->where('NhanVien.ID_NhanVien', $nv->ID_NhanVien),
            default    => $truyVan->whereRaw('1 = 0'),     // không có quyền (hoặc nhãn lạ): từ chối
        };
    }

    // Một nhân viên cụ thể có nằm trong phạm vi không (dùng cho trang chi tiết, sửa, ngừng)
    public function trongPhamVi(TaiKhoan $taiKhoan, ?string $phamVi, int $idNhanVien): bool
    {
        return $this->ap(NhanVien::query(), $taiKhoan, $phamVi)
            ->where('NhanVien.ID_NhanVien', $idNhanVien)
            ->exists();
    }
        // ID các công ty mà phạm vi này bao phủ.
    // null = tất cả công ty (không giới hạn); [] = không có công ty nào (không có quyền).
    public function idCongTyTrongPhamVi(TaiKhoan $taiKhoan, ?string $phamVi): ?array
    {
        if ($phamVi === 'tat_ca') {
            return null;
        }

        $nv = $taiKhoan->nhanVien;
        if ($phamVi === null || !$nv) {
            return [];
        }

        return match ($phamVi) {
            'cong_ty' => $this->congTyTrongNhom((int) $nv->ID_CongTy),     // công ty của mình và các công ty con
            default   => [(int) $nv->ID_CongTy],                           // cap_duoi, cua_minh: chỉ công ty của chính mình
        };
    }

    // ID công ty của người dùng + mọi công ty con (đệ quy theo ID_CongTyCha)
    private function congTyTrongNhom(int $idCongTy): array
    {
        $sql = "
            WITH RECURSIVE nhom AS (
                SELECT ID_CongTy FROM CongTy WHERE ID_CongTy = ?
                UNION
                SELECT c.ID_CongTy FROM CongTy c JOIN nhom n ON c.ID_CongTyCha = n.ID_CongTy
            )
            SELECT ID_CongTy FROM nhom
        ";

        return array_map(fn ($dong) => (int) $dong->ID_CongTy, DB::select($sql, [$idCongTy]));
    }

    // Chính mình + mọi cấp dưới nhiều tầng, chỉ tính quan hệ còn hiệu lực hôm nay.
    // Dùng UNION (không phải UNION ALL) để tự loại dòng trùng: nếu dữ liệu lỡ có vòng lặp A quản lý B,
    // B quản lý A thì truy vấn vẫn dừng, không chạy mãi.
    private function capDuoiCua(int $idNhanVien): array
    {
        $sql = "
            WITH RECURSIVE cap_duoi AS (
                SELECT ID_NhanVien FROM NhanVien WHERE ID_NhanVien = ?
                UNION
                SELECT b.ID_NhanVien
                FROM BanQuanLy b
                JOIN cap_duoi c ON b.ID_NguoiQuanLy = c.ID_NhanVien
                WHERE b.Ngay_BatDau <= CURDATE()
                  AND (b.Ngay_KetThuc IS NULL OR b.Ngay_KetThuc >= CURDATE())
            )
            SELECT ID_NhanVien FROM cap_duoi
        ";

        return array_map(fn ($dong) => (int) $dong->ID_NhanVien, DB::select($sql, [$idNhanVien]));
    }
    
}