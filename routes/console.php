<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Sync Queue Scheduler
|--------------------------------------------------------------------------
| ProcessSyncQueue  : Ambil dan proses 1 item dari antrean setiap menit
| CleanSyncHistory  : Retention policy — hapus record sukses > 90 hari
*/
Schedule::command('sync:process-queue')->everyMinute();
Schedule::command('sync:clean-history')->daily();
