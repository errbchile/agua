<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Order;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $orders_pending = Order::where('status', 'pending')->count();
        $orders_rejected = Order::where('status', 'rejected')->count();
        $orders_finished = Order::where('status', 'finished')->count();
        $total_money_earned = Order::where('status', 'finished')->sum('total_price');
        $total_money_pending = Order::where('status', 'pending')->sum('total_price');

        return [
            Stat::make('Orders Pending', $orders_pending),
            Stat::make('Orders Rejected', $orders_rejected),
            Stat::make('Orders Finished', $orders_finished),
            Stat::make('Money Earned', number_format($total_money_earned, 2, ',', '.'))
                ->color('success'),
            Stat::make('Money Pending', number_format($total_money_pending, 2, ',', '.'))
                ->color('warning'),
        ];
    }
}
