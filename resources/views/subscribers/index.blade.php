@extends('layouts.admin')
@section('title', 'Subscribers')
@section('section', 'Engagement')

@section('content')
<x-admin.page-header title="Newsletter Subscribers" subtitle="Manage email newsletter subscribers, opt-in sources, and subscription status.">
  <a href="{{ route('subscribers.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Add Subscriber</a>
</x-admin.page-header>

<x-admin.filters :statuses="['subscribed', 'unsubscribed']" placeholder="Search email...">
  <select name="sort" class="form-select" style="max-width: 170px;" onchange="this.form.submit()">
    <option value="">Newest First</option><option value="email" @selected(request('sort') === 'email')>Email: A to Z</option>
  </select>
</x-admin.filters>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>ID</th><th>Subscriber Email</th><th>Opt-in Source</th><th>Subscription Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $s)
          <tr>
            <td><a href="{{ route('subscribers.show', $s) }}" class="fw-bold text-dark">#SUB-{{ $s->id }}</a></td>
            <td>{{ $s->email }}</td>
            <td>{{ \App\Http\Controllers\SubscriberController::SOURCES[$s->source] ?? ($s->source ?: '—') }}</td>
            <td>{{ ($s->subscribed_at ?? $s->created_at)->format('M d, Y') }}</td>
            <td><x-admin.badge :status="$s->status" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('subscribers.show', $s)" :edit="route('subscribers.edit', $s)" :delete="route('subscribers.destroy', $s)" label="subscriber" /></td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No subscribers found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
