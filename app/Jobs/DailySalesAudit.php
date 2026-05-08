<?php

namespace App\Jobs;

use App\Models\Orders;
use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;


class DailySalesAudit implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        $totalSales = 0;
        $orderCount = 0;
        Orders::whereDate('created_at', today())
            ->chunk(100, function ($orders) use (&$totalSales, &$orderCount) {
                foreach ($orders as $order) {
                    $totalSales += $order->total_price;
                    $orderCount++;
                }
            });

        DB::table('daily_reports')->insert([
            'report_date' => today(),
            'total_sales' => $totalSales,
            'total_orders' => $orderCount,
            'created_at' => now()
        ]);
    }
}
