@extends('layouts.marketing')

@section('title', 'Community Guidelines | MakanApa')
@section('description', 'How to post, vouch and share on MakanApa, and what happens when someone breaks the rules.')

@php
    $h2 = 'font-display text-3xl font-bold uppercase leading-none text-ink';
    $link = 'font-medium text-sambal-600 underline';
@endphp

@section('content')

    <h1 class="font-display text-[clamp(3.2rem,9vw,5rem)] font-bold uppercase leading-[0.88] tracking-tight">Community Guidelines</h1>
    <p class="mt-2 text-sm text-ink/70">Last updated: {{ \Illuminate\Support\Carbon::parse(config('legal.guidelines_version'))->format('j F Y') }}</p>
    <p class="mt-4 text-ink/70">
        MakanApa works because people share honest tips about food and places. These guidelines keep it useful and
        friendly for everyone. They're part of our <a href="{{ route('terms') }}" class="{{ $link }}">Terms of Use</a>,
        and they apply to everything you share: posts, replies, reactions, places, photos, halal vouches, ownership
        claims and your display name.
    </p>
    @include('legal.partials.links')

    <div class="mt-10 space-y-10 text-ink/80 leading-relaxed">

        <section id="do">
            <h2 class="{{ $h2 }}">01. Keep it about makan</h2>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>Talk about food, places and eating around your campus or area.</li>
                <li>Be kind. Disagree with a place, not a person.</li>
                <li>Share what you've actually tried or seen yourself.</li>
                <li>Keep details accurate: prices, opening hours and menus change, so say when you saw them.</li>
            </ul>
        </section>

        <section id="dont">
            <h2 class="{{ $h2 }}">02. Not allowed</h2>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>Hate speech, or attacking anyone for their race, religion, gender, sexuality, disability or background.</li>
                <li>Harassment, bullying, threats or encouraging violence.</li>
                <li>Sexual content or nudity.</li>
                <li>Anything illegal, or promoting illegal activity.</li>
                <li>Spam, ads, links, or promoting a business while pretending to be a customer.</li>
                <li>Pretending to be someone else, including a restaurant owner you aren't.</li>
                <li>Sharing someone's personal information, like their phone number, address or IC number.</li>
                <li>Photos of people who haven't agreed to be in them.</li>
                <li>Content you don't have the rights to, like someone else's photos.</li>
                <li>Fake or misleading halal claims, or fake or altered certificates.</li>
            </ul>
            <p class="mt-4 font-medium text-ink">
                We have zero tolerance for objectionable content and abusive users. Content like this is removed, and
                accounts that post it or abuse others are suspended or permanently banned.
            </p>
        </section>

        <section id="report">
            <h2 class="{{ $h2 }}">03. Report and block</h2>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li><strong class="text-ink">Report:</strong> tap the menu on any post and choose Report. Reports are anonymous; the author isn't told who reported them.</li>
                <li><strong class="text-ink">Block:</strong> choose Block on a post to hide that person's posts from you, and yours from them. You can unblock people in Settings → Blocked.</li>
                <li>
                    For anything else, like a photo, a place or a display name, email
                    @if (config('marketing.support_email'))
                        <a href="mailto:{{ config('marketing.support_email') }}" class="{{ $link }}">{{ config('marketing.support_email') }}</a>.
                    @else
                        us from the <a href="{{ url('/support') }}" class="{{ $link }}">support page</a>.
                    @endif
                </li>
            </ul>
        </section>

        <section id="enforcement">
            <h2 class="{{ $h2 }}">04. What happens next</h2>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>We review reports within 24 hours.</li>
                <li>Posts reported by several people are hidden automatically while we review them.</li>
                <li>Content that breaks these guidelines is removed.</li>
                <li>
                    Accounts that break them may be suspended or permanently banned, depending on how serious it is. A
                    suspended account can't sign in. Creating a new account to get around a ban isn't allowed.
                </li>
            </ul>
        </section>

        <section id="halal">
            <h2 class="{{ $h2 }}">05. Halal vouches</h2>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>Only vouch for what you've seen yourself, like a certificate on display or the menu.</li>
                <li>Photos of certificates must be real and unedited.</li>
                <li>Our team checks certificates against the official registry before a place is shown as halal certified.</li>
                <li>A vouch is community information, not halal certification. Only JAKIM and the state Islamic religious authorities certify halal.</li>
            </ul>
        </section>

        <section id="contributions">
            <h2 class="{{ $h2 }}">06. Places and photos</h2>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>Add real places that serve food, with details you know are right.</li>
                <li>Upload photos you took yourself, of the food, the menu or the place.</li>
                <li>Places and photos are checked by our team before other people see them.</li>
            </ul>
        </section>

    </div>

@endsection
