<?php

namespace Modules\Iwhmcs\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Modules\Isite\Jobs\ProcessSeeds;

class IwhmcsDatabaseSeeder extends Seeder
{
  /**
   * Run the database seeds.
   *
   * @return void
   */
  public function run()
  {
    Model::unguard();
    ProcessSeeds::dispatch([
      "baseClass" => "\Modules\Iwhmcs\Database\Seeders",
      "seeds" => ["IwhmcsModuleTableSeeder"]
    ]);
  }
}
