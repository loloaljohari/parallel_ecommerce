<?php

namespace App\Jobs;

use App\Models\Orders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class DailySalesAudit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $totalSales = 0.0;
        $orderCount = 0;

        Orders::query()
            ->whereBetween('created_at', [
                now()->startOfDay(),
                now()->endOfDay(),
            ])
            ->chunk(100, function ($orders) use (&$totalSales, &$orderCount) {
                foreach ($orders as $order) {
                    $totalSales += (float) $order->total_price;
                    $orderCount++;
                }
            });

        DB::table('daily_reports')->updateOrInsert(
            ['report_date' => today()->toDateString()],
            [
                'total_sales' => $totalSales,
                'total_orders' => $orderCount,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
