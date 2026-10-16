<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomepageController extends Controller
{
    private const KEYS = [
        'home_hero_slider_enabled' => 'bool', 'home_hero_title' => 'text', 'home_hero_subtitle' => 'text',
        'home_announcement_enabled' => 'bool', 'home_announcement_text' => 'text', 'home_featured_title' => 'text',
    ];

    public function edit()
    {
        $stored = DB::table('site_settings')->whereIn('setting_key', array_keys(self::KEYS))->pluck('setting_value', 'setting_key')->map(fn ($v) => json_decode($v, true));

        return view('homepage.edit', ['s' => $stored]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'home_hero_title' => ['nullable', 'string', 'max:220'], 'home_hero_subtitle' => ['nullable', 'string', 'max:500'],
            'home_announcement_text' => ['nullable', 'string', 'max:300'], 'home_featured_title' => ['nullable', 'string', 'max:220'],
        ]);

        DB::transaction(function () use ($request, $data) {
            foreach (self::KEYS as $key => $type) {
                $value = $type === 'bool' ? $request->boolean($key) : (string) ($data[$key] ?? '');
                DB::table('site_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => json_encode($value), 'updated_by' => auth('admin')->id(), 'updated_at' => now()]);
            }
        });

        return redirect()->route('homepage.edit')->with('success', 'Homepage settings saved.');
    }
}
