self.addEventListener('push', (event) => {
  let message = {};
  try {
    message = event.data ? event.data.json() : {};
  } catch {
    message = { title: 'New notification', body: event.data ? event.data.text() : '' };
  }

  const work = [];
  if (message.deliveredUrl) {
    work.push(fetch(message.deliveredUrl, { method: 'POST', mode: 'cors', credentials: 'omit' }).catch(() => undefined));
  }
  work.push(self.registration.showNotification(message.title || 'New notification', {
    body: message.body || '',
    icon: message.icon || undefined,
    badge: message.badge || undefined,
    image: message.image || undefined,
    tag: message.tag || undefined,
    data: { clickUrl: message.clickUrl || '/' },
  }));
  event.waitUntil(Promise.all(work));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  let target;
  try {
    target = new URL(event.notification.data?.clickUrl || '/', self.location.origin);
    if (!['https:', 'http:'].includes(target.protocol)) throw new Error('Unsupported URL');
  } catch {
    target = new URL('/', self.location.origin);
  }

  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (windows) => {
    for (const windowClient of windows) {
      if (new URL(windowClient.url).origin === self.location.origin) {
        await windowClient.navigate(target.href);
        return windowClient.focus();
      }
    }
    return clients.openWindow(target.href);
  }));
});
