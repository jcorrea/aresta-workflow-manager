<?php

use App\Console\Commands\NotifySlaDeadlines;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 02-motor-de-execucao.md §5: varre atividades pendentes com prazo, de hora em hora.
Schedule::command(NotifySlaDeadlines::class)->hourly();
