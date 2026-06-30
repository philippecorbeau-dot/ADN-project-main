// Fallback léger si le bundle JS n'est pas chargé: active le bouton et envoie/polle en AJAX
(() => {
    function startFallback() {
        // Si le bundle a déjà initialisé le ChatPoller, on ne démarre PAS le fallback pour éviter le double envoi
        if (window.__adnChatPollerPreferred || window.__adnChatPollerActive) {
            /* blocked */
        }
        // Ne rien faire tant que la conversation n'est pas sélectionnée
        else if (!window.__adnChatSelected) {
            /* blocked */
        } else {
            const root = document.querySelector('[data-chat-fetch-url]');
            const fetchUrl = root && root.dataset ? root.dataset.chatFetchUrl : null;
            const readUrl = root && root.dataset ? root.dataset.chatReadUrl : null;
            const sendUrl = root && root.dataset ? root.dataset.chatSendUrl : null;
            const conversationId = root && root.dataset ? root.dataset.conversationId : null;
            const newUrl = root && root.dataset ? root.dataset.chatNewUrl : null;
            const messagesList = document.getElementById('messages-list');
            const messagesContent = document.getElementById('messages-content');
            const form = document.getElementById('chat-form');
            const input = document.getElementById('message-input');
            const sendBtn = document.getElementById('send-button');
            const charCount = document.getElementById('char-count');
            if (!root || !form || !input || !sendBtn) return;

            // Désactiver l'input si conversation fermée
            const ccEl = document.getElementById('conversation-card');
            const isClosed = ccEl && ccEl.getAttribute('data-status') === 'ferme';
            if (isClosed) {
                // Si la conversation affichée est fermée, on bascule automatiquement vers un nouveau chat
                if (newUrl) {
                    window.location.assign(newUrl);
                    return;
                } else {
                    input.disabled = true;
                    sendBtn.disabled = true;
                }
            }

            const toggle = () => {
                if (charCount) charCount.textContent = String(input.value.length);
                sendBtn.disabled = input.value.length === 0 || input.value.length > 1000;
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 120) + 'px';
            };
            input.addEventListener('input', toggle);
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
            toggle();
            // Focus initial sur la zone de saisie
            try { input.focus(); } catch (_) {}

            let lastId = 0;
            const render = (msg) => {
                const div = document.createElement('div');
                const safe = (t) => { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; };
                if (msg.isFromUser) {
                    div.className = 'flex justify-end';
                    div.setAttribute('data-message-id', msg.id || '');
                    div.innerHTML = '\n            <div class="flex items-end space-x-2 max-w-xs lg:max-w-md">\n                <div class="bg-blue-600 text-white rounded-lg px-4 py-2 shadow-sm">\n                    <p class="text-sm">' + safe(msg.message) + '</p>\n                    <p class="text-xs opacity-75 mt-1">' + (msg.formattedTime || '') + '</p>\n                </div>\n                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center flex-shrink-0">\n                    <span class="text-gray-700 font-bold text-xs">' + (msg.senderInitials || '') + '</span>\n                </div>\n            </div>';
                } else {
                    div.className = 'flex justify-start';
                    div.setAttribute('data-message-id', msg.id || '');
                    div.innerHTML = '\n            <div class="flex items-end space-x-2 max-w-xs lg:max-w-md">\n                <div class="w-8 h-8 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-full flex items-center justify-center flex-shrink-0">\n                    <span class="text-white font-bold text-xs">' + (msg.senderInitials || '') + '</span>\n                </div>\n                <div class="bg-white rounded-lg px-4 py-2 shadow-sm border">\n                    <p class="text-sm text-gray-900">' + safe(msg.message) + '</p>\n                    <p class="text-xs text-gray-500 mt-1">' + (msg.formattedTime || '') + '</p>\n                </div>\n            </div>';
                }
                return div;
            };

            const append = (msg) => {
                if (msg && msg.id) {
                    const exists = messagesList.querySelector(`[data-message-id="${msg.id}"]`);
                    if (exists) { lastId = Math.max(lastId, msg.id || 0); return; }
                }
                const node = render(msg);
                if (msg && msg.id) node.setAttribute('data-message-id', msg.id);
                messagesList.appendChild(node);
                lastId = Math.max(lastId, msg.id || 0);
                setTimeout(() => { if (messagesContent) { messagesContent.scrollTop = messagesContent.scrollHeight; } }, 50);
            };

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const val = input.value.trim();
                if (!val || !sendUrl) return;
                sendBtn.disabled = true; input.disabled = true;
                try {
                    const res = await fetch(sendUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'message=' + encodeURIComponent(val) + (conversationId ? '&conversationId=' + encodeURIComponent(conversationId) : '')
                    });
                    if (!res.ok) {
                        // Conversation fermée → ouvrir automatiquement une nouvelle conversation
                        if (res.status === 403 && newUrl) {
                            window.location.assign(newUrl);
                            return;
                        } else if (res.status === 403) {
                            input.disabled = true; sendBtn.disabled = true;
                        }
                        try { const err = await res.json(); console.warn(err); } catch (_) {}
                        return;
                    }
                    const data = await res.json();
                    if (data && data.success && data.message) { input.value = ''; toggle(); append(data.message); }
                } catch (_) { /* noop */ } finally { sendBtn.disabled = false; input.disabled = false; try { input.focus({ preventScroll: true }); } catch (_) {} }
            });

            const tick = async () => {
                if (!fetchUrl) return;
                try {
                    const url = new URL(fetchUrl, window.location.origin);
                    if (conversationId) url.searchParams.set('conversationId', conversationId);
                    if (lastId) url.searchParams.set('afterId', String(lastId));
                    const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    (data.messages || []).forEach(append);
                    if (typeof data.lastId === 'number') lastId = Math.max(lastId, data.lastId);
                } catch (_) {}
            };
            // initial lastId depuis le DOM
            const lastDomId = Number((messagesList && messagesList.lastElementChild && messagesList.lastElementChild.dataset && messagesList.lastElementChild.dataset.messageId) ? messagesList.lastElementChild.dataset.messageId : 0);
            if (lastDomId) lastId = lastDomId;
            tick();
            setInterval(tick, 4000);
            // Scroll initial en bas
            setTimeout(() => { if (messagesContent) { messagesContent.scrollTop = messagesContent.scrollHeight; } try { input.focus({ preventScroll: true }); } catch (_) {} }, 0);
        } // fin else
    }

    // On ne démarre le fallback qu'après sélection explicite
    window.__adnChatSelected = false;

    function selectConversation() {
        if (window.__adnChatSelected) return;
        window.__adnChatSelected = true;
        // Afficher les zones masquées
        const mc = document.getElementById('messages-container');
        const ia = document.getElementById('chat-input-area');
        if (mc) mc.style.display = '';
        if (ia) ia.style.display = '';
        // Marquer comme lu côté serveur (recalcule local du readUrl)
        try {
            const root = document.querySelector('[data-chat-fetch-url]');
            const readUrl = root && root.dataset ? root.dataset.chatReadUrl : null;
            const conversationId = root && root.dataset ? root.dataset.conversationId : null;
            const newUrl = root && root.dataset ? root.dataset.chatNewUrl : null;
            if (readUrl) {
                const body = conversationId ? 'conversationId=' + encodeURIComponent(conversationId) : '';
                fetch(readUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }, body });
            }
        } catch (_) {}
        // Lancer le fallback
        setTimeout(startFallback, 0);
    }
    // Clic sur la carte conversation pour ouvrir
    ((el) => { if (el) { el.addEventListener('click', selectConversation); } })(document.getElementById('conversation-card'));
})();

