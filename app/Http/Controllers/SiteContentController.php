<?php

namespace App\Http\Controllers;

use App\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteContentController extends Controller
{
    // Location slugs are created by developers / SQL, so there is no create or delete here.
    public function index(Request $request)
    {
        $rows = SiteContent::query()
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('slug', 'like', "%$v%")->orWhere('title', 'like', "%$v%")->orWhere('content', 'like', "%$v%")))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('slug')->paginate($this->perPage())->withQueryString();

        return view('site-contents.index', compact('rows'));
    }

    public function show(SiteContent $site_content)
    {
        return view('site-contents.show', ['item' => $site_content]);
    }

    public function edit(SiteContent $site_content)
    {
        return view('site-contents.form', ['item' => $site_content]);
    }

    public function update(Request $request, SiteContent $site_content)
    {
        $site_content->update(collect($request->validate([
            'title' => ['required', 'string', 'max:220'],
            'content' => ['nullable', 'string', 'max:500000'],
            'status' => ['required', Rule::in(['enabled', 'disabled'])],
        ]))->map(fn ($v) => $v === '' ? null : $v)->all());

        return redirect()->route('site-contents.show', $site_content)->with('success', 'Site content updated.');
    }
}
