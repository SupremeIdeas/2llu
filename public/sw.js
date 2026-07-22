/* NaaraSim service worker — self-hosted web push (no third-party service).
   Shows OS notifications from our own VAPID-signed pushes and focuses/opens the
   right in-app page when the user taps one. */

self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'NaaraSim', body: event.data ? event.data.text() : '' };
    }

    var title = data.title || 'NaaraSim';
    var options = {
        body: data.body || '',
        icon: data.icon || '/favicon.ico',
        badge: data.icon || '/favicon.ico',
        data: { url: data.url || '/notifications' },
        // Coalesce a burst into one entry per destination so we never spam.
        tag: data.url || 'naarasim',
        renotify: true,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/notifications';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if ('focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
