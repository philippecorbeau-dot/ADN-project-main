// Chat polling module - réutilisable front/admin

export class ChatPoller {
    constructor(options) {
        this.fetchUrl = options.fetchUrl; // URL pour récupérer les messages (GET)
        this.sendUrl = options.sendUrl; // URL pour envoyer un message (POST x-www-form-urlencoded)
        this.messagesList = options.messagesList; // Element UL/div qui contient les messages
        this.messagesContainer = options.messagesContainer; // Element scrollable
        this.messageInput = options.messageInput; // textarea/input
        this.sendButton = options.sendButton; // bouton submit
        this.form = options.form; // formulaire
        this.renderMessage = options.renderMessage; // (message) => HTMLElement
        this.pollIntervalMs = options.pollIntervalMs ?? 4000;
        this.lastMessageId = options.lastMessageId ?? 0;
        this.autoScroll = options.autoScroll ?? true;
        this.isSending = false;
        this.pollTimer = null;
        this.pending = new Map(); // clientId -> pending DOM node
    }

    start() {
        // Garde anti double-bind
        if (this.form && this.form.__chatPollerBound) {
            return;
        }
        this.bindForm();
        this.startPolling();
        // Scroll initial
        if (this.autoScroll) {
            this.scrollToBottom();
        }
        // Focus input si disponible
        if (this.messageInput) {
            try { this.messageInput.focus({ preventScroll: true }); } catch (_) {}
        }
        if (this.form) {
            this.form.__chatPollerBound = true;
        }
    }

