<?php

namespace App\Http\Controllers;

use App\Http\Requests\LuuPhongBanRequest;
use App\Services\PhongBanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhongBanController extends Controller
{
    public function __construct(private PhongBanService $dichVu)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'du_lieu' => $this->dichVu->danhSach($request->user())]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $p = $this->dichVu->chiTiet($request->user(), $id);

        // không tồn tại và ngoài phạm vi trả cùng một kết quả
        if ($p === null) {
            return response()->json(['ok' => false, 'loi' => 'Không tìm thấy phòng ban.'], 404);
        }

        return response()->json(['ok' => true, 'du_lieu' => $p]);
    }

    public function store(LuuPhongBanRequest $request): JsonResponse
    {
        try {
            $p = $this->dichVu->them($request->user(), $request->validated());
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'loi' => $e->getMessage()], $this->maLoi($e));
        }

        return response()->json(['ok' => true, 'du_lieu' => $p], 201);
    }

    public function update(LuuPhongBanRequest $request, int $id): JsonResponse
    {
        $p = $this->dichVu->sua($request->user(), $id, $request->validated());

        if ($p === null) {
            return response()->json(['ok' => false, 'loi' => 'Không tìm thấy phòng ban.'], 404);
        }

        return response()->json(['ok' => true, 'du_lieu' => $p]);
    }

    public function doiTrangThai(Request $request, int $id): JsonResponse
    {
        $duLieu = $request->validate(
            ['Trang_Thai' => ['required', 'integer', 'in:0,1']],
            [
                'Trang_Thai.required' => 'Vui lòng chọn trạng thái.',
                'Trang_Thai.integer'  => 'Trạng thái không hợp lệ.',
                'Trang_Thai.in'       => 'Trạng thái không hợp lệ.',
            ]
        );

        try {
            $kq = $this->dichVu->doiTrangThai($request->user(), $id, (int) $duLieu['Trang_Thai']);
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'loi' => $e->getMessage()], $this->maLoi($e));
        }

        if ($kq === null) {
            return response()->json(['ok' => false, 'loi' => 'Không tìm thấy phòng ban.'], 404);
        }

        return response()->json(['ok' => true, 'du_lieu' => $kq]);
    }

    // 403 = ngoài phạm vi, 422 = lựa chọn không hợp lệ, còn lại = 409 (vi phạm luật nghiệp vụ)
    private function maLoi(\DomainException $e): int
    {
        return in_array($e->getCode(), [403, 422], true) ? $e->getCode() : 409;
    }
}