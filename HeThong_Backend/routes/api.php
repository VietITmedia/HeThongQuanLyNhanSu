<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChucVuController;
use App\Http\Controllers\DanhMucController;
use App\Http\Controllers\NhanVienController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CongTyController;
use App\Http\Controllers\PhongBanController;
use App\Http\Controllers\DoiMatKhauController;

Route::post('/dang-nhap', [AuthController::class, 'dangNhap'])
    ->middleware('throttle:60,1');   // nhớ đổi về 5,1 khi chạy thật

Route::middleware(['auth:sanctum', 'hoat.dong','doi.mat.khau'])->group(function () {

    Route::get('/toi', [AuthController::class, 'toi']);
    Route::post('/dang-xuat', [AuthController::class, 'dangXuat']);

    // ----- Chức vụ -----
    Route::get('/chuc-vu', [ChucVuController::class, 'index'])->middleware('quyen:chucvu.xem');
    Route::get('/chuc-vu/{chuc_vu}', [ChucVuController::class, 'show'])->middleware('quyen:chucvu.xem');
    Route::post('/chuc-vu', [ChucVuController::class, 'store'])->middleware('quyen:chucvu.them');
    Route::put('/chuc-vu/{chuc_vu}', [ChucVuController::class, 'update'])->middleware('quyen:chucvu.sua');
    Route::delete('/chuc-vu/{chuc_vu}', [ChucVuController::class, 'destroy'])->middleware('quyen:chucvu.ngung');

    // ----- Nhân viên -----
    Route::get('/nhan-vien', [NhanVienController::class, 'index'])->middleware('quyen:nhanvien.xem');
    Route::get('/nhan-vien/{id}', [NhanVienController::class, 'show'])->whereNumber('id')->middleware('quyen:nhanvien.xem');
    Route::put('/nhan-vien/{id}/trang-thai', [NhanVienController::class, 'doiTrangThai'])->whereNumber('id')->middleware('quyen:nhanvien.doi_trang_thai');
    Route::post('/nhan-vien', [NhanVienController::class, 'store'])->middleware('quyen:nhanvien.them');
        Route::put('/nhan-vien/{id}', [NhanVienController::class, 'update'])->whereNumber('id')->middleware('quyen:nhanvien.sua');
    // ----- Danh mục để chọn trong form (công ty, phòng ban) -----
    Route::get('/cong-ty', [DanhMucController::class, 'congTy'])->middleware('quyen:congty.xem');
    Route::get('/phong-ban', [DanhMucController::class, 'phongBan'])->middleware('quyen:phongban.xem');

        // ----- Quản lý công ty (chỉ quản trị cấp cao theo ma trận quyền) -----
    Route::get('/cong-ty/danh-sach', [CongTyController::class, 'index'])->middleware('quyen:congty.xem');
    Route::get('/cong-ty/{id}', [CongTyController::class, 'show'])->whereNumber('id')->middleware('quyen:congty.sua');
    Route::post('/cong-ty', [CongTyController::class, 'store'])->middleware('quyen:congty.them');
    Route::put('/cong-ty/{id}', [CongTyController::class, 'update'])->whereNumber('id')->middleware('quyen:congty.sua');
    Route::put('/cong-ty/{id}/trang-thai', [CongTyController::class, 'doiTrangThai'])->whereNumber('id')->middleware('quyen:congty.doi_trang_thai');
        // ----- Phòng ban -----
    Route::get('/phong-ban/danh-sach', [PhongBanController::class, 'index'])->middleware('quyen:phongban.xem');
    Route::get('/phong-ban/{id}', [PhongBanController::class, 'show'])->whereNumber('id')->middleware('quyen:phongban.sua');
    Route::post('/phong-ban', [PhongBanController::class, 'store'])->middleware('quyen:phongban.them');
    Route::put('/phong-ban/{id}', [PhongBanController::class, 'update'])->whereNumber('id')->middleware('quyen:phongban.sua');
    Route::put('/phong-ban/{id}/trang-thai', [PhongBanController::class, 'doiTrangThai'])->whereNumber('id')->middleware('quyen:phongban.doi_trang_thai');
        // ----- Đổi mật khẩu bắt buộc (OTP qua email) -----
    Route::get('/doi-mat-khau/trang-thai', [DoiMatKhauController::class, 'trangThai']);
    Route::post('/doi-mat-khau/yeu-cau', [DoiMatKhauController::class, 'yeuCau'])->middleware('throttle:10,1');
    Route::post('/doi-mat-khau/gui-lai', [DoiMatKhauController::class, 'guiLai'])->middleware('throttle:10,1');
    Route::post('/doi-mat-khau/xac-nhan', [DoiMatKhauController::class, 'xacNhan'])->middleware('throttle:20,1');
    Route::post('/doi-mat-khau/kiem-tra-cu', [DoiMatKhauController::class, 'kiemTraCu'])->middleware('throttle:20,1');
});
