<?php

namespace Modules\Iwhmcs\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductsToBitrix24Transformer extends JsonResource
{
  public function toArray($request)
  {
    return [
      'ID' => $this->when($this->bitrixId, $this->bitrixId),
      'XML_ID' => $this->id,
      'CATALOG_ID' => '24',
      'SECTION_ID' => $this->productSectionBitrixId ?? '',
      'PRICE' => ($this->paytype == 'onetime') ? $this->copMonthlyPrice : $this->copAnnuallyPrice,
      'CURRENCY_ID' => 'COP',
      'NAME' => $this->name ?? '',
      'DESCRIPTION' => $this->description ?? '',
      'DESCRIPTION_TYPE' => 'html',
      "VAT_INCLUDED" => "N",
    ];
  }
}
