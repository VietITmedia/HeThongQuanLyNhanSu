<?php

namespace App\Services;use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\NhanVien;
use App\Models\TaiKhoan;
use Illuminate\Support\Facades\DB;

class NhanVienService
{
    // Các trạng thái của nhân viên (cột NhanVien.Trang_Thai).
    // Muốn thêm trạng thái mới: thêm một dòng ở đây và một dòng trong js/pages/nhanvien.js
    public const TRANG_THAI = [
        1 => 'Đang làm việc',
        2 => 'Tạm nghỉ',
        0 => 'Đã nghỉ việc',
    ];

    public function __construct(
        private PhanQuyenService $phanQuyen,
        private PhamViService $phamVi,
    ) {
    }

    /**
     * Danh sách nhân viên mà tài khoản được xem, có lọc và phân trang.
     * $loc: tu_khoa, id_cong_ty, id_phong_ban, id_chuc_vu,
     *       trang_thai (0, 1 hoặc 2; bỏ trống = đang làm và tạm nghỉ), trang, moi_trang
     */
    public function danhSach(TaiKhoan $taiKhoan, array $loc = []): array
    {
        // không chọn trạng thái: hiện người còn thuộc công ty (đang làm và tạm nghỉ); chọn thì hiện đúng trạng thái đó
        $trangThai = isset($loc['trang_thai']) && $loc['trang_thai'] !== '' ? (int) $loc['trang_thai'] : null;
        $moiTrang  = min(max((int) ($loc['moi_trang'] ?? 20), 1), 50);
        $trang     = max((int) ($loc['trang'] ?? 1), 1);

        // người đã nghỉ việc chỉ hiện với người có quyền xem đầy đủ, theo đúng phạm vi của quyền đó
        $phamViDanhSach = $trangThai === 0
            ? $this->phanQuyen->phamVi($taiKhoan, 'nhanvien.xem_day_du')
            : $this->phanQuyen->phamVi($taiKhoan, 'nhanvien.xem');

        $truyVan = $this->phamVi->ap(NhanVien::query(), $taiKhoan, $phamViDanhSach)
            ->with(['congTy', 'phongBan', 'chucVu'])
            ->when(
                $trangThai === null,
                fn ($q) => $q->whereIn('NhanVien.Trang_Thai', [1, 2]),
                fn ($q) => $q->where('NhanVien.Trang_Thai', $trangThai),
            );

        if (!empty($loc['tu_khoa'])) {
            $tuKhoa = '%' . $loc['tu_khoa'] . '%';
            $truyVan->where(fn ($q) => $q
                ->where('NhanVien.Ho_Ten', 'like', $tuKhoa)
                ->orWhere('NhanVien.Ma_NhanVien', 'like', $tuKhoa));
        }

        foreach (['id_cong_ty' => 'ID_CongTy', 'id_phong_ban' => 'ID_PhongBan', 'id_chuc_vu' => 'ID_ChucVu'] as $khoa => $cot) {
            if (!empty($loc[$khoa])) {
                $truyVan->where('NhanVien.' . $cot, (int) $loc[$khoa]);
            }
        }

        $ketQua = $truyVan
            ->orderBy('NhanVien.Ho_Ten')
            ->orderBy('NhanVien.ID_NhanVien')
            ->paginate($moiTrang, ['NhanVien.*'], 'trang', $trang);

        $bac = $this->bacCuaTungNguoi(
            $taiKhoan,
            $ketQua->pluck('ID_NhanVien')->map(fn ($id) => (int) $id)->all()
        );

        return [
            'du_lieu' => $ketQua->getCollection()
                ->map(fn (NhanVien $nv) => $this->dongNhanVien($nv, $bac[(int) $nv->ID_NhanVien]))
                ->values()
                ->all(),
            'phan_trang' => [
                'trang'     => $ketQua->currentPage(),
                'moi_trang' => $ketQua->perPage(),
                'tong'      => $ketQua->total(),
                'so_trang'  => $ketQua->lastPage(),
            ],
        ];
    }

