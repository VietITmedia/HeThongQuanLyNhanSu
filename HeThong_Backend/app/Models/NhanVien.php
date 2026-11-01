<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NhanVien extends Model
{
    protected $table = 'NhanVien';
    protected $primaryKey = 'ID_NhanVien';
    public $timestamps = false;

    protected $fillable = [
        'Ma_NhanVien', 'Ho_Ten', 'Gioi_Tinh', 'Ngay_Sinh', 'SDT_NhanVien', 'Email_NhanVien',
        'Dia_Chi', 'Ngay_VaoLam', 'ID_CongTy', 'ID_PhongBan', 'ID_ChucVu', 'Trang_Thai',
    ];

    protected function casts(): array
    {
        return ['Ngay_Sinh' => 'date', 'Ngay_VaoLam' => 'date'];
    }

    public function congTy()    { return $this->belongsTo(CongTy::class,   'ID_CongTy',   'ID_CongTy'); }
    public function phongBan()  { return $this->belongsTo(PhongBan::class, 'ID_PhongBan', 'ID_PhongBan'); }
    public function chucVu()    { return $this->belongsTo(ChucVu::class,   'ID_ChucVu',   'ID_ChucVu'); }
    public function taiKhoan()  { return $this->hasOne(TaiKhoan::class,    'ID_NhanVien', 'ID_NhanVien'); }
    public function thanNhans() { return $this->hasMany(ThanNhan::class,   'ID_NhanVien', 'ID_NhanVien'); }

    // Ai quản lý trực tiếp tôi (dòng còn hiệu lực)
    public function quanLyTrucTiep()
    {
        return $this->belongsToMany(NhanVien::class, 'BanQuanLy', 'ID_NhanVien', 'ID_NguoiQuanLy')
            ->wherePivot('Loai_QuanLy', 1)->wherePivotNull('Ngay_KetThuc');
    }

    // Tôi quản lý trực tiếp những ai
    public function capDuoiTrucTiep()
    {
        return $this->belongsToMany(NhanVien::class, 'BanQuanLy', 'ID_NguoiQuanLy', 'ID_NhanVien')
            ->wherePivot('Loai_QuanLy', 1)->wherePivotNull('Ngay_KetThuc');
    }

    public function scopeDangLam($query) { return $query->where('Trang_Thai', 1); }
}