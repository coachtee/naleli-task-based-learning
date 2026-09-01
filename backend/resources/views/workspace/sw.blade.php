@verbatim
/*
 * Naleli Workspace service worker.
 *
 * Two jobs, and a rule.
 *
 *  1. Keep the app openable with no internet — precache the shell so a lab PC
 *     that loses the line mid-morning still has the whole interface.
 *  2. Keep the course content available — it is the same JSON for everyone and
 *     it is big enough that re-downloading it every morning would hurt.
 *
 * The rule: never cache anything under /me/. That is one learner's work, on a
 * machine three learners a day sit at, and a cache the next page can read is
 * exactly the leak the login is there to prevent. Learner data lives in
 * IndexedDB, scoped to a learner, and is cleared on sign-out.
 */
@endverbatim
const VERSION = "{{ $version }}";
const SHELL_URL = "{{ $base }}/";
const PRECACHE = [
  SHELL_URL,
  "{{ $base }}/icon.svg",
  "{{ $base }}/manifest.webmanifest",
];
@verbatim
const SHELL_CACHE = `naleli-shell-${VERSION}`;
const CONTENT_CACHE = "naleli-content-v1";

self.addEventListener("install", (e) => {
  e.waitUntil(
    caches.open(SHELL_CACHE)
      .then((c) => c.addAll(PRECACHE))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting()),
  );
});

self.addEventListener("activate", (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((k) => k.startsWith("naleli-shell-") && k !== SHELL_CACHE)
            .map((k) => caches.delete(k)),
      ))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return;                     // never touch a push

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // One learner's work. Straight to the network, never stored here.
  if (url.pathname.includes("/api/v1/me")) return;

  // The app itself: try the network so a deploy is picked up, fall back to
  // the copy we hold so a dead line is not a dead app.
  if (req.mode === "navigate") {
    event.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(SHELL_CACHE).then((c) => c.put(SHELL_URL, copy)).catch(() => {});
          return res;
        })
        .catch(() => caches.match(SHELL_URL).then((r) => r || Response.error())),
    );
    return;
  }

  // Course content: serve what we have at once, refresh in the background.
  if (url.pathname.includes("/api/v1/content/")) {
    event.respondWith(
      caches.open(CONTENT_CACHE).then(async (cache) => {
        const hit = await cache.match(req);
        const live = fetch(req)
          .then((res) => { if (res.ok) cache.put(req, res.clone()); return res; })
          .catch(() => hit);
        return hit || live;
      }),
    );
    return;
  }

  // Everything else under the app (icon, manifest): cache, then network.
  event.respondWith(
    caches.match(req).then((hit) => hit || fetch(req)),
  );
});
@endverbatim
