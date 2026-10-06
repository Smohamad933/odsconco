(() => {
    const app = document.getElementById('messenger-app');
    if (!app) return;

    const api = app.dataset.api;
    const csrf = app.dataset.csrf;
    const myId = Number(app.dataset.currentUser);
    const usersBox = document.getElementById('chat-user-list');
    const messagesBox = document.getElementById('chat-messages');
    const form = document.getElementById('chat-form');
    const composer = form?.querySelector('textarea[name="body"]');
    const fileInput = document.getElementById('chat-file');
    const statusBox = document.getElementById('chat-status');
    const progress = document.getElementById('chat-file-progress');
    const peerHeading = document.getElementById('chat-peer-name');
    const connectionStatus = document.getElementById('chat-connection-status');

    let peerId = Number(app.dataset.peer) || 0;
    let peerName = document.querySelector('.chat-user.is-active')?.dataset.name || '';
    let lastMessageId = 0;
    let lastSignalId = Number(sessionStorage.getItem(`odsconco-signal-${peerId}`) || 0);
    let messagePoll = null;
    let signalPoll = null;
    let localDbPromise = null;
    const pendingRequests = new Set();
    const transfers = new Map();
    const CHUNK_SIZE = 16 * 1024;
    const MAX_FILE_SIZE = 2 * 1024 * 1024 * 1024;

    const setStatus = (message) => { if (statusBox) statusBox.textContent = message || ''; };
    const byteLabel = (value) => {
        const size = Number(value) || 0;
        if (size < 1024) return `${size} بایت`;
        if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} کیلوبایت`;
        if (size < 1024 * 1024 * 1024) return `${(size / (1024 * 1024)).toFixed(1)} مگابایت`;
        return `${(size / (1024 * 1024 * 1024)).toFixed(2)} گیگابایت`;
    };
    const messageTime = (raw) => {
        try {
            const date = new Date(`${String(raw).replace(' ', 'T')}+03:30`);
            return new Intl.DateTimeFormat('fa-IR', { hour: '2-digit', minute: '2-digit', month: '2-digit', day: '2-digit' }).format(date);
        } catch (_) { return String(raw || ''); }
    };
    const requestUrl = (params) => `${api}?${new URLSearchParams(params).toString()}`;

    async function getJson(params) {
        const response = await fetch(requestUrl(params), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) throw new Error(data.error || 'دریافت اطلاعات انجام نشد.');
        return data;
    }
    async function postJson(payload) {
        const response = await fetch(api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) throw new Error(data.error || 'درخواست انجام نشد.');
        return data;
    }

    function openLocalDb() {
        if (localDbPromise) return localDbPromise;
        localDbPromise = new Promise((resolve, reject) => {
            if (!('indexedDB' in window)) return reject(new Error('مرورگر این دستگاه از فضای محلی فایل پشتیبانی نمی‌کند.'));
            const request = indexedDB.open('odsconco-local-attachments', 1);
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains('files')) db.createObjectStore('files', { keyPath: 'key' });
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error || new Error('فضای محلی مرورگر در دسترس نیست.'));
        });
        return localDbPromise;
    }
    async function storeLocalFile(key, file, messageId = 0, peer = peerId) {
        const localDb = await openLocalDb();
        return new Promise((resolve, reject) => {
            const transaction = localDb.transaction('files', 'readwrite');
            transaction.objectStore('files').put({ key: String(key), blob: file, name: file.name, type: file.type || 'application/octet-stream', size: file.size, messageId: Number(messageId), peer: Number(peer), savedAt: Date.now() });
            transaction.oncomplete = () => resolve(true);
            transaction.onerror = () => reject(transaction.error || new Error('ذخیره محلی فایل انجام نشد.'));
            transaction.onabort = () => reject(transaction.error || new Error('ذخیره محلی فایل لغو شد.'));
        });
    }
    async function rekeyLocalFile(oldKey, newKey, messageId, peer) {
        const localDb = await openLocalDb();
        return new Promise((resolve, reject) => {
            const transaction = localDb.transaction('files', 'readwrite');
            const store = transaction.objectStore('files');
            const request = store.get(String(oldKey));
            request.onsuccess = () => {
                if (!request.result) return reject(new Error('نسخه محلی فایل پیدا نشد.'));
                const row = request.result;
                store.put({ ...row, key: String(newKey), messageId: Number(messageId), peer: Number(peer), savedAt: Date.now() });
                store.delete(String(oldKey));
            };
            transaction.oncomplete = () => resolve(true);
            transaction.onerror = () => reject(transaction.error || new Error('به‌روزرسانی فایل محلی انجام نشد.'));
            transaction.onabort = () => reject(transaction.error || new Error('به‌روزرسانی فایل محلی لغو شد.'));
        });
    }
    async function findLocalFile(messageId) {
        const localDb = await openLocalDb();
        return new Promise((resolve, reject) => {
            const request = localDb.transaction('files', 'readonly').objectStore('files').get(String(messageId));
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error || new Error('خواندن فایل محلی انجام نشد.'));
        });
    }
    async function deleteLocalFile(messageId) {
        try {
            const localDb = await openLocalDb();
            await new Promise((resolve, reject) => {
                const transaction = localDb.transaction('files', 'readwrite');
                transaction.objectStore('files').delete(String(messageId));
                transaction.oncomplete = resolve;
                transaction.onerror = () => reject(transaction.error);
            });
        } catch (_) { /* local cleanup is best effort */ }
    }

    function addEmptyMessage(text) {
        messagesBox.innerHTML = '';
        const p = document.createElement('p');
        p.className = 'chat-empty';
        p.textContent = text;
        messagesBox.append(p);
    }
    function renderMessage(message) {
        const empty = messagesBox.querySelector('.chat-empty');
        if (empty) empty.remove();
        const wrapper = document.createElement('article');
        wrapper.className = `chat-message ${Number(message.sender_id) === myId ? 'own' : 'other'}`;
        wrapper.dataset.messageId = String(message.id);

        if (message.body) {
            const body = document.createElement('div');
            body.className = 'chat-message-body';
            body.textContent = message.body;
            wrapper.append(body);
        }
        if (message.attachment_name) {
            const fileCard = document.createElement('div');
            fileCard.className = 'chat-file-card';
            const icon = document.createElement('span');
            icon.className = 'chat-file-icon';
            icon.textContent = '↗';
            const info = document.createElement('div');
            info.className = 'chat-file-info';
            const name = document.createElement('strong');
            name.textContent = message.attachment_name;
            const size = document.createElement('small');
            size.textContent = byteLabel(message.attachment_size);
            info.append(name, size);
            fileCard.append(icon, info);

            if (Number(message.sender_id) === myId) {
                const localState = document.createElement('small');
                localState.className = 'chat-file-local-state';
                localState.textContent = 'فایل فقط روی دستگاه فرستنده نگهداری می‌شود';
                info.append(localState);
            } else {
                const receive = document.createElement('button');
                receive.className = 'chat-file-button';
                receive.type = 'button';
                receive.textContent = 'درخواست دریافت';
                receive.addEventListener('click', () => requestFile(Number(message.id), Number(message.sender_id), message.attachment_name));
                fileCard.append(receive);
            }
            wrapper.append(fileCard);
        }
        const meta = document.createElement('div');
        meta.className = 'chat-message-meta';
        const sender = document.createElement('span');
        sender.textContent = Number(message.sender_id) === myId ? 'شما' : peerName;
        const time = document.createElement('time');
        time.textContent = messageTime(message.created_at);
        meta.append(sender, time);
        wrapper.append(meta);
        messagesBox.append(wrapper);
    }

    async function refreshMessages(initial = false) {
        if (!peerId) return;
        try {
            const data = await getJson({ action: 'messages', peer: String(peerId), after: initial ? '0' : String(lastMessageId) });
            if (initial) {
                messagesBox.innerHTML = '';
                if (!data.messages.length) addEmptyMessage('هنوز پیامی در این گفت‌وگو وجود ندارد.');
            }
            data.messages.forEach((message) => {
                if (messagesBox.querySelector(`[data-message-id="${Number(message.id)}"]`)) return;
                renderMessage(message);
                lastMessageId = Math.max(lastMessageId, Number(message.id));
            });
            messagesBox.scrollTop = messagesBox.scrollHeight;
            connectionStatus.textContent = 'متصل به سامانه';
        } catch (error) {
            connectionStatus.textContent = 'ارتباط موقتاً برقرار نیست';
            setStatus(error.message);
        }
    }

    async function sendSignal(peer, messageId, signalType, payload = {}) {
        return postJson({ action: 'signal', peer, message_id: messageId, signal_type: signalType, payload });
    }

    function waitForIce(pc) {
        if (pc.iceGatheringState === 'complete') return Promise.resolve();
        return new Promise((resolve) => {
            const done = () => {
                if (pc.iceGatheringState === 'complete') {
                    pc.removeEventListener('icegatheringstatechange', done);
                    resolve();
                }
            };
            pc.addEventListener('icegatheringstatechange', done);
            setTimeout(() => {
                pc.removeEventListener('icegatheringstatechange', done);
                resolve();
            }, 12000);
        });
    }

    function waitForBuffer(channel) {
        if (channel.bufferedAmount < 512 * 1024) return Promise.resolve();
        return new Promise((resolve) => {
            const finish = () => {
                channel.removeEventListener('bufferedamountlow', finish);
                resolve();
            };
            channel.bufferedAmountLowThreshold = 256 * 1024;
            channel.addEventListener('bufferedamountlow', finish, { once: true });
            setTimeout(finish, 2000);
        });
    }

    async function requestFile(messageId, senderId, name) {
        if (!peerId || Number(senderId) !== peerId) {
            setStatus('ابتدا گفت‌وگوی فرستنده را انتخاب کنید.');
            return;
        }
        if (!('RTCPeerConnection' in window)) {
            setStatus('مرورگر از انتقال مستقیم فایل پشتیبانی نمی‌کند.');
            return;
        }
        pendingRequests.add(messageId);
        setStatus(`درخواست دریافت «${name}» برای فرستنده ارسال شد. هر دو نفر باید آنلاین باشند.`);
        try {
            await sendSignal(peerId, messageId, 'file-request', {});
        } catch (error) {
            pendingRequests.delete(messageId);
            setStatus(error.message);
        }
    }

    async function offerFile(messageId, receiverId) {
        let row;
        try { row = await findLocalFile(messageId); } catch (_) { row = null; }
        if (!row?.blob) {
            setStatus('نسخه محلی فایل پیدا نشد؛ ممکن است داده مرورگر پاک شده باشد.');
            await sendSignal(receiverId, messageId, 'file-cancel', { reason: 'local-file-missing' }).catch(() => {});
            return;
        }
        if (!('RTCPeerConnection' in window)) {
            setStatus('مرورگر از انتقال مستقیم فایل پشتیبانی نمی‌کند.');
            return;
        }
        if (transfers.has(messageId)) {
            setStatus('انتقال این فایل در حال انجام است.');
            return;
        }
        setStatus(`در حال آماده‌سازی انتقال مستقیم «${row.name}»…`);
        const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
        const channel = pc.createDataChannel('odsconco-file', { ordered: true });
        const transfer = { pc, channel, role: 'sender', messageId, peer: receiverId, file: row.blob, name: row.name, mime: row.type, size: row.size, started: false };
        transfers.set(messageId, transfer);
        channel.binaryType = 'arraybuffer';
        channel.onopen = () => sendFileChunks(transfer).catch((error) => setStatus(error.message));
        channel.onerror = () => setStatus('کانال انتقال فایل با خطا روبه‌رو شد.');
        channel.onclose = () => {
            if (transfers.get(messageId) === transfer) {
                transfers.delete(messageId);
                pc.close();
            }
        };
        try {
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            await waitForIce(pc);
            await sendSignal(receiverId, messageId, 'file-offer', { description: pc.localDescription });
            setStatus('پیشنهاد انتقال ارسال شد؛ منتظر اتصال مستقیم گیرنده…');
        } catch (error) {
            transfers.delete(messageId);
            pc.close();
            setStatus(`برقراری ارتباط مستقیم ناموفق بود: ${error.message}`);
        }
    }

    async function sendFileChunks(transfer) {
        if (transfer.started) return;
        transfer.started = true;
        const channel = transfer.channel;
        progress.hidden = false;
        const progressFill = progress.querySelector('span');
        channel.send(JSON.stringify({ type: 'meta', name: transfer.name, size: transfer.size, mime: transfer.mime }));
        for (let offset = 0; offset < transfer.size; offset += CHUNK_SIZE) {
            if (channel.readyState !== 'open') throw new Error('ارتباط مستقیم حین انتقال قطع شد.');
            await waitForBuffer(channel);
            const chunk = await transfer.file.slice(offset, Math.min(offset + CHUNK_SIZE, transfer.size)).arrayBuffer();
            channel.send(chunk);
            const percent = Math.min(100, Math.round(((offset + chunk.byteLength) / transfer.size) * 100));
            if (progressFill) progressFill.style.width = `${percent}%`;
            setStatus(`انتقال مستقیم فایل: ${percent}٪`);
        }
        channel.send(JSON.stringify({ type: 'complete' }));
        setStatus('فایل مستقیماً ارسال شد.');
        setTimeout(() => { progress.hidden = true; if (progressFill) progressFill.style.width = '0%'; }, 2500);
    }

    async function acceptOffer(signal) {
        const messageId = Number(signal.message_id);
        if (!pendingRequests.has(messageId)) return;
        if (transfers.has(messageId)) return;
        if (!('RTCPeerConnection' in window)) return;
        const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
        const transfer = { pc, channel: null, role: 'receiver', messageId, peer: peerId, chunks: [], received: 0, meta: null };
        transfers.set(messageId, transfer);
        pc.ondatachannel = (event) => {
            const channel = event.channel;
            transfer.channel = channel;
            channel.binaryType = 'arraybuffer';
            channel.onmessage = (messageEvent) => {
                if (typeof messageEvent.data === 'string') {
                    let data;
                    try { data = JSON.parse(messageEvent.data); } catch (_) { return; }
                    if (data.type === 'meta') {
                        transfer.meta = data;
                        progress.hidden = false;
                        setStatus(`در حال دریافت «${data.name}»…`);
                    } else if (data.type === 'complete') {
                        finishFile(transfer);
                    }
                    return;
                }
                const bytes = messageEvent.data instanceof ArrayBuffer ? messageEvent.data : null;
                if (!bytes) return;
                transfer.chunks.push(bytes);
                transfer.received += bytes.byteLength;
                const percent = transfer.meta?.size ? Math.min(100, Math.round((transfer.received / transfer.meta.size) * 100)) : 0;
                const progressFill = progress.querySelector('span');
                if (progressFill) progressFill.style.width = `${percent}%`;
                setStatus(`دریافت مستقیم فایل: ${percent}٪`);
                if (transfer.meta?.size && transfer.received >= Number(transfer.meta.size)) finishFile(transfer);
            };
            channel.onerror = () => setStatus('دریافت فایل با خطا روبه‌رو شد.');
            channel.onclose = () => {
                if (transfers.get(messageId) === transfer && transfer.received < Number(transfer.meta?.size || 0)) {
                    setStatus('انتقال فایل پیش از پایان قطع شد. برای تلاش دوباره، دریافت را درخواست کنید.');
                    transfers.delete(messageId);
                    pc.close();
                }
            };
        };
        try {
            await pc.setRemoteDescription(signal.payload.description);
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await waitForIce(pc);
            await sendSignal(peerId, messageId, 'file-answer', { description: pc.localDescription });
            setStatus('پاسخ ارسال شد؛ در حال اتصال مستقیم به فرستنده…');
        } catch (error) {
            transfers.delete(messageId);
            pc.close();
            setStatus(`پاسخ انتقال فایل ارسال نشد: ${error.message}`);
        }
    }

    function finishFile(transfer) {
        if (!transfer.meta || transfer.finished) return;
        transfer.finished = true;
        const blob = new Blob(transfer.chunks, { type: transfer.meta.mime || 'application/octet-stream' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = transfer.meta.name || 'attachment';
        document.body.append(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 60000);
        setStatus('دریافت فایل کامل شد؛ فایل در پوشه دانلود دستگاه ذخیره می‌شود.');
        pendingRequests.delete(transfer.messageId);
        transfers.delete(transfer.messageId);
        transfer.channel?.close();
        transfer.pc.close();
        setTimeout(() => { progress.hidden = true; const fill = progress.querySelector('span'); if (fill) fill.style.width = '0%'; }, 3000);
    }

    async function processSignals() {
        if (!peerId) return;
        try {
            const data = await getJson({ action: 'signals', peer: String(peerId), after: String(lastSignalId) });
            for (const signal of data.signals) {
                lastSignalId = Math.max(lastSignalId, Number(signal.id));
                sessionStorage.setItem(`odsconco-signal-${peerId}`, String(lastSignalId));
                const messageId = Number(signal.message_id);
                if (signal.signal_type === 'file-request') {
                    await offerFile(messageId, peerId);
                } else if (signal.signal_type === 'file-offer') {
                    await acceptOffer(signal);
                } else if (signal.signal_type === 'file-answer') {
                    const transfer = transfers.get(messageId);
                    if (transfer?.role === 'sender' && signal.payload?.description) {
                        await transfer.pc.setRemoteDescription(signal.payload.description);
                    }
                } else if (signal.signal_type === 'file-cancel') {
                    setStatus('انتقال فایل لغو شد یا نسخه محلی فرستنده در دسترس نیست.');
                }
            }
        } catch (error) {
            connectionStatus.textContent = 'سیگنال انتقال موقتاً در دسترس نیست';
        }
    }

    async function selectPeer(nextPeer, name) {
        if (Number(nextPeer) === peerId) return;
        peerId = Number(nextPeer);
        peerName = name || 'همکار';
        lastMessageId = 0;
        lastSignalId = Number(sessionStorage.getItem(`odsconco-signal-${peerId}`) || 0);
        app.dataset.peer = String(peerId);
        peerHeading.textContent = peerName;
        form.hidden = !peerId;
        usersBox?.querySelectorAll('.chat-user').forEach((button) => button.classList.toggle('is-active', Number(button.dataset.peer) === peerId));
        history.replaceState(null, '', `${location.pathname}?peer=${encodeURIComponent(peerId)}`);
        addEmptyMessage('در حال دریافت پیام‌ها…');
        setStatus('');
        await refreshMessages(true);
        clearInterval(messagePoll);
        clearInterval(signalPoll);
        messagePoll = setInterval(() => refreshMessages(false), 2500);
        signalPoll = setInterval(processSignals, 2200);
        processSignals();
    }

    usersBox?.querySelectorAll('.chat-user').forEach((button) => {
        button.addEventListener('click', () => selectPeer(Number(button.dataset.peer), button.dataset.name));
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!peerId) return;
        const body = composer.value.trim();
        const file = fileInput.files?.[0] || null;
        if (!body && !file) return;
        if (file && file.size > MAX_FILE_SIZE) {
            setStatus('حداکثر اندازه فایل برای انتقال مستقیم ۲ گیگابایت است.');
            return;
        }
        const sendButton = form.querySelector('button[type="submit"]');
        sendButton.disabled = true;
        try {
            let messageId;
            if (file) {
                setStatus('در حال نگهداری نسخه محلی فایل…');
                const tempKey = `pending-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                await storeLocalFile(tempKey, file, 0, peerId);
                try {
                    const data = await postJson({ action: 'send', peer: peerId, body, attachment_name: file.name, attachment_size: file.size, attachment_type: file.type || 'application/octet-stream' });
                    messageId = Number(data.id);
                    await rekeyLocalFile(tempKey, messageId, messageId, peerId);
                } catch (error) {
                    await deleteLocalFile(tempKey);
                    throw error;
                }
                fileInput.value = '';
                setStatus('پیام و مشخصات فایل ثبت شد؛ فایل اصلی فقط روی همین دستگاه است.');
            } else {
                const data = await postJson({ action: 'send', peer: peerId, body });
                messageId = Number(data.id);
                setStatus('پیام ارسال شد.');
            }
            composer.value = '';
            await refreshMessages(false);
        } catch (error) {
            setStatus(error.message || 'ارسال انجام نشد.');
        } finally {
            sendButton.disabled = false;
        }
    });

    fileInput?.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (file) setStatus(`فایل انتخاب شد: ${file.name} (${byteLabel(file.size)}). هنگام ارسال، نسخه‌ای در مرورگر ذخیره می‌شود.`);
    });

    if (peerId) {
        refreshMessages(true);
        messagePoll = setInterval(() => refreshMessages(false), 2500);
        signalPoll = setInterval(processSignals, 2200);
        processSignals();
    }
})();