// Interactions modernes pour les filtres et notifications
document.addEventListener('DOMContentLoaded', function () {
    // Gestion des filtres modernisés
    const filterButtons = document.querySelectorAll('.filter-btn[data-filter]');
    const conversationsList = document.getElementById('conversations-list');
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            // Retirer la classe active de tous les boutons
            filterButtons.forEach(b => b.classList.remove('active'));
            // Ajouter la classe active au bouton cliqué
            this.classList.add('active');

            // Animation de clic
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);

            // Filtrage côté client (pour 1+ conversations)
            const filter = this.dataset.filter;
            const items = conversationsList ? conversationsList.querySelectorAll('.conversation-item') : null;
            if (items) {
                items.forEach((el) => {
                    const status = el.getAttribute('data-status'); // 'ouvert' | 'ferme'
                    const unread = el.getAttribute('data-unread'); // '1' | '0'
                    let show = true;
                    if (filter === 'open') show = status === 'ouvert';
                    else if (filter === 'closed') show = status === 'ferme';
                    else if (filter === 'unread') show = unread === '1';
                    el.style.display = show ? '' : 'none';
                });
            }

            // Masquer le fil et la zone d'input pendant la navigation dans les filtres
            try {
                const mc = document.getElementById('messages-container');
                const ia = document.getElementById('chat-input-area');
                if (mc) mc.style.display = 'none';
                if (ia) ia.style.display = 'none';
                window.__adnChatSelected = false;
                if (window.__adnChatPollerInstance && typeof window.__adnChatPollerInstance.stopPolling === 'function') {
                    window.__adnChatPollerInstance.stopPolling();
                }
            } catch (_) {}
        });
    });

    // Gestion des notifications modernisées
    const notifBanner = document.querySelector('.notification-banner');
    const activateBtn = notifBanner ? notifBanner.querySelector('.notification-btn') : null;
    // Masquer la bannière si déjà autorisé ou mémorisé
    try {
        if (notifBanner && 'Notification' in window) {
            if (Notification.permission === 'granted' || localStorage.getItem('adnNotif') === '1') {
                notifBanner.remove();
            }
        }
    } catch (_) { }
    if (activateBtn) activateBtn.addEventListener('click', async () => {
        // Animation du bouton
        activateBtn.style.transform = 'scale(0.95)';
        activateBtn.textContent = 'Activation...';

        try {
            if ('Notification' in window) {
                const perm = await Notification.requestPermission();
                if (perm === 'granted') {
                    // Animation de disparition
                    notifBanner.style.transform = 'translateY(-100%)';
                    notifBanner.style.opacity = '0';

                    setTimeout(() => {
                        notifBanner.remove();
                    }, 500);

                    // Notification de succès
                    new Notification('🎉 Notifications activées !', {
                        body: 'Vous recevrez désormais les réponses de l\'équipe ADN en temps réel.',
                        icon: '/favicon.ico'
                    });
                    try { localStorage.setItem('adnNotif', '1'); } catch (_) { }
                } else {
                    activateBtn.textContent = 'Autorisation refusée';
                    activateBtn.style.background = 'linear-gradient(to right, #ef4444, #dc2626)';
                    setTimeout(() => {
                        activateBtn.textContent = 'Réessayer';
                        activateBtn.style.background = '';
                    }, 2000);
                }
            }
        } catch (e) {
            console.warn('Notifications non supportées:', e);
            activateBtn.textContent = 'Non supporté';
        } finally {
            activateBtn.style.transform = '';
        }
    });

    // Animation d'entrée progressive des éléments
    const animateElements = document.querySelectorAll('.filter-btn, .conversation-item, .welcome-message');
    animateElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';

        setTimeout(() => {
            el.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Amélioration de l'input avec animation
    const messageInput = document.getElementById('message-input');
    const inputWrapper = messageInput ? messageInput.closest('.input-wrapper') : null;

    if (messageInput && inputWrapper) {
        messageInput.addEventListener('focus', () => {
            inputWrapper.style.transform = 'scale(1.02)';
            inputWrapper.style.boxShadow = '0 10px 30px rgba(59, 130, 246, 0.2)';
        });

        messageInput.addEventListener('blur', () => {
            inputWrapper.style.transform = '';
            inputWrapper.style.boxShadow = '';
        });
    }

    // Animation du bouton d'envoi
    const sendBtn = document.getElementById('send-button');
    if (sendBtn) {
        sendBtn.addEventListener('mousedown', () => {
            sendBtn.style.transform = 'scale(0.95)';
        });

        sendBtn.addEventListener('mouseup', () => {
            sendBtn.style.transform = '';
        });
    }

    // Suppression d'une conversation
    const deleteBtn = document.getElementById('delete-chat-btn');
    const root = document.querySelector('[data-chat-fetch-url]');
    const deleteUrl = root && root.dataset ? root.dataset.chatDeleteUrl : null;
    const conversationId = root && root.dataset ? root.dataset.conversationId : null;
    const newUrl = root && root.dataset ? root.dataset.chatNewUrl : null;
    if (deleteBtn && deleteUrl && conversationId) {
        deleteBtn.addEventListener('click', async () => {
            if (!confirm('Supprimer définitivement ce chat ?')) return;
            try {
                const res = await fetch(deleteUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'conversationId=' + encodeURIComponent(conversationId)
                });
                if (res.ok) {
                    if (newUrl) window.location.assign(newUrl);
                }
            } catch (e) {
                console.warn('Suppression impossible', e);
            }
        });
    }
});


