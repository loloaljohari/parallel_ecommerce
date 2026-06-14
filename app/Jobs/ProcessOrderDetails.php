<?php

namespace App\Jobs;

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

    public Orders $order;

    public function __construct(Orders $order)
    {
        $this->order = $order;
    }

    public function handle(): void
    {
        $this->order->update(['status' => 'completed']);

        Log::info("Asynchronous Process: Order #{$this->order->id} completed and notification sent.");
    }
}
