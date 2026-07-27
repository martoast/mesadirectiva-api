<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
            'tickets_sold' => $event->getTotalTicketsSold(),
            'tickets_available' => $event->getTotalTicketsAvailable(),
            'revenue' => round($revenue, 2),
            'orders_count' => $ordersCount,
            'sales_by_day' => $salesByDay,
            'extra_items_sold' => $extraItemsSold,
        ];
    }

    /**
     * Get sales report data (all records — used by exports)
     */
    public function getSalesReport(User $user, array $filters = []): Collection
    {
        return $this->buildSalesQuery($user, $filters)
            ->orderBy('paid_at', 'desc')
            ->get();
    }

    /**
     * Get paginated sales report data
     */
    public function getSalesReportPaginated(User $user, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->buildSalesQuery($user, $filters)
            ->orderBy('paid_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get summary totals for the full filtered set (not just the current page)
     */
    public function getSalesSummary(User $user, array $filters = []): array
    {
        $query = $this->buildSalesQuery($user, $filters, false);

        return [
            'total_orders' => (clone $query)->count(),
            'total_revenue' => round((clone $query)->sum('total'), 2),
        ];
    }

    /**
     * Build the base query for sales/orders reports
     */
    private function buildSalesQuery(User $user, array $filters = [], bool $withRelations = true): Builder
    {
        $eventIds = Event::accessibleBy($user)->pluck('id');

        $query = Order::whereIn('event_id', $eventIds)
            ->where('status', 'completed');

        if ($withRelations) {
            $query->with('event:id,name,slug,group_id', 'event.group:id,name', 'items');
        }

        if (!empty($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        if (!empty($filters['group_id'])) {
            $query->whereHas('event', function ($q) use ($filters) {
                $q->where('group_id', $filters['group_id']);
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

        return $query;
    }

    /**
     * Summary report for coordinadoras (role: viewer) — one row per sold
     * ticket, restricted to the columns configured in config/reports.php.
     */
    public function getSummaryReport(User $user, array $filters = []): Collection
    {
        $eventIds = Event::accessibleBy($user)->pluck('id');

        $query = OrderItem::where('item_type', 'ticket')
            ->whereHas('order', function ($q) use ($eventIds, $filters) {
                $q->where('status', 'completed')->whereIn('event_id', $eventIds);

                if (!empty($filters['event_id'])) {
                    $q->where('event_id', $filters['event_id']);
                }

                if (!empty($filters['date_from'])) {
                    $q->whereDate('paid_at', '>=', $filters['date_from']);
                }

                if (!empty($filters['date_to'])) {
                    $q->whereDate('paid_at', '<=', $filters['date_to']);
                }
            })
            ->with(['order.event:id,name,slug,group_id', 'order.event.group:id,name', 'ticketTier:id,name']);

        if (!empty($filters['group_id'])) {
            $query->whereHas('order.event', function ($q) use ($filters) {
                $q->where('group_id', $filters['group_id']);
            });
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('attendee_name', 'like', "%{$search}%")
                    ->orWhere('student_key', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('customer_name', 'like', "%{$search}%");
                    });
            });
        }

        $columns = array_keys(config('reports.summary_columns'));

        return $query->get()
            ->sortByDesc(fn ($item) => $item->order->paid_at)
            ->values()
            ->map(function ($item) use ($columns) {
                $all = [
                    'event' => $item->order->event->name ?? '',
                    'group' => $item->order->event->group->name ?? '',
                    'attendee_name' => $item->attendee_name ?: $item->order->customer_name,
                    'student_key' => $item->student_key,
                    'note' => $item->attendee_note,
                    'tier' => $item->ticketTier->name ?? $item->item_name,
                    'quantity' => $item->quantity,
                    'total' => (float) $item->total_price,
                    'date' => $item->order->paid_at?->format('Y-m-d'),
                ];

                return array_intersect_key($all, array_flip($columns));
            });
    }
}
