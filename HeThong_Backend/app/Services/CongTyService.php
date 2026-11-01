<?php

namespace App\Services;

use App\Models\TaiKhoan;
use Illuminate\Support\Facades\DB;

class CongTyService
{
    // Cột CongTy.Loai_CongTy
    public const LOAI = [0 => 'Tập đoàn', 1 => 'Công ty thành viên'];

    // Cột CongTy.Trang_Thai
    public const TRANG_THAI = [1 => 'Hoạt động', 0 => 'Ngừng hoạt động'];

    public function __construct(
        private PhanQuyenService $phanQuyen,
        private PhamViService $phamVi,
    ) {
    }

    // Danh sách đầy đủ (kể cả công ty đã ngừng) trong phạm vi của quyền "congty.sua"
    public function danhSach(TaiKhoan $taiKhoan): array
    {
                // admin (phạm vi tat_ca) thấy mọi công ty; người khác chỉ thấy đúng công ty của mình
        $cacId = $this->phanQuyen->phamVi($taiKhoan, 'congty.xem') === 'tat_ca'
            ? null
            : [(int) $taiKhoan->nhanVien?->ID_CongTy];

        return $this->truyVan()
            ->when($cacId !== null, fn ($q) => $q->whereIn('c.ID_CongTy', $cacId))
            ->get()
            ->map(fn ($c) => $this->dong($c))
            ->all();
    }

    // Trả null nếu không tồn tại HOẶC ngoài phạm vi (cùng một kết quả)
    public function chiTiet(TaiKhoan $taiKhoan, int $id): ?array
    {
        $cacId = $this->congTyDuocPhep($taiKhoan, 'congty.sua');
        if ($cacId !== null && !in_array($id, $cacId, true)) {
            return null;
        }

        $c = $this->truyVan()->where('c.ID_CongTy', $id)->first();

        return $c ? $this->dong($c) : null;
    }

    /**
     * Thêm công ty thành viên mới.
     * Ném DomainException: code 403 = ngoài phạm vi, code 422 = công ty cha không hợp lệ.
     */
    public function them(TaiKhoan $nguoiThem, array $d): array
    {
        $cacId = $this->congTyDuocPhep($nguoiThem, 'congty.them');
        if ($cacId !== null && !in_array((int) $d['ID_CongTyCha'], $cacId, true)) {
            throw new \DomainException('Bạn không được thêm công ty vào nhánh này.', 403);
        }

        $chaHopLe = DB::table('CongTy')->where('ID_CongTy', $d['ID_CongTyCha'])->where('Trang_Thai', 1)->exists();
        if (!$chaHopLe) {
            throw new \DomainException('Công ty cha không tồn tại hoặc đã ngừng hoạt động.', 422);
        }

        $id = DB::table('CongTy')->insertGetId([
            'Ma_CongTy'     => $d['Ma_CongTy'],
            'Ten_CongTy'    => $d['Ten_CongTy'],
            'Loai_CongTy'   => 1,                              // luôn là công ty thành viên; hệ thống chỉ có một tập đoàn
            'ID_CongTyCha'  => (int) $d['ID_CongTyCha'],
            'Dia_Diem'      => $d['Dia_Diem'],
            'SDT_CongTy'    => $d['SDT_CongTy'],
            'Email_CongTy'  => $d['Email_CongTy'],
            'Ngay_ThanhLap' => $d['Ngay_ThanhLap'] ?? null,
            'Trang_Thai'    => 1,
        ]);

        return $this->laySau($id);
    }

