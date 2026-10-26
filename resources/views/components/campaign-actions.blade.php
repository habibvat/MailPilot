@props(['campaign', 'small' => false])
@php $size = $small ? ' btn-sm' : ''; @endphp
<div class="flex flex-wrap items-center justify-end gap-2">
    @switch($campaign->status)
        @case('draft')
            <a href="{{ route('campaigns.edit', $campaign) }}" wire:navigate class="btn-secondary{{ $size }}">{{ __('Modifier') }}</a>
            <button type="button" wire:click="startCampaign({{ $campaign->id }})" wire:confirm="{{ __('Démarrer la campagne « :name » ?', ['name' => $campaign->name]) }}" class="btn-success{{ $size }}">{{ __('Démarrer') }}</button>
            @break
        @case('running')
            <button type="button" wire:click="pauseCampaign({{ $campaign->id }})" class="btn-warning{{ $size }}">{{ __('Pause') }}</button>
            <button type="button" wire:click="stopCampaign({{ $campaign->id }})" wire:confirm="{{ __('Arrêter la campagne ?') }}" class="btn-danger{{ $size }}">{{ __('Arrêter') }}</button>
            @break
        @case('paused')
            <button type="button" wire:click="resumeCampaign({{ $campaign->id }})" class="btn-success{{ $size }}">{{ __('Reprendre') }}</button>
            <button type="button" wire:click="stopCampaign({{ $campaign->id }})" wire:confirm="{{ __('Arrêter la campagne ?') }}" class="btn-danger{{ $size }}">{{ __('Arrêter') }}</button>
            @break
        @case('stopped')
            <button type="button" wire:click="resumeCampaign({{ $campaign->id }})" wire:confirm="{{ __('Reprendre la campagne ? Assurez-vous d\'avoir corrigé la cause de l\'arrêt.') }}" class="btn-secondary{{ $size }}">{{ __('Reprendre') }}</button>
            @break
    @endswitch
</div>
