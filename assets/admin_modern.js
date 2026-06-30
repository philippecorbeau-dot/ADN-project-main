// Admin Modern JS Bundle
import './admin_modern.css';
import './bootstrap.js';
import { ChatPoller, renderAdminSideMessage } from './chat/polling.js';

// Chart.js
import Chart from 'chart.js/auto';

// Alpine.js components
import Alpine from 'alpinejs';

// Make Chart.js available globally
window.Chart = Chart;

// Start Alpine
window.Alpine = Alpine;
Alpine.start();

// console.log removed in production

// Boot minimal du poller pour la vue conversation admin si présente
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('messages-container');
    const list = document.getElementById('messages-list');
    const form = document.querySelector('form[data-admin-chat-form]');
    const input = document.getElementById('message-input');
    const sendButton = document.getElementById('send-button');
    const fetchUrl = document.querySelector('[data-admin-chat-fetch-url]')?.dataset?.adminChatFetchUrl;
    const sendUrl = document.querySelector('[data-admin-chat-send-url]')?.dataset?.adminChatSendUrl;

    if (container && list && fetchUrl && sendUrl) {
        const poller = new ChatPoller({
            fetchUrl,
            sendUrl,
            messagesList: list,
            messagesContainer: container,
            messageInput: input,
            sendButton,
            form,
            renderMessage: renderAdminSideMessage,
            pollIntervalMs: 3000,
        });
        poller.start();
    }
});

