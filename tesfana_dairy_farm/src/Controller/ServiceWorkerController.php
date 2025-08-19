<?php

namespace Drupal\tesfana_dairy_farm\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the Service Worker JavaScript at /sw.js.
 */
class ServiceWorkerController {

  /**
   * Returns the service worker script.
   */
  public function js(): Response {
    $js = <<<'JS'
/* Tesfana Dairy Farm - Service Worker (simple network-first with cache fallback) */
const CACHE_STATIC = 'tesfana-static-v1';

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_STATIC).then((cache) => {
      // Precache *very* small essentials only. Add more as needed.
      return cache.addAll([
        '/',
      ]).catch(() => {});
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((k) => k !== CACHE_STATIC)
          .map((k) => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Ignore non-GET or cross-origin if desired.
  if (req.method !== 'GET') return;

  event.respondWith(
    (async () => {
      try {
        const fresh = await fetch(req);
        const cache = await caches.open(CACHE_STATIC);
        // Only cache basic OK responses.
        if (fresh && fresh.status === 200 && fresh.type === 'basic') {
          cache.put(req, fresh.clone());
        }
        return fresh;
      } catch (e) {
        const cached = await caches.match(req);
        if (cached) return cached;
        // Last resort: return a minimal fallback.
        return new Response('/* offline */', { status: 200, headers: { 'Content-Type': 'application/javascript' } });
      }
    })()
  );
});
JS;

    $response = new Response($js);
    $response->headers->set('Content-Type', 'application/javascript; charset=UTF-8');
    // Let browsers cache it briefly; you can tune this.
    $response->setPublic();
    $response->setMaxAge(3600);
    // Allow scope at site root.
    $response->headers->set('Service-Worker-Allowed', '/');
    return $response;
  }

}
