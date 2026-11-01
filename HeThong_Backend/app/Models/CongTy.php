<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CongTy extends Model
{
    protected $table = 'CongTy';
    protected $primaryKey = 'ID_CongTy';
    public $timestamps = false;

    protected $fillable = [
        'Ma_CongTy', 'Ten_CongTy', 'Loai_CongTy', 'ID_CongTyCha',
        'Dia_Diem', 'SDT_CongTy', 'Email_CongTy', 'Ngay_ThanhLap', 'Trang_Thai',
    ];

    protected function casts(): array
    {
        return ['Ngay_ThanhLap' => 'date'];
    }

    public function congTyCha() { return $this->belongsTo(CongTy::class, 'ID_CongTyCha', 'ID_CongTy'); }
    public function congTyCon() { return $this->hasMany(CongTy::class, 'ID_CongTyCha', 'ID_CongTy'); }
    public function phongBans() { return $this->hasMany(PhongBan::class, 'ID_CongTy', 'ID_CongTy'); }
    public function nhanViens() { return $this->hasMany(NhanVien::class, 'ID_CongTy', 'ID_CongTy'); }

    public function scopeHoatDong($query) { return $query->where('Trang_Thai', 1); }
}
