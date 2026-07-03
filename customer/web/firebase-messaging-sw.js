importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: 'AIzaSyBi-pRKorYRAGhCip1CAe7LQ4FpHMFBXlY',
  authDomain: 'renjo-technology.firebaseapp.com',
  databaseURL: 'https://renjo-technology-default-rtdb.firebaseio.com',
  projectId: 'renjo-technology',
  storageBucket: 'renjo-technology.firebasestorage.app',
  messagingSenderId: '737787730111',
  appId: '1:737787730111:web:abb75eb61f127ec4364e0a',
  measurementId: 'G-SPEPT9CS1B',
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  const notification = payload.notification || {};
  const data = payload.data || {};
  const title = notification.title || 'FoodFlow';
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
    tag: data.order_id ? `order-${data.order_id}` : 'foodflow-generic',
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
