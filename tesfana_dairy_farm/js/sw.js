const CACHE_NAME = 'tesfana-shell-v1';
const APP_SHELL = [
  '/admin/tesfana-dairy/dashboard',
  '/modules/custom/tesfana_dairy_farm/css/ui.css',
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL)));
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;
  event.respondWith(
    fetch(event.request)
      .then(response => {
        if (/\/entity\/|\/json\//.test(event.request.url)) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
        }
        return response;
      })
      .catch(() => caches.match(event.request))
  );
});
