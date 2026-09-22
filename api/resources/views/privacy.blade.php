@extends('layouts.marketing')

@section('title', 'Privacy Policy | MakanApa')
@section('description', 'How MakanApa collects, uses, and deletes your data.')

@section('content')

    <h1 class="text-3xl font-semibold">Privacy Policy</h1>
    <p class="mt-2 text-sm text-ink/60">Last updated: September 23, 2026</p>
    <p class="mt-4 text-ink/70">
        This page explains what information MakanApa collects, why, and what happens to it, including when
        you delete your account.
    </p>

    <div class="mt-10 space-y-10 text-ink/80 leading-relaxed">

        <section id="account">
            <h2 class="text-lg font-semibold text-ink">01. Account information</h2>
            <p class="mt-2">
                When you create a MakanApa account, we collect your name, email address, and avatar (if provided).
                If you sign in with Apple or Google, we store the account identifier those providers give us so we
                can recognize you on future sign-ins. If you set a password, we store a securely hashed version of
                it, never the password itself.
            </p>
        </section>

        <section id="location">
            <h2 class="text-lg font-semibold text-ink">02. Location</h2>
            <p class="mt-2">
                When you allow location access, MakanApa uses your device location to find nearby restaurants,
                calculate distance, and improve recommendations. Based on the current service design, your device
                location is processed for these requests and is not stored as part of your account profile. Your
                precise location is not displayed publicly.
            </p>
        </section>

        <section id="community">
            <h2 class="text-lg font-semibold text-ink">03. Community affiliation</h2>
            <p class="mt-2">
                MakanApa lets you associate your account with a community, such as a university or area, along
                with a verification status. This is used to group and filter restaurant discovery around people in
                your selected community. Community affiliation doesn't necessarily mean every person shown is
                currently verified, unless the app specifically says so.
            </p>
        </section>

        <section id="activity">
            <h2 class="text-lg font-semibold text-ink">04. Your activity</h2>
            <p class="mt-2">
                We record the choices you make while using MakanApa: cravings, budget, and distance preferences
                (decisions), the recommendations shown to you, restaurants you save, and "vibe" votes you cast on
                restaurants. This activity is used to power recommendations and isn't displayed publicly attached
                to your identity.
            </p>
        </section>

        <section id="contributions">
            <h2 class="text-lg font-semibold text-ink">05. Restaurant contributions</h2>
            <p class="mt-2">
                If you submit a restaurant or suggest a correction, we store what you submitted along with a
                review status. Once approved, submitted restaurant information becomes part of MakanApa's public
                restaurant data and may continue to be shown to other users even after any connection to your
                account is later removed (see Account deletion, below).
            </p>
        </section>

        <section id="photos">
            <h2 class="text-lg font-semibold text-ink">06. Photos</h2>
            <p class="mt-2">
                Photos you upload for a restaurant are re-encoded on our servers before publication. This process
                removes embedded metadata such as GPS coordinates and device information as a side effect, before
                the photo is shown to anyone. Published photos are stored in S3-compatible object storage.
            </p>
            <p class="mt-2">
                Restaurant photos sourced from Google Places (rather than uploaded by you) are streamed to your
                device on request and are not stored by MakanApa.
            </p>
        </section>

        <section id="notifications">
            <h2 class="text-lg font-semibold text-ink">07. Push notifications</h2>
            <p class="mt-2">
                If you allow notifications, your device registers a push token with Apple's Push Notification
                service (APNs), which we store alongside a device identifier, platform, and whether it's a
                sandbox or production build. This lets us deliver notifications to your device; we never see or
                store the content of a notification beyond what we sent to trigger it.
            </p>
            <p class="mt-2">
                You can control which categories of notification you receive from Settings in the app:
            </p>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>Account &amp; admin notices, and updates about your community submissions — on by default</li>
                <li>News &amp; release announcements — off by default, opt-in only</li>
            </ul>
            <p class="mt-2">
                If Apple reports that your device's token is no longer valid (for example, the app was
                uninstalled), we stop sending to it but keep the record for diagnostics rather than deleting it
                outright.
            </p>
        </section>

        <section id="providers">
            <h2 class="text-lg font-semibold text-ink">08. Service providers</h2>
            <p class="mt-2">We use the following third parties, each for a specific purpose:</p>
            <ul class="mt-3 divide-y divide-sambal-100 rounded-2xl border border-sambal-100 bg-white text-sm">
                <li class="p-4"><strong class="text-ink">Apple</strong>: Sign in with Apple, account authentication.</li>
                <li class="p-4"><strong class="text-ink">Google Sign-In</strong>: account authentication.</li>
                <li class="p-4"><strong class="text-ink">Google Places</strong>: restaurant discovery and information, and temporary restaurant-photo delivery (not stored by us).</li>
                <li class="p-4"><strong class="text-ink">Object storage provider</strong>: stores photos you upload for restaurants (S3-compatible storage).</li>
                <li class="p-4"><strong class="text-ink">Apple Push Notification service (APNs)</strong>: delivers push notifications to your device.</li>
            </ul>
            <p class="mt-3 text-sm text-ink/60">
                We don't use analytics or crash-reporting services at this time. If that changes, this section
                will be updated.
            </p>
        </section>

        <section id="deletion">
            <h2 class="text-lg font-semibold text-ink">09. Account deletion</h2>
            <p class="mt-2">
                You can delete your MakanApa account from within the app. Deletion is immediate, self-service, and
                permanent: there is no review queue and it cannot be undone.
            </p>

            <p class="mt-4 font-medium text-ink">What disappears:</p>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>Your name, email, avatar, and password credential</li>
                <li>Your Apple/Google account identifiers (and your Apple sign-in grant is revoked)</li>
                <li>Your active sessions and sign-in tokens</li>
                <li>Your community affiliation</li>
                <li>Your notification preferences</li>
                <li>Photos you uploaded, both the file and the record, deleted rather than anonymized</li>
            </ul>

            <p class="mt-4 font-medium text-ink">What may remain, in anonymized form:</p>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                <li>Activity data: decisions, recommendations shown to you, saves, vibe votes</li>
                <li>Community contributions: restaurant submissions you made</li>
                <li>Group activity: decision rooms you created</li>
                <li>Device push tokens: kept but detached from your account, so notifications stop</li>
            </ul>
            <p class="mt-2 text-sm text-ink/70">
                In each case, the connection to your account is removed rather than the record being deleted,
                since this content may have independent value to other users once it's been published or approved.
            </p>

            <p class="mt-4 font-medium text-ink">What we retain:</p>
            <p class="mt-2 text-sm text-ink/70">
                One minimal record containing your email address and the date of deletion, kept for security and
                compliance visibility (for example, to recognize abuse patterns or respond to support requests
                about a deleted account).
            </p>
        </section>

        <section id="contact">
            <h2 class="text-lg font-semibold text-ink">10. Contact</h2>
            <p class="mt-2">
                Questions about this policy or your data?
                @if (config('marketing.privacy_email'))
                    Email <a href="mailto:{{ config('marketing.privacy_email') }}" class="font-medium text-sambal-600 underline">{{ config('marketing.privacy_email') }}</a>.
                @endif
            </p>
        </section>

    </div>

@endsection
