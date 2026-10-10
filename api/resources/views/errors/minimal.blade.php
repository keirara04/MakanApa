{{-- Shell for every error page except 404 (which uses the full marketing layout). Overrides
     Laravel's own errors::minimal, so its default 401/403/419/429 pages get this look too.
     It must render while the app itself is broken: no @vite (the build may be missing), no
     routes, no database, no session — inline CSS and plain /public paths only. --}}
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>@yield('title') | MakanApa</title>
        <link rel="icon" href="/images/mascot-default.svg" type="image/svg+xml">
        <style>
            @font-face { font-family: 'Fredoka'; font-weight: 700; font-display: swap; src: url('/fonts/fredoka/fredoka-latin-700.woff2') format('woff2'); }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem;
                   background: #f4efe6; color: #2b1c14; font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; text-align: center; }
            main { max-width: 32rem; }
            img { width: 8rem; height: 8rem; }
            .code { margin: 1rem 0 0; font: 700 clamp(4rem, 16vw, 6rem)/0.9 'Fredoka', ui-rounded, ui-sans-serif, sans-serif; color: #cc3814; }
            h1 { margin: 1.25rem 0 0; font: 700 clamp(2rem, 6vw, 2.8rem)/1 'Fredoka', ui-rounded, ui-sans-serif, sans-serif; letter-spacing: -0.02em; }
            p { margin: 1rem 0 0; font-size: 1.125rem; line-height: 1.6; color: rgb(43 28 20 / 0.75); }
            a { display: inline-flex; align-items: center; min-height: 3rem; margin-top: 2rem; padding: 0.75rem 1.75rem; border-radius: 999px;
                background: #2b1c14; color: #f4efe6; font-weight: 600; text-decoration: none; box-shadow: 5px 5px 0 #cc3814; }
            a:focus-visible { outline: 3px solid #cc3814; outline-offset: 3px; }
        </style>
    </head>
    <body>
        <main>
            <img src="/images/mascot-sad.svg" alt="" aria-hidden="true" width="128" height="128">
            <p class="code" aria-hidden="true">@yield('code')</p>
            <h1>@yield('message')</h1>
            @hasSection('detail')
                <p>@yield('detail')</p>
            @endif
            <a href="/">Back to MakanApa</a>
        </main>
    </body>
</html>
