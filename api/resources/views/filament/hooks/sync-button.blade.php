@php
    use Filament\Support\Icons\Heroicon;
@endphp

{{--
    Refreshes every mounted Livewire component (the current page + its widgets) in place —
    re-runs their mount/query logic without a full browser reload. Sits in the topbar's
    x-persist block (see livewire/topbar.blade.php) so it's rendered once and survives Filament's
    wire:navigate SPA transitions, instead of disappearing/re-mounting on every page.
--}}
<div x-data="{ syncing: false }">
    <x-filament::icon-button
        :icon="Heroicon::OutlinedArrowPath"
        color="gray"
        label="Sync"
        tooltip="Refresh this page's data"
        x-bind:class="syncing && 'fi-sync-btn-spinning'"
        x-on:click="
            syncing = true;
            Promise.all(Livewire.all().map((component) => component.$wire.$refresh()))
                .finally(() => setTimeout(() => syncing = false, 400));
        "
    />
</div>

<style>
    .fi-sync-btn-spinning svg {
        animation: fi-sync-btn-spin 0.6s linear infinite;
    }

    @keyframes fi-sync-btn-spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>
