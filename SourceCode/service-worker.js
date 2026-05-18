const CACHE_NAME = 'prt-app-v7'; // 更新了版本号，强制浏览器加载新代码

const urlsToCache = [
  'login.php',
  'manifest.json',
  'assets/icon-192.png', 
  'assets/icon-512.png'  
];

// 1. 缓存安装逻辑 (保留你原本的代码)
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
  );
});

// 2. 离线访问逻辑 (保留你原本的代码)
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request);
    })
  );
});

// ==========================================
// 3. 新增：Web Push 接收与弹窗逻辑
// ==========================================

// 监听服务器发来的 Push 事件
self.addEventListener('push', function(event) {
    if (event.data) {
        // 解析 PHP (cron_push.php) 传过来的 JSON 数据
        const data = event.data.json(); 

        const options = {
            body: data.body,
            icon: 'assets/icon-192.png', // 使用你现有的图标路径
            badge: 'assets/icon-192.png',
            vibrate: [200, 100, 200, 100, 200], // 手机震动模式
            data: { url: data.url } // 把要跳转的链接存在这里
        };

        // 触发操作系统的原生弹窗
        event.waitUntil(
            self.registration.showNotification(data.title, options)
        );
    }
});

// 4. 新增：监听用户点击通知栏的事件
self.addEventListener('notificationclick', function(event) {
    event.notification.close(); // 用户点击后自动关闭通知

    // 如果通知里附带了链接，就打开那个网页
    if (event.notification.data && event.notification.data.url) {
        event.waitUntil(
            clients.openWindow(event.notification.data.url)
        );
    }
});