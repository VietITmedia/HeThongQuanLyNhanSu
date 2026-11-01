<?php

namespace App\Services;

use App\Mail\OtpDoiMatKhauMail;
use App\Models\TaiKhoan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DoiMatKhauService
{
    private const PHUT_HIEU_LUC     = 10;   // OTP sống bao lâu
    private const TOI_DA_SAI        = 5;    // nhập sai OTP quá số này thì phải xin mã mới
    private const GIAY_GIUA_HAI_LAN = 60;   // hai lần gửi mã phải cách nhau
    private const TOI_DA_LAN_GUI    = 3;    // mỗi yêu cầu gửi tối đa bấy nhiêu mã
    private const TOI_DA_YEU_CAU    = 5;    // tối đa bấy nhiêu yêu cầu trong 15 phút
    private const TOI_DA_SAI_MAT_KHAU_CU = 5;    // nhập sai mật khẩu hiện tại quá số này thì khóa chức năng đổi mật khẩu
    private const PHUT_KHOA              = 30;   // khóa bao lâu
    // Trạng thái hiện tại của yêu cầu đang chờ (giao diện dùng để hiện đếm ngược, kể cả khi tải lại trang)
    public function trangThai(TaiKhoan $tk): array
    {
        $yc = $this->yeuCauDangCho($tk);
        if (!$yc) {
            return ['dang_cho' => false];
        }

        $bay = now()->timestamp;

        return [
            'dang_cho'         => true,
            'email_che'        => $this->cheEmail((string) $tk->nhanVien?->Email_NhanVien),
            'het_han_sau_giay' => max(Carbon::parse($yc->Het_Han)->timestamp - $bay, 0),
            'gui_lai_sau_giay' => max(self::GIAY_GIUA_HAI_LAN - ($bay - Carbon::parse($yc->Lan_Gui_Cuoi)->timestamp), 0),
            'con_lan_gui'      => max(self::TOI_DA_LAN_GUI - (int) $yc->So_Lan_Gui, 0),
            'con_lan_thu'      => max(self::TOI_DA_SAI - (int) $yc->So_Lan_Sai, 0),
        ];
    }

    /**
     * Bước 3 và 4: nhận mật khẩu mới (đã qua kiểm tra độ mạnh ở Request), lưu bản băm ở trạng thái chờ, gửi OTP qua email.
     * Ném DomainException: code 409 = thiếu email, 422 = mật khẩu không hợp lệ, 429 = yêu cầu quá nhiều, 503 = gửi thư lỗi.
     */
    public function taoYeuCau(TaiKhoan $tk, string $matKhauMoi): array
    {
        $email = $tk->nhanVien?->Email_NhanVien;
        if (!$email) {
            throw new \DomainException('Tài khoản chưa có email để nhận mã xác minh, hãy liên hệ bộ phận nhân sự.', 409);
        }

        // mật khẩu mới phải khác mật khẩu hiện tại (mật khẩu tạm) và không chứa tên đăng nhập
        if (Hash::check($matKhauMoi, $tk->MatKhau_hash)) {
            throw new \DomainException('Mật khẩu mới phải khác mật khẩu hiện tại.', 422);
        }
        if (mb_stripos($matKhauMoi, $tk->Ten_DangNhap) !== false) {
            throw new \DomainException('Mật khẩu không được chứa tên đăng nhập.', 422);
        }

        $ganDay = DB::table('YeuCauDoiMatKhau')
            ->where('ID_TaiKhoan', $tk->ID_TaiKhoan)
            ->where('Ngay_Tao', '>=', now()->subMinutes(15))
            ->count();
        if ($ganDay >= self::TOI_DA_YEU_CAU) {
            throw new \DomainException('Bạn yêu cầu quá nhiều lần, hãy thử lại sau ít phút.', 429);
        }

        $otp = $this->sinhOtp();

        $idYeuCau = DB::transaction(function () use ($tk, $matKhauMoi, $otp) {
            // chỉ giữ yêu cầu mới nhất: các yêu cầu đang chờ cũ chuyển thành "bị thay thế" (2), không xóa để còn đếm giới hạn
            DB::table('YeuCauDoiMatKhau')->where('ID_TaiKhoan', $tk->ID_TaiKhoan)->where('Trang_Thai', 0)->update(['Trang_Thai' => 2]);

            return DB::table('YeuCauDoiMatKhau')->insertGetId([
                'ID_TaiKhoan'      => $tk->ID_TaiKhoan,
                'MatKhau_Moi_Hash' => Hash::make($matKhauMoi),
                'OTP_Hash'         => Hash::make($otp),
                'Het_Han'          => now()->addMinutes(self::PHUT_HIEU_LUC),
                'So_Lan_Sai'       => 0,
                'So_Lan_Gui'       => 1,
                'Lan_Gui_Cuoi'     => now(),
                'Trang_Thai'       => 0,
                'Ngay_Tao'         => now(),
            ]);
        });

        $this->guiThu($tk, $email, $otp, $idYeuCau);

        return $this->trangThai($tk);
    }

    /**
     * Bước 4 (gửi lại): sinh OTP mới, mã cũ hết hiệu lực. Cách nhau tối thiểu 60 giây, tối đa 3 lần gửi mỗi yêu cầu.
     */
    public function guiLai(TaiKhoan $tk): array
    {
        $email = $tk->nhanVien?->Email_NhanVien;
        if (!$email) {
            throw new \DomainException('Tài khoản chưa có email để nhận mã xác minh, hãy liên hệ bộ phận nhân sự.', 409);
        }

        $otp = $this->sinhOtp();

        // kiểm tra và ghi trong giao dịch, khóa dòng để hai lần bấm cùng lúc không qua được giới hạn
        $ket = DB::transaction(function () use ($tk, $otp) {
            $yc = DB::table('YeuCauDoiMatKhau')
                ->where('ID_TaiKhoan', $tk->ID_TaiKhoan)->where('Trang_Thai', 0)
                ->orderByDesc('ID_YeuCau')->lockForUpdate()->first();

            if (!$yc) {
                return ['Chưa có yêu cầu đổi mật khẩu nào, hãy nhập mật khẩu mới để nhận mã.', 422, null];
            }
            if ((int) $yc->So_Lan_Gui >= self::TOI_DA_LAN_GUI) {
                return ['Bạn đã dùng hết số lần gửi mã, hãy nhập lại mật khẩu mới để tạo yêu cầu mới.', 429, null];
            }

            $cho = self::GIAY_GIUA_HAI_LAN - (now()->timestamp - Carbon::parse($yc->Lan_Gui_Cuoi)->timestamp);
            if ($cho > 0) {
                return ["Hãy đợi {$cho} giây nữa rồi gửi lại mã.", 429, null];
            }

            DB::table('YeuCauDoiMatKhau')->where('ID_YeuCau', $yc->ID_YeuCau)->update([
                'OTP_Hash'     => Hash::make($otp),
                'Het_Han'      => now()->addMinutes(self::PHUT_HIEU_LUC),
                'Lan_Gui_Cuoi' => now(),
                'So_Lan_Gui'   => (int) $yc->So_Lan_Gui + 1,
                'So_Lan_Sai'   => 0,
            ]);

            return [null, null, $yc->ID_YeuCau];
        });

        if ($ket[0] !== null) {
            throw new \DomainException($ket[0], $ket[1]);
        }

        $this->guiThu($tk, $email, $otp, (int) $ket[2]);

        return $this->trangThai($tk);
    }

    /**
     * Bước 5 và 6: kiểm tra OTP; đúng thì ghi mật khẩu mới, tắt cờ bắt đổi, đóng yêu cầu.
     * Ném DomainException: code 422 = mã sai, hết hạn hoặc chưa có yêu cầu; code 429 = sai quá số lần.
     */
    public function xacNhan(TaiKhoan $tk, string $otp): array
    {
        // lỗi được trả về rồi mới ném SAU khi giao dịch xong, để số lần sai đã tăng không bị hoàn tác
        $loi = DB::transaction(function () use ($tk, $otp) {
            $yc = DB::table('YeuCauDoiMatKhau')
                ->where('ID_TaiKhoan', $tk->ID_TaiKhoan)->where('Trang_Thai', 0)
                ->orderByDesc('ID_YeuCau')->lockForUpdate()->first();

            if (!$yc) {
                return ['Chưa có yêu cầu đổi mật khẩu nào, hãy nhập mật khẩu mới để nhận mã.', 422];
            }
            if (Carbon::parse($yc->Het_Han)->isPast()) {
                return ['Mã đã hết hạn, hãy bấm "Gửi lại mã".', 422];
            }
            if ((int) $yc->So_Lan_Sai >= self::TOI_DA_SAI) {
                return ['Bạn đã nhập sai quá nhiều lần, hãy bấm "Gửi lại mã" để nhận mã mới.', 429];
            }

            if (!Hash::check($otp, $yc->OTP_Hash)) {
                DB::table('YeuCauDoiMatKhau')->where('ID_YeuCau', $yc->ID_YeuCau)->increment('So_Lan_Sai');
                $con = self::TOI_DA_SAI - ((int) $yc->So_Lan_Sai + 1);

                return [$con > 0 ? "Mã không đúng, còn {$con} lần thử." : 'Mã không đúng. Bạn đã hết lượt thử, hãy bấm "Gửi lại mã".', 422];
            }

            // đúng: đổi mật khẩu, tắt cờ bắt đổi, đóng yêu cầu (một mã chỉ dùng một lần)
            DB::table('TaiKhoan')->where('ID_TaiKhoan', $tk->ID_TaiKhoan)->update([
                'MatKhau_hash'  => $yc->MatKhau_Moi_Hash,
                'Doi_MatKhau'   => 0,
                'SoLan_NhapSai' => 0,
            ]);
            DB::table('YeuCauDoiMatKhau')->where('ID_YeuCau', $yc->ID_YeuCau)->update(['Trang_Thai' => 1]);

            return null;
        });

        if ($loi !== null) {
            throw new \DomainException($loi[0], $loi[1]);
        }

        return ['da_doi' => true];
    }

        // Số giây còn lại của lần khóa chức năng đổi mật khẩu (0 = không bị khóa)
    public function giayKhoaConLai(TaiKhoan $tk): int
    {
        return $this->giayKhoa($tk->KhoaDoiMatKhau_Den);
    }

    private function giayKhoa($den): int
    {
        return $den ? max(Carbon::parse($den)->timestamp - now()->timestamp, 0) : 0;
    }

    private function cauKhoa(int $giay): string
    {
        return 'Bạn đã nhập sai mật khẩu hiện tại quá ' . self::TOI_DA_SAI_MAT_KHAU_CU
            . ' lần, chức năng đổi mật khẩu bị khóa. Hãy thử lại sau ' . (int) ceil($giay / 60) . ' phút.';
    }

    /**
     * Bước nhập mật khẩu hiện tại (chỉ người đổi chủ động): sai tối đa 5 lần, quá thì khóa chức năng này 30 phút.
     * Ném DomainException: 409 = đang dùng mật khẩu tạm (đi đường mã email), 422 = sai, 423 = đang bị khóa.
     */
    public function kiemTraMatKhauCu(TaiKhoan $tk, string $matKhauCu): void
    {
        // người còn mật khẩu tạm đi đường có mã email, không có bước này
        if ((int) $tk->Doi_MatKhau === 1) {
            throw new \DomainException('Bạn đang dùng mật khẩu tạm, hãy đặt mật khẩu mới và xác minh bằng mã email.', 409);
        }

        // kiểm tra và ghi trong một giao dịch, khóa dòng tài khoản: nhiều yêu cầu cùng lúc không đoán lố được số lần
        // lỗi được trả về rồi mới ném SAU khi giao dịch xong, để số lần sai đã tăng không bị hoàn tác
        $loi = DB::transaction(function () use ($tk, $matKhauCu) {
            $t = DB::table('TaiKhoan')->where('ID_TaiKhoan', $tk->ID_TaiKhoan)->lockForUpdate()->first();

            $giay = $this->giayKhoa($t->KhoaDoiMatKhau_Den);
            if ($giay > 0) {
                return [$this->cauKhoa($giay), 423];
            }

            if (!Hash::check($matKhauCu, $t->MatKhau_hash)) {
                $sai = (int) $t->SoLan_SaiMatKhauCu + 1;

                if ($sai >= self::TOI_DA_SAI_MAT_KHAU_CU) {
                    DB::table('TaiKhoan')->where('ID_TaiKhoan', $t->ID_TaiKhoan)->update([
                        'SoLan_SaiMatKhauCu' => 0,
                        'KhoaDoiMatKhau_Den' => now()->addMinutes(self::PHUT_KHOA),
                    ]);

                    return [$this->cauKhoa(self::PHUT_KHOA * 60), 423];
                }

                DB::table('TaiKhoan')->where('ID_TaiKhoan', $t->ID_TaiKhoan)->update(['SoLan_SaiMatKhauCu' => $sai]);

                return ['Mật khẩu hiện tại không đúng, còn ' . (self::TOI_DA_SAI_MAT_KHAU_CU - $sai) . ' lần thử.', 422];
            }

            DB::table('TaiKhoan')->where('ID_TaiKhoan', $t->ID_TaiKhoan)->update(['SoLan_SaiMatKhauCu' => 0, 'KhoaDoiMatKhau_Den' => null]);

            return null;
        });

        if ($loi !== null) {
            throw new \DomainException($loi[0], $loi[1]);
        }
    }
    private function guiThu(TaiKhoan $tk, string $email, string $otp, int $idYeuCau): void
    {
        try {
            Mail::to($email)->send(new OtpDoiMatKhauMail((string) $tk->nhanVien?->Ho_Ten, $otp, self::PHUT_HIEU_LUC));
        } catch (\Throwable $e) {
            DB::table('YeuCauDoiMatKhau')->where('ID_YeuCau', $idYeuCau)->update(['Trang_Thai' => 2]);
            Log::error('Không gửi được email OTP', ['id_tai_khoan' => $tk->ID_TaiKhoan, 'loi' => $e->getMessage()]);

            throw new \DomainException('Không gửi được email xác minh, hãy thử lại sau.', 503);
        }

        // CHỈ khi dùng mailer "log" (phát triển, demo): ghi mã ra log để lấy mã khi chưa có hộp thư thật.
        // Đổi sang SMTP thật thì dòng này tự không chạy.
        if (config('mail.default') === 'log') {
            Log::info('[OTP thử nghiệm] ' . $otp, ['id_tai_khoan' => $tk->ID_TaiKhoan]);
        }
    }

    private function yeuCauDangCho(TaiKhoan $tk): ?object
    {
        return DB::table('YeuCauDoiMatKhau')
            ->where('ID_TaiKhoan', $tk->ID_TaiKhoan)->where('Trang_Thai', 0)
            ->orderByDesc('ID_YeuCau')->first();
    }

    // 6 chữ số từ bộ sinh số ngẫu nhiên an toàn của PHP
    private function sinhOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // an.kt@example.com -> a*****@example.com
    private function cheEmail(string $email): string
    {
        $vi = strpos($email, '@');

        return $vi === false ? '***' : mb_substr($email, 0, 1) . str_repeat('*', max($vi - 1, 2)) . substr($email, $vi);
    }
}