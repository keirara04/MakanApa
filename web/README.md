# MakanApa web app

The Android (and any-browser) version of MakanApa: a PWA on the same API as the iOS app. Plan,
phases and decisions: [`../ANDROID_PWA_PLAN.md`](../ANDROID_PWA_PLAN.md).

```sh
cp .env.example .env.local   # API URL and Google client id
npm install
npm run dev                  # http://localhost:5174 (the API must allow this origin: CORS_ALLOWED_ORIGINS)
npm test                     # vitest
npm run build                # production build with the service worker
```

Rules carried over from the app: English-only copy, the mascot from `marketing/mascot-refresh/`
never moves and isn't named, light mode only, motion only in answer to a tap.
