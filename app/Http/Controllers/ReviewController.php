<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $rows = ProductReview::query()
            ->select('id', 'product_id', 'customer_id', 'reviewer_name', 'reviewer_email', 'rating', 'title', 'body', 'status', 'created_at')
            ->with('product:id,name')
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('reviewer_name', 'like', "%$v%")->orWhere('reviewer_email', 'like', "%$v%")->orWhere('title', 'like', "%$v%")->orWhere('body', 'like', "%$v%")->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%$v%"))))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->rating, fn ($q, $v) => $q->where('rating', $v))
            ->when($request->sort, fn ($q, $v) => match ($v) {
                'oldest' => $q->oldest('id'), 'high' => $q->orderByDesc('rating'), 'low' => $q->orderBy('rating'), default => $q->latest('id'),
            }, fn ($q) => $q->latest('id'))
            ->paginate($this->perPage())->withQueryString();

        return view('reviews.index', compact('rows'));
    }

    public function create()
    {
        return view('reviews.form', ['review' => new ProductReview(['rating' => 5, 'status' => 'pending']), 'products' => $this->products()]);
    }

    public function store(Request $request)
    {
        $review = ProductReview::create($this->validated($request));

        return redirect()->route('reviews.show', $review)->with('success', 'Review created.');
    }

    public function show(ProductReview $review)
    {
        $review->load(['product:id,name,sku', 'customer:id,first_name,last_name']);

        return view('reviews.show', compact('review'));
    }

    public function edit(ProductReview $review)
    {
        return view('reviews.form', ['review' => $review, 'products' => $this->products()]);
    }

    public function update(Request $request, ProductReview $review)
    {
        $data = $this->validated($request, $review);
        $review->update($data);

        return redirect()->route('reviews.show', $review)->with('success', 'Review updated.');
    }

    public function destroy(ProductReview $review)
    {
        return $this->destroyWith(fn () => $review->delete(), 'reviews.index', 'review');
    }

    private function products()
    {
        return Product::orderBy('name')->pluck('name', 'id');
    }

    private function validated(Request $request, ?ProductReview $review = null): array
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'reviewer_name' => ['required', 'string', 'max:160'],
            'reviewer_email' => ['nullable', 'email', 'max:190'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:220'],
            'body' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'admin_reply' => ['nullable', 'string', 'max:5000'],
        ]);
        $data = collect($data)->map(fn ($v) => $v === '' ? null : $v)->all();

        // Stamp the reply time only when the reply text actually changes.
        if (($data['admin_reply'] ?? null) !== ($review?->admin_reply)) {
            $data['replied_at'] = ($data['admin_reply'] ?? null) ? now() : null;
        }

        return $data;
    }
}
