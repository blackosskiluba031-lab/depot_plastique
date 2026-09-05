const CACHE_NAME = 'business-moses-v2';
const ASSETS_TO_CACHE = [
  './',
  './index.php',
  './ventes.php',
  './produits.php',
  './mouvements.php',
  './clients.php',
  './historique.php',
  './facture.php',
  './manifest.json',
  './pwa-install.js',
  './icons/icon-192x192.png',
  './icons/icon-512x512.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'
];

// Installation du Service Worker avec mise en cache résiliente
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Mettre en cache chaque ressource individuellement pour éviter qu'un échec unique ne bloque l'installation PWA
      return Promise.allSettled(
        ASSETS_TO_CACHE.map((asset) =>
          cache.add(asset).catch((err) => {
            console.warn('Ressource non mise en cache immédiat:', asset, err);
          })
        )
      );
    })
  );
  self.skipWaiting();
});

// Activation et nettoyage des anciens caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Stratégie Network-First avec repli Cache pour mode hors-ligne
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
          const responseClone = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
        }
        return networkResponse;
      })
      .catch(() => {
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          // Si navigation HTML hors-ligne sans cache direct, proposer index.php en repli
          if (event.request.mode === 'navigate') {
            return caches.match('./index.php') || caches.match('./');
          }
        });
      })
  );
});
