<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $rows = Customer::query()
            ->select('customers.id', 'first_name', 'last_name', 'email', 'phone', 'status', 'customers.created_at')
            ->withCount(['orders as orders_count' => fn ($q) => $q->whereNotIn('order_status', ['cancelled', 'returned'])])
            ->withSum(['orders as total_spent' => fn ($q) => $q->whereNotIn('order_status', ['cancelled', 'returned'])], 'grand_total')
            ->withMax('orders as last_order_at', 'created_at')
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('first_name', 'like', "%$v%")->orWhere('last_name', 'like', "%$v%")->orWhere('email', 'like', "%$v%")->orWhere('phone', 'like', "%$v%")))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->sort, fn ($q, $v) => match ($v) {
                'spent' => $q->orderByDesc('total_spent'), 'orders' => $q->orderByDesc('orders_count'), default => $q->latest('customers.id'),
            }, fn ($q) => $q->latest('customers.id'))
            ->paginate($this->perPage())->withQueryString();

        return view('customers.index', compact('rows'));
    }

    public function show(Customer $customer)
    {
        $customer->load('addresses');
        $orders = $customer->orders()->select('id', 'customer_id', 'order_number', 'grand_total', 'payment_status', 'order_status', 'created_at')->latest('id')->limit(10)->get();
        $stats = $customer->orders()->whereNotIn('order_status', ['cancelled', 'returned'])->selectRaw('COUNT(*) c, COALESCE(SUM(grand_total),0) s, MAX(created_at) last')->first();
        $requests = [
            'sourcing' => DB::table('sourcing_requests')->where('customer_id', $customer->id)->latest('id')->limit(5)->get(['id', 'request_number', 'product_name', 'status', 'created_at']),
            'bulk' => DB::table('bulk_order_requests')->where('customer_id', $customer->id)->latest('id')->limit(5)->get(['id', 'request_number', 'company_name', 'status', 'created_at']),
        ];

        return view('customers.show', compact('customer', 'orders', 'stats', 'requests'));
    }

    public function edit(Customer $customer)
    {
        $customer->load('addresses');

        return view('customers.edit', ['customer' => $customer, 'address' => $customer->addresses->firstWhere('is_default', 1) ?? $customer->addresses->first()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('customers', 'email')->ignore($customer)],
            'phone' => ['nullable', 'string', 'max:40', Rule::unique('customers', 'phone')->ignore($customer)],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'suburb' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:30'],
        ]);

        DB::transaction(function () use ($customer, $data, $request) {
            $customer->update(collect($data)->only(['first_name', 'last_name', 'email', 'phone', 'status', 'notes'])->map(fn ($v) => $v === '' ? null : $v)->all());

            if ($request->filled('street_address')) {
                $address = $customer->addresses()->where('is_default', 1)->first() ?? $customer->addresses()->first();
                $fields = [
                    'street_address' => $data['street_address'], 'suburb' => $data['suburb'] ?? null, 'state' => $data['state'] ?? null, 'postcode' => $data['postcode'] ?? null,
                ];
                $address
                    ? $address->update($fields)
                    : $customer->addresses()->create($fields + ['label' => 'Home', 'recipient_name' => trim($customer->first_name.' '.$customer->last_name), 'phone' => $customer->phone ?: '-', 'is_default' => 1]);
            }
        });

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated.');
    }
}
