# Splash screen

V1 uses Android's standard `SplashScreen` API (`core-splashscreen`), themed
via `app/src/main/res/values/themes.xml`:

- Background: `SurfaceWhite` (#FFFFFF)
- Icon: the launcher adaptive icon (see `branding/icons/`), the same "N"
  monogram used across the app for consistency
- Brand text below the icon: "Naleli Task-Based Learning" in `NaleliNavy`

No animation, no network call, no delay beyond what's needed to check
whether a learner profile already exists (routes to Welcome if not, Home if
so). This keeps the offline-first promise honest — the splash is not
hiding a loading spinner for a network request.
