@auth
<style>
    .direct-chat-fab {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 1085;
        width: 58px;
        height: 58px;
        border: 0;
        border-radius: 50%;
        display: grid;
        place-items: center;
        color: #fff;
        background: #25D366;
        box-shadow: 0 16px 40px rgba(37, 211, 102, .35);
    }

    .direct-chat-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        min-width: 22px;
        height: 22px;
        padding: 0 6px;
        border-radius: 999px;
        background: #ef4444;
        color: #fff;
        font-size: 12px;
        font-weight: 800;
        display: none;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
    }

    .direct-chat-panel {
        position: fixed;
        right: 24px;
        bottom: 92px;
        z-index: 1084;
        width: min(380px, calc(100vw - 32px));
        height: min(620px, calc(100vh - 118px));
        background: #fff;
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 18px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
        overflow: hidden;
        display: none;
        flex-direction: column;
    }

    .direct-chat-panel.open { display: flex; }
    .direct-chat-header {
        padding: 14px 16px;
        background: #075E54;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .direct-chat-tabs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .direct-chat-tab {
        border: 0;
        background: transparent;
        padding: 10px;
        font-weight: 800;
        color: #64748b;
    }

    .direct-chat-tab.active {
        color: #075E54;
        border-bottom: 3px solid #25D366;
    }

    .direct-chat-list,
    .direct-chat-search-results {
        overflow-y: auto;
    }

    .direct-chat-list { flex: 1; }
    .direct-chat-row {
        width: 100%;
        border: 0;
        background: #fff;
        border-bottom: 1px solid #eef2f7;
        padding: 12px 14px;
        display: flex;
        gap: 10px;
        text-align: left;
        align-items: center;
    }

    .direct-chat-row:hover,
    .direct-chat-row.active { background: #f0fdf4; }
    .direct-chat-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: #dcfce7;
        color: #075E54;
        font-weight: 900;
        flex: 0 0 auto;
    }

    .direct-chat-main { min-width: 0; flex: 1; }
    .direct-chat-title { font-weight: 850; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .direct-chat-preview { color: #64748b; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .direct-chat-unread {
        min-width: 20px;
        height: 20px;
        border-radius: 999px;
        background: #25D366;
        color: #fff;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 900;
    }

    .direct-chat-thread {
        display: none;
        flex: 1;
        min-height: 0;
        background: #efe7dd;
        flex-direction: column;
    }

    .direct-chat-thread.open { display: flex; }
    .direct-chat-thread-top {
        background: #075E54;
        color: #fff;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .direct-chat-back {
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 50%;
        color: #fff;
        background: rgba(255,255,255,.14);
    }

    .direct-chat-messages {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .direct-chat-bubble {
        max-width: 82%;
        border-radius: 12px;
        padding: 9px 11px;
        font-size: 14px;
        line-height: 1.35;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .08);
    }

    .direct-chat-bubble.mine {
        align-self: flex-end;
        background: #dcf8c6;
    }

    .direct-chat-bubble.theirs {
        align-self: flex-start;
        background: #fff;
    }

    .direct-chat-time {
        display: block;
        margin-top: 4px;
        color: #64748b;
        font-size: 10px;
        text-align: right;
    }

    .direct-chat-compose {
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        padding: 10px;
        display: flex;
        gap: 8px;
    }

    .direct-chat-compose input {
        flex: 1;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: 10px 14px;
        outline: 0;
    }

    .direct-chat-send {
        width: 42px;
        height: 42px;
        border: 0;
        border-radius: 50%;
        color: #fff;
        background: #25D366;
    }

    @media (max-width: 576px) {
        .direct-chat-panel {
            right: 8px;
            left: 8px;
            width: auto;
            bottom: 82px;
            height: min(620px, calc(100vh - 96px));
        }

        .direct-chat-fab {
            right: 18px;
            bottom: 18px;
        }
    }
</style>

<button type="button" class="direct-chat-fab" id="directChatFab" aria-label="Open chat">
    <i class="fab fa-whatsapp fa-xl"></i>
    <span class="direct-chat-badge" id="directChatBadge">0</span>
</button>

<div class="direct-chat-panel" id="directChatPanel">
    <div id="directChatHome" class="d-flex flex-column h-100">
        <div class="direct-chat-header">
            <div>
                <div class="fw-bold">Chats</div>
                <div class="small opacity-75">Realtime direct messages</div>
            </div>
            <button type="button" class="btn btn-sm text-white" id="directChatClose"><i class="fas fa-times"></i></button>
        </div>
        <div class="direct-chat-tabs">
            <button type="button" class="direct-chat-tab active" data-direct-chat-tab="conversations">Chats</button>
            <button type="button" class="direct-chat-tab" data-direct-chat-tab="new">New Chat</button>
        </div>
        <div id="directChatConversations" class="direct-chat-list"></div>
        <div id="directChatNew" class="d-none p-3">
            <input type="search" class="form-control rounded-pill" id="directChatSearch" placeholder="Search users by name, email, or phone">
            <div id="directChatSearchResults" class="direct-chat-search-results mt-3"></div>
        </div>
    </div>
    <div id="directChatThread" class="direct-chat-thread">
        <div class="direct-chat-thread-top">
            <button type="button" class="direct-chat-back" id="directChatBack"><i class="fas fa-arrow-left"></i></button>
            <div class="direct-chat-avatar" id="directChatThreadAvatar">?</div>
            <div class="min-w-0">
                <div class="fw-bold text-truncate" id="directChatThreadTitle">Chat</div>
                <div class="small opacity-75">Direct chat</div>
            </div>
        </div>
        <div class="direct-chat-messages" id="directChatMessages"></div>
        <form class="direct-chat-compose" id="directChatForm">
            <input type="text" id="directChatInput" placeholder="Message" maxlength="2000" autocomplete="off">
            <button type="submit" class="direct-chat-send"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const config = {
        me: {{ auth()->id() }},
        conversationsUrl: @json(route('direct-chat.conversations')),
        usersUrl: @json(route('direct-chat.users')),
        startUrl: @json(route('direct-chat.start')),
        csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
    };

    const els = {
        fab: document.getElementById('directChatFab'),
        badge: document.getElementById('directChatBadge'),
        panel: document.getElementById('directChatPanel'),
        close: document.getElementById('directChatClose'),
        conversations: document.getElementById('directChatConversations'),
        newPane: document.getElementById('directChatNew'),
        search: document.getElementById('directChatSearch'),
        searchResults: document.getElementById('directChatSearchResults'),
        thread: document.getElementById('directChatThread'),
        home: document.getElementById('directChatHome'),
        back: document.getElementById('directChatBack'),
        messages: document.getElementById('directChatMessages'),
        form: document.getElementById('directChatForm'),
        input: document.getElementById('directChatInput'),
        threadTitle: document.getElementById('directChatThreadTitle'),
        threadAvatar: document.getElementById('directChatThreadAvatar'),
    };

    let conversations = [];
    let activeConversation = null;
    let searchTimer = null;
    let unreadCount = 0;
    let audioContext = null;

    const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': config.csrf,
    };

    function initials(name) {
        return (name || '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || '?';
    }

    function formatTime(value) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    async function jsonFetch(url, options = {}) {
        const response = await fetch(url, { headers, ...options });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Chat request failed');
        return data;
    }

    async function loadConversations() {
        try {
            const data = await jsonFetch(config.conversationsUrl);
            const nextUnreadCount = Number(data.unread_count || 0);
            conversations = data.data || [];
            renderConversations();
            if (nextUnreadCount > unreadCount) playMessageSound();
            unreadCount = nextUnreadCount;
            updateBadge(nextUnreadCount);
        } catch (error) {
            console.warn(error);
        }
    }

    function unlockMessageSound() {
        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) return;
            audioContext = audioContext || new AudioContextClass();
            if (audioContext.state === 'suspended') audioContext.resume();
        } catch (error) {
            console.warn(error);
        }
    }

    function playMessageSound() {
        try {
            unlockMessageSound();
            if (!audioContext || audioContext.state === 'suspended') return;
            const oscillator = audioContext.createOscillator();
            const gain = audioContext.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
            oscillator.frequency.exponentialRampToValueAtTime(660, audioContext.currentTime + 0.16);
            gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.08, audioContext.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.22);
            oscillator.connect(gain);
            gain.connect(audioContext.destination);
            oscillator.start();
            oscillator.stop(audioContext.currentTime + 0.24);
        } catch (error) {
            console.warn(error);
        }
    }

    function updateBadge(count) {
        els.badge.textContent = count > 99 ? '99+' : count;
        els.badge.style.display = count > 0 ? 'flex' : 'none';
    }

    function renderConversations() {
        if (!conversations.length) {
            els.conversations.innerHTML = '<div class="p-4 text-center text-muted">No chats yet. Start a new chat.</div>';
            return;
        }

        els.conversations.innerHTML = conversations.map((conversation) => {
            const title = conversation.title || 'Chat';
            const orderLabel = conversation.order?.order_number ? `Order #${conversation.order.order_number}` : '';
            const prefix = orderLabel && title !== orderLabel ? `${escapeHtml(orderLabel)} · ` : '';
            const preview = conversation.last_message?.message || 'No messages yet';
            const unread = Number(conversation.unread_count || 0);
            return `
                <button type="button" class="direct-chat-row" data-conversation-id="${conversation.id}">
                    <div class="direct-chat-avatar">${initials(title)}</div>
                    <div class="direct-chat-main">
                        <div class="direct-chat-title">${escapeHtml(title)}</div>
                        <div class="direct-chat-preview">${prefix}${escapeHtml(preview)}</div>
                    </div>
                    <span class="direct-chat-unread" style="display:${unread > 0 ? 'flex' : 'none'}">${unread > 99 ? '99+' : unread}</span>
                </button>
            `;
        }).join('');
    }

    async function openConversation(id) {
        const url = `${config.conversationsUrl}/${id}`.replace('/conversations/conversations/', '/conversations/');
        const data = await jsonFetch(url);
        activeConversation = data.conversation;
        els.threadTitle.textContent = activeConversation.title || 'Chat';
        if (activeConversation.order?.order_number && activeConversation.title !== `Order #${activeConversation.order.order_number}`) {
            els.threadTitle.textContent = `${activeConversation.title || 'Chat'} · Order #${activeConversation.order.order_number}`;
        }
        els.threadAvatar.textContent = initials(activeConversation.title);
        renderMessages(data.messages || []);
        els.home.classList.add('d-none');
        els.thread.classList.add('open');
        await jsonFetch(`${config.conversationsUrl}/${id}/read`.replace('/conversations/conversations/', '/conversations/'), { method: 'POST', body: '{}' });
        loadConversations();
    }

    function renderMessages(messages) {
        els.messages.innerHTML = messages.map((message) => {
            const mine = Number(message.sender_id) === Number(config.me);
            return `
                <div class="direct-chat-bubble ${mine ? 'mine' : 'theirs'}">
                    ${escapeHtml(message.message || '')}
                    <span class="direct-chat-time">${escapeHtml(formatTime(message.created_at))}</span>
                </div>
            `;
        }).join('');
        els.messages.scrollTop = els.messages.scrollHeight;
    }

    async function sendMessage(text) {
        if (!activeConversation || !text.trim()) return;
        const url = `${config.conversationsUrl}/${activeConversation.id}/messages`.replace('/conversations/conversations/', '/conversations/');
        await jsonFetch(url, {
            method: 'POST',
            body: JSON.stringify({ message: text.trim() }),
        });
        els.input.value = '';
        await openConversation(activeConversation.id);
    }

    async function searchUsers(query) {
        const url = `${config.usersUrl}?q=${encodeURIComponent(query || '')}`;
        const data = await jsonFetch(url);
        const users = data.data || [];
        if (!users.length) {
            els.searchResults.innerHTML = '<div class="text-center text-muted py-3">No users found.</div>';
            return;
        }

        els.searchResults.innerHTML = users.map((user) => `
            <button type="button" class="direct-chat-row" data-user-id="${user.id}">
                <div class="direct-chat-avatar">${initials(user.name)}</div>
                <div class="direct-chat-main">
                    <div class="direct-chat-title">${escapeHtml(user.name || 'User')}</div>
                    <div class="direct-chat-preview">${escapeHtml(user.email || user.phone || '')}</div>
                </div>
            </button>
        `).join('');
    }

    async function startChat(userId) {
        const data = await jsonFetch(config.startUrl, {
            method: 'POST',
            body: JSON.stringify({ user_id: userId }),
        });
        await loadConversations();
        await openConversation(data.data.id);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[char]);
    }

    document.querySelectorAll('[data-direct-chat-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('[data-direct-chat-tab]').forEach((item) => item.classList.remove('active'));
            tab.classList.add('active');
            const isNew = tab.dataset.directChatTab === 'new';
            els.conversations.classList.toggle('d-none', isNew);
            els.newPane.classList.toggle('d-none', !isNew);
            if (isNew) searchUsers(els.search.value);
        });
    });

    els.fab.addEventListener('click', () => {
        unlockMessageSound();
        els.panel.classList.toggle('open');
        if (els.panel.classList.contains('open')) loadConversations();
    });
    els.close.addEventListener('click', () => els.panel.classList.remove('open'));
    els.back.addEventListener('click', () => {
        activeConversation = null;
        els.thread.classList.remove('open');
        els.home.classList.remove('d-none');
        loadConversations();
    });
    els.conversations.addEventListener('click', (event) => {
        const row = event.target.closest('[data-conversation-id]');
        if (row) openConversation(row.dataset.conversationId);
    });
    els.searchResults.addEventListener('click', (event) => {
        const row = event.target.closest('[data-user-id]');
        if (row) startChat(row.dataset.userId);
    });
    els.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => searchUsers(els.search.value), 250);
    });
    els.form.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(els.input.value);
    });

    loadConversations();
    setInterval(() => {
        if (activeConversation) {
            openConversation(activeConversation.id).catch(() => {});
        } else {
            loadConversations();
        }
    }, 5000);
});
</script>
@endauth
