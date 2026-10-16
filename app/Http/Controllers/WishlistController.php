<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $rows = WishlistItem::query()
            ->select('wishlist_items.*')
            ->join('products', 'products.id', '=', 'wishlist_items.product_id')
            ->with(['customer:id,first_name,last_name,email', 'product:id,name,sku,price,selling_price,has_variants,stock_qty,availability'])
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('products.name', 'like', "%$v%")->orWhereHas('customer', fn ($c) => $c->where('email', 'like', "%$v%")->orWhere('first_name', 'like', "%$v%")->orWhere('last_name', 'like', "%$v%"))))
            ->when($request->stock === 'out', fn ($q) => $q->where('products.availability', 'out_of_stock'))
            ->when($request->stock === 'in', fn ($q) => $q->where('products.availability', '!=', 'out_of_stock'))
            ->when($request->sort, fn ($q, $v) => match ($v) {
                'oldest' => $q->oldest('wishlist_items.id'), 'price_high' => $q->orderByDesc('products.price'), 'price_low' => $q->orderBy('products.price'), default => $q->latest('wishlist_items.id'),
            }, fn ($q) => $q->latest('wishlist_items.id'))
            ->paginate($this->perPage())->withQueryString();

        return view('wishlists.index', compact('rows'));
    }

    public function create()
    {
        return view('wishlists.form', [
            'customers' => Customer::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email'])->mapWithKeys(fn ($c) => [$c->id => trim($c->first_name.' '.$c->last_name).' ('.$c->email.')']),
            'products' => Product::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'product_id' => ['required', 'integer', 'exists:products,id', Rule::unique('wishlist_items')->where('customer_id', $request->input('customer_id'))],
        ], ['product_id.unique' => 'That product is already on this customer\'s wishlist.']);

        $item = WishlistItem::create($data);

        return redirect()->route('wishlists.show', $item)->with('success', 'Wishlist item added.');
    }

    public function show(WishlistItem $wishlist)
    {
        $wishlist->load(['customer:id,first_name,last_name,email', 'product:id,name,sku,price,selling_price,has_variants,stock_qty,availability']);

        return view('wishlists.show', ['item' => $wishlist]);
    }

    public function destroy(WishlistItem $wishlist)
    {
        return $this->destroyWith(fn () => $wishlist->delete(), 'wishlists.index', 'wishlist item');
    }
}
