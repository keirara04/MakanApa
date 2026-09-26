{{-- English privacy notice. Keep section numbers and ids in step with legal/privacy/ms.blade.php. --}}
<h1 class="font-display text-[clamp(3.2rem,9vw,5rem)] font-bold uppercase leading-[0.88] tracking-tight">Privacy Policy</h1>
<p class="mt-2 text-sm text-ink/70">Last updated: {{ $updated }}</p>
<p class="mt-4 text-ink/70">
    This notice explains what personal data MakanApa collects, why, who we share it with, how long we keep it and
    what you can do about it, as required by Malaysia's Personal Data Protection Act 2010 (PDPA). MakanApa is
    operated by {{ config('legal.operator_name') }} in Malaysia ("we", "us"), who is responsible for your data.
</p>
<p class="mt-2 text-sm text-ink/70">
    This notice is also available in Bahasa Malaysia. If the two versions differ, the English version applies.
</p>
@include('legal.partials.links')

<ul class="sketch mt-6 divide-y divide-dashed divide-ink/20 bg-paper-50 text-sm" style="--sketch-radius: 12px">
    <li class="p-4"><strong class="text-ink">No ads, no selling.</strong> We don't show ads, sell your data, or track you across other companies' apps and websites.</li>
    <li class="p-4"><strong class="text-ink">Location is stored with your picks.</strong> We save where you were when you asked for a pick, linked to your account. It's never shown publicly.</li>
    <li class="p-4"><strong class="text-ink">No account needed.</strong> You can use MakanApa as a guest without giving your name or email.</li>
    <li class="p-4"><strong class="text-ink">You're in control.</strong> You can edit your profile, reset your taste profile and delete your account in the app at any time.</li>
</ul>

