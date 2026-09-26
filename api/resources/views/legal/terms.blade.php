@extends('layouts.marketing')

@section('title', 'Terms of Use | MakanApa')
@section('description', 'The rules for using MakanApa: accounts, community content, halal information and more.')

@php
    $h2 = 'font-display text-3xl font-bold uppercase leading-none text-ink';
    $link = 'font-medium text-sambal-600 underline';
    $supportEmail = config('marketing.support_email');
@endphp

@section('content')

    <h1 class="font-display text-[clamp(3.2rem,9vw,5rem)] font-bold uppercase leading-[0.88] tracking-tight">Terms of Use</h1>
    <p class="mt-2 text-sm text-ink/70">Last updated: {{ \Illuminate\Support\Carbon::parse(config('legal.terms_version'))->format('j F Y') }}</p>
    <p class="mt-4 text-ink/70">
        These are the rules for using MakanApa. Please read them. They include our Community Guidelines and a
        zero-tolerance rule for objectionable content and abusive users.
    </p>
    @include('legal.partials.links')

    <div class="mt-10 space-y-10 text-ink/80 leading-relaxed">

        <section id="about">
            <h2 class="{{ $h2 }}">01. Who we are</h2>
            <p class="mt-2">
                MakanApa ("MakanApa", "we", "us") is an app and website that helps you decide where to eat. It is
                operated by {{ config('legal.operator_name') }}, an individual based in Malaysia. These Terms of Use
                ("Terms") are an agreement between you and us about your use of the MakanApa app, this website and
                related services (together, the "Service").
            </p>
        </section>

        <section id="acceptance">
            <h2 class="{{ $h2 }}">02. Accepting these Terms</h2>
            <p class="mt-2">
                By using the Service, you agree to these Terms and to our
                <a href="{{ route('community-guidelines') }}" class="{{ $link }}">Community Guidelines</a>, which are part of
                these Terms. Our <a href="{{ route('privacy') }}" class="{{ $link }}">Privacy Policy</a> explains how we
                handle your personal data. If you don't agree, please don't use the Service.
            </p>
            <p class="mt-2">
                Before you contribute anything other people can see (a post, reply, place, photo, halal vouch,
                ownership claim or community request), the app asks you to agree to these Terms and the Community
                Guidelines. We record which version you agreed to and when.
            </p>
            <p class="mt-2">
                We may update these Terms. The date at the top shows the current version. If we make a material
                change, we'll tell you in the app, and you'll need to agree to the new version before you contribute
                again. If you keep using the Service after an update, the updated Terms apply to you.
            </p>
        </section>

        <section id="eligibility">
            <h2 class="{{ $h2 }}">03. Who can use MakanApa</h2>
            <p class="mt-2">
                You must be at least {{ config('legal.minimum_age') }} years old to use MakanApa. If you're under 18,
                your parent or guardian must agree to these Terms on your behalf and is responsible for your use of the
                Service. When you agree to these Terms as someone under 18, you confirm that your parent or guardian has
                agreed to them.
            </p>
            <p class="mt-2">
                If we learn that someone under {{ config('legal.minimum_age') }} is using MakanApa, we'll delete their
                account.
            </p>
        </section>

        <section id="accounts">
            <h2 class="{{ $h2 }}">04. Accounts and guest use</h2>
            <p class="mt-2">
                You can use MakanApa without an account. A guest session doesn't ask for your name or email: it's a
                guest account tied to the app on your device. Guests can get recommendations, browse nearby places and
                save places, but can't post or contribute. Guest accounts with no activity for 90 days are deleted
                automatically. If you sign up from a guest session, your history moves to your new account.
            </p>
            <p class="mt-2">To create an account you can use Sign in with Apple, Google, or an email and password. You agree to:</p>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>give accurate information and keep it up to date;</li>
                <li>keep your sign-in details secure, and tell us if you think someone else is using your account;</li>
                <li>use one account for yourself only, and not share, sell or transfer it;</li>
                <li>not choose a display name that impersonates someone or breaks the Community Guidelines.</li>
            </ul>
            <p class="mt-2">You're responsible for what happens under your account.</p>
        </section>

        <section id="community">
            <h2 class="{{ $h2 }}">05. Community Guidelines and zero tolerance</h2>
            <p class="mt-2">
                Some features let you share content that other people see ("Your Content"): community posts and replies,
                reactions, restaurant submissions and edits, photos, halal vouches and the evidence you attach, ownership
                claims and community requests. Your Content must follow the
                <a href="{{ route('community-guidelines') }}" class="{{ $link }}">Community Guidelines</a>.
            </p>
            <p class="mt-2 font-medium text-ink">
                We have zero tolerance for objectionable content and abusive users. Content that breaks these Terms or
                the Community Guidelines will be removed, and users who post it or abuse others will have their accounts
                suspended or permanently terminated.
            </p>
        </section>

        <section id="enforcement">
            <h2 class="{{ $h2 }}">06. Reports and enforcement</h2>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>You can report any community post, and block its author so you no longer see each other's posts.</li>
                <li>We review reports within 24 hours. We remove content that breaks the rules and act against the account responsible.</li>
                <li>Posts reported by several people are hidden automatically while we review them.</li>
                <li>Places, photos and halal vouches are checked by our team before other people see them.</li>
                <li>
                    We filter some content automatically, but we don't pre-screen everything and aren't obliged to. We may
                    still review, refuse, edit (for example, to correct restaurant details) or remove any content.
                </li>
                <li>
                    We may remove content, limit features, or suspend or terminate an account, with or without notice,
                    when we reasonably believe someone has broken these Terms, put others at risk, or where the law
                    requires it. A suspended account can't sign in or use the Service.
                </li>
            </ul>
            <p class="mt-2">
                If you think we got a decision wrong, contact us (see the end of this page).
            </p>
        </section>

        <section id="your-content">
            <h2 class="{{ $h2 }}">07. Your content</h2>
            <p class="mt-2">
                You keep ownership of Your Content. You give us a worldwide, non-exclusive, royalty-free licence to host,
                store, copy, display, adapt (for example, resizing a photo or formatting a menu) and distribute Your
                Content to run, improve and promote the Service. We may let our service providers use it only to run the
                Service for us. If MakanApa is ever transferred to someone else, this licence goes with it.
            </p>
            <p class="mt-2">
                Restaurant information you contribute and we approve (names, addresses, menus, prices, opening hours,
                halal details) becomes part of MakanApa's restaurant data, and stays after you delete your account,
                without your name. Community posts and the photos you uploaded are deleted when you delete your account,
                as described in our <a href="{{ route('privacy') }}#deletion" class="{{ $link }}">Privacy Policy</a>.
            </p>
            <p class="mt-2">When you share Your Content, you confirm that:</p>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>you own it or have permission to share it, and it doesn't infringe anyone's rights;</li>
                <li>it's accurate to the best of your knowledge;</li>
                <li>any identifiable person in your photos has agreed to it being shared.</li>
            </ul>
        </section>

        <section id="halal">
            <h2 class="{{ $h2 }}">08. Halal information</h2>
            <p class="mt-2">
                MakanApa is not a halal certification body. In Malaysia, only JAKIM and the state Islamic religious
                authorities can certify food as halal.
            </p>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>
                    When we show a place as halal certified, it's because we checked its certificate against the issuing
                    authority's directory at the time we checked. Certificates can expire or be withdrawn after that.
                </li>
                <li>Community notes and vouches are not halal certification, and no authority has verified them.</li>
                <li>
                    Always check the halal certificate at the restaurant, or on the official
                    <a href="https://myehalal.halal.gov.my/" class="{{ $link }}" rel="noopener">MyeHalal directory</a>,
                    before you rely on it. Don't rely on MakanApa alone for religious or dietary decisions.
                </li>
            </ul>
        </section>

        <section id="recommendations">
            <h2 class="{{ $h2 }}">09. Recommendations and restaurant information</h2>
            <p class="mt-2">
                Recommendations are suggestions, not promises. Restaurant information, including ratings, opening hours,
                prices, menus, photos, distances and halal details, comes from Google, from other users and from us. It
                may be incomplete, out of date or wrong, and we provide it "as is".
            </p>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>If you have a food allergy or dietary need, check with the restaurant yourself. That's your responsibility.</li>
                <li>
                    Restaurants are independent businesses. We aren't responsible for their food, service, prices, safety
                    or anything else about them. Anything between you and a restaurant is between you and the restaurant.
                </li>
            </ul>
        </section>

        <section id="third-parties">
            <h2 class="{{ $h2 }}">10. Third-party services</h2>
            <p class="mt-2">
                <strong class="text-ink">Google Maps.</strong> MakanApa includes Google Maps features and content. Use
                of Google Maps features and content is subject to the then-current versions of the
                <a href="https://maps.google.com/help/terms_maps/" class="{{ $link }}" rel="noopener">Google Maps End User Additional Terms of Service</a>
                (https://maps.google.com/help/terms_maps/) and the
                <a href="https://policies.google.com/privacy" class="{{ $link }}" rel="noopener">Google Privacy Policy</a>
                (https://policies.google.com/privacy).
            </p>
            <p class="mt-2">
                Sign in with Apple and Google Sign-In are provided by Apple and Google under their own terms. Directions
                open in the maps app you choose (Apple Maps by default). We aren't responsible for third-party services.
            </p>
        </section>

        <section id="apple">
            <h2 class="{{ $h2 }}">11. The iOS app and Apple</h2>
            <p class="mt-2">
                The MakanApa iOS app is licensed to you under Apple's
                <a href="https://www.apple.com/legal/internet-services/itunes/dev/stdeula/" class="{{ $link }}" rel="noopener">Licensed Application End User License Agreement</a>,
                together with these Terms. If the two conflict about the licence to use the app itself, Apple's agreement
                applies. These Terms are between you and us, not Apple. Apple isn't responsible for the Service or its
                content and has no obligation to support it.
            </p>
        </section>

        <section id="acceptable-use">
            <h2 class="{{ $h2 }}">12. Acceptable use</h2>
            <p class="mt-2">You agree not to:</p>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>scrape, crawl or bulk-copy the Service or its restaurant data, or access it by automated means;</li>
                <li>create accounts or guest accounts by automated means, or create a new account to get around a suspension;</li>
                <li>reverse engineer, decompile or get around security features or rate limits, except where the law allows it;</li>
                <li>interfere with, overload or disrupt the Service;</li>
                <li>use the Service to harass, threaten or harm anyone, or for anything illegal;</li>
                <li>submit false reports, fake certificates or information you know is misleading;</li>
                <li>use the Service to advertise, including posting about your own business while pretending to be a customer.</li>
            </ul>
        </section>

        <section id="termination">
            <h2 class="{{ $h2 }}">13. Ending your use</h2>
            <p class="mt-2">
                You can stop using MakanApa at any time, and delete your account from Settings in the app. What happens to
                your data when you do is explained in the
                <a href="{{ route('privacy') }}#deletion" class="{{ $link }}">Privacy Policy</a>.
            </p>
            <p class="mt-2">
                We may suspend or terminate accounts as described in section 06. We may also change, pause or stop any
                part of the Service. Sections 07 (for restaurant information we keep), 08, 09, 14, 15 and 16 continue to
                apply after your use ends.
            </p>
        </section>

        <section id="disclaimers">
            <h2 class="{{ $h2 }}">14. Disclaimers</h2>
            <p class="mt-2">
                MakanApa is free, and we provide it "as is" and "as available". To the extent the law allows, we make no
                promises that the Service will be accurate, available, uninterrupted or suitable for any particular
                purpose. Nothing in these Terms takes away rights you have under the Consumer Protection Act 1999 or any
                other law that can't be excluded by agreement.
            </p>
        </section>

        <section id="liability">
            <h2 class="{{ $h2 }}">15. Limitation of liability</h2>
            <p class="mt-2">To the extent permitted by Malaysian law, we aren't liable for:</p>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>indirect, incidental, special or consequential loss, or loss of data, profit or goodwill;</li>
                <li>loss caused by relying on restaurant, recommendation or halal information;</li>
                <li>the conduct or content of other users, restaurants or third-party services.</li>
            </ul>
            <p class="mt-2">
                Where we are liable, our total liability to you is limited to RM100. None of this limits liability for
                death or personal injury caused by our negligence, for fraud, or for anything else the law doesn't allow us
                to limit.
            </p>
        </section>

        <section id="law">
            <h2 class="{{ $h2 }}">16. Governing law</h2>
            <p class="mt-2">
                These Terms are governed by the laws of Malaysia, and the courts of Malaysia have jurisdiction over any
                dispute about them. If you have a problem, please contact us first so we can try to sort it out.
            </p>
        </section>

        <section id="general">
            <h2 class="{{ $h2 }}">17. General</h2>
            <p class="mt-2">
                These Terms, the Community Guidelines and the Privacy Policy are the whole agreement between you and us
                about the Service. If part of these Terms can't be enforced, the rest still applies. If we don't enforce a
                right straight away, we haven't given it up. These Terms are written in English; if we provide a
                translation and it differs, the English version applies.
            </p>
        </section>

        <section id="contact">
            <h2 class="{{ $h2 }}">18. Contact</h2>
            <p class="mt-2">
                Questions about these Terms, or want to appeal a moderation decision?
                @if ($supportEmail)
                    Email <a href="mailto:{{ $supportEmail }}" class="{{ $link }}">{{ $supportEmail }}</a>.
                @else
                    Use the <a href="{{ url('/support') }}" class="{{ $link }}">support page</a>.
                @endif
            </p>
        </section>

    </div>

@endsection
