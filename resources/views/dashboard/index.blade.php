@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
@php
  use App\Support\Fmt;
  $totalOrders = max(1, $statusCounts->sum());
  $statusMeta = [
    'pending' => ['mdi-clock-outline', 'warning'], 'confirmed' => ['mdi-check-circle-outline', 'info'], 'processing' => ['mdi-cog-sync-outline', 'info'],
    'shipped' => ['mdi-truck-delivery-outline', 'primary'], 'delivered' => ['mdi-package-variant-closed-check', 'success'],
    'cancelled' => ['mdi-close-circle-outline', 'danger'], 'returned' => ['mdi-undo-variant', 'danger'],
  ];
@endphp
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="fw-bold mb-1" style="color: var(--beige-text-primary); letter-spacing: -0.015em;">Ecommerce Overview</h4>
    <p class="text-secondary mb-0 fs-7">Welcome back, here is your store activity summary.</p>
  </div>
  <span class="badge" style="background-color: var(--beige-primary-light); color: var(--beige-primary-accent); font-weight: 600; padding: 0.5rem 0.85rem; border-radius: 6px;">
    <i class="mdi mdi-calendar-blank-outline me-1"></i> {{ now()->format('M d, Y') }}
  </span>
</div>

<div class="row g-4 mb-4">
  @foreach ([['Total Sales', 'mdi-currency-usd', Fmt::money($cards['sales'])], ['Orders', 'mdi-cart-outline', number_format($cards['orders'])], ['Customers', 'mdi-account-group-outline', number_format($cards['customers'])], ['Active Products', 'mdi-package-variant-closed', number_format($cards['products'])]] as $i => [$label, $icon, $value])
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card overview-card h-100">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <span class="card-metric-label">{{ $label }}</span>
          <div class="overview-icon"><i class="mdi {{ $icon }}"></i></div>
        </div>
        <h3 class="fw-bold mb-1" style="color: var(--beige-text-primary);">{{ $value }}</h3>
        @if ($i === 0 && $cards['growth'] !== null)
          <div class="d-flex align-items-center gap-1 fs-8">
            <span class="badge {{ $cards['growth'] >= 0 ? 'bg-label-success' : 'bg-label-danger' }}"><i class="mdi mdi-arrow-{{ $cards['growth'] >= 0 ? 'up' : 'down' }}-thin"></i> {{ abs($cards['growth']) }}%</span>
            <span class="text-muted">vs last month</span>
          </div>
        @endif
      </div>
    </div>
  @endforeach
</div>

