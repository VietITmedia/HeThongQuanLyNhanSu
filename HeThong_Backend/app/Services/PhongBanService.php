<?php

namespace App\Services;

use App\Models\TaiKhoan;
use Illuminate\Support\Facades\DB;

class PhongBanService
{
    // Cột PhongBan.Trang_Thai
    public const TRANG_THAI = [1 => 'Hoạt động', 0 => 'Ngừng hoạt động'];

    public function __construct(
        private PhanQuyenService $phanQuyen,
        private PhamViService $phamVi,
    ) {
    }

    // Danh sách phòng ban được xem (kể cả đã ngừng): admin thấy tất cả, người khác thấy công ty mình
    public function danhSach(TaiKhoan $taiKhoan): array
    {
        $cacId = $this->congTyDuocXem($taiKhoan);

        return $this->truyVan()
            ->when($cacId !== null, fn ($q) => $q->whereIn('p.ID_CongTy', $cacId))
            ->get()
            ->map(fn ($p) => $this->dong($p))
            ->all();
    }

    // Dùng cho trang Sửa; trả null nếu không tồn tại HOẶC ngoài phạm vi được sửa (cùng một kết quả)
    public function chiTiet(TaiKhoan $taiKhoan, int $id): ?array
    {
        $cacId = $this->congTyDuocPhep($taiKhoan, 'phongban.sua');
        $p     = $this->truyVan()->where('p.ID_PhongBan', $id)->first();

        if (!$p || ($cacId !== null && !in_array((int) $p->ID_CongTy, $cacId, true))) {
            return null;
        }

        return $this->dong($p);
    }

    /**
     * Thêm phòng ban mới.
     * Ném DomainException: code 403 = ngoài phạm vi, code 422 = công ty hoặc phòng ban cha không hợp lệ.
     */
    public function them(TaiKhoan $nguoiThem, array $d): array
    {
        $cacId = $this->congTyDuocPhep($nguoiThem, 'phongban.them');
        if ($cacId !== null && !in_array((int) $d['ID_CongTy'], $cacId, true)) {
            throw new \DomainException('Bạn không được thêm phòng ban vào công ty này.', 403);
        }

        $congTyHopLe = DB::table('CongTy')->where('ID_CongTy', $d['ID_CongTy'])->where('Trang_Thai', 1)->exists();
        if (!$congTyHopLe) {
            throw new \DomainException('Công ty không tồn tại hoặc đã ngừng hoạt động.', 422);
        }

        $idCha = !empty($d['ID_PhongBanCha']) ? (int) $d['ID_PhongBanCha'] : null;
        if ($idCha !== null) {
            $chaHopLe = DB::table('PhongBan')
                ->where('ID_PhongBan', $idCha)
                ->where('ID_CongTy', $d['ID_CongTy'])
                ->where('Trang_Thai', 1)
                ->exists();
            if (!$chaHopLe) {
                throw new \DomainException('Phòng ban cha phải thuộc cùng công ty và đang hoạt động.', 422);
            }
        }

        $id = DB::table('PhongBan')->insertGetId([
            'Ma_PhongBan'    => $d['Ma_PhongBan'],
            'Ten_PhongBan'   => $d['Ten_PhongBan'],
            'ID_CongTy'      => (int) $d['ID_CongTy'],
            'ID_PhongBanCha' => $idCha,
            'Email_PhongBan' => $d['Email_PhongBan'] ?? null,
            'Ngay_ThanhLap'  => $d['Ngay_ThanhLap'] ?? null,
            'Trang_Thai'     => 1,
        ]);

        return $this->laySau($id);
    }

