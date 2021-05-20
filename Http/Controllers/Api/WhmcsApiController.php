<?php

namespace Modules\Iwhmcs\Http\Controllers\Api;

use Illuminate\Http\Request;
use Mockery\CountValidator\Exception;
use Modules\Ihelpers\Http\Controllers\Api\BaseApiController;
use Log;
use Route;

//Services
use Modules\Iwhmcs\Services\Bitrix24\CRest;

//jobs
use Modules\Iwhmcs\Jobs\SyncClientsToBitrix24;
use Modules\Iwhmcs\Jobs\syncProjectsAsHosting;
use Modules\Iwhmcs\Jobs\SetGroupToClients;
use Modules\Iwhmcs\Jobs\SetClientToInvoices;

class WhmcsApiController extends BaseApiController
{
  public function __construct()
  {
    parent::__construct();
  }

  //Sync clients to bitrix24
  public function syncClientsToBitrix24(Request $request)
  {
    try {
      //Dispatch job
      SyncClientsToBitrix24::dispatch()->onQueue('whmcsJob');
      //Response
      $response = ['data' => 'Job was created to sync clients in BITRIX24 from WHCMS'];
    } catch (\Exception $e) {
      $status = $this->getStatusError($e->getCode());
      $response = ["errors" => $e->getMessage()];
    }

    //Return response
    return response()->json($response ?? ["data" => "Request successful"], $status ?? 200);
  }

  //Sync projects as hosting whcms-bitrix
  public function syncProjectsAsHosting(Request $request)
  {
    try {
      syncProjectsAsHosting::dispatch()->onQueue('whmcsJob');
      //Response
      $response = ['data' => 'Job was created to sync projects as hosting in WHCMS'];
    } catch (\Exception $e) {
      $status = $this->getStatusError($e->getCode());
      $response = ["errors" => $e->getMessage()];
    }

    //Return response
    return response()->json($response ?? ["data" => "Request successful"], $status ?? 200);
  }

  //Set group to clients
  public function SetGroupClients(Request $request)
  {
    try {
      //Dispatch job
      SetGroupToClients::dispatch()->onQueue('whmcsJob');
      //Response
      $response = ['data' => 'Job was created to set group clients in WHCMS'];
    } catch (\Exception $e) {
      $status = $this->getStatusError($e->getCode());
      $response = ["errors" => $e->getMessage()];
    }

    //Return response
    return response()->json($response ?? ["data" => "Request successful"], $status ?? 200);
  }

  //Set group to clients
  public function setClientToInvoice(Request $request)
  {
    try {
      //Dispatch job
      SetClientToInvoices::dispatch()->onQueue('whmcsJob');
      //Response
      $response = ['data' => 'Job was created to set client id to invoice items in WHCMS'];
    } catch (\Exception $e) {
      $status = $this->getStatusError($e->getCode());
      $response = ["errors" => $e->getMessage()];
    }

    //Return response
    return response()->json($response ?? ["data" => "Request successful"], $status ?? 200);
  }
}
