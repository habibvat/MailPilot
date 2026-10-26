<?php

use App\Jobs\ProcessMailboxesJob;
use Illuminate\Support\Facades\Schedule;

Schedule::command('mailpilot:dispatch')->everyMinute()->withoutOverlapping();

Schedule::job(new ProcessMailboxesJob)->everyFifteenMinutes()->withoutOverlapping();