<div class="mt-10 space-y-10 text-ink/80 leading-relaxed">

    <section id="collect">
        <h2 class="{{ $h2 }}">01. What we collect</h2>
        <p class="mt-4 font-medium text-ink">Account information</p>
        <p class="mt-2">
            When you create an account, we collect your name, email address and the avatar you pick from our set. If you
            sign in with Apple or Google, we store the account identifier they give us so we can recognise you, and for
            Apple, a sign-in grant (stored encrypted) so we can revoke it if you delete your account. If you set a
            password, we store only a secure hash of it. We also store your settings, such as your halal preference and
            notification choices.
        </p>
        <p class="mt-2">
            A guest account has none of this: just a random account identifier tied to the app on your device.
        </p>

        <p id="location" class="mt-4 font-medium text-ink">Location</p>
        <p class="mt-2">
            If you allow location access, the app sends your device's location with requests to find nearby places,
            pick a place, search, and show what's popular in your community. We store your exact location with each
            pick you ask for, linked to your account. We use this to give you recommendations, and in aggregate to
            understand which areas people look for food in. Your location is never shown to other users.
        </p>

        <p class="mt-4 font-medium text-ink">Your picks and activity</p>
        <p class="mt-2">
            When you use MakanApa we record what you asked for and what happened: your mood, budget and distance, the
            recommendations we showed you, the place you chose, how you interacted with a pick (for example, rerolling
            or tuning it), places you save, vibe votes, the words you searched when you chose a place from search, and
            how you opened a place (for example, from a shared link or a notification). We also note the context of a
            pick, such as the time of day and whether it was raining in your area.
        </p>

        <p class="mt-4 font-medium text-ink">Taste profile ("Selera")</p>
        <p class="mt-2">
            From your activity, MakanApa builds a taste profile so recommendations fit you better. You can see what it
            has learned, correct it, and reset it in the app.
        </p>

        <p class="mt-4 font-medium text-ink">Community and contributions</p>
        <p class="mt-2">If you use community features, we collect what you share and do there:</p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>your community (a university or area) and its verification status;</li>
            <li>community posts and replies (with an optional place tag), and your reactions;</li>
            <li>reports you make (the reason and an optional note), and the people you block;</li>
            <li>places you add or edit, and photos you upload;</li>
            <li>halal vouches: your claim, comment, certificate details and photos;</li>
            <li>ownership claims and the proof you attach;</li>
            <li>requests to add a university or area that isn't listed;</li>
            <li>which version of our Terms of Use and Community Guidelines you agreed to, and when.</li>
        </ul>

        <p class="mt-4 font-medium text-ink">Notifications</p>
        <p class="mt-2">
            If you allow notifications, we store your device's push token, a random identifier for your installation of
            the app, and whether it's a test or live build. We keep a record of the notifications we send you, and, if
            you turn on mealtime picks, when your next one is due.
        </p>

        <p class="mt-4 font-medium text-ink">App usage</p>
        <p class="mt-2">
            We record when you open and close the app, with the random identifier for your installation. This is a
            MakanApa-generated identifier, not your device's advertising identifier. We use these records only for our
            own statistics, such as how many people use the app each week. We don't use third-party analytics or
            crash-reporting tools.
        </p>

        <p class="mt-4 font-medium text-ink">Searches with no results</p>
        <p class="mt-2">
            When a search finds nothing, we keep the search words and a rough area (about 1 km), with no link to you, so
            we can see which places MakanApa is missing.
        </p>

        <p class="mt-4 font-medium text-ink">Support and this website</p>
        <p class="mt-2">
            If you email us, we keep the emails. This website keeps an anonymous count of page views and download-button
            clicks. It doesn't set cookies or store IP addresses or device identifiers for this.
        </p>
    </section>

    <section id="sources">
        <h2 class="{{ $h2 }}">02. Where it comes from</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>From you, when you sign up, use the app or contact us.</li>
            <li>From your device, such as its location and push token, when you allow them.</li>
            <li>From Apple or Google, if you sign in with them (your name, email and account identifier).</li>
            <li>From how you use MakanApa, such as your picks and your taste profile.</li>
            <li>From our team, such as the verification status of your community.</li>
        </ul>
    </section>

    <section id="purposes">
        <h2 class="{{ $h2 }}">03. Why we use it</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>To run your account and sign you in.</li>
            <li>To find places near you, recommend one and remember the places you save.</li>
            <li>To personalise recommendations through your taste profile.</li>
            <li>To run the community: show posts, handle reports and blocks, filter content and enforce our Terms of Use.</li>
            <li>To check restaurant and halal information before it's shown.</li>
            <li>To send the notifications you've chosen.</li>
            <li>To understand how MakanApa is used and improve it, using our own statistics only.</li>
            <li>To keep MakanApa secure and prevent abuse, such as spam and rate-limit evasion.</li>
            <li>To comply with the law and respond to lawful requests.</li>
        </ul>
    </section>

    <section id="visible">
        <h2 class="{{ $h2 }}">04. What other people can see</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Your community posts and replies, with your display name and avatar, are visible to people in the same community.</li>
            <li>Once approved, your halal vouches are shown on the place with your display name, comment and photos.</li>
            <li>Once approved, places, details and photos you contribute become part of MakanApa's restaurant information.</li>
            <li>Reports are anonymous: the author isn't told who reported them. The people you block aren't told.</li>
            <li>Shared place pages on this website show restaurant information and how many people picked the place, never who.</li>
            <li>Your location, email and activity are never shown to other users.</li>
        </ul>
    </section>

    <section id="sharing">
        <h2 class="{{ $h2 }}">05. Who we share it with</h2>
        <p class="mt-2">We share only what each service needs to do its job for us:</p>
        <ul class="sketch mt-4 divide-y divide-dashed divide-ink/20 bg-paper-50 text-sm" style="--sketch-radius: 12px">
            <li class="p-4"><strong class="text-ink">Apple</strong>: Sign in with Apple, and delivering push notifications (your push token and the notification).</li>
            <li class="p-4"><strong class="text-ink">Google</strong>: Google Sign-In; Google Places, which our servers ask for restaurant information using your search words and a location in your area (which can be your location); and the Google Map in the app, which Google provides directly under the <a href="https://policies.google.com/privacy" class="{{ $link }}" rel="noopener">Google Privacy Policy</a>.</li>
            <li class="p-4"><strong class="text-ink">OpenRouter</strong> and the AI model provider it routes to: the text of cravings you type (up to 200 characters), so we can understand them; and the details of halal vouches (the claim, comment and certificate details, never your name or account), so our team gets a suggested review. AI suggestions never change a halal status or remove content by themselves.</li>
            <li class="p-4"><strong class="text-ink">Open-Meteo</strong>: a rough area of about 5 km, sent from our servers, to check whether it's raining. Never your exact location.</li>
            <li class="p-4"><strong class="text-ink">Hosting and storage providers</strong>: run our servers and store uploaded photos.</li>
            <li class="p-4"><strong class="text-ink">Email provider</strong>: handles emails you send to our support address.</li>
            <li class="p-4"><strong class="text-ink">Authorities</strong>: when the law requires it, or to protect people's safety.</li>
        </ul>
        <p class="mt-3">
            Some of these providers process data outside Malaysia, for example in the United States or Europe. We only
            send them what they need, and they handle it under their own privacy and security commitments.
        </p>
    </section>

    <section id="photos">
        <h2 class="{{ $h2 }}">06. Photos</h2>
        <p class="mt-2">
            Photos you upload are re-encoded on our servers, which removes hidden metadata such as GPS coordinates and
            device details. They stay private until our team approves them, and are then stored in S3-compatible object
            storage. Restaurant photos from Google Places are streamed to your device and aren't stored by us.
        </p>
    </section>

    <section id="notifications">
        <h2 class="{{ $h2 }}">07. Notifications</h2>
        <p class="mt-2">You choose which notifications you get in Settings in the app:</p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Updates about places and halal vouches you submitted: on by default</li>
            <li>Account and admin notices: on by default</li>
            <li>Replies to your posts: on by default</li>
            <li>Reactions to your posts: off by default</li>
            <li>Mealtime picks, at most one a day: off by default</li>
            <li>News and release announcements: off by default</li>
        </ul>
        <p class="mt-2">You can also turn notifications off completely in your iPhone's Settings.</p>
    </section>

    <section id="retention">
        <h2 class="{{ $h2 }}">08. How long we keep it</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Account information: until you delete your account.</li>
            <li>Picks and activity: kept to improve MakanApa. When you delete your account, they're kept without any link to it.</li>
            <li>Guest accounts: deleted automatically after 90 days without activity.</li>
            <li>Sign-in sessions: expire after 90 days, and expired ones are removed daily.</li>
            <li>Text sent for AI review: removed from our records after 90 days.</li>
            <li>Unfinished submissions: drafts are removed after 7 days. Photos from cancelled submissions are removed, and photos from rejected ones after 30 days.</li>
            <li>Searches with no results: kept anonymously, with no link to anyone.</li>
        </ul>
    </section>

    <section id="rights">
        <h2 class="{{ $h2 }}">09. Your choices and rights</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li><strong class="text-ink">See and correct your data:</strong> edit your name, avatar and community in the app, and see or reset your taste profile. To ask for a copy of your data or to correct anything else, email us. We'll reply within 21 days.</li>
            <li><strong class="text-ink">Limit what we collect:</strong> turn off location or notifications in your iPhone's Settings, choose notification types in the app, reset your taste profile, or use MakanApa as a guest.</li>
            <li><strong class="text-ink">Withdraw consent:</strong> delete your account in the app at any time (see below).</li>
            <li><strong class="text-ink">Complain:</strong> contact us first. You can also contact Malaysia's Personal Data Protection Commissioner at <a href="https://www.pdp.gov.my/" class="{{ $link }}" rel="noopener">pdp.gov.my</a>.</li>
        </ul>
        <p class="mt-4 font-medium text-ink">What's required and what's optional</p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>An email address (or Sign in with Apple or Google) is required to create an account, and a name for email sign-up. Without them, you can still use MakanApa as a guest.</li>
            <li>Location is optional, but without it we can't find places near you.</li>
            <li>Notifications, community features and contributions are optional.</li>
        </ul>
    </section>

    <section id="children">
        <h2 class="{{ $h2 }}">10. Age</h2>
        <p class="mt-2">
            MakanApa is for people aged {{ config('legal.minimum_age') }} and over. If you're under 18, you need your
            parent's or guardian's agreement to use it. If we learn that someone under {{ config('legal.minimum_age') }}
            has an account, we'll delete it.
        </p>
    </section>

    <section id="security">
        <h2 class="{{ $h2 }}">11. Security</h2>
        <p class="mt-2">
            Data is sent over encrypted connections. Passwords and sign-in tokens are stored as secure hashes, and Apple
            sign-in grants are encrypted. Only a small admin team can access personal data. If a data breach puts your
            data at risk, we'll notify you and the authorities as the law requires.
        </p>
    </section>

    <section id="deletion">
        <h2 class="{{ $h2 }}">12. Deleting your account</h2>
        <p class="mt-2">
            You can delete your account in the app (Settings → Delete account). Deletion is immediate and permanent, and
            can't be undone.
        </p>

        <p class="mt-4 font-medium text-ink">What's deleted:</p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Your name, email, avatar and password</li>
            <li>Your Apple or Google account identifiers (your Apple sign-in grant is revoked)</li>
            <li>Your sign-in sessions</li>
            <li>Your community, notification settings and mealtime-pick schedule</li>
            <li>Your taste profile and the activity it was built from</li>
            <li>Your app open and close records</li>
            <li>Your community posts, replies, reactions, the reports you made, and your blocks</li>
            <li>Your community requests and any ownership of places</li>
            <li>Photos you uploaded, both the files and the records</li>
        </ul>

        <p class="mt-4 font-medium text-ink">What's kept, with no link to your account:</p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Your picks, including their location, and vibe votes (with the random installation identifier)</li>
            <li>Places you saved (with the random installation identifier)</li>
            <li>Places, edits, halal vouches and ownership claims you submitted. Approved vouches are then shown as from "a MakanApa user"</li>
            <li>Your device's push token, so notifications stop but the record remains</li>
            <li>Records of notifications we sent you</li>
        </ul>
        <p class="mt-2 text-sm text-ink/70">
            We keep these because restaurant information and overall statistics stay useful to other people after an
            account is gone.
        </p>

        <p class="mt-4 font-medium text-ink">What we retain:</p>
        <p class="mt-2 text-sm text-ink/70">
            One record with your email address and the date of deletion, for security and to answer questions about a
            deleted account. We don't keep this for guest accounts, which have no email.
        </p>
    </section>

    <section id="changes">
        <h2 class="{{ $h2 }}">13. Changes to this notice</h2>
        <p class="mt-2">
            When we change this notice, we update the date at the top. If a change is significant, we'll also tell you
            in the app.
        </p>
    </section>

    <section id="contact">
        <h2 class="{{ $h2 }}">14. Contact</h2>
        <p class="mt-2">
            Questions, requests or complaints about your data?
            @if ($privacyEmail)
                Email <a href="mailto:{{ $privacyEmail }}" class="{{ $link }}">{{ $privacyEmail }}</a>.
            @else
                Use the <a href="{{ url('/support') }}" class="{{ $link }}">support page</a>.
            @endif
        </p>
    </section>

</div>
