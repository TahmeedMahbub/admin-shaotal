<?php

namespace App\Http\Controllers;

use App\Models\AbandonedCartLog;
use App\Models\Cart;
use Illuminate\Http\Request;

class AbandonedCartController extends Controller
{
    /** Active carts idle for this many hours count as abandoned. */
    private const IDLE_HOURS = 1;

    public function index(Request $request)
    {
        $cutoff = now()->subHours(self::IDLE_HOURS);

        $rows = Cart::query()
            ->select('carts.id', 'carts.customer_id', 'carts.status', 'carts.last_activity_at', 'carts.created_at')
            ->with('customer:id,first_name,last_name,email')
            ->withSum('items as items_qty', 'quantity')
            ->selectSub(
                fn ($q) => $q->from('cart_items as ci')->join('products as p', 'p.id', '=', 'ci.product_id')->leftJoin('product_variants as v', 'v.id', '=', 'ci.variant_id')
                    ->whereColumn('ci.cart_id', 'carts.id')
                    ->selectRaw('COALESCE(SUM(ci.quantity * COALESCE(v.selling_price_override, p.selling_price, v.price_override, p.price)), 0)'),
                'cart_value'
            )
            ->whereHas('items')
            ->where('carts.status', '!=', 'converted')
            ->where(fn ($q) => $q->where('carts.status', 'abandoned')->orWhere('carts.last_activity_at', '<', $cutoff))
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('carts.id', ltrim($v, '#CRT-'))->orWhereHas('customer', fn ($c) => $c->where('email', 'like', "%$v%")->orWhere('first_name', 'like', "%$v%")->orWhere('last_name', 'like', "%$v%"))))
            ->when($request->type === 'registered', fn ($q) => $q->whereNotNull('carts.customer_id'))
            ->when($request->type === 'guest', fn ($q) => $q->whereNull('carts.customer_id'))
            ->when($request->sort === 'value', fn ($q) => $q->orderByDesc('cart_value'), fn ($q) => $q->orderByDesc('carts.last_activity_at'))
            ->paginate($this->perPage())->withQueryString();

        return view('abandoned-carts.index', compact('rows'));
    }

    public function show(Cart $cart)
    {
        $cart->load(['customer:id,first_name,last_name,email,phone', 'logs']);
        $items = $cart->items()->with(['product:id,name,sku,price,selling_price', 'variant:id,sku,color_id,size_id,price_override,selling_price_override', 'variant.color:id,name', 'variant.size:id,size_label'])->get();
        $total = $items->sum(fn ($i) => $i->quantity * (float) ($i->variant->selling_price_override ?? $i->product->selling_price ?? $i->variant->price_override ?? $i->product->price));

        return view('abandoned-carts.show', compact('cart', 'items', 'total'));
    }

    /** Record that a recovery reminder was handled (no email is sent from here). */
    public function update(Request $request, Cart $cart)
    {
        $data = $request->validate(['reminder_status' => ['required', 'in:sent,failed,not_sent']]);

        AbandonedCartLog::create([
            'cart_id' => $cart->id, 'customer_id' => $cart->customer_id, 'reminder_status' => $data['reminder_status'],
            'reminder_sent_at' => $data['reminder_status'] === 'sent' ? now() : null,
        ]);
        $cart->update(['status' => 'abandoned']);

        return back()->with('success', 'Reminder status recorded.');
    }
}
