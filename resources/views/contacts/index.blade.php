@extends('layouts.admin')
@section('title', 'Contacts')
@section('section', 'Engagement')

@section('content')
<x-admin.page-header title="Customer Contacts &amp; Inquiries" subtitle="Track, respond to, and resolve store contact form messages.">
  <a href="{{ route('contacts.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Log Message</a>
</x-admin.page-header>

<x-admin.filters :statuses="$statuses" placeholder="Search name, email, subject..." />

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Contact ID</th><th>Sender Name</th><th>Email</th><th>Subject</th><th>Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $c)
          <tr>
            <td><a href="{{ route('contacts.show', $c) }}" class="fw-bold text-dark">#CNT-{{ $c->id }}</a></td>
            <td>{{ $c->name }}</td><td>{{ $c->email ?: '—' }}</td><td>{{ \Illuminate\Support\Str::limit($c->subject, 50) }}</td>
            <td>{{ $c->created_at->format('M d, Y') }}</td>
            <td><x-admin.badge :status="$c->status" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('contacts.show', $c)" :edit="route('contacts.edit', $c)" :delete="route('contacts.destroy', $c)" label="contact message" /></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No messages found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
