@extends('layouts.marketing')

@section('title', 'Support | MakanApa')
@section('description', 'Get help with MakanApa: sign-in issues, restaurant info, account deletion, and how to reach us.')

@section('content')

    <div class="text-center">
        <img src="{{ asset('images/mascot-celebrate.svg') }}" alt="" class="mx-auto h-24 w-24" aria-hidden="true">
        <h1 class="mt-4 font-display text-[clamp(3.2rem,9vw,5rem)] font-bold uppercase leading-[0.88] tracking-tight">Need help? We got you.</h1>
        <p class="mt-3 text-ink/70">
            Something not working, found the wrong restaurant info, or just have an idea that could make
            MakanApa better? Send it our way. We're still improving MakanApa and feedback genuinely helps.
        </p>
        @if (config('marketing.support_email'))
            <a href="mailto:{{ config('marketing.support_email') }}"
               class="mt-7 inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-ink px-7 py-3 font-semibold text-paper shadow-[5px_5px_0_var(--color-sambal-600)] transition-[translate,box-shadow] duration-200 hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-[8px_8px_0_var(--color-sambal-600)]">
                Email support
            </a>
            <p class="mt-2 text-sm text-ink/70">{{ config('marketing.support_email') }}</p>
        @endif
        <p class="mt-1 text-sm text-ink/70">
            For technical problems, please include your device model, iOS version, MakanApa version, and a
            screenshot if possible.
        </p>
    </div>

    <section class="mt-12">
        <h2 class="font-display text-3xl font-bold uppercase tracking-wide text-sambal-600">Quick help</h2>
        <div class="sketch mt-4 divide-y divide-dashed divide-ink/20 bg-paper-50" style="--sketch-radius: 12px">

            <details class="group p-5">
                <summary class="cursor-pointer list-none text-lg font-semibold marker:content-none [&::-webkit-details-marker]:hidden">
                    MakanApa can't find my location
                    <x-marketing.doodle type="plus" class="float-right h-5 w-5 text-sambal-600 transition-transform duration-300 group-open:rotate-45" />
                </summary>
                <div class="mt-3 text-sm text-ink/70 space-y-2">
                    <p>MakanApa uses your location to find restaurants nearby and give you better recommendations.</p>
                    <p>On your iPhone: <strong>Settings → Privacy &amp; Security → Location Services → MakanApa</strong>. Make sure location access is enabled.</p>
                    <p>If you prefer not to share your location, some location-based features may be limited.</p>
                </div>
            </details>

            <details class="group p-5">
                <summary class="cursor-pointer list-none text-lg font-semibold marker:content-none [&::-webkit-details-marker]:hidden">
                    I can't sign in
                    <x-marketing.doodle type="plus" class="float-right h-5 w-5 text-sambal-600 transition-transform duration-300 group-open:rotate-45" />
                </summary>
                <div class="mt-3 text-sm text-ink/70 space-y-2">
                    <p>MakanApa supports the sign-in methods shown inside the app, including email/password and Apple/Google sign-in.</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Make sure you have an internet connection.</li>
                        <li>Confirm you're using the same sign-in method you originally used.</li>
                        <li>Restart MakanApa and try again.</li>
                        <li>Make sure you're running the latest available version.</li>
                    </ul>
                    <p>Still stuck? Contact us and tell us which sign-in method you're using. <strong>Never send us your password.</strong></p>
                </div>
            </details>

            <details class="group p-5">
                <summary class="cursor-pointer list-none text-lg font-semibold marker:content-none [&::-webkit-details-marker]:hidden">
                    Sign in with Apple isn't working
                    <x-marketing.doodle type="plus" class="float-right h-5 w-5 text-sambal-600 transition-transform duration-300 group-open:rotate-45" />
                </summary>
                <div class="mt-3 text-sm text-ink/70 space-y-2">
                    <p>Check that you're signed in to your Apple ID on your iPhone and that your internet connection is working.</p>
                    <p>If you previously used <strong>Hide My Email</strong>, your MakanApa account may be linked to an Apple private relay email rather than your normal address. Contact support if you need help identifying the account.</p>
                </div>
            </details>

            <details class="group p-5">
                <summary class="cursor-pointer list-none text-lg font-semibold marker:content-none [&::-webkit-details-marker]:hidden">
                    Google sign-in isn't working
                    <x-marketing.doodle type="plus" class="float-right h-5 w-5 text-sambal-600 transition-transform duration-300 group-open:rotate-45" />
                </summary>
                <div class="mt-3 text-sm text-ink/70 space-y-2">
                    <p>Make sure you're signed in to the correct Google account and try again.</p>
                    <p>If the same email was previously used to create a MakanApa account with a password, MakanApa may ask you to verify the existing account before connecting Google.</p>
                </div>
            </details>

        </div>
    </section>

    <section class="mt-10">
        <h2 class="font-display text-3xl font-bold uppercase tracking-wide text-sambal-600">Restaurants</h2>
        <div class="sketch mt-4 divide-y divide-dashed divide-ink/20 bg-paper-50" style="--sketch-radius: 12px">

            <details class="group p-5">
                <summary class="cursor-pointer list-none text-lg font-semibold marker:content-none [&::-webkit-details-marker]:hidden">
                    Found something wrong?
                    <x-marketing.doodle type="plus" class="float-right h-5 w-5 text-sambal-600 transition-transform duration-300 group-open:rotate-45" />
                </summary>
                <div class="mt-3 text-sm text-ink/70 space-y-2">
                    <p>Restaurant information changes all the time. Let us know if a restaurant has incorrect opening hours, location, price, or contact details, or if it's closed permanently, moved, appears more than once, or is otherwise wrong.</p>
                    <p>Please include the restaurant name and what needs correcting when you contact us.</p>
                </div>
            </details>

            <details class="group p-5">
                <summary class="cursor-pointer list-none text-lg font-semibold marker:content-none [&::-webkit-details-marker]:hidden">
                    Know a place we're missing?
                    <x-marketing.doodle type="plus" class="float-right h-5 w-5 text-sambal-600 transition-transform duration-300 group-open:rotate-45" />
                </summary>
                <div class="mt-3 text-sm text-ink/70 space-y-2">
                    <p>Small stalls, campus food, local favourites and hidden gems are exactly what we want MakanApa to discover. You can submit a restaurant directly from the app.</p>
                    <p>If you're contacting us instead, include the restaurant name, approximate location, type of food, and any helpful details.</p>
                    <p>Uploaded restaurant photos are re-encoded before publication, which removes embedded metadata such as device and GPS information.</p>
                </div>
            </details>

        </div>
    </section>

    <section class="mt-10">
        <h2 class="font-display text-3xl font-bold uppercase tracking-wide text-sambal-600">Account &amp; privacy</h2>
        <div class="sketch mt-4 divide-y divide-dashed divide-ink/20 bg-paper-50" style="--sketch-radius: 12px">

            <details class="group p-5">
                <summary class="cursor-pointer list-none text-lg font-semibold marker:content-none [&::-webkit-details-marker]:hidden">
                    Delete my account
                    <x-marketing.doodle type="plus" class="float-right h-5 w-5 text-sambal-600 transition-transform duration-300 group-open:rotate-45" />
                </summary>
                <div class="mt-3 text-sm text-ink/70 space-y-2">
                    <p>You can delete your MakanApa account from within the app. Deletion is instant and permanent: there's no review queue and it can't be undone.</p>
                    <p>If you're unable to access your account or need help, contact support below.</p>
                </div>
            </details>

            <details class="group p-5">
                <summary class="cursor-pointer list-none text-lg font-semibold marker:content-none [&::-webkit-details-marker]:hidden">
                    What happens to my data when I delete my account?
                    <x-marketing.doodle type="plus" class="float-right h-5 w-5 text-sambal-600 transition-transform duration-300 group-open:rotate-45" />
                </summary>
                <div class="mt-3 text-sm text-ink/70 space-y-2">
                    <p>Your account and personal account information (name, email, password credential, sign-in identifiers, sessions) are permanently removed, along with any photos you've uploaded.</p>
                    <p>Some activity and community contributions, like restaurant submissions, may remain in anonymized form, no longer connected to your account.</p>
                    <p>We keep one minimal record (your email and the deletion date) for security and compliance purposes.</p>
                    <p><a href="{{ url('/privacy') }}" class="font-medium text-sambal-600 underline">Read the full privacy policy →</a></p>
                </div>
            </details>

        </div>
    </section>

    <section class="mt-10">
        <h2 class="font-display text-3xl font-bold uppercase tracking-wide text-sambal-600">Report a bug</h2>
        <div class="mt-4 sketch bg-paper-50 p-6 text-sm text-ink/70" style="--sketch-radius: 12px">
            <p>Please send us:</p>
            <ul class="mt-2 list-disc pl-5 space-y-1">
                <li><strong>What happened</strong>: describe the problem.</li>
                <li><strong>What you expected to happen.</strong></li>
                <li><strong>Steps to reproduce it</strong>: e.g. "Open Nearby, select '≤ RM20', tap restaurant, app closes."</li>
                <li><strong>Device information</strong>: iPhone model and iOS version.</li>
                <li><strong>MakanApa version</strong>: found in the app or App Store.</li>
                <li><strong>Screenshot or screen recording</strong>, if possible.</li>
            </ul>
        </div>
    </section>

    <section class="mt-12 sketch bg-paper-50 p-6" style="--sketch-radius: 12px">
        <h2 class="font-display text-3xl font-bold uppercase tracking-wide text-sambal-600">Feedback &amp; feature requests</h2>
        <p class="mt-2 text-sm text-ink/70">
            Got an idea? Whether it's a tiny improvement, a restaurant we're missing, or something completely
            new, we'd love to hear it. Good food, less overthinking. 🍚
        </p>
    </section>

    <section class="mt-10 text-center">
        <h2 class="font-display text-5xl font-bold uppercase leading-none">Still stuck?</h2>
        <p class="mt-2 text-ink/70">We're happy to help.</p>
        @if (config('marketing.support_email'))
            <a href="mailto:{{ config('marketing.support_email') }}"
               class="mt-5 inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-ink px-7 py-3 font-semibold text-paper shadow-[5px_5px_0_var(--color-sambal-600)] transition-[translate,box-shadow] duration-200 hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-[8px_8px_0_var(--color-sambal-600)]">
                Contact MakanApa Support
            </a>
        @endif
    </section>

    <section class="mt-12 sketch bg-paper-50 p-6 text-sm text-ink/70" style="--sketch-radius: 12px">
        <div class="flex flex-wrap gap-x-8 gap-y-2">
            <div><span class="font-medium text-ink">MakanApa</span>: What to Eat</div>
            <div>Developer: Hakeemi Ridza</div>
            @if (config('marketing.support_email'))
                <div>Support: {{ config('marketing.support_email') }}</div>
            @endif
        </div>
    </section>

@endsection
