<?php

namespace App\Http\Controllers;

use App\Models\HeroSlide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BannerController extends Controller
{
    /** Reordering is drag-and-drop from the list, so keep the whole set on one page whenever possible. */
    private const REORDER_PER_PAGE = 100;

    public function index(Request $request)
    {
        $filtered = $request->filled('q') || $request->filled('status');

        $rows = HeroSlide::query()
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', "%$v%")->orWhere('subtitle', 'like', "%$v%")))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('sort_order')->orderByDesc('id')->paginate(self::REORDER_PER_PAGE)->withQueryString();

        // Dragging only makes sense over the full, unfiltered, single-page list; otherwise order is ambiguous.
        $canReorder = ! $filtered && ! $rows->hasPages();

        return view('banners.index', compact('rows', 'canReorder'));
    }

    /** Persist the drag-and-drop order from the list page. */
    public function reorder(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'distinct', 'exists:hero_slides,id']]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['ids']) as $i => $id) {
                HeroSlide::where('id', $id)->update(['sort_order' => $i + 1, 'updated_by' => auth('admin')->id()]);
            }
        });

        return response()->json(['message' => 'Banner order saved.']);
    }

    public function create()
    {
        return view('banners.form', ['banner' => new HeroSlide(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null, true);
        $data['image_url'] = $this->uploadImage($request, 'image', 'banners', $data['image_url'] ?? null);
        $data['mobile_image_url'] = $this->uploadImage($request, 'mobile_image', 'banners');
        // New banners join at the end of the list; reordering happens from the list page, not this form.
        $data['sort_order'] = (int) HeroSlide::max('sort_order') + 1;
        $banner = HeroSlide::create($data);

        return redirect()->route('banners.show', $banner)->with('success', 'Banner created.');
    }

    public function show(HeroSlide $banner)
    {
        return view('banners.show', compact('banner'));
    }

    public function edit(HeroSlide $banner)
    {
        return view('banners.form', compact('banner'));
    }

    public function update(Request $request, HeroSlide $banner)
    {
        $data = $this->validated($request, $banner);
        $data['image_url'] = $this->uploadImage($request, 'image', 'banners', $data['image_url'] ?? $banner->image_url);
        $data['mobile_image_url'] = $this->uploadImage($request, 'mobile_image', 'banners', $banner->mobile_image_url);
        $banner->update($data);

        return redirect()->route('banners.show', $banner)->with('success', 'Banner updated.');
    }

    public function destroy(HeroSlide $banner)
    {
        return $this->destroyWith(fn () => $banner->delete(), 'banners.index', 'banner');
    }

    private function validated(Request $request, ?HeroSlide $banner = null, bool $creating = false): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:220'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'image' => [$creating ? 'required_without:image_url' : 'nullable', 'file'],
            'image_url' => ['nullable', 'url:https', 'max:2048'],
            'mobile_image' => ['nullable', 'file'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'button_url' => ['nullable', 'string', 'max:2048', 'not_regex:/^\s*(javascript|data|vbscript):/i'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], ['button_url.not_regex' => 'That destination type is not allowed.', 'image.required_without' => 'Upload a banner image or paste an https image URL.']);

        unset($data['image'], $data['mobile_image']);

        return collect($data)->map(fn ($v) => $v === '' ? null : $v)->all();
    }
}
