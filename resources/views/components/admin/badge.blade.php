@props(['status'])
<span {{ $attributes->merge(['class' => 'badge fs-8 '.\App\Support\Fmt::badge($status)]) }}>{{ ucwords(str_replace('_', ' ', (string) $status)) }}</span>