    // Chi tiết một nhân viên; trả null nếu không tồn tại HOẶC nằm ngoài phạm vi (cùng một kết quả, để không lộ ai có thật)
    public function chiTiet(TaiKhoan $taiKhoan, int $id): ?array
    {
        $nv = NhanVien::with(['congTy', 'phongBan', 'chucVu'])->find($id);
        if (!$nv) {
            return null;
        }

        $phamVi = (int) $nv->Trang_Thai === 0
            ? $this->phanQuyen->phamVi($taiKhoan, 'nhanvien.xem_day_du')
            : $this->phanQuyen->phamVi($taiKhoan, 'nhanvien.xem');

        if (!$this->phamVi->trongPhamVi($taiKhoan, $phamVi, $id)) {
            return null;
        }

        return $this->dongNhanVien($nv, $this->bacCuaTungNguoi($taiKhoan, [$id])[$id])
            + $this->quanHe($taiKhoan, $id);
    }

    /**
     * Chuyển đổi trạng thái của một nhân viên.
     * Trả null nếu nhân viên không tồn tại HOẶC nằm ngoài phạm vi được đổi (cùng một kết quả).
     * Ném DomainException khi vi phạm luật nghiệp vụ (Controller đổi thành mã 409).
     */
    public function doiTrangThai(TaiKhoan $nguoiThucHien, int $id, int $trangThaiMoi): ?array
    {
        if (!array_key_exists($trangThaiMoi, self::TRANG_THAI)) {
            throw new \DomainException('Trạng thái không hợp lệ.');
        }

        $phamVi = $this->phanQuyen->phamVi($nguoiThucHien, 'nhanvien.doi_trang_thai');
        if (!$this->phamVi->trongPhamVi($nguoiThucHien, $phamVi, $id)) {
            return null;
        }

        if ((int) $nguoiThucHien->nhanVien?->ID_NhanVien === $id) {
            throw new \DomainException('Bạn không thể tự đổi trạng thái của chính mình.');
        }

        $nv = NhanVien::findOrFail($id);

        if ((int) $nv->Trang_Thai === $trangThaiMoi) {
            throw new \DomainException('Nhân viên đã ở trạng thái "' . self::TRANG_THAI[$trangThaiMoi] . '".');
        }

        // rời khỏi công việc (tạm nghỉ hoặc nghỉ việc) mà còn đang quản lý người khác thì phải chuyển người quản lý trước
        if ($trangThaiMoi !== 1) {
            $soCapDuoi = $this->demCapDuoiDangLam($id);

            if ($soCapDuoi > 0) {
                throw new \DomainException(
                    "Nhân viên này đang quản lý trực tiếp {$soCapDuoi} người. Hãy chuyển người quản lý của họ sang người khác trước."
                );
            }
        }

        DB::table('NhanVien')->where('ID_NhanVien', $id)->update(['Trang_Thai' => $trangThaiMoi]);

        return [
            'id'             => $id,
            'trang_thai'     => $trangThaiMoi,
            'ten_trang_thai' => self::TRANG_THAI[$trangThaiMoi],
        ];
    }
        /**
     * Thêm nhân viên mới kèm tài khoản, vai trò EMPLOYEE và (nếu có) quan hệ quản lý, trong MỘT giao dịch:
     * một bước lỗi thì cả bốn không được ghi.
     * Ném DomainException: code 403 = ngoài phạm vi được thêm, code 422 = lựa chọn không hợp lệ.
     */
    public function them(TaiKhoan $nguoiTao, array $d): array
    {
        // 1. công ty phải nằm trong phạm vi quyền "nhanvien.them" của người tạo
        $phamViThem     = $this->phanQuyen->phamVi($nguoiTao, 'nhanvien.them');
        $congTyDuocPhep = $this->phamVi->idCongTyTrongPhamVi($nguoiTao, $phamViThem);

        if ($congTyDuocPhep !== null && !in_array((int) $d['ID_CongTy'], $congTyDuocPhep, true)) {
            throw new \DomainException('Bạn không được thêm nhân viên vào công ty này.', 403);
        }

        // 2. các lựa chọn phải hợp lệ và còn hoạt động
        $congTyHopLe = DB::table('CongTy')->where('ID_CongTy', $d['ID_CongTy'])->where('Trang_Thai', 1)->exists();
        if (!$congTyHopLe) {
            throw new \DomainException('Công ty không tồn tại hoặc đã ngừng hoạt động.', 422);
        }

        $phongBan = DB::table('PhongBan')
            ->where('ID_PhongBan', $d['ID_PhongBan'])
            ->where('ID_CongTy', $d['ID_CongTy'])
            ->where('Trang_Thai', 1)
            ->first();
        if (!$phongBan) {
            throw new \DomainException('Phòng ban không thuộc công ty đã chọn hoặc đã ngừng hoạt động.', 422);
        }

               // chức vụ phải còn dùng và là chức vụ dùng chung hoặc chức vụ riêng của đúng công ty đã chọn
                // chức vụ phải còn dùng và là chức vụ dùng chung hoặc chức vụ riêng của đúng công ty đã chọn
        $chucVuHopLe = DB::table('ChucVu')
            ->where('ID_ChucVu', $d['ID_ChucVu'])
            ->where('Trang_Thai', 1)
            ->where(fn ($q) => $q->whereNull('ID_CongTy')->orWhere('ID_CongTy', $d['ID_CongTy']))
            ->exists();
        if (!$chucVuHopLe) {
            throw new \DomainException('Chức vụ không áp dụng cho công ty đã chọn hoặc đã ngừng dùng.', 422);
        }

        $idQuanLy = !empty($d['ID_QuanLy']) ? (int) $d['ID_QuanLy'] : null;
        if ($idQuanLy !== null) {
            $phamViXem = $this->phanQuyen->phamVi($nguoiTao, 'nhanvien.xem');
            $conLam    = DB::table('NhanVien')->where('ID_NhanVien', $idQuanLy)->where('Trang_Thai', 1)->exists();

            if (!$conLam || !$this->phamVi->trongPhamVi($nguoiTao, $phamViXem, $idQuanLy)) {
                throw new \DomainException('Người quản lý không hợp lệ.', 422);
            }
        }

        $idVaiTro = DB::table('VaiTro')->where('Ma_VaiTro', 'EMPLOYEE')->value('ID_VaiTro');
        if (!$idVaiTro) {
            throw new \RuntimeException('Chưa có vai trò EMPLOYEE trong bảng VaiTro.');
        }

        // 3. ghi cả bốn cùng lúc
        return DB::transaction(function () use ($nguoiTao, $d, $phongBan, $idVaiTro, $idQuanLy) {
            $ma          = $this->sinhMaNhanVien($phongBan->Ma_PhongBan);
            $tenDangNhap = $this->sinhTenDangNhap($d['Ho_Ten'], $ma);
            $matKhauTam  = Str::password(10, true, true, false, false);   // 10 ký tự, chữ và số

            $idNhanVien = DB::table('NhanVien')->insertGetId([
                'Ma_NhanVien'    => $ma,
                'Ho_Ten'         => $d['Ho_Ten'],
                'Gioi_Tinh'      => (int) $d['Gioi_Tinh'],
                'Ngay_Sinh'      => $d['Ngay_Sinh'],
                'SDT_NhanVien'   => $d['SDT_NhanVien'],
                'Email_NhanVien' => $d['Email_NhanVien'],
                'Dia_Chi'        => $d['Dia_Chi'],
                'Ngay_VaoLam'    => $d['Ngay_VaoLam'],
                'ID_CongTy'      => (int) $d['ID_CongTy'],
                'ID_PhongBan'    => (int) $d['ID_PhongBan'],
                'ID_ChucVu'      => (int) $d['ID_ChucVu'],
                'Trang_Thai'     => 1,
            ]);

            $idTaiKhoan = DB::table('TaiKhoan')->insertGetId([
                'ID_NhanVien'   => $idNhanVien,
                'Ten_DangNhap'  => $tenDangNhap,
                'MatKhau_hash'  => Hash::make($matKhauTam),
                'Trang_Thai'    => 1,
                'SoLan_NhapSai' => 0,
                'Doi_MatKhau'   => 1,                          // đòi đổi mật khẩu ở lần đăng nhập đầu
            ]);

            DB::table('TaiKhoan_VaiTro')->insert([
                'ID_TaiKhoan' => $idTaiKhoan,
                'ID_VaiTro'   => $idVaiTro,
                'Ngay_Cap'    => now()->toDateString(),
                // đổi thành $nguoiTao->ID_TaiKhoan nếu khóa ngoại của cột này trỏ về TaiKhoan
                'ID_NguoiCap' => $nguoiTao->nhanVien?->ID_NhanVien,
            ]);

            if ($idQuanLy !== null) {
                DB::table('BanQuanLy')->insert([
                    'ID_NhanVien'    => $idNhanVien,
                    'ID_NguoiQuanLy' => $idQuanLy,
                    'Loai_QuanLy'    => 1,                      // 1 = quản lý trực tiếp
                    'Ngay_BatDau'    => $d['Ngay_VaoLam'],
                    'Ngay_KetThuc'   => null,
                    'Ghi_Chu'        => null,
                ]);
            }

            return [
                'id'            => $idNhanVien,
                'ma'            => $ma,
                'ho_ten'        => $d['Ho_Ten'],
                'ten_dang_nhap' => $tenDangNhap,
                'mat_khau_tam'  => $matKhauTam,                 // chỉ hiện ĐÚNG MỘT LẦN này
            ];
        });
    }
        /**
     * Sửa hồ sơ nhân viên. Trả null nếu không tồn tại HOẶC ngoài phạm vi được sửa (cùng một kết quả).
     * Ném DomainException: code 409 = vi phạm luật nghiệp vụ, code 422 = lựa chọn không hợp lệ.
     */
    public function sua(TaiKhoan $nguoiSua, int $id, array $d): ?array
    {
        // 1. phải được phép sửa người này
        $phamViSua = $this->phanQuyen->phamVi($nguoiSua, 'nhanvien.sua');
        if (!$this->phamVi->trongPhamVi($nguoiSua, $phamViSua, $id)) {
            return null;
        }

        $nv = DB::table('NhanVien')->where('ID_NhanVien', $id)->first();
        if (!$nv) {
            return null;
        }

        if ((int) $nguoiSua->nhanVien?->ID_NhanVien === $id) {
            throw new \DomainException('Bạn không thể tự sửa hồ sơ của chính mình ở đây.', 409);
        }
        if ((int) $nv->Trang_Thai === 0) {
            throw new \DomainException('Nhân viên đã nghỉ việc, không thể sửa hồ sơ.', 409);
        }

        // 2. phòng ban phải thuộc đúng công ty hiện tại của nhân viên (chức năng này không chuyển công ty)
        $phongBanHopLe = DB::table('PhongBan')
            ->where('ID_PhongBan', $d['ID_PhongBan'])
            ->where('ID_CongTy', $nv->ID_CongTy)
            ->where('Trang_Thai', 1)
            ->exists();
        if (!$phongBanHopLe) {
            throw new \DomainException('Phòng ban không thuộc công ty của nhân viên hoặc đã ngừng hoạt động.', 422);
        }

        // 3. chức vụ phải còn dùng và là chức vụ dùng chung hoặc chức vụ riêng của công ty của nhân viên
        $chucVuHopLe = DB::table('ChucVu')
            ->where('ID_ChucVu', $d['ID_ChucVu'])
            ->where('Trang_Thai', 1)
            ->where(fn ($q) => $q->whereNull('ID_CongTy')->orWhere('ID_CongTy', $nv->ID_CongTy))
            ->exists();
        if (!$chucVuHopLe) {
            throw new \DomainException('Chức vụ không áp dụng cho công ty của nhân viên hoặc đã ngừng dùng.', 422);
        }

        // 4. người quản lý (chỉ kiểm tra khi có gửi khóa ID_QuanLy)
        $doiQuanLy = array_key_exists('ID_QuanLy', $d);
        $idQuanLy  = $doiQuanLy && !empty($d['ID_QuanLy']) ? (int) $d['ID_QuanLy'] : null;

        if ($idQuanLy !== null) {
            if ($idQuanLy === $id) {
                throw new \DomainException('Nhân viên không thể tự làm quản lý của chính mình.', 422);
            }

            $phamViXem = $this->phanQuyen->phamVi($nguoiSua, 'nhanvien.xem');
            $conLam    = DB::table('NhanVien')->where('ID_NhanVien', $idQuanLy)->where('Trang_Thai', 1)->exists();
            if (!$conLam || !$this->phamVi->trongPhamVi($nguoiSua, $phamViXem, $idQuanLy)) {
                throw new \DomainException('Người quản lý không hợp lệ.', 422);
            }

            if ($this->laCapDuoiCua($idQuanLy, $id)) {
                throw new \DomainException('Không thể chọn người đang là cấp dưới của nhân viên này làm quản lý (tạo vòng lặp).', 422);
            }
        }

        // 5. ghi hồ sơ và người quản lý cùng một giao dịch
        DB::transaction(function () use ($id, $d, $doiQuanLy, $idQuanLy) {
            DB::table('NhanVien')->where('ID_NhanVien', $id)->update([
                'Ho_Ten'         => $d['Ho_Ten'],
                'Gioi_Tinh'      => (int) $d['Gioi_Tinh'],
                'Ngay_Sinh'      => $d['Ngay_Sinh'],
                'SDT_NhanVien'   => $d['SDT_NhanVien'],
                'Email_NhanVien' => $d['Email_NhanVien'],
                'Dia_Chi'        => $d['Dia_Chi'],
                'Ngay_VaoLam'    => $d['Ngay_VaoLam'],
                'ID_PhongBan'    => (int) $d['ID_PhongBan'],
                'ID_ChucVu'      => (int) $d['ID_ChucVu'],
            ]);

            if ($doiQuanLy) {
                $this->doiQuanLy($id, $idQuanLy);
            }
        });

        return $this->chiTiet($nguoiSua, $id);
    }