    // Sửa tên, email, ngày thành lập; KHÔNG đổi mã, công ty, phòng ban cha. Trả null nếu không tồn tại hoặc ngoài phạm vi.
    public function sua(TaiKhoan $nguoiSua, int $id, array $d): ?array
    {
        $cacId = $this->congTyDuocPhep($nguoiSua, 'phongban.sua');
        $p     = DB::table('PhongBan')->where('ID_PhongBan', $id)->first();

        if (!$p || ($cacId !== null && !in_array((int) $p->ID_CongTy, $cacId, true))) {
            return null;
        }

        DB::table('PhongBan')->where('ID_PhongBan', $id)->update([
            'Ten_PhongBan'   => $d['Ten_PhongBan'],
            'Email_PhongBan' => $d['Email_PhongBan'] ?? null,
            'Ngay_ThanhLap'  => $d['Ngay_ThanhLap'] ?? null,
        ]);

        return $this->laySau($id);
    }

    /**
     * Chuyển đổi trạng thái: 1 = hoạt động, 0 = ngừng hoạt động.
     * Trả null nếu không tồn tại hoặc ngoài phạm vi. Ném DomainException khi vi phạm luật (409, hoặc 422 nếu trạng thái sai).
     */
    public function doiTrangThai(TaiKhoan $nguoiDoi, int $id, int $trangThaiMoi): ?array
    {
        if (!array_key_exists($trangThaiMoi, self::TRANG_THAI)) {
            throw new \DomainException('Trạng thái không hợp lệ.', 422);
        }

        $cacId = $this->congTyDuocPhep($nguoiDoi, 'phongban.doi_trang_thai');

        // kiểm tra rồi ghi trong một giao dịch, khóa dòng phòng ban để hai người đổi cùng lúc phải xếp hàng
        return DB::transaction(function () use ($id, $trangThaiMoi, $cacId) {
            $p = DB::table('PhongBan')->where('ID_PhongBan', $id)->lockForUpdate()->first();

            if (!$p || ($cacId !== null && !in_array((int) $p->ID_CongTy, $cacId, true))) {
                return null;
            }

            if ((int) $p->Trang_Thai === $trangThaiMoi) {
                throw new \DomainException('Phòng ban đã ở trạng thái "' . self::TRANG_THAI[$trangThaiMoi] . '".', 409);
            }

            if ($trangThaiMoi === 0) {
                $this->kiemTraDuocNgung($p);
            } else {
                $congTyHoatDong = DB::table('CongTy')->where('ID_CongTy', $p->ID_CongTy)->where('Trang_Thai', 1)->exists();
                if (!$congTyHoatDong) {
                    throw new \DomainException('Công ty của phòng ban đang ngừng hoạt động, hãy bật công ty trước.', 409);
                }

                if ($p->ID_PhongBanCha !== null) {
                    $chaHoatDong = DB::table('PhongBan')->where('ID_PhongBan', $p->ID_PhongBanCha)->where('Trang_Thai', 1)->exists();
                    if (!$chaHoatDong) {
                        throw new \DomainException('Phòng ban cha đang ngừng hoạt động, hãy bật phòng ban cha trước.', 409);
                    }
                }
            }

            DB::table('PhongBan')->where('ID_PhongBan', $id)->update(['Trang_Thai' => $trangThaiMoi]);

            return ['id' => $id, 'trang_thai' => $trangThaiMoi, 'ten_trang_thai' => self::TRANG_THAI[$trangThaiMoi]];
        });
    }

    // Phòng ban chỉ ngừng được khi không còn nhân viên (đang làm hoặc tạm nghỉ) và không còn phòng ban con hoạt động
    private function kiemTraDuocNgung(object $p): void
    {
        $id          = (int) $p->ID_PhongBan;
        $soNhanVien  = DB::table('NhanVien')->where('ID_PhongBan', $id)->whereIn('Trang_Thai', [1, 2])->count();
        $soPhongBanCon = DB::table('PhongBan')->where('ID_PhongBanCha', $id)->where('Trang_Thai', 1)->count();

        $con = [];
        if ($soNhanVien > 0) {
            $con[] = "{$soNhanVien} nhân viên (đang làm hoặc tạm nghỉ)";
        }
        if ($soPhongBanCon > 0) {
            $con[] = "{$soPhongBanCon} phòng ban con đang hoạt động";
        }

        if ($con) {
            throw new \DomainException('Không thể ngừng phòng ban: còn ' . implode(', ', $con) . '.', 409);
        }
    }

