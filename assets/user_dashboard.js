// User Dashboard JavaScript
import './user_dashboard.css';
import { ChatPoller, renderUserSideMessage } from './chat/polling.js';
// console.log removed in production

// Tracking des clics sur les opportunités d'investissement
function trackClick(productType, action) {
    fetch('/api/investment/track-click', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            product_type: productType,
            action: action,
            timestamp: new Date().toISOString()
        })
    }).catch(error => {
        console.error('Erreur lors du tracking:', error);
    });
}

// Faire le tracking global
window.trackClick = trackClick;

// Animations des cartes au chargement
// Indique qu'on préfère utiliser le module ChatPoller plutôt que le fallback inline
// (le fallback vérifiera ce flag avant de s'initialiser)
window.__adnChatPollerPreferred = true;

document.addEventListener('DOMContentLoaded', function() {
    // Animer les statistiques au chargement
    const statCards = document.querySelectorAll('.hover-lift');
    statCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.transform = 'translateY(0)';
            card.style.opacity = '1';
        }, index * 100);
    });
    
    // Animer les barres de progression
    const progressBars = document.querySelectorAll('.kyc-progress-bar, [class*="bg-gradient-to-r"]');
    progressBars.forEach(bar => {
        if (bar.style.width) {
            const targetWidth = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = targetWidth;
            }, 500);
        }
    });
    
    // Auto-hide uniquement pour les flashs Symfony (pas pour les bannières persistantes du dashboard)
    const flashMessages = document.querySelectorAll('.js-flash-message');
    flashMessages.forEach(message => {
        if (message.textContent.trim() && !message.querySelector('button')) {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => {
                    message.remove();
                }, 300);
            }, 5000);
        }
    });

    // Initialisation du polling chat côté utilisateur si la page contient les éléments nécessaires
    const messagesList = document.getElementById('messages-list');
    // La zone réellement scrollable est le contenu, pas le conteneur global avec l'en-tête
    const messagesContainer = document.getElementById('messages-content') || document.getElementById('messages-container');
    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message-input');
    const sendButton = document.getElementById('send-button');
    const fetchUrl = document.body?.dataset?.chatFetchUrl || document.querySelector('[data-chat-fetch-url]')?.dataset?.chatFetchUrl;
    const sendUrl = document.body?.dataset?.chatSendUrl || document.querySelector('[data-chat-send-url]')?.dataset?.chatSendUrl;

    if (messagesList && messagesContainer && fetchUrl && sendUrl) {
        // Rendre visibles les zones masquées si le fallback n'est pas utilisé
        const mc = document.getElementById('messages-container');
        const ia = document.getElementById('chat-input-area');
        if (mc) mc.style.display = '';
        if (ia) ia.style.display = '';

        const poller = new ChatPoller({
            fetchUrl,
            sendUrl,
            messagesList,
            messagesContainer,
            messageInput,
            sendButton,
            form: chatForm,
            renderMessage: renderUserSideMessage,
            pollIntervalMs: 4000,
            lastMessageId: Number(messagesList?.lastElementChild?.dataset?.messageId || 0)
        });
        poller.start();
        // Expose l'instance pour l'intégration avec d'autres scripts (ex: filtres)
        window.__adnChatPollerInstance = poller;
        // Focus immédiat sur la zone de saisie
        try { messageInput?.focus({ preventScroll: false }); } catch (_) {}
        // Indique que le ChatPoller est réellement actif (garde anti-double init)
        setTimeout(() => { window.__adnChatPollerActive = true; }, 0);
    }

    // Emoji picker (simple)
    const emojiBtn = document.querySelector('.emoji-btn');
    const emojiPicker = document.getElementById('emoji-picker');
    const insertAtCursor = (ta, text) => {
        if (!ta) return;
        const start = ta.selectionStart ?? ta.value.length;
        const end = ta.selectionEnd ?? ta.value.length;
        const before = ta.value.slice(0, start);
        const after = ta.value.slice(end);
        ta.value = before + text + after;
        const pos = start + text.length;
        try { ta.selectionStart = ta.selectionEnd = pos; } catch (_) {}
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    };
    if (emojiBtn && emojiPicker && messageInput) {
        const emojis = ['😀','😁','😂','😅','😊','😍','🤔','😉','👍','👏','🔥','🎉','❤️','🙏','😎','🤝','💡','📈','💬','📝','✅','❗'];
        emojiPicker.innerHTML = emojis.map(e => `<button type="button" class="emoji-item">${e}</button>`).join('');
        const togglePicker = () => {
            if (emojiPicker.classList.contains('open')) {
                emojiPicker.classList.remove('open');
            } else {
                emojiPicker.classList.add('open');
            }
        };
        emojiBtn.addEventListener('click', (e) => {
            e.preventDefault();
            togglePicker();
        });
        emojiPicker.addEventListener('click', (e) => {
            const t = e.target;
            if (t && t.classList && t.classList.contains('emoji-item')) {
                insertAtCursor(messageInput, t.textContent || '');
                try { messageInput.focus({ preventScroll: true }); } catch (_) {}
            }
        });
        document.addEventListener('click', (e) => {
            if (!emojiPicker.contains(e.target) && !emojiBtn.contains(e.target)) {
                emojiPicker.classList.remove('open');
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') emojiPicker.classList.remove('open');
        });
    }

    // Gestion UX: masquer le chat lors du filtrage, et relancer au clic sur la carte conversation
    const filterButtons = document.querySelectorAll('.filter-btn[data-filter]');
    filterButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const mc = document.getElementById('messages-container');
            const ia = document.getElementById('chat-input-area');
            if (mc) mc.style.display = 'none';
            if (ia) ia.style.display = 'none';
            window.__adnChatSelected = false;
            try { window.__adnChatPollerInstance?.stopPolling(); } catch (_) {}
        });
    });
    const conversationCard = document.getElementById('conversation-card');
    if (conversationCard) {
        conversationCard.addEventListener('click', () => {
            const mc = document.getElementById('messages-container');
            const ia = document.getElementById('chat-input-area');
            if (mc) mc.style.display = '';
            if (ia) ia.style.display = '';
            try {
                window.__adnChatPollerInstance?.startPolling();
                window.__adnChatPollerInstance?.scrollToBottom();
            } catch (_) {}
        });
    }
});

// Confirmation pour les actions sensibles
document.addEventListener('click', function(e) {
    const target = e.target;
    
    // Confirmation pour supprimer le compte
    if (target.textContent.includes('Supprimer') && target.classList.contains('bg-red-600')) {
        if (!confirm('Êtes-vous sûr de vouloir effectuer cette action ?')) {
            e.preventDefault();
        }
    }
    
    // Confirmation pour refaire le KYC
    if (target.textContent.includes('Recommencer') && target.closest('form')) {
        if (!confirm('Êtes-vous sûr de vouloir recommencer le parcours KYC ? Votre progression actuelle sera perdue.')) {
            e.preventDefault();
        }
    }
});