    // Đổi người quản lý trực tiếp: đóng quan hệ cũ (không xóa, để còn lịch sử) rồi mở quan hệ mới từ hôm nay
    private function doiQuanLy(int $idNhanVien, ?int $idQuanLyMoi): void
    {
        $homNay = now()->toDateString();
        $homQua = now()->subDay()->toDateString();

        $hienTai = DB::table('BanQuanLy')
            ->where('ID_NhanVien', $idNhanVien)
            ->where('Loai_QuanLy', 1)
            ->where('Ngay_BatDau', '<=', $homNay)
            ->where(fn ($q) => $q->whereNull('Ngay_KetThuc')->orWhere('Ngay_KetThuc', '>=', $homNay))
            ->first();

        if ($hienTai && (int) $hienTai->ID_NguoiQuanLy === $idQuanLyMoi) {
            return;                                           // không đổi gì
        }

        if ($hienTai) {
            if (substr((string) $hienTai->Ngay_BatDau, 0, 10) === $homNay) {
                // quan hệ mới tạo hôm nay: sửa tại chỗ, tránh có hai quan hệ cùng hiệu lực trong một ngày
                $dong = DB::table('BanQuanLy')->where('ID_BanQuanLy', $hienTai->ID_BanQuanLy);
                $idQuanLyMoi === null ? $dong->delete() : $dong->update(['ID_NguoiQuanLy' => $idQuanLyMoi]);

                return;
            }

            DB::table('BanQuanLy')->where('ID_BanQuanLy', $hienTai->ID_BanQuanLy)->update(['Ngay_KetThuc' => $homQua]);
        }

        if ($idQuanLyMoi !== null) {
            DB::table('BanQuanLy')->insert([
                'ID_NhanVien'    => $idNhanVien,
                'ID_NguoiQuanLy' => $idQuanLyMoi,
                'Loai_QuanLy'    => 1,
                'Ngay_BatDau'    => $homNay,
                'Ngay_KetThuc'   => null,
                'Ghi_Chu'        => null,
            ]);
        }
    }

