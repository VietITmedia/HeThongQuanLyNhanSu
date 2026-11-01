<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaiTro extends Model
{
    protected $table = 'VaiTro';
    protected $primaryKey = 'ID_VaiTro';
    public $timestamps = false;

    protected $fillable = ['Ma_VaiTro', 'Ten_VaiTro', 'Trang_Thai'];

    public function taiKhoans()
    {
        return $this->belongsToMany(TaiKhoan::class, 'TaiKhoan_VaiTro', 'ID_VaiTro', 'ID_TaiKhoan');
    }
}