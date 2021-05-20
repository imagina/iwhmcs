<?php

namespace Modules\Iwhmcs\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

//Transformers
use Modules\Iwhmcs\Transformers\ClientsToBitrix24Transformer;

//Services
use Modules\Iwhmcs\Services\Bitrix24\CRest;

class SyncClientsToBitrix24 implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  protected $logTitle;
  protected $tblImOptionsType;

  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct()
  {
    $this->logTitle = '[IWHMCS]::JOB SyncClientsToBitrix24';
    $this->tblImOptionsType = 'bitrixContactId';
  }

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle()
  {
    $this->syncClientsToBitrix();
    //$this->getClientBitrixId();
  }

  //Sync Clients to Bitrix
  public function syncClientsToBitrix()
  {
    try {
      //Get WHMCS clients with contactBitrixId
      $clients = \DB::connection('whmcs')->table('tblclients')
        ->select('tblclients.*', \DB::raw(
          "(SELECT value FROM tblimoptions WHERE rel_id = tblclients.id and type = '{$this->tblImOptionsType}') as contactBitrixId"
        ))->get();

      //Transform clients to bitrix64
      $contactsBitrix = json_decode(json_encode(ClientsToBitrix24Transformer::collection($clients)));

      foreach ($contactsBitrix as $index => $contact) {
        //Update Contact
        if (isset($contact->ID) && $contact->ID) {
          //Update Contact
          $result = CRest::call('crm.contact.update', ["id" => $contact->ID, "fields" => $contact]);
          //Log
          \Log::info("{$this->logTitle} UPDATED (" . ($index + 1) . "/" . count($contactsBitrix) . ')');
        } else {//Create contact
          $result = CRest::call('crm.contact.add', ["fields" => $contact]);
          //Save relation
          if ($result && isset($result['result']) && $result['result']) {
            \DB::connection('whmcs')->table('tblimoptions')->insert([
              'name' => $this->tblImOptionsType,
              'type' => $this->tblImOptionsType,
              'rel_id' => $contact->ORIGIN_ID,
              'value' => $result['result']
            ]);
          }
          //Log
          \Log::info("{$this->logTitle} CREATED (" . ($index + 1) . "/" . count($contactsBitrix) . ')');
        }

        //Sleep by 0.6 seconds to prevent QUERY_LIMIT_EXCEEDED (2 by second)
        usleep(600000);
      }
    } catch (\Exception $e) {
      \Log::info("{$this->logTitle} | failed" . json_encode($e->getMessage()));
    }
  }

  //GEt client bitrix ID
  public function getClientBitrixId()
  {
    try {
      //Get WHMCS clients without bitrix id
      $clientsWithOutBitrixId = \DB::connection('whmcs')->table('tblclients')
        ->whereNotIn('id', function ($q) {
          $q->select('rel_id')->from('tblimoptions')->where('type', $this->tblImOptionsType);
        })->get()->pluck('id');

      foreach ($clientsWithOutBitrixId as $index => $clientId) {
        //Validate if client id already exist on bitrix
        $bt24Contact = CRest::call('crm.contact.list', ['filter' => ['ORIGIN_ID' => $clientId]]);
        $bt24Contact = ($bt24Contact && $bt24Contact['total']) ? $bt24Contact['result'][0] : false;

        if ($bt24Contact) {
          \DB::connection('whmcs')->table('tblimoptions')->insert([
            'name' => $this->tblImOptionsType,
            'type' => $this->tblImOptionsType,
            'rel_id' => $clientId,
            'value' => $bt24Contact['ID']
          ]);
        }

        //Log
        \Log::info("{$this->logTitle} Get Contact bitrix24 ID (" . ($index + 1) . "/" . count($clientsWithOutBitrixId) . ')');

        //Sleep by 0.6 seconds to prevent QUERY_LIMIT_EXCEEDED (2 by second)
        usleep(600000);
      }
    } catch (\Exception $e) {
      \Log::info("{$this->logTitle} | failed" . json_encode($e->getMessage()));
    }
  }
}
