<?php

namespace App\Http\Controllers;

use App\Http\Requests\LuuChucVuRequest;
use App\Http\Resources\ChucVuResource;
use App\Models\ChucVu;
use App\Services\ChucVuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChucVuController extends Controller
{
    public function __construct(private ChucVuService $dichVu)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $loc = $request->validate([
            'trang_thai' => ['nullable', 'in:0,1'],
            'id_cong_ty' => ['nullable', 'integer', 'min:1'],
        ]);

        $ds = $this->dichVu->danhSach(
            isset($loc['trang_thai']) ? (int) $loc['trang_thai'] : null,
            isset($loc['id_cong_ty']) ? (int) $loc['id_cong_ty'] : null,
        );

        return response()->json(['ok' => true, 'du_lieu' => ChucVuResource::collection($ds)->resolve()]);
    }

    public function show(ChucVu $chuc_vu): JsonResponse
    {
        return response()->json(['ok' => true, 'du_lieu' => (new ChucVuResource($chuc_vu))->resolve()]);
    }

    public function store(LuuChucVuRequest $request): JsonResponse
    {
        $cv = $this->dichVu->tao($request->validated());

        return response()->json(['ok' => true, 'du_lieu' => (new ChucVuResource($cv))->resolve()], 201);
    }

    public function update(LuuChucVuRequest $request, ChucVu $chuc_vu): JsonResponse
    {
        $cv = $this->dichVu->capNhat($chuc_vu, $request->validated());

        return response()->json(['ok' => true, 'du_lieu' => (new ChucVuResource($cv))->resolve()]);
    }

    public function destroy(ChucVu $chuc_vu): JsonResponse
    {
        try {
            $this->dichVu->ngungDung($chuc_vu);
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'loi' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true]);
    }
}