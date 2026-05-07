<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Orders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderDetails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $order;

    public function __construct(Orders $order)
    {
        $this->order = $order;
    }

    public function handle()
    {
        sleep(30);

       Log::channel('aop_console')->info("Asynchronous Process: Order #{$this->order->id} has been fully processed and notification sent.");

        $this->order->update(['status' => 'processed']);
    }
}
