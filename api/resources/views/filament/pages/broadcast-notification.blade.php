<x-filament-panels::page>
    {{-- Enter opens the confirm step too; send() never runs straight from the form. --}}
    <form wire:submit="mountAction('send')">
        {{ $this->form }}

        <div class="mt-6">
            {{ $this->sendAction }}
        </div>
    </form>

</x-filament-panels::page>