<div class="row g-4 mb-4">
  <div class="col-12 col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div><h5 class="card-title mb-0">Sales Overview</h5><small class="card-subtitle">Revenue performance &amp; trends</small></div>
        <div class="d-flex align-items-center gap-1" id="timeFilterGroup">
          @foreach (['7d' => '7 Days', '30d' => '30 Days', '3m' => '3 Months', '1y' => '1 Year'] as $k => $l)
            <button type="button" class="time-filter-btn {{ $k === '30d' ? 'active' : '' }}" data-period="{{ $k }}">{{ $l }}</button>
          @endforeach
        </div>
      </div>
      <div class="card-body pt-3"><div id="salesOverviewChart" style="min-height: 310px;"></div></div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card h-100">
      <div class="card-header"><h5 class="card-title mb-0">Order Status</h5><small class="card-subtitle">Distribution of all orders</small></div>
      <div class="card-body d-flex flex-column gap-3">
        @foreach ($statusMeta as $status => [$icon, $color])
          @php $n = (int) ($statusCounts[$status] ?? 0); $pct = round($n / $totalOrders * 100); @endphp
          <div>
            <div class="d-flex align-items-center justify-content-between mb-1">
              <span class="fs-8 fw-medium text-dark"><i class="mdi {{ $icon }} me-1 text-{{ $color }}"></i> {{ ucfirst($status) }}</span>
              <span class="fs-8 fw-bold text-{{ $color }}">{{ $n }} orders ({{ $pct }}%)</span>
            </div>
            <div class="status-progress-bg"><div class="status-progress-bar bg-{{ $color }}" style="width: {{ $pct }}%;"></div></div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-12 col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div><h5 class="card-title mb-0">Recent Orders</h5><small class="card-subtitle">Latest customer purchases</small></div>
        <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-beige">View All</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead><tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
          <tbody>
            @forelse ($recentOrders as $o)
              <tr data-row-href="{{ route('orders.show', $o->id) }}">
                <td><a href="{{ route('orders.show', $o->id) }}" class="fw-bold text-dark">#{{ $o->order_number }}</a></td>
                <td>{{ $o->shipping_recipient_name }}</td>
                <td class="text-muted">{{ \Illuminate\Support\Carbon::parse($o->created_at)->format('M d, Y') }}</td>
                <td class="fw-bold">{{ Fmt::money($o->grand_total) }}</td>
                <td><x-admin.badge :status="$o->order_status" /></td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted py-4">No orders yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card h-100">
      <div class="card-header"><h5 class="card-title mb-0">Top Products</h5><small class="card-subtitle">Best sellers by units</small></div>
      <div class="card-body d-flex flex-column gap-3">
        @forelse ($topProducts as $p)
          <div class="d-flex align-items-center justify-content-between">
            <div><div class="fw-bold text-dark fs-8">{{ $p->name }}</div><small class="text-muted">{{ $p->units }} units sold</small></div>
            <span class="fw-bold fs-8" style="color: var(--beige-primary-accent);">{{ Fmt::money($p->revenue) }}</span>
          </div>
        @empty
          <div class="text-muted text-center py-3">No sales yet.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-12 col-lg-7">
    <div class="card h-100">
      <div class="card-header"><h5 class="card-title mb-0">Recent Requests</h5><small class="card-subtitle">Sourcing &amp; bulk order enquiries</small></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead><tr><th>Request ID</th><th>Type</th><th>Customer</th><th>Status</th></tr></thead>
          <tbody>
            @forelse ($recentRequests as $r)
              <tr data-row-href="{{ route($r->type === 'Bulk' ? 'bulk-orders.show' : 'sourcing-requests.show', $r->id) }}">
                <td><a href="{{ route($r->type === 'Bulk' ? 'bulk-orders.show' : 'sourcing-requests.show', $r->id) }}" class="fw-bold text-dark">#{{ $r->request_number }}</a></td>
                <td><span class="badge bg-label-secondary">{{ $r->type }}</span></td>
                <td>{{ $r->name }}</td>
                <td><x-admin.badge :status="$r->status" /></td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted py-4">No requests yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-5">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div><h5 class="card-title mb-0">Low Stock Products</h5><small class="card-subtitle">Items requiring reorder</small></div>
        <span class="badge bg-label-danger">{{ $lowStock->count() }} Alerts</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead><tr><th>Product</th><th>Stock</th><th class="text-end">Status</th></tr></thead>
          <tbody>
            @forelse ($lowStock as $s)
              <tr data-row-href="{{ route('products.edit', $s->product_id) }}">
                <td><div class="fw-medium text-dark fs-8">{{ $s->name }}</div><small class="text-muted">SKU: {{ $s->sku ?: '—' }}</small></td>
                <td><span class="fw-bold {{ $s->stock <= 2 ? 'text-danger' : 'text-warning' }}">{{ $s->stock }} left</span></td>
                <td class="text-end"><span class="badge {{ $s->stock <= 2 ? 'bg-label-danger' : 'bg-label-warning' }}">{{ $s->stock <= 2 ? 'Critical' : 'Low Stock' }}</span></td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted py-4">All stock levels are healthy.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
<script>
  (function () {
    var series = @json($charts);
    var chart = new ApexCharts(document.querySelector('#salesOverviewChart'), {
      chart: { type: 'area', height: 310, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
      series: [{ name: 'Revenue (A$)', data: series['30d'].data }],
      xaxis: { categories: series['30d'].categories },
      stroke: { curve: 'smooth', width: 3 }, colors: ['#7E5B44'], dataLabels: { enabled: false },
      yaxis: { labels: { formatter: function (v) { return 'A$' + Math.round(v); } } }
    });
    chart.render();
    document.querySelectorAll('#timeFilterGroup .time-filter-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('#timeFilterGroup .time-filter-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var s = series[btn.dataset.period];
        chart.updateOptions({ xaxis: { categories: s.categories } });
        chart.updateSeries([{ name: 'Revenue (A$)', data: s.data }]);
      });
    });
  })();
</script>
@endpush
