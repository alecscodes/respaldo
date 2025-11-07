// Service Worker - NO CACHING MODE
// Always fetches from network to ensure latest version

// Skip waiting to activate immediately
self.addEventListener("install", event => {
    self.skipWaiting();
});

// Clear all existing caches on activate
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => caches.delete(cacheName))
            );
        }).then(() => {
            return self.clients.claim();
        })
    );
});

// Always fetch from network - NO CACHING
self.addEventListener("fetch", event => {
    // Always fetch from network, bypass cache completely
    event.respondWith(
        fetch(event.request).catch(() => {
            // Only if network fails completely, show offline message
            if (event.request.mode === 'navigate') {
                return new Response('You are offline. Please check your connection.', {
                    headers: { 'Content-Type': 'text/html' }
                });
            }
            return new Response('Network error', { status: 408 });
        })
    );
});
