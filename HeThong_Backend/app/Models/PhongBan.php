<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhongBan extends Model
{
    protected $table = 'PhongBan';
    protected $primaryKey = 'ID_PhongBan';
    public $timestamps = false;

    protected $fillable = [
        'Ma_PhongBan', 'Ten_PhongBan', 'ID_CongTy', 'ID_PhongBanCha',
        'Email_PhongBan', 'Ngay_ThanhLap', 'Trang_Thai',
    ];

    protected function casts(): array
    {
        return ['Ngay_ThanhLap' => 'date'];
    }

    public function congTy()       { return $this->belongsTo(CongTy::class,   'ID_CongTy',       'ID_CongTy'); }
    public function phongBanCha()  { return $this->belongsTo(PhongBan::class, 'ID_PhongBanCha',  'ID_PhongBan'); }
    public function phongBanCon()  { return $this->hasMany(PhongBan::class,   'ID_PhongBanCha',  'ID_PhongBan'); }
    public function nhanViens()    { return $this->hasMany(NhanVien::class,   'ID_PhongBan',     'ID_PhongBan'); }

    public function scopeHoatDong($query) { return $query->where('Trang_Thai', 1); }
}