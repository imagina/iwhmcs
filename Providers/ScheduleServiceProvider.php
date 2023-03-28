<?php

namespace Modules\Iwhmcs\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;


class ScheduleServiceProvider extends ServiceProvider
{
  public function boot()
  {
    $this->app->booted(function () {
      $schedule = $this->app->make(Schedule::class);
      //Sync first the clients
      $schedule->call(function () {
        \Modules\Iwhmcs\Jobs\SyncClientsToBitrix24::dispatch();
        \Log::info("Iwhmcs::scheduled SyncClientsToBitrix24");
      })->dailyAt('17:00');
      //sync the due deals
      $schedule->call(function () {
        \Modules\Iwhmcs\Jobs\syncDueInvoicesItemsToBitrix::dispatch();
        \Log::info("Iwhmcs::scheduled syncDueInvoicesItemsToBitrix");
      })->dailyAt('18:00');
    });
  }
}
