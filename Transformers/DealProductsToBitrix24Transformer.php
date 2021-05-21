<?php

namespace Modules\Iwhmcs\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class DealProductsToBitrix24Transformer extends JsonResource
{
  public function toArray($request)
  {
    return [
      "PRODUCT_ID" => ($this->type == 'Domain') ? 616/*Dominio Registrado*/ :
        ($this->bitrixProductId ?? 132/*hosting Linux Personal*/),
      "PRICE" => $this->amount,
      "QUANTITY" => 1
    ];
  }
}
