<?php

namespace Modules\Iwhmcs\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class DealsToBitrix24Transformer extends JsonResource
{
  public function toArray($request)
  {
    return [
      "ID" => $this->bitrixId,
      "TITLE" => $this->id . '-' . $this->clientFullName,
      "STAGE_ID" => "WON",
      "CONTACT_ID" => $this->bitrixContactId,
      "OPENED" => "Y",
      "CURRENCY_ID" => "COP",
      "BEGINDATE" => (!$this->datepaid || ($this->datepaid == '0000-00-00 00:00:00')) ? $this->date : $this->datepaid,
      "DATA_CREATE" => $this->date,
      "ORIGIN_ID" => $this->id,
    ];
  }
}