    // $idNguoi có đang là cấp dưới (trực tiếp hoặc gián tiếp) của $idQuanLy không?
    // Dùng để chặn vòng lặp: không được chọn cấp dưới của mình làm quản lý của mình.
    private function laCapDuoiCua(int $idNguoi, int $idQuanLy): bool
    {
        $homNay = now()->toDateString();

        $kq = DB::select(
            "WITH RECURSIVE cay AS (
                SELECT b.ID_NhanVien FROM BanQuanLy b
                WHERE b.ID_NguoiQuanLy = ? AND b.Loai_QuanLy = 1 AND b.Ngay_BatDau <= ? AND (b.Ngay_KetThuc IS NULL OR b.Ngay_KetThuc >= ?)
                UNION
                SELECT b.ID_NhanVien FROM BanQuanLy b JOIN cay c ON b.ID_NguoiQuanLy = c.ID_NhanVien
                WHERE b.Loai_QuanLy = 1 AND b.Ngay_BatDau <= ? AND (b.Ngay_KetThuc IS NULL OR b.Ngay_KetThuc >= ?)
            ) SELECT 1 AS co FROM cay WHERE ID_NhanVien = ? LIMIT 1",
            [$idQuanLy, $homNay, $homNay, $homNay, $homNay, $idNguoi]
        );

