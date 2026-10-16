<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\BulkOrderRequest;
use App\Models\BulkOrderRequestItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BulkOrderController extends Controller
{
    public function index(Request $request)
    {
        $rows = BulkOrderRequest::query()
            ->select('id', 'request_number', 'contact_name', 'company_name', 'email', 'status', 'created_at')
            ->withCount('items')
            ->addSelect(['total_qty' => BulkOrderRequestItem::selectRaw('COALESCE(SUM(quantity),0)')->whereColumn('request_id', 'bulk_order_requests.id')])
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('request_number', 'like', "%$v%")->orWhere('contact_name', 'like', "%$v%")->orWhere('company_name', 'like', "%$v%")->orWhere('email', 'like', "%$v%")))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')->paginate($this->perPage())->withQueryString();

        return view('bulk-orders.index', ['rows' => $rows, 'statuses' => SourcingRequestController::STATUSES]);
    }

    public function show(BulkOrderRequest $bulk_order)
    {
        $bulk_order->load(['items.variants', 'customer:id,first_name,last_name', 'assignee:id,name']);

        return view('bulk-orders.show', ['req' => $bulk_order, 'statuses' => SourcingRequestController::STATUSES, 'admins' => AdminUser::where('status', 'active')->pluck('name', 'id')]);
    }

    public function update(Request $request, BulkOrderRequest $bulk_order)
    {
        $bulk_order->update($request->validate([
            'status' => ['required', Rule::in(SourcingRequestController::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
            'assigned_admin_id' => ['nullable', 'integer', 'exists:admin_users,id'],
        ]));

        return back()->with('success', 'Request updated.');
    }

    public function destroy(BulkOrderRequest $bulk_order)
    {
        return $this->destroyWith(fn () => $bulk_order->delete(), 'bulk-orders.index', 'request');
    }
}
