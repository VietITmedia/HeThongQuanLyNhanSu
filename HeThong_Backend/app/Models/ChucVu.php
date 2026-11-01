<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChucVu extends Model
{
    protected $table = 'ChucVu';
    protected $primaryKey = 'ID_ChucVu';
    public $timestamps = false;

    protected $fillable = ['Ma_ChucVu', 'Ten_ChucVu', 'Mo_Ta', 'Trang_Thai'];

    public function nhanViens() { return $this->hasMany(NhanVien::class, 'ID_ChucVu', 'ID_ChucVu'); }

    public function scopeHoatDong($query) { return $query->where('Trang_Thai', 1); }
}