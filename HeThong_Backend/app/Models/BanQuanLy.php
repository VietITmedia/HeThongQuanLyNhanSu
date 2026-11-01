<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BanQuanLy extends Model
{
    protected $table = 'BanQuanLy';
    protected $primaryKey = 'ID_BanQuanLy';
    public $timestamps = false;

    protected $fillable = [
        'ID_NhanVien', 'ID_NguoiQuanLy', 'Loai_QuanLy', 'Ngay_BatDau', 'Ngay_KetThuc', 'Ghi_Chu',
    ];

    protected function casts(): array
    {
        return ['Ngay_BatDau' => 'date', 'Ngay_KetThuc' => 'date'];
    }

    public function nhanVien()     { return $this->belongsTo(NhanVien::class, 'ID_NhanVien',    'ID_NhanVien'); }
    public function nguoiQuanLy()  { return $this->belongsTo(NhanVien::class, 'ID_NguoiQuanLy', 'ID_NhanVien'); }

    public function scopeHieuLuc($query)  { return $query->whereNull('Ngay_KetThuc'); }
    public function scopeTrucTiep($query) { return $query->where('Loai_QuanLy', 1); }
}