<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use App\Notifications\UserRervationStartingReminder;
use App\Notifications\HostRervationStartingReminder;

class SendDueReservationNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:send-reservations-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Reservation::query()
                   ->with('office.user')
                   ->where('status', Reservation::STATUS_ACTIVE)
                   ->where('start_date', now()->toDateString())
                   ->each( function($reservation) {
                      Notification::send($reservation->user, new UserRervationStartingReminder($reservation));
                      Notification::send($reservation->user, new HostRervationStartingReminder($reservation));
                    });
        return 0;
    }
}
