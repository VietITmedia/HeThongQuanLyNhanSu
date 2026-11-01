<?php

namespace App\Services;

use App\Models\CongTy;
use App\Models\PhongBan;
use App\Models\TaiKhoan;

class DanhMucService
{
    public function __construct(
        private PhanQuyenService $phanQuyen,
        private PhamViService $phamVi,
    ) {
    }

    // Công ty đang hoạt động mà tài khoản được thấy
    public function congTy(TaiKhoan $taiKhoan): array
    {
        $cacId = $this->phamVi->idCongTyTrongPhamVi($taiKhoan, $this->phanQuyen->phamVi($taiKhoan, 'congty.xem'));

        return CongTy::query()
            ->when($cacId !== null, fn ($q) => $q->whereIn('ID_CongTy', $cacId))
            ->where('Trang_Thai', 1)
            ->orderBy('Loai_CongTy')
            ->orderBy('Ten_CongTy')
            ->get()
            ->map(fn (CongTy $c) => [
                'id'             => (int) $c->ID_CongTy,
                'ma'             => $c->Ma_CongTy,
                'ten'            => $c->Ten_CongTy,
                'id_cong_ty_cha' => $c->ID_CongTyCha !== null ? (int) $c->ID_CongTyCha : null,
            ])
            ->all();
    }

    // Phòng ban đang hoạt động trong các công ty mà tài khoản được thấy (có thể lọc theo một công ty)
    public function phongBan(TaiKhoan $taiKhoan, ?int $idCongTy = null): array
    {
        $cacId = $this->phamVi->idCongTyTrongPhamVi($taiKhoan, $this->phanQuyen->phamVi($taiKhoan, 'phongban.xem'));

        return PhongBan::query()
            ->when($cacId !== null, fn ($q) => $q->whereIn('ID_CongTy', $cacId))
            ->when($idCongTy !== null, fn ($q) => $q->where('ID_CongTy', $idCongTy))
            ->where('Trang_Thai', 1)
            ->orderBy('ID_CongTy')
            ->orderBy('Ten_PhongBan')
            ->get()
            ->map(fn (PhongBan $p) => [
                'id'         => (int) $p->ID_PhongBan,
                'ma'         => $p->Ma_PhongBan,
                'ten'        => $p->Ten_PhongBan,
                'id_cong_ty' => (int) $p->ID_CongTy,
            ])
            ->all();
    }
}