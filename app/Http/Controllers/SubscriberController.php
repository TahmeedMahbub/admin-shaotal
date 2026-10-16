<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriberController extends Controller
{
    public const SOURCES = ['footer' => 'Footer Newsletter', 'checkout' => 'Checkout Popup', 'landing' => 'Landing Page Modal', 'header' => 'Header Banner Bar', 'manual' => 'Manual Entry'];

    public function index(Request $request)
    {
        $rows = NewsletterSubscriber::query()
            ->when($request->q, fn ($q, $v) => $q->where('email', 'like', "%$v%"))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->sort === 'email', fn ($q) => $q->orderBy('email'), fn ($q) => $q->latest('id'))
            ->paginate($this->perPage())->withQueryString();

        return view('subscribers.index', compact('rows'));
    }

    public function create()
    {
        return view('subscribers.form', ['subscriber' => new NewsletterSubscriber(['status' => 'subscribed', 'source' => 'manual'])]);
    }

    public function store(Request $request)
    {
        $subscriber = NewsletterSubscriber::create($this->validated($request));

        return redirect()->route('subscribers.show', $subscriber)->with('success', 'Subscriber added.');
    }

    public function show(NewsletterSubscriber $subscriber)
    {
        return view('subscribers.show', compact('subscriber'));
    }

    public function edit(NewsletterSubscriber $subscriber)
    {
        return view('subscribers.form', compact('subscriber'));
    }

    public function update(Request $request, NewsletterSubscriber $subscriber)
    {
        $subscriber->update($this->validated($request, $subscriber));

        return redirect()->route('subscribers.show', $subscriber)->with('success', 'Subscriber updated.');
    }

    public function destroy(NewsletterSubscriber $subscriber)
    {
        return $this->destroyWith(fn () => $subscriber->delete(), 'subscribers.index', 'subscriber');
    }

    private function validated(Request $request, ?NewsletterSubscriber $subscriber = null): array
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190', Rule::unique('newsletter_subscribers', 'email')->ignore($subscriber)],
            'status' => ['required', Rule::in(['subscribed', 'unsubscribed'])],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        if ($data['status'] !== $subscriber?->status) {
            $data[$data['status'] === 'subscribed' ? 'subscribed_at' : 'unsubscribed_at'] = now();
        }

        return $data;
    }
}
