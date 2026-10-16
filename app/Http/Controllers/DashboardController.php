<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $live = fn ($q) => $q->whereNotIn('order_status', ['cancelled', 'returned']);

        $totals = DB::table('orders')->tap($live)
            ->selectRaw('COUNT(*) orders, COALESCE(SUM(grand_total),0) sales')->first();
        $monthStart = now()->startOfMonth();
        $prevStart = now()->subMonthNoOverflow()->startOfMonth();
        $sales = fn ($from, $to) => (float) DB::table('orders')->tap($live)
            ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$from, $to])->sum('grand_total');
        $thisMonth = $sales($monthStart, now());
        $lastMonth = $sales($prevStart, $monthStart);

        // Revenue per day for the last year; periods are folded from this one query.
        $perDay = DB::table('orders')->tap($live)
            ->whereRaw('COALESCE(placed_at, created_at) >= ?', [now()->subYear()->startOfDay()])
            ->selectRaw('DATE(COALESCE(placed_at, created_at)) d, SUM(grand_total) total')
            ->groupBy('d')->pluck('total', 'd');

        $statusCounts = DB::table('orders')->selectRaw('order_status, COUNT(*) c')->groupBy('order_status')->pluck('c', 'order_status');

        return view('dashboard.index', [
            'cards' => [
                'sales' => (float) $totals->sales,
                'orders' => (int) $totals->orders,
                'customers' => DB::table('customers')->count(),
                'products' => DB::table('products')->where('status', 'active')->count(),
                'growth' => $lastMonth > 0 ? round(($thisMonth - $lastMonth) / $lastMonth * 100, 1) : null,
            ],
            'charts' => $this->chartSeries($perDay),
            'statusCounts' => $statusCounts,
            'recentOrders' => DB::table('orders')->select('id', 'order_number', 'shipping_recipient_name', 'grand_total', 'order_status', 'created_at')
                ->latest('id')->limit(5)->get(),
            'topProducts' => DB::table('order_items')->selectRaw('product_id, MAX(product_name) name, SUM(quantity) units, SUM(line_total) revenue')
                ->groupBy('product_id', 'product_name')->orderByDesc('units')->limit(5)->get(),
            'recentRequests' => $this->recentRequests(),
            'lowStock' => $this->lowStock(),
        ]);
    }

    private function chartSeries($perDay): array
    {
        $days = fn (int $n) => collect(range($n - 1, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $daily = fn ($list) => ['categories' => $list->map(fn ($d) => Carbon::parse($d)->format('M d'))->all(), 'data' => $list->map(fn ($d) => round((float) ($perDay[$d] ?? 0), 2))->all()];
        $monthly = function (int $n) use ($perDay) {
            $months = collect(range($n - 1, 0))->map(fn ($i) => now()->subMonthsNoOverflow($i)->format('Y-m'));
            $sum = $perDay->groupBy(fn ($v, $d) => substr($d, 0, 7))->map->sum();

            return ['categories' => $months->map(fn ($m) => Carbon::parse($m.'-01')->format('M Y'))->all(), 'data' => $months->map(fn ($m) => round((float) ($sum[$m] ?? 0), 2))->all()];
        };

        return ['7d' => $daily($days(7)), '30d' => $daily($days(30)), '3m' => $monthly(3), '1y' => $monthly(12)];
    }

    private function recentRequests()
    {
        $s = DB::table('sourcing_requests')->selectRaw("id, request_number, 'Sourcing' type, customer_name name, status, created_at");
        $b = DB::table('bulk_order_requests')->selectRaw("id, request_number, 'Bulk' type, contact_name name, status, created_at");

        return $s->unionAll($b)->orderByDesc('created_at')->limit(5)->get();
    }

    private function lowStock()
    {
        $simple = DB::table('products')->where('has_variants', 0)->where('status', 'active')
            ->whereRaw('stock_qty <= COALESCE(low_stock_threshold, 5)')
            ->selectRaw('id product_id, name, sku, stock_qty stock');
        $variants = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('v.is_active', 1)->where('p.status', 'active')
            ->whereRaw('v.stock_qty <= COALESCE(p.low_stock_threshold, 5)')
            ->selectRaw('p.id product_id, p.name, v.sku, v.stock_qty stock');

        return $simple->unionAll($variants)->orderBy('stock')->limit(5)->get();
    }
}
