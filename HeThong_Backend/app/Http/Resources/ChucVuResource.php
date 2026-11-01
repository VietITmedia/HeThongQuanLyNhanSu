<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChucVuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->ID_ChucVu,
            'ma'         => $this->Ma_ChucVu,
            'ten'        => $this->Ten_ChucVu,
            'mo_ta'      => $this->Mo_Ta,
            'trang_thai' => (int) $this->Trang_Thai,
            'id_cong_ty' => $this->ID_CongTy !== null ? (int) $this->ID_CongTy : null,   // null = chức vụ dùng chung
        ];
    }
}