// runner/track_worker.js
let timer = null;

self.onmessage = function(e) {
    if (e.data.action === 'start') {
        if (timer) clearInterval(timer);
        // 每 8 秒向主线程发送一个信号
        timer = setInterval(() => {
            self.postMessage({ action: 'tick' });
        }, 8000);
    } else if (e.data.action === 'stop') {
        clearInterval(timer);
    }
};