        return !empty($kq);
    }
    // Mã dạng NV-<mã phòng ban>-<số thứ tự trong phòng>, ví dụ phòng VC-VH thì NV-VC-VH-1.
    // (Ma_PhongBan đã chứa mã công ty nên KHÔNG ghép thêm Ma_CongTy.)
    // lockForUpdate: hai người tạo cùng lúc phải xếp hàng, không sinh trùng số.
    private function sinhMaNhanVien(string $maPhongBan): string
    {
        $tienTo = 'NV-' . $maPhongBan . '-';

        $soLonNhat = DB::table('NhanVien')
            ->where('Ma_NhanVien', 'like', $tienTo . '%')
            ->lockForUpdate()
            ->pluck('Ma_NhanVien')
            ->map(fn ($ma) => (int) substr($ma, strlen($tienTo)))
            ->max() ?? 0;

        return $tienTo . ($soLonNhat + 1);
    }

    // Tên gọi (từ cuối của họ tên) bỏ dấu + "." + mã nhân viên viết thường, ví dụ dung.nv-vc-vh-1
    private function sinhTenDangNhap(string $hoTen, string $ma): string
    {
        $hoTen  = str_replace(['đ', 'Đ'], ['d', 'D'], $hoTen);
        $tuCuoi = Str::lower(Str::ascii(Arr::last(preg_split('/\s+/u', trim($hoTen)))));
        $tuCuoi = preg_replace('/[^a-z0-9]/', '', $tuCuoi);

        return $tuCuoi . '.' . Str::lower($ma);
    }
    // Số người đang làm việc mà nhân viên này đang quản lý trực tiếp (quan hệ còn hiệu lực hôm nay)
    private function demCapDuoiDangLam(int $idQuanLy): int
    {
        $homNay = now()->toDateString();

        return DB::table('BanQuanLy')
            ->join('NhanVien', 'NhanVien.ID_NhanVien', '=', 'BanQuanLy.ID_NhanVien')
            ->where('BanQuanLy.ID_NguoiQuanLy', $idQuanLy)
            ->where('BanQuanLy.Loai_QuanLy', 1)
            ->where('BanQuanLy.Ngay_BatDau', '<=', $homNay)
            ->where(fn ($q) => $q
                ->whereNull('BanQuanLy.Ngay_KetThuc')
                ->orWhere('BanQuanLy.Ngay_KetThuc', '>=', $homNay))
            ->where('NhanVien.Trang_Thai', 1)
            ->count();
    }

    // Cấp trên trực tiếp và cấp dưới trực tiếp (quan hệ còn hiệu lực hôm nay).
    // Chỉ liệt kê người nằm trong phạm vi xem của tài khoản, để không lộ người ngoài phạm vi.
    private function quanHe(TaiKhoan $taiKhoan, int $idNhanVien): array
    {
        $phamViXem = $this->phanQuyen->phamVi($taiKhoan, 'nhanvien.xem');
        $homNay    = now()->toDateString();

        // $cotCanLay: cột trong BanQuanLy trỏ tới những người ta muốn liệt kê
        // $cotDaBiet: cột trong BanQuanLy trỏ tới nhân viên đang xem (người đã biết)
        $lay = function (string $cotCanLay, string $cotDaBiet) use ($taiKhoan, $phamViXem, $idNhanVien, $homNay): array {
            return $this->phamVi->ap(NhanVien::query(), $taiKhoan, $phamViXem)
                ->join('BanQuanLy', 'BanQuanLy.' . $cotCanLay, '=', 'NhanVien.ID_NhanVien')
                ->where('BanQuanLy.' . $cotDaBiet, $idNhanVien)
                ->where('BanQuanLy.Loai_QuanLy', 1)                      // 1 = quản lý trực tiếp
                ->where('BanQuanLy.Ngay_BatDau', '<=', $homNay)
                ->where(fn ($q) => $q
                    ->whereNull('BanQuanLy.Ngay_KetThuc')
                    ->orWhere('BanQuanLy.Ngay_KetThuc', '>=', $homNay))
                ->whereIn('NhanVien.Trang_Thai', [1, 2])
                ->with('chucVu')
                ->orderBy('NhanVien.Ho_Ten')
                ->get(['NhanVien.*'])
                ->map(fn (NhanVien $n) => [
                    'id'      => (int) $n->ID_NhanVien,
                    'ma'      => $n->Ma_NhanVien,
                    'ho_ten'  => $n->Ho_Ten,
                    'chuc_vu' => $n->chucVu?->Ten_ChucVu,
                ])
                ->values()
                ->all();
        };

        return [
            // cấp trên: những người đang quản lý tôi (tôi là ID_NhanVien trong BanQuanLy)
            'cap_tren' => $lay('ID_NguoiQuanLy', 'ID_NhanVien'),
            // cấp dưới: những người tôi đang quản lý (tôi là ID_NguoiQuanLy trong BanQuanLy)
            'cap_duoi' => $lay('ID_NhanVien', 'ID_NguoiQuanLy'),
        ];
    }

    // Với từng ID: bậc cao nhất mà tài khoản được thấy (1, 2 hoặc 3). Mỗi quyền chỉ tốn một truy vấn cho cả trang
    private function bacCuaTungNguoi(TaiKhoan $taiKhoan, array $cacId): array
    {
        $bac = array_fill_keys($cacId, 1);

        foreach ([2 => 'nhanvien.xem_lien_he', 3 => 'nhanvien.xem_day_du'] as $muc => $quyen) {
            $phamVi = $this->phanQuyen->phamVi($taiKhoan, $quyen);
            if ($phamVi === null) {
                continue;                                  // không có quyền này: không ai được nâng lên bậc đó
            }

            $trongPhamVi = $this->phamVi->ap(NhanVien::query(), $taiKhoan, $phamVi)
                ->whereIn('NhanVien.ID_NhanVien', $cacId)
                ->pluck('NhanVien.ID_NhanVien')
                ->all();

            foreach ($trongPhamVi as $id) {
                $bac[(int) $id] = max($bac[(int) $id], $muc);
            }
        }

        return $bac;
    }

    // Chỉ đưa vào kết quả những trường mà bậc cho phép
    private function dongNhanVien(NhanVien $nv, int $bac): array
    {
        $dong = [
            'id'        => (int) $nv->ID_NhanVien,
            'ma'        => $nv->Ma_NhanVien,
            'ho_ten'    => $nv->Ho_Ten,
            'gioi_tinh' => $nv->Gioi_Tinh,
            'cong_ty'   => $nv->congTy?->Ten_CongTy,
            'phong_ban' => $nv->phongBan?->Ten_PhongBan,
            'chuc_vu'   => $nv->chucVu?->Ten_ChucVu,
            'bac'       => $bac,
            'id_cong_ty'   => $nv->ID_CongTy !== null ? (int) $nv->ID_CongTy : null,
            'id_phong_ban' => $nv->ID_PhongBan !== null ? (int) $nv->ID_PhongBan : null,
            'id_chuc_vu'   => $nv->ID_ChucVu !== null ? (int) $nv->ID_ChucVu : null,
        ];

        if ($bac >= 2) {
            $dong['email'] = $nv->Email_NhanVien;
            $dong['sdt']   = $nv->SDT_NhanVien;
        }

        if ($bac >= 3) {
            $dong['ngay_sinh']    = $this->ngay($nv->Ngay_Sinh);
            $dong['dia_chi']      = $nv->Dia_Chi;
            $dong['ngay_vao_lam'] = $this->ngay($nv->Ngay_VaoLam);
            $dong['trang_thai']   = (int) $nv->Trang_Thai;
        }

        return $dong;
    }

    private function ngay($giaTri): ?string
    {
        return $giaTri instanceof \DateTimeInterface ? $giaTri->format('Y-m-d') : $giaTri;
    }
}