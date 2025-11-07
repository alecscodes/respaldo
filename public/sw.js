"use strict";

// Service Worker configured to NEVER cache - always fetch from network
// This ensures users always get the latest content
// Version: 2.0.0 - No Cache Mode

const SW_VERSION = '2.0.0-no-cache';

self.addEventListener("install", (event) => {
    // Skip waiting to activate immediately - no caching, always fresh
    event.waitUntil(
        Promise.resolve().then(() => {
            return self.skipWaiting();
        })
    );
});

self.addEventListener("fetch", (event) => {
    // Always fetch from network, never use cache
    event.respondWith(
        fetch(event.request)
            .catch((error) => {
                // Only if network completely fails, show offline page
                if (event.request.mode === 'navigate') {
                    return caches.match('/offline.html').catch(() => {
                        // If offline page doesn't exist, return a basic response
                        return new Response('You are offline', {
                            status: 503,
                            statusText: 'Service Unavailable',
                            headers: new Headers({
                                'Content-Type': 'text/plain'
                            })
                        });
                    });
                }
                throw error;
            })
    );
});

self.addEventListener('activate', (event) => {
    // Delete ALL caches to ensure no stale content
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    return caches.delete(cacheName);
                })
            );
        }).then(() => {
            // Take control of all pages immediately
            return self.clients.claim();
        })
    );
});
