<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public const ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];

    public const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'partially_refunded', 'refunded'];

    public function index(Request $request)
    {
        $from = match ($request->range) {
            'today' => now()->startOfDay(), '7d' => now()->subDays(7), '30d' => now()->subDays(30), 'month' => now()->startOfMonth(), default => null,
        };

        $rows = Order::query()
            ->select('id', 'order_number', 'customer_id', 'shipping_recipient_name', 'customer_email', 'grand_total', 'payment_status', 'order_status', 'created_at')
            ->withSum('items as items_qty', 'quantity')
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('order_number', 'like', "%$v%")->orWhere('shipping_recipient_name', 'like', "%$v%")->orWhere('customer_email', 'like', "%$v%")))
            ->when($request->order_status, fn ($q, $v) => $q->where('order_status', $v))
            ->when($request->payment_status, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($request->sort === 'oldest', fn ($q) => $q->oldest('id'), fn ($q) => $request->sort === 'amount' ? $q->orderByDesc('grand_total') : $q->latest('id'))
            ->paginate($this->perPage())->withQueryString();

        return view('orders.index', ['rows' => $rows, 'orderStatuses' => self::ORDER_STATUSES, 'paymentStatuses' => self::PAYMENT_STATUSES]);
    }

    public function show(Order $order)
    {
        $order->load(['items', 'payments', 'timeline.admin:id,name', 'customer:id,first_name,last_name,email']);

        return view('orders.show', ['order' => $order, 'orderStatuses' => self::ORDER_STATUSES, 'paymentStatuses' => self::PAYMENT_STATUSES]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate(['order_status' => ['required', Rule::in(self::ORDER_STATUSES)], 'note' => ['nullable', 'string', 'max:500']]);

        if ($data['order_status'] !== $order->order_status) {
            DB::transaction(function () use ($order, $data) {
                $old = $order->order_status;
                $order->update(['order_status' => $data['order_status'], 'cancelled_at' => $data['order_status'] === 'cancelled' ? now() : $order->cancelled_at]);
                OrderTimeline::create(['order_id' => $order->id, 'old_status' => $old, 'new_status' => $data['order_status'], 'note' => $data['note'] ?? null, 'changed_by_admin_id' => auth('admin')->id()]);
            });
        }

        return back()->with('success', 'Order status updated.');
    }

    public function updatePayment(Request $request, Order $order)
    {
        $data = $request->validate(['payment_status' => ['required', Rule::in(self::PAYMENT_STATUSES)]]);

        DB::transaction(function () use ($order, $data) {
            $old = $order->payment_status;
            $order->update(['payment_status' => $data['payment_status']]);
            if ($old !== $data['payment_status']) {
                // Keep the latest payment row in step with the order, for payments the order already has.
                $payment = $order->payments()->latest('id')->first();
                $payment?->update(['status' => $data['payment_status'], 'paid_at' => $data['payment_status'] === 'paid' ? now() : $payment->paid_at]);
                OrderTimeline::create(['order_id' => $order->id, 'old_status' => 'payment:'.$old, 'new_status' => 'payment:'.$data['payment_status'], 'changed_by_admin_id' => auth('admin')->id()]);
            }
        });

        return back()->with('success', 'Payment status updated.');
    }

    public function updateNote(Request $request, Order $order)
    {
        $order->update($request->validate(['admin_note' => ['nullable', 'string', 'max:5000']]));

        return back()->with('success', 'Note saved.');
    }
}