    // Sửa thông tin; KHÔNG đổi mã và công ty cha. Trả null nếu không tồn tại hoặc ngoài phạm vi.
    public function sua(TaiKhoan $nguoiSua, int $id, array $d): ?array
    {
        $cacId = $this->congTyDuocPhep($nguoiSua, 'congty.sua');
        if ($cacId !== null && !in_array($id, $cacId, true)) {
            return null;
        }
        if (!DB::table('CongTy')->where('ID_CongTy', $id)->exists()) {
            return null;
        }

        DB::table('CongTy')->where('ID_CongTy', $id)->update([
            'Ten_CongTy'    => $d['Ten_CongTy'],
            'Dia_Diem'      => $d['Dia_Diem'],
            'SDT_CongTy'    => $d['SDT_CongTy'],
            'Email_CongTy'  => $d['Email_CongTy'],
            'Ngay_ThanhLap' => $d['Ngay_ThanhLap'] ?? null,
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

        $cacId = $this->congTyDuocPhep($nguoiDoi, 'congty.doi_trang_thai');
        if ($cacId !== null && !in_array($id, $cacId, true)) {
            return null;
        }

        // kiểm tra rồi ghi trong một giao dịch, khóa dòng công ty để hai người đổi cùng lúc phải xếp hàng
        return DB::transaction(function () use ($id, $trangThaiMoi) {
            $c = DB::table('CongTy')->where('ID_CongTy', $id)->lockForUpdate()->first();
            if (!$c) {
                return null;
            }

            if ((int) $c->Trang_Thai === $trangThaiMoi) {
                throw new \DomainException('Công ty đã ở trạng thái "' . self::TRANG_THAI[$trangThaiMoi] . '".', 409);
            }

            if ($trangThaiMoi === 0) {
                $this->kiemTraDuocNgung($c);
            } elseif ($c->ID_CongTyCha !== null) {
                $chaDangHoatDong = DB::table('CongTy')->where('ID_CongTy', $c->ID_CongTyCha)->where('Trang_Thai', 1)->exists();
                if (!$chaDangHoatDong) {
                    throw new \DomainException('Công ty cha đang ngừng hoạt động, hãy bật công ty cha trước.', 409);
                }
            }

            DB::table('CongTy')->where('ID_CongTy', $id)->update(['Trang_Thai' => $trangThaiMoi]);

            return ['id' => $id, 'trang_thai' => $trangThaiMoi, 'ten_trang_thai' => self::TRANG_THAI[$trangThaiMoi]];
        });
    }

    // Công ty chỉ ngừng được khi không còn nhân viên, phòng ban hoạt động, công ty con hoạt động
    private function kiemTraDuocNgung(object $c): void
    {
        if ((int) $c->Loai_CongTy === 0) {
            throw new \DomainException('Không thể ngừng tập đoàn.', 409);
        }

        $id          = (int) $c->ID_CongTy;
        $soNhanVien  = DB::table('NhanVien')->where('ID_CongTy', $id)->whereIn('Trang_Thai', [1, 2])->count();
        $soPhongBan  = DB::table('PhongBan')->where('ID_CongTy', $id)->where('Trang_Thai', 1)->count();
        $soCongTyCon = DB::table('CongTy')->where('ID_CongTyCha', $id)->where('Trang_Thai', 1)->count();

        $con = [];
        if ($soNhanVien > 0) {
            $con[] = "{$soNhanVien} nhân viên (đang làm hoặc tạm nghỉ)";
        }
        if ($soPhongBan > 0) {
            $con[] = "{$soPhongBan} phòng ban đang hoạt động";
        }
        if ($soCongTyCon > 0) {
            $con[] = "{$soCongTyCon} công ty con đang hoạt động";
        }

        if ($con) {
            throw new \DomainException('Không thể ngừng công ty: còn ' . implode(', ', $con) . '.', 409);
        }
    }

    // null = tất cả công ty; mảng = chỉ các công ty đó ([] = không công ty nào)
    private function congTyDuocPhep(TaiKhoan $taiKhoan, string $quyen): ?array
    {
        return $this->phamVi->idCongTyTrongPhamVi($taiKhoan, $this->phanQuyen->phamVi($taiKhoan, $quyen));
    }

    // Truy vấn nền: công ty kèm tên công ty cha và ba con số đếm
    private function truyVan()
    {
        return DB::table('CongTy as c')
            ->leftJoin('CongTy as cha', 'cha.ID_CongTy', '=', 'c.ID_CongTyCha')
            ->select('c.*', 'cha.Ten_CongTy as Ten_CongTyCha')
            ->selectSub(fn ($q) => $q->from('NhanVien')->selectRaw('COUNT(*)')
                ->whereColumn('NhanVien.ID_CongTy', 'c.ID_CongTy')->whereIn('NhanVien.Trang_Thai', [1, 2]), 'so_nhan_vien')
            ->selectSub(fn ($q) => $q->from('PhongBan')->selectRaw('COUNT(*)')
                ->whereColumn('PhongBan.ID_CongTy', 'c.ID_CongTy')->where('PhongBan.Trang_Thai', 1), 'so_phong_ban')
            ->selectSub(fn ($q) => $q->from('CongTy as k')->selectRaw('COUNT(*)')
                ->whereColumn('k.ID_CongTyCha', 'c.ID_CongTy')->where('k.Trang_Thai', 1), 'so_cong_ty_con')
            ->orderBy('c.Loai_CongTy')
            ->orderBy('c.ID_CongTy');
    }

    // Đọc lại một công ty sau khi ghi (không kiểm phạm vi vì vừa được phép ghi)
    private function laySau(int $id): array
    {
        return $this->dong($this->truyVan()->where('c.ID_CongTy', $id)->first());
    }

    private function dong(object $c): array
    {
        return [
            'id'              => (int) $c->ID_CongTy,
            'ma'              => $c->Ma_CongTy,
            'ten'             => $c->Ten_CongTy,
            'loai'            => (int) $c->Loai_CongTy,
            'ten_loai'        => self::LOAI[(int) $c->Loai_CongTy] ?? '—',
            'id_cong_ty_cha'  => $c->ID_CongTyCha !== null ? (int) $c->ID_CongTyCha : null,
            'ten_cong_ty_cha' => $c->Ten_CongTyCha,
            'dia_diem'        => $c->Dia_Diem,
            'sdt'             => $c->SDT_CongTy,
            'email'           => $c->Email_CongTy,
            'ngay_thanh_lap'  => $c->Ngay_ThanhLap ? substr((string) $c->Ngay_ThanhLap, 0, 10) : null,
            'trang_thai'      => (int) $c->Trang_Thai,
            'ten_trang_thai'  => self::TRANG_THAI[(int) $c->Trang_Thai] ?? '—',
            'so_nhan_vien'    => (int) $c->so_nhan_vien,
            'so_phong_ban'    => (int) $c->so_phong_ban,
            'so_cong_ty_con'  => (int) $c->so_cong_ty_con,
        ];
    }
}
