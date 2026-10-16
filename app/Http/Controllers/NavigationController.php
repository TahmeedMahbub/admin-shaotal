<?php

namespace App\Http\Controllers;

use App\Models\NavigationItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NavigationController extends Controller
{
    public const LOCATIONS = ['header' => 'Main Navbar', 'footer_quick_links' => 'Footer: Quick Links', 'footer_customer_service' => 'Footer: Customer Service'];

    public function index(Request $request)
    {
        $rows = NavigationItem::query()->with('parent:id,title')
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', "%$v%")->orWhere('url', 'like', "%$v%")))
            ->when($request->location, fn ($q, $v) => $q->where('location', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('location')->orderBy('parent_id')->orderBy('sort_order')->paginate($this->perPage())->withQueryString();

        return view('navigation.index', ['rows' => $rows, 'locations' => self::LOCATIONS]);
    }

    public function create()
    {
        return view('navigation.form', $this->formData(new NavigationItem(['location' => 'header', 'status' => 'active', 'sort_order' => 1])));
    }

    public function store(Request $request)
    {
        NavigationItem::create($this->validated($request));

        return redirect()->route('navigation.index')->with('success', 'Navigation item created.');
    }

    public function edit(NavigationItem $navigation)
    {
        return view('navigation.form', $this->formData($navigation));
    }

    public function update(Request $request, NavigationItem $navigation)
    {
        $navigation->update($this->validated($request, $navigation));

        return redirect()->route('navigation.index')->with('success', 'Navigation item updated.');
    }

    public function destroy(NavigationItem $navigation)
    {
        // Sub-items are removed with their parent (ON DELETE CASCADE).
        return $this->destroyWith(fn () => $navigation->delete(), 'navigation.index', 'navigation item');
    }

    private function formData(NavigationItem $item): array
    {
        return [
            'item' => $item, 'locations' => self::LOCATIONS,
            'parents' => NavigationItem::whereNull('parent_id')->where('id', '!=', $item->id ?? 0)->orderBy('title')->pluck('title', 'id'),
        ];
    }

    private function validated(Request $request, ?NavigationItem $item = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'url' => ['required', 'string', 'max:2048', 'not_regex:/^\s*(javascript|data|vbscript):/i'],
            'location' => ['required', Rule::in(array_keys(self::LOCATIONS))],
            'parent_id' => ['nullable', 'integer', Rule::exists('navigation_items', 'id')->whereNull('parent_id'), Rule::notIn([$item?->id ?? 0])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        return collect($data)->map(fn ($v) => $v === '' ? null : $v)->all();
    }
}
