<?php

namespace App\Http\Controllers;
use App\Http\Requests\ThemNhanVienRequest;
use App\Services\NhanVienService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\SuaNhanVienRequest;
class NhanVienController extends Controller
{
    public function __construct(private NhanVienService $dichVu)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $loc = $request->validate([
            'tu_khoa'      => ['nullable', 'string', 'max:100'],
            'id_cong_ty'   => ['nullable', 'integer', 'min:1'],
            'id_phong_ban' => ['nullable', 'integer', 'min:1'],
            'id_chuc_vu'   => ['nullable', 'integer', 'min:1'],
            'trang_thai'   => ['nullable', 'in:0,1,2'],
            'trang'        => ['nullable', 'integer', 'min:1'],
            'moi_trang'    => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $ketQua = $this->dichVu->danhSach($request->user(), $loc);

        return response()->json(['ok' => true] + $ketQua);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $nv = $this->dichVu->chiTiet($request->user(), $id);

        // không tồn tại và ngoài phạm vi trả cùng một kết quả, để không lộ ai có thật
        if ($nv === null) {
            return response()->json(['ok' => false, 'loi' => 'Không tìm thấy nhân viên.'], 404);
        }

        return response()->json(['ok' => true, 'du_lieu' => $nv]);
    }
    public function store(ThemNhanVienRequest $request): JsonResponse
    {
        try {
            $kq = $this->dichVu->them($request->user(), $request->validated());
        } catch (\DomainException $e) {
            // 403 = ngoài phạm vi được thêm, 422 = lựa chọn không hợp lệ, còn lại = 409
            $ma = in_array($e->getCode(), [403, 422], true) ? $e->getCode() : 409;

            return response()->json(['ok' => false, 'loi' => $e->getMessage()], $ma);
        }

        // kết quả có mật khẩu tạm: cấm trình duyệt và máy chủ trung gian lưu lại
        return response()->json(['ok' => true, 'du_lieu' => $kq], 201)->header('Cache-Control', 'no-store');
    }
        public function update(SuaNhanVienRequest $request, int $id): JsonResponse
    {
        try {
            $kq = $this->dichVu->sua($request->user(), $id, $request->validated());
        } catch (\DomainException $e) {
            // 422 = lựa chọn không hợp lệ, còn lại (409) = vi phạm luật nghiệp vụ
            $ma = $e->getCode() === 422 ? 422 : 409;

            return response()->json(['ok' => false, 'loi' => $e->getMessage()], $ma);
        }

        // không tồn tại và ngoài phạm vi trả cùng một kết quả, để không lộ ai có thật
        if ($kq === null) {
            return response()->json(['ok' => false, 'loi' => 'Không tìm thấy nhân viên.'], 404);
        }

        return response()->json(['ok' => true, 'du_lieu' => $kq]);
    }
    public function doiTrangThai(Request $request, int $id): JsonResponse
    {
        $duLieu = $request->validate(
            ['Trang_Thai' => ['required', 'integer', 'in:0,1,2']],
            [
                'Trang_Thai.required' => 'Vui lòng chọn trạng thái.',
                'Trang_Thai.integer'  => 'Trạng thái không hợp lệ.',
                'Trang_Thai.in'       => 'Trạng thái không hợp lệ.',
            ]
        );

        try {
            $kq = $this->dichVu->doiTrangThai($request->user(), $id, (int) $duLieu['Trang_Thai']);
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'loi' => $e->getMessage()], 409);
        }

        if ($kq === null) {
            return response()->json(['ok' => false, 'loi' => 'Không tìm thấy nhân viên.'], 404);
        }

        return response()->json(['ok' => true, 'du_lieu' => $kq]);
    }
}