    // Công ty được XEM phòng ban: null = tất cả (admin); người khác = công ty của mình cộng các công ty họ được quản lý
    private function congTyDuocXem(TaiKhoan $taiKhoan): ?array
    {
        if ($this->phanQuyen->phamVi($taiKhoan, 'phongban.xem') === 'tat_ca') {
            return null;
        }

        $quanLy = $this->congTyDuocPhep($taiKhoan, 'phongban.sua');
        if ($quanLy === null) {
            return null;                                     // phạm vi sửa là tat_ca nên thấy hết
        }

        return array_values(array_unique(array_merge([(int) $taiKhoan->nhanVien?->ID_CongTy], $quanLy)));
    }

    // null = tất cả công ty; mảng = chỉ các công ty đó ([] = không công ty nào)
    private function congTyDuocPhep(TaiKhoan $taiKhoan, string $quyen): ?array
    {
        return $this->phamVi->idCongTyTrongPhamVi($taiKhoan, $this->phanQuyen->phamVi($taiKhoan, $quyen));
    }

    // Truy vấn nền: phòng ban kèm tên công ty, tên phòng ban cha và hai con số đếm
    private function truyVan()
    {
        return DB::table('PhongBan as p')
            ->join('CongTy as c', 'c.ID_CongTy', '=', 'p.ID_CongTy')
            ->leftJoin('PhongBan as cha', 'cha.ID_PhongBan', '=', 'p.ID_PhongBanCha')
            ->select('p.*', 'c.Ten_CongTy', 'cha.Ten_PhongBan as Ten_PhongBanCha')
            ->selectSub(fn ($q) => $q->from('NhanVien')->selectRaw('COUNT(*)')
                ->whereColumn('NhanVien.ID_PhongBan', 'p.ID_PhongBan')->whereIn('NhanVien.Trang_Thai', [1, 2]), 'so_nhan_vien')
            ->selectSub(fn ($q) => $q->from('PhongBan as k')->selectRaw('COUNT(*)')
                ->whereColumn('k.ID_PhongBanCha', 'p.ID_PhongBan')->where('k.Trang_Thai', 1), 'so_phong_ban_con')
            ->orderBy('p.ID_CongTy')
            ->orderBy('p.ID_PhongBan');
    }

    // Đọc lại một phòng ban sau khi ghi (không kiểm phạm vi vì vừa được phép ghi)
    private function laySau(int $id): array
    {
        return $this->dong($this->truyVan()->where('p.ID_PhongBan', $id)->first());
    }

    private function dong(object $p): array
    {
        return [
            'id'                => (int) $p->ID_PhongBan,
            'ma'                => $p->Ma_PhongBan,
            'ten'               => $p->Ten_PhongBan,
            'id_cong_ty'        => (int) $p->ID_CongTy,
            'ten_cong_ty'       => $p->Ten_CongTy,
            'id_phong_ban_cha'  => $p->ID_PhongBanCha !== null ? (int) $p->ID_PhongBanCha : null,
            'ten_phong_ban_cha' => $p->Ten_PhongBanCha,
            'email'             => $p->Email_PhongBan,
            'ngay_thanh_lap'    => $p->Ngay_ThanhLap ? substr((string) $p->Ngay_ThanhLap, 0, 10) : null,
            'trang_thai'        => (int) $p->Trang_Thai,
            'ten_trang_thai'    => self::TRANG_THAI[(int) $p->Trang_Thai] ?? '—',
            'so_nhan_vien'      => (int) $p->so_nhan_vien,
            'so_phong_ban_con'  => (int) $p->so_phong_ban_con,
        ];
    }
}