<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get overall dashboard statistics
     */
    public function getDashboardStats(User $user): array
    {
        $eventsQuery = Event::accessibleBy($user);

        $totalEvents = (clone $eventsQuery)->count();
        $liveEvents = (clone $eventsQuery)->where('status', 'live')->count();

        $eventIds = (clone $eventsQuery)->pluck('id');

        $ordersQuery = Order::whereIn('event_id', $eventIds)
            ->where('status', 'completed');

        $totalOrders = (clone $ordersQuery)->count();
        $totalRevenue = (clone $ordersQuery)->sum('total');

        $todayOrders = Order::whereIn('event_id', $eventIds)
            ->where('status', 'completed')
            ->whereDate('paid_at', today());

        $ticketsSoldToday = (clone $todayOrders)->count();
        $revenueToday = (clone $todayOrders)->sum('total');

        return [
            'total_events' => $totalEvents,
            'live_events' => $liveEvents,
            'total_orders' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'tickets_sold_today' => $ticketsSoldToday,
            'revenue_today' => round($revenueToday, 2),
        ];
    }

    /**
     * Get statistics for a specific event
     */
    public function getEventStats(Event $event): array
    {
        $completedOrders = $event->completedOrders();

        $ordersCount = $completedOrders->count();
        $revenue = $completedOrders->sum('total');

        $salesByDay = Order::where('event_id', $event->id)
            ->where('status', 'completed')
            ->where('paid_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $extraItemsSold = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.event_id', $event->id)
            ->where('orders.status', 'completed')
            ->where('order_items.item_type', 'extra_item')
            ->select(
                'order_items.item_name as name',
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(order_items.total_price) as revenue')
            )
            ->groupBy('order_items.item_name')
            ->get();

        return [
            'event' => $event,
            'tickets_sold' => $event->tickets_sold,
            'tickets_available' => $event->getTicketsAvailable(),
            'revenue' => round($revenue, 2),
            'orders_count' => $ordersCount,
            'sales_by_day' => $salesByDay,
            'extra_items_sold' => $extraItemsSold,
        ];
    }

    /**
     * Get sales report data
     */
    public function getSalesReport(User $user, array $filters = []): Collection
    {
        $eventIds = Event::accessibleBy($user)->pluck('id');

        $query = Order::whereIn('event_id', $eventIds)
            ->where('status', 'completed')
            ->with('event:id,name,slug,category_id', 'event.category:id,name', 'items');

        if (!empty($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->whereHas('event', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('paid_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('paid_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('paid_at', 'desc')->get();
    }
}
