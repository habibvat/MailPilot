@props(['value'])
<div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
    <div class="h-full rounded-full bg-brand-600 transition-all" style="width: {{ min(100, max(0, $value)) }}%"></div>
</div>
