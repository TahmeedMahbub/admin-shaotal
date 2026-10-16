<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OfferController extends Controller
{
    public function index(Request $request)
    {
        $now = now();
        $rows = Offer::query()->withCount(['products', 'categories'])
            ->when($request->q, fn ($q, $v) => $q->where('name', 'like', "%$v%"))
            ->when($request->type, fn ($q, $v) => $q->where('discount_type', $v))
            ->when($request->status, fn ($q, $v) => match ($v) {
                'scheduled' => $q->where('status', 'active')->where('starts_at', '>', $now),
                'expired' => $q->where('status', 'active')->where('ends_at', '<', $now),
                'active' => $q->where('status', 'active')->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now)),
                default => $q->where('status', $v),
            })
            ->latest('id')->paginate($this->perPage())->withQueryString();

        return view('offers.index', compact('rows'));
    }

    public function create()
    {
        return view('offers.form', $this->formData(new Offer(['discount_type' => 'percentage', 'status' => 'active'])));
    }

    public function store(Request $request)
    {
        $d = $this->validated($request);
        $offer = DB::transaction(function () use ($d) {
            $offer = Offer::create($d['fields']);
            $offer->products()->sync($d['products']);
            $offer->categories()->sync($d['categories']);

            return $offer;
        });

        return redirect()->route('offers.show', $offer)->with('success', 'Offer created.');
    }

    public function show(Offer $offer)
    {
        $offer->load(['products:id,name', 'categories:id,name']);

        return view('offers.show', compact('offer'));
    }

    public function edit(Offer $offer)
    {
        return view('offers.form', $this->formData($offer));
    }

    public function update(Request $request, Offer $offer)
    {
        $d = $this->validated($request);
        DB::transaction(function () use ($offer, $d) {
            $offer->update($d['fields']);
            $offer->products()->sync($d['products']);
            $offer->categories()->sync($d['categories']);
        });

        return redirect()->route('offers.show', $offer)->with('success', 'Offer updated.');
    }

    public function destroy(Offer $offer)
    {
        return $this->destroyWith(fn () => $offer->delete(), 'offers.index', 'offer');
    }

    private function formData(Offer $offer): array
    {
        $offer->loadMissing(['products:id', 'categories:id']);

        return ['offer' => $offer, 'categories' => Category::orderBy('name')->pluck('name', 'id'), 'products' => Product::orderBy('name')->pluck('name', 'id')];
    }

    private function validated(Request $request): array
    {
        $in = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'discount_value' => ['required', 'numeric', 'min:0', $request->input('discount_type') === 'percentage' ? 'max:100' : 'max:9999999999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'product_ids' => ['nullable', 'array'], 'product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['nullable', 'array'], 'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        return [
            'fields' => collect($in)->except(['product_ids', 'category_ids'])->map(fn ($v) => $v === '' ? null : $v)->all(),
            'products' => $in['product_ids'] ?? [], 'categories' => $in['category_ids'] ?? [],
        ];
    }
}
