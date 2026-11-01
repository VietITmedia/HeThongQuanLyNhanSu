<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
class TaiKhoan extends Authenticatable
{
    protected $table = 'TaiKhoan';
    protected $primaryKey = 'ID_TaiKhoan';
    public $timestamps = false;

    // Chỉ ghi các cột này từ code phía máy chủ. KHÔNG bao giờ truyền $request->all() vào create()
    protected $fillable = ['ID_NhanVien', 'Ten_DangNhap', 'MatKhau_hash', 'Doi_MatKhau', 'Trang_Thai'];

    protected $hidden = ['MatKhau_hash', 'MaXacNhan_hash'];

    protected function casts(): array
    {
        return [
            'Doi_MatKhau'      => 'boolean',
            'MatKhau_HetHan'   => 'datetime',
            'MaXacNhan_HetHan' => 'datetime',
            'LanDangNhap_Cuoi' => 'datetime',
        ];
    }

    public function getAuthPassword()       { return $this->MatKhau_hash; }
    public function getRememberTokenName()  { return ''; }   // không dùng "ghi nhớ đăng nhập"

    public function nhanVien()
    {
        return $this->belongsTo(NhanVien::class, 'ID_NhanVien', 'ID_NhanVien');
    }

    public function vaiTros()
    {
        return $this->belongsToMany(VaiTro::class, 'TaiKhoan_VaiTro', 'ID_TaiKhoan', 'ID_VaiTro')
            ->withPivot('Ngay_Cap', 'ID_NguoiCap');
    }
    public static function taoTenDangNhap(string $hoTen, string $maNhanVien): string
{
    $ten = Str::lower(Str::ascii(Str::afterLast(trim($hoTen), ' '), 'vi'));   // "Đỗ Thị Hương" thành "huong"
    return $ten . '.' . Str::lower($maNhanVien);                               // "huong.nv-vg-ns-1"
}
}