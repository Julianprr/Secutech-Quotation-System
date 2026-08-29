/*
 * Minimal service worker - required for the browser/PWABuilder to treat
 * this site as installable. Since every page here needs live, logged-in
 * data from the database, this intentionally does NOT cache pages -
 * everything just passes straight through to the network as normal.
 */

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    self.clients.claim();
});

self.addEventListener('fetch', function (event) {
    event.respondWith(fetch(event.request));
});
