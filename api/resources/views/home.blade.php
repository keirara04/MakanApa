@extends('layouts.marketing')

@section('title', 'MakanApa | What to Eat')
@section('description', 'MakanApa helps you decide what to eat in seconds: craving, budget, distance, done.')

@section('content')

    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-b from-sambal-50 to-cream px-6 py-16 text-center">

        <span class="absolute left-[8%] top-[18%] text-3xl animate-float" style="animation-delay:0s">🍜</span>
        <span class="absolute right-[10%] top-[12%] text-2xl animate-float" style="animation-delay:.6s">🧋</span>
        <span class="absolute left-[14%] bottom-[14%] text-2xl animate-float" style="animation-delay:1.2s">🍢</span>
        <span class="absolute right-[14%] bottom-[20%] text-3xl animate-float" style="animation-delay:1.8s">🍛</span>

        <img src="{{ asset('images/mascot-celebrate.svg') }}" alt=""
             class="relative mx-auto h-28 w-28 animate-bob" aria-hidden="true">

        <h1 class="relative mt-6 text-4xl font-semibold tracking-tight sm:text-5xl animate-rise" style="animation-delay:.05s">
            MakanApa
        </h1>
        <p class="relative mt-3 text-lg text-sambal-600 font-medium animate-rise" style="animation-delay:.15s">
            What to eat, decided in seconds.
        </p>
        <p class="relative mt-4 max-w-xl mx-auto text-ink/70 animate-rise" style="animation-delay:.25s">
            Tell us your craving, your budget, and how far you're willing to go. MakanApa picks a place
            nearby so you stop scrolling and start eating.
        </p>

        <div class="relative mt-8 flex flex-wrap items-center justify-center gap-3 animate-rise" style="animation-delay:.35s">
            <a href="{{ url('/support') }}"
               class="rounded-full bg-sambal-500 px-6 py-3 font-medium text-white transition hover:bg-sambal-600 hover:-translate-y-0.5 hover:shadow-lg">
                Get support
            </a>
            <a href="{{ url('/privacy') }}"
               class="rounded-full border border-sambal-200 bg-white px-6 py-3 font-medium text-ink transition hover:border-sambal-500 hover:-translate-y-0.5">
                Privacy Policy
            </a>
        </div>
    </div>

    <section class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="group rounded-2xl border border-sambal-100 bg-white p-6 text-center transition duration-300 hover:-translate-y-1 hover:border-sambal-300 hover:shadow-xl">
            <div class="text-3xl transition-transform duration-300 group-hover:scale-125 group-hover:rotate-6">🍛</div>
            <h2 class="mt-3 font-medium">Craving something?</h2>
            <p class="mt-1 text-sm text-ink/70">Spicy, comfort, light, quick. Tell MakanApa the mood.</p>
        </div>
        <div class="group rounded-2xl border border-sambal-100 bg-white p-6 text-center transition duration-300 hover:-translate-y-1 hover:border-sambal-300 hover:shadow-xl">
            <div class="text-3xl transition-transform duration-300 group-hover:scale-125 group-hover:-rotate-6">💸</div>
            <h2 class="mt-3 font-medium">Set a budget</h2>
            <p class="mt-1 text-sm text-ink/70">Save, normal, or treat yourself. You're in control.</p>
        </div>
        <div class="group rounded-2xl border border-sambal-100 bg-white p-6 text-center transition duration-300 hover:-translate-y-1 hover:border-sambal-300 hover:shadow-xl">
            <div class="text-3xl transition-transform duration-300 group-hover:scale-125 group-hover:rotate-6">📍</div>
            <h2 class="mt-3 font-medium">Nearby, fast</h2>
            <p class="mt-1 text-sm text-ink/70">Walk, drive, or stay close. MakanApa finds it near you.</p>
        </div>
    </section>

    <section class="mt-10 overflow-hidden rounded-2xl border border-sambal-100 bg-white p-6">
        <p class="text-center text-xs font-semibold uppercase tracking-wider text-sambal-600">How it works</p>
        <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-3">
            <div class="flex items-start gap-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sambal-500 font-semibold text-white">1</span>
                <p class="text-sm text-ink/70">Pick your craving, budget, and how far you'll go.</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sambal-500 font-semibold text-white">2</span>
                <p class="text-sm text-ink/70">MakanApa matches it against restaurants near you.</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sambal-500 font-semibold text-white">3</span>
                <p class="text-sm text-ink/70">Get a pick, go eat, less overthinking. 🍚</p>
            </div>
        </div>
    </section>

@endsection

@push('styles')
<style>
    @keyframes bob {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-8px); }
    }
    @keyframes float {
        0%, 100% { transform: translateY(0) rotate(0deg); opacity: .85; }
        50% { transform: translateY(-14px) rotate(6deg); opacity: 1; }
    }
    @keyframes rise {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-bob { animation: bob 3.5s ease-in-out infinite; }
    .animate-float { animation: float 4.5s ease-in-out infinite; }
    .animate-rise {
        opacity: 0;
        animation: rise .6s ease-out forwards;
    }
    @media (prefers-reduced-motion: reduce) {
        .animate-bob, .animate-float { animation: none; }
        .animate-rise { opacity: 1; animation: none; }
    }
</style>
@endpush
