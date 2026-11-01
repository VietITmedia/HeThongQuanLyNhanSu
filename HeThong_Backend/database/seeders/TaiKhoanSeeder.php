<?php

namespace Database\Seeders;

use App\Models\NhanVien;
use App\Models\TaiKhoan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TaiKhoanSeeder extends Seeder
{
    private const MAT_KHAU_THU = 'Test@12345';

    public function run(): void
    {
        if (TaiKhoan::query()->exists()) {
            $this->command->warn('Bảng TaiKhoan đã có dữ liệu, không tạo thêm.');
            return;
        }

        $nhanViens = NhanVien::query()
            ->orderBy('ID_NhanVien')
            ->get(['ID_NhanVien', 'Ma_NhanVien', 'Ho_Ten']);

        if ($nhanViens->isEmpty()) {
            $this->command->error('Bảng NhanVien đang trống. Nhập nhân viên trước.');
            return;
        }

        $daDung = [];

        DB::transaction(function () use ($nhanViens, &$daDung) {
            foreach ($nhanViens as $nv) {
                $ten = TaiKhoan::taoTenDangNhap(
                    $nv->Ho_Ten,
                    $nv->Ma_NhanVien
                );

                if (isset($daDung[$ten])) {
                    throw new \RuntimeException("Trùng tên đăng nhập: $ten");
                }

                $daDung[$ten] = true;

                DB::table('TaiKhoan')->insert([
                    'ID_NhanVien'  => $nv->ID_NhanVien,
                    'Ten_DangNhap' => $ten,
                    'MatKhau_hash' => Hash::make(self::MAT_KHAU_THU),
                    'Doi_MatKhau'  => 0,
                ]);
            }
        });

        $this->command->info(
            'Đã tạo ' . $nhanViens->count() .
            ' tài khoản. Mật khẩu thử: ' . self::MAT_KHAU_THU
        );
    }
}