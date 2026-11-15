<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rappels de paiement et récapitulatif des retards, chaque matin
Schedule::command('paiements:rappels')->dailyAt('08:00')->withoutOverlapping();
