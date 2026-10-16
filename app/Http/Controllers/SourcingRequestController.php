<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\SourcingRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SourcingRequestController extends Controller
{
    public const STATUSES = ['new', 'reviewing', 'quoted', 'accepted', 'rejected', 'completed'];

    public function index(Request $request)
    {
        $rows = SourcingRequest::query()
            ->select('id', 'request_number', 'customer_name', 'email', 'product_name', 'requested_quantity', 'target_budget', 'status', 'created_at')
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('request_number', 'like', "%$v%")->orWhere('customer_name', 'like', "%$v%")->orWhere('email', 'like', "%$v%")->orWhere('product_name', 'like', "%$v%")))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->sort === 'budget', fn ($q) => $q->orderByDesc('target_budget'), fn ($q) => $q->latest('id'))
            ->paginate($this->perPage())->withQueryString();

        return view('sourcing-requests.index', ['rows' => $rows, 'statuses' => self::STATUSES]);
    }

    public function show(SourcingRequest $sourcing_request)
    {
        $sourcing_request->load(['images', 'customer:id,first_name,last_name', 'assignee:id,name']);

        return view('sourcing-requests.show', ['req' => $sourcing_request, 'statuses' => self::STATUSES, 'admins' => AdminUser::where('status', 'active')->pluck('name', 'id')]);
    }

    public function update(Request $request, SourcingRequest $sourcing_request)
    {
        $sourcing_request->update($request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
            'assigned_admin_id' => ['nullable', 'integer', 'exists:admin_users,id'],
        ]));

        return back()->with('success', 'Request updated.');
    }

    public function destroy(SourcingRequest $sourcing_request)
    {
        return $this->destroyWith(fn () => $sourcing_request->delete(), 'sourcing-requests.index', 'request');
    }
}
