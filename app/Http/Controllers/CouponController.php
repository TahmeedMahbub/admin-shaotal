<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $now = now();
        $rows = Coupon::query()
            ->addSelect(['redemptions' => DB::table('orders')->selectRaw('COUNT(*)')->whereColumn('coupon_code', 'coupons.code')->whereNotIn('order_status', ['cancelled', 'returned'])])
            ->when($request->q, fn ($q, $v) => $q->where('code', 'like', "%$v%"))
            ->when($request->type, fn ($q, $v) => $q->where('discount_type', $v))
            ->when($request->status, fn ($q, $v) => match ($v) {
                'scheduled' => $q->where('status', 'active')->where('starts_at', '>', $now),
                'expired' => $q->where(fn ($w) => $w->where('status', 'expired')->orWhere(fn ($e) => $e->where('status', 'active')->where('expires_at', '<', $now))),
                'active' => $q->where('status', 'active')->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>=', $now)),
                default => $q->where('status', $v),
            })
            ->latest('id')->paginate($this->perPage())->withQueryString();

        return view('coupons.index', compact('rows'));
    }

    public function create()
    {
        return view('coupons.form', $this->formData(new Coupon(['discount_type' => 'percentage', 'status' => 'active'])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $coupon = DB::transaction(function () use ($data) {
            $coupon = Coupon::create($data['fields']);
            $coupon->products()->sync($data['products']);
            $coupon->categories()->sync($data['categories']);

            return $coupon;
        });

        return redirect()->route('coupons.show', $coupon)->with('success', 'Coupon created.');
    }

    public function show(Coupon $coupon)
    {
        $coupon->load(['products:id,name', 'categories:id,name']);
        $redemptions = DB::table('orders')->where('coupon_code', $coupon->code)->whereNotIn('order_status', ['cancelled', 'returned'])
            ->selectRaw('COUNT(*) c, COALESCE(SUM(discount_total),0) d')->first();

        return view('coupons.show', compact('coupon', 'redemptions'));
    }

    public function edit(Coupon $coupon)
    {
        return view('coupons.form', $this->formData($coupon));
    }

    public function update(Request $request, Coupon $coupon)
    {
        $data = $this->validated($request, $coupon);
        DB::transaction(function () use ($coupon, $data) {
            $coupon->update($data['fields']);
            $coupon->products()->sync($data['products']);
            $coupon->categories()->sync($data['categories']);
        });

        return redirect()->route('coupons.show', $coupon)->with('success', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon)
    {
        return $this->destroyWith(fn () => $coupon->delete(), 'coupons.index', 'coupon');
    }

    private function formData(Coupon $coupon): array
    {
        $coupon->loadMissing(['products:id', 'categories:id']);

        return [
            'coupon' => $coupon,
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
            'products' => Product::orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $type = $request->input('discount_type');
        $in = $request->validate([
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon)],
            'description' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed_amount', 'free_shipping'])],
            'discount_value' => [$type === 'free_shipping' ? 'nullable' : 'required', 'numeric', 'min:0', $type === 'percentage' ? 'max:100' : 'max:9999999999'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'maximum_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(['active', 'inactive', 'expired'])],
            'product_ids' => ['nullable', 'array'], 'product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['nullable', 'array'], 'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $fields = collect($in)->except(['product_ids', 'category_ids'])->map(fn ($v) => $v === '' ? null : $v)->all();
        $fields['code'] = strtoupper($fields['code']);
        $fields['discount_value'] = $fields['discount_value'] ?? 0;

        return ['fields' => $fields, 'products' => $in['product_ids'] ?? [], 'categories' => $in['category_ids'] ?? []];
    }
}
