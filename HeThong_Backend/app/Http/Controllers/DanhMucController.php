<?php

namespace App\Http\Controllers;

use App\Services\DanhMucService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DanhMucController extends Controller
{
    public function __construct(private DanhMucService $dichVu)
    {
    }

    public function congTy(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'du_lieu' => $this->dichVu->congTy($request->user())]);
    }

    public function phongBan(Request $request): JsonResponse
    {
        $loc = $request->validate(['id_cong_ty' => ['nullable', 'integer', 'min:1']]);

        return response()->json([
            'ok'      => true,
            'du_lieu' => $this->dichVu->phongBan(
                $request->user(),
                isset($loc['id_cong_ty']) ? (int) $loc['id_cong_ty'] : null
            ),
        ]);
    }
}