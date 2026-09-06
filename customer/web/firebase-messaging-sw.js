importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: 'AIzaSyB6Ixat1fY3Knl7mNiGl4zuNASMxHv23Qg',
  authDomain: 'yumma-458b0.firebaseapp.com',
  databaseURL: 'https://yumma-458b0-default-rtdb.firebaseio.com',
  projectId: 'yumma-458b0',
  storageBucket: 'yumma-458b0.firebasestorage.app',
  messagingSenderId: '596992936599',
  appId: '1:596992936599:web:8d6fa739975dfcf6f6b7d2',
  measurementId: 'G-0ZTK9YVV43',
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  const notification = payload.notification || {};
  const data = payload.data || {};
  const title = notification.title || 'Yumma!';
  const body =
    notification.body ||
    (data.order_number
      ? `Order #${data.order_number} has a new update.`
      : 'You have a new notification.');

  self.registration.showNotification(title, {
    body,
    icon: '/icons/Icon-192.png',
    badge: '/icons/Icon-192.png',
    data,
    tag: data.order_id ? `order-${data.order_id}` : 'yumma-generic',
    renotify: true,
  });
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientsArr) => {
      const existing = clientsArr.find((client) => client.url.includes('/') && 'focus' in client);
      if (existing) {
        return existing.focus();
      }
      if (clients.openWindow) {
        return clients.openWindow('/');
      }
      return null;
    }),
  );
});
