<?php

namespace Modules\Iwhmcs\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

//Transformers
use Modules\Iwhmcs\Transformers\ClientsToBitrix24Transformer;
use Modules\Iwhmcs\Transformers\ProductGroupsToBitrix24Transformer;
use Modules\Iwhmcs\Transformers\ProductsToBitrix24Transformer;
use Modules\Iwhmcs\Transformers\DealsToBitrix24Transformer;
use Modules\Iwhmcs\Transformers\DealProductsToBitrix24Transformer;

//Services
use Modules\Iwhmcs\Services\Bitrix24\CRest;

class syncInvoicesToBitrix implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  protected $logTitle;
  protected $tblOptInvoicesType;

  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct()
  {
    $this->logTitle = '[IWHMCS]::JOB syncInvoicesToBitrix';
    $this->tblOptInvoicesType = 'bitrixDealId';
  }

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle()
  {
    $this->dispatchInvoicesBatch();
  }

  //Get invoices batch
  public function dispatchInvoicesBatch()
  {
    $numBatchs = 20;//Batchs quanitty
    $quantityPerBatch = 500;//Define record quantity to generate batch

    for ($batch = 0; $batch <= $numBatchs; $batch++) {
      //Get WHMCS clients with contactBitrixId
      $invoices = \DB::connection('whmcs')->table('tblinvoices')
        ->select('tblinvoices.*',
          \DB::raw("concat(tblclients.firstname,' ',tblclients.lastname) as clientFullName"),
          \DB::raw("(SELECT value FROM tblimoptions WHERE rel_id = tblinvoices.id and type = '{$this->tblOptInvoicesType}') as bitrixId"),
          \DB::raw("(SELECT value FROM tblimoptions WHERE rel_id = tblinvoices.userid and type = 'bitrixContactId') as bitrixContactId")
        )
        ->leftJoin('tblclients', 'tblclients.id', 'tblinvoices.userid')
        ->where('tblinvoices.status', 'paid')
        ->where('tblinvoices.total', '>=', '0')
        ->whereNotIn('tblinvoices.id', function ($q) {
          $q->select('rel_id')->from('tblimoptions')->where('type', $this->tblOptInvoicesType);
        })->take($quantityPerBatch)->get();

      //Set batch
      if ($invoices && $invoices->count()) $this->syncInvoicesToBitrix($invoices, ['batch' => $batch, "numBatchs" => $numBatchs]);
    }
  }

  //Sync invoices to Bitrix
  public function syncInvoicesToBitrix($invoices, $params)
  {
    try {
      //Transform clients to bitrix64
      $ordersBitrix = json_decode(json_encode(DealsToBitrix24Transformer::collection($invoices)));

      foreach ($ordersBitrix as $index => $order) {
        //Text to log
        $infoLog = "Invoice ID {$order->ORIGIN_ID} | Batch ({$params['batch']}/{$params['numBatchs']}) | Record (" . $index . "/" . count($ordersBitrix) . ")";
        //Update Contact
        if (isset($order->ID) && $order->ID) {
          //Update Contact
          $result = CRest::call('crm.deal.update', ["id" => $order->ID, "fields" => $order]);
          //Set Products
          $itemsQuanity = $this->setProductsToDealBitrix($order);
          //Log
          \Log::info("{$this->logTitle}::syncInvoicesToBitrix UPDATED | Products {$itemsQuanity} | {$infoLog}");
        } else {//Create contact
          $result = CRest::call('crm.deal.add', ["fields" => $order]);
          //Handler to success response
          if ($result && isset($result['result']) && $result['result']) {
            //Set ID to order
            $order->ID = $result['result'];
            //Save relation
            \DB::connection('whmcs')->table('tblimoptions')->insert([
              'name' => $this->tblOptInvoicesType,
              'type' => $this->tblOptInvoicesType,
              'rel_id' => $order->ORIGIN_ID,
              'value' => $result['result']
            ]);
            //Set Products
            $itemsQuanity = $this->setProductsToDealBitrix($order);
            //Log
            \Log::info("{$this->logTitle}::syncInvoicesToBitrix CREATED | Products {$itemsQuanity} | {$infoLog}");
          } else {
            //Log
            \Log::info("{$this->logTitle}::syncInvoicesToBitrix NOT CREATED | {$infoLog} | {$result['error_description']}");
          }
        }

        //Sleep by 0.6 seconds to prevent QUERY_LIMIT_EXCEEDED (2 by second)
        usleep(600000);
      }
    } catch (\Exception $e) {
      \Log::info("{$this->logTitle}::syncInvoicesToBitrix Failed " . json_encode($e->getMessage()));
    }
  }

  //Sync invoices to Bitrix
  public function setProductsToDealBitrix($order)
  {
    try {
      //Get invoices items
      $invoiceItems = \DB::connection('whmcs')->table('tblinvoiceitems')
        ->select('tblinvoiceitems.*',
          \DB::raw("(SELECT value FROM tblimoptions WHERE rel_id = tblinvoiceitems.relid and type = 'bitrixProductId') as bitrixProductId")
        )
        ->where('tblinvoiceitems.invoiceid', $order->ORIGIN_ID)
        ->get();

      //Transform clients to bitrix64
      $dealProductsBitrix = json_decode(json_encode(DealProductsToBitrix24Transformer::collection($invoiceItems)));

      //Update Contact
      if ($dealProductsBitrix && count($dealProductsBitrix))
        $result = CRest::call('crm.deal.productrows.set', ["id" => $order->ID, "rows" => $dealProductsBitrix]);


      //Sleep by 0.6 seconds to prevent QUERY_LIMIT_EXCEEDED (2 by second)
      usleep(600000);

      //Response
      return $dealProductsBitrix ? count($dealProductsBitrix) : 0;
    } catch (\Exception $e) {
      \Log::info("{$this->logTitle}::syncInvoicesToBitrix Failed " . json_encode($e->getMessage()));
    }
  }
}
