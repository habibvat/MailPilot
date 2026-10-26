@props(['status', 'label' => null])
<span {{ $attributes->merge(['class' => 'badge '.\App\Support\Badge::classes($status)]) }}>{{ __($label ?? $status) }}</span>
