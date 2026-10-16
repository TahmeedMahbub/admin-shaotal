<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $rows = Page::query()->select('id', 'title', 'slug', 'featured_image_url', 'status', 'updated_at')
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', "%$v%")->orWhere('slug', 'like', "%$v%")->orWhere('content', 'like', "%$v%")))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')->paginate($this->perPage())->withQueryString();

        return view('pages.index', compact('rows'));
    }

    public function create()
    {
        return view('pages.form', ['page' => new Page(['status' => 'draft'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['featured_image_url'] = $this->uploadImage($request, 'image', 'pages', $data['featured_image_url'] ?? null);
        $page = Page::create($data);

        return redirect()->route('pages.show', $page)->with('success', 'Page created.');
    }

    public function show(Page $page)
    {
        return view('pages.show', compact('page'));
    }

    public function edit(Page $page)
    {
        return view('pages.form', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $data = $this->validated($request, $page);
        $data['featured_image_url'] = $this->uploadImage($request, 'image', 'pages', $data['featured_image_url'] ?? null);
        $page->update($data);

        return redirect()->route('pages.show', $page)->with('success', 'Page updated.');
    }

    public function destroy(Page $page)
    {
        return $this->destroyWith(fn () => $page->delete(), 'pages.index', 'page');
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:220'],
            'slug' => ['nullable', 'string', 'max:240', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('pages', 'slug')->ignore($page)],
            'content' => ['nullable', 'string', 'max:500000'],
            'featured_image_url' => ['nullable', 'url:https', 'max:2048'],
            'image' => ['nullable', 'file'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ]);
        $data['slug'] = $this->slugFor('pages', $data['slug'] ?? null, $data['title'], $page?->id);
        unset($data['image']);

        return collect($data)->map(fn ($v) => $v === '' ? null : $v)->all();
    }
}
