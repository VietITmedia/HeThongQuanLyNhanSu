<?php

namespace App\Http\Controllers;

use App\Http\Requests\LuuCongTyRequest;
use App\Services\CongTyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CongTyController extends Controller
{
    public function __construct(private CongTyService $dichVu)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'du_lieu' => $this->dichVu->danhSach($request->user())]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $c = $this->dichVu->chiTiet($request->user(), $id);

        // không tồn tại và ngoài phạm vi trả cùng một kết quả
        if ($c === null) {
            return response()->json(['ok' => false, 'loi' => 'Không tìm thấy công ty.'], 404);
        }

        return response()->json(['ok' => true, 'du_lieu' => $c]);
    }

    public function store(LuuCongTyRequest $request): JsonResponse
    {
        try {
            $c = $this->dichVu->them($request->user(), $request->validated());
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'loi' => $e->getMessage()], $this->maLoi($e));
        }

        return response()->json(['ok' => true, 'du_lieu' => $c], 201);
    }

    public function update(LuuCongTyRequest $request, int $id): JsonResponse
    {
        $c = $this->dichVu->sua($request->user(), $id, $request->validated());

        if ($c === null) {
            return response()->json(['ok' => false, 'loi' => 'Không tìm thấy công ty.'], 404);
        }

        return response()->json(['ok' => true, 'du_lieu' => $c]);
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
            return response()->json(['ok' => false, 'loi' => 'Không tìm thấy công ty.'], 404);
        }

        return response()->json(['ok' => true, 'du_lieu' => $kq]);
    }

    // 403 = ngoài phạm vi, 422 = lựa chọn không hợp lệ, còn lại = 409 (vi phạm luật nghiệp vụ)
    private function maLoi(\DomainException $e): int
    {
        return in_array($e->getCode(), [403, 422], true) ? $e->getCode() : 409;
    }
}