    bindForm() {
        if (!this.form) return;
        // Nettoyer tout handler précédent pour éviter le double envoi
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });

        if (this.messageInput) {
            this.messageInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            // compteur caractère et resize simple
            const charCount = document.getElementById('char-count');
            this.messageInput.addEventListener('input', () => {
                if (charCount) charCount.textContent = String(this.messageInput.value.length);
                if (this.sendButton) this.sendButton.disabled = this.messageInput.value.length === 0 || this.messageInput.value.length > 1000;
                this.messageInput.style.height = 'auto';
                this.messageInput.style.height = Math.min(this.messageInput.scrollHeight, 120) + 'px';
            });
        }
    }

    startPolling() {
        this.stopPolling();
        const tick = async () => {
            try {
                const url = new URL(this.fetchUrl, window.location.origin);
                if (this.lastMessageId) url.searchParams.set('afterId', String(this.lastMessageId));
                const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) return;
                const data = await res.json();
                if (Array.isArray(data.messages) && data.messages.length > 0) {
                    for (const msg of data.messages) {
                        this.appendMessage(msg);
                    }
                }
                if (typeof data.lastId === 'number' && data.lastId > (this.lastMessageId || 0)) {
                    this.lastMessageId = data.lastId;
                }
            } catch (e) {
                // Échec de poll: on ignore pour ne pas spammer la console
            }
        };
        // première exécution rapide
        tick();
        this.pollTimer = setInterval(tick, this.pollIntervalMs);
    }

    stopPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    }

    async sendMessage() {
        if (this.isSending || !this.messageInput) return;
        const message = this.messageInput.value.trim();
        if (!message) return;
        this.isSending = true;
        if (this.sendButton) this.sendButton.disabled = true;
        this.messageInput.disabled = true;
        // Message optimiste (pour retour visuel immédiat)
        const clientId = `c-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        const optimistic = {
            id: null,
            clientId,
            isFromUser: true,
            message,
            formattedTime: new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
            senderInitials: ''
        };
        this.appendMessage(optimistic);
        try {
            const res = await fetch(this.sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: `message=${encodeURIComponent(message)}`,
            });
            const data = await res.json();
            if (data && data.success && data.message) {
                // Remplacer le pending par la version serveur
                this.removePending(clientId);
                const serverMsg = Object.assign({}, data.message, { isFromUser: true });
                this.appendMessage(serverMsg);
                const returnedLastId = typeof data.lastId === 'number' ? data.lastId : (data.message.id || 0);
                this.lastMessageId = Math.max(this.lastMessageId || 0, returnedLastId);
                this.messageInput.value = '';
                const charCount = document.getElementById('char-count');
                if (charCount) charCount.textContent = '0';
                this.messageInput.style.height = 'auto';
            } else if (data && data.error) {
                this.removePending(clientId);
                alert(data.error);
            }
        } catch (e) {
            this.removePending(clientId);
            alert('Erreur de connexion');
        } finally {
            if (this.sendButton) this.sendButton.disabled = false;
            this.messageInput.disabled = false;
            this.messageInput.focus();
            this.isSending = false;
        }
    }

    appendMessage(message) {
        if (!this.messagesList || !this.renderMessage) return;
        if (message && message.id) {
            const exists = this.messagesList.querySelector(`[data-message-id="${message.id}"]`);
            if (exists) {
                this.lastMessageId = Math.max(this.lastMessageId || 0, message.id);
                return;
            }
        }
        const node = this.renderMessage(message);
        if (!node) return;
        if (message && message.id) {
            node.setAttribute('data-message-id', String(message.id));
        }
        // Marquer les messages en attente (optimistes) pour éviter les confusions visuelles
        if ((!message || !message.id) && message && message.clientId) {
            node.setAttribute('data-client-id', message.clientId);
            node.setAttribute('data-pending', '1');
            try { node.style.opacity = '0.75'; } catch (_) {}
            this.pending.set(message.clientId, node);
        }
        this.messagesList.appendChild(node);
        if (this.autoScroll) this.scrollToBottom();
    }

    scrollToBottom() {
        if (!this.messagesContainer) return;
        setTimeout(() => {
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
        }, 50);
    }

    removePending(clientId) {
        if (!clientId) return;
        const node = this.pending.get(clientId) || this.messagesList?.querySelector(`[data-client-id="${clientId}"]`);
        if (node && node.parentNode) {
            node.parentNode.removeChild(node);
        }
        this.pending.delete(clientId);
    }
}

// Helpers de rendu génériques (front vs admin)
export function renderUserSideMessage(message) {
    const div = document.createElement('div');
    const safeText = escapeHtml(message.message || '');
    if (message.isFromUser) {
        div.className = 'flex justify-end';
        div.innerHTML = `
            <div class="flex items-end space-x-2 max-w-xs lg:max-w-md">
                <div class="chat-bubble-user rounded-lg px-4 py-2 shadow-sm">
                    <p class="text-sm">${safeText}</p>
                    <p class="text-xs opacity-75 mt-1">${message.formattedTime || ''}</p>
                </div>
                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-gray-700 font-bold text-xs">${message.senderInitials || ''}</span>
                </div>
            </div>`;
    } else {
        div.className = 'flex justify-start';
        div.innerHTML = `
            <div class="flex items-end space-x-2 max-w-xs lg:max-w-md">
                <div class="w-8 h-8 chat-avatar-admin rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-white font-bold text-xs">${message.senderInitials || ''}</span>
                </div>
                <div class="chat-bubble-admin rounded-lg px-4 py-2 shadow-sm border">
                    <p class="text-sm text-gray-900">${safeText}</p>
                    <p class="text-xs text-gray-500 mt-1">${message.formattedTime || ''}</p>
                </div>
            </div>`;
    }
    return div;
}

export function renderAdminSideMessage(message) {
    const div = document.createElement('div');
    const safeText = escapeHtml(message.message || '');
    if (message.isFromAdmin || message.senderType === 'admin') {
        div.className = 'flex justify-end';
        div.innerHTML = `
            <div class="flex items-end space-x-2 max-w-xs lg:max-w-md">
                <div class="bg-indigo-600 text-white rounded-lg px-4 py-2 shadow-sm">
                    <p class="text-sm">${safeText}</p>
                    <p class="text-xs opacity-75 mt-1">${message.formattedTime || ''}</p>
                </div>
                <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-white font-bold text-xs">ADN</span>
                </div>
            </div>`;
    } else {
        div.className = 'flex justify-start';
        div.innerHTML = `
            <div class="flex items-end space-x-2 max-w-xs lg:max-w-md">
                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-gray-700 font-bold text-xs">${message.senderInitials || ''}</span>
                </div>
                <div class="bg-white rounded-lg px-4 py-2 shadow-sm border">
                    <p class="text-sm text-gray-900">${safeText}</p>
                    <p class="text-xs text-gray-500 mt-1">${message.formattedTime || ''}</p>
                </div>
            </div>`;
    }
    return div;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}


