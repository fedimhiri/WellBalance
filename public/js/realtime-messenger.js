/**
 * Système de messagerie temps réel - Version Production
 */

const MessengerConfig = {
    conversationId: null,
    currentUserId: null,
    baseUrl: null,
    lastUpdate: 0,
    isTyping: false,
    typingTimeout: null,
    pollingInterval: 2000,
    existingMessageIds: new Set()
};

document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('messages-container');

    if (container) {
        initChatMode(container);
    } else {
        const listTable = document.getElementById('conversations-table');
        if (listTable) {
            initListMode(listTable);
        }
    }
});

// ==========================================
// MODE CHAT (Conversation individuelle)
// ==========================================

function initChatMode(container) {
    MessengerConfig.conversationId = container.dataset.conversationId;
    MessengerConfig.currentUserId = container.dataset.userId;
    MessengerConfig.baseUrl = container.dataset.baseUrl;
    MessengerConfig.lastUpdate = parseInt(container.dataset.lastUpdate) || 0;

    if (!MessengerConfig.conversationId || !MessengerConfig.currentUserId || !MessengerConfig.baseUrl) {
        return;
    }

    collectExistingMessageIds();
    startPolling();
    // setupMessageForm(); // DESACTIVE POUR ERREURS SYMFONY STANDARD
    setupTypingDetection();
    scrollToBottom();
}

function collectExistingMessageIds() {
    document.querySelectorAll('.message-row[data-message-id]').forEach(row => {
        const id = parseInt(row.dataset.messageId);
        if (id) {
            MessengerConfig.existingMessageIds.add(id);
        }
    });
}

// ==========================================
// MODE LISTE (Tableau des conversations)
// ==========================================

function initListMode(table) {
    const baseUrl = table.dataset.baseUrl;

    if (!baseUrl) {
        return;
    }

    // Premier appel immédiat
    pollGlobalStatus(baseUrl);

    // Puis polling toutes les 3 secondes
    setInterval(() => pollGlobalStatus(baseUrl), 3000);
}

async function pollGlobalStatus(baseUrl) {
    try {
        const url = `${baseUrl}/check-global-status?_=${Date.now()}`;
        const response = await fetch(url);

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        if (data.conversations && Array.isArray(data.conversations)) {
            updateConversationList(data.conversations);
        }
    } catch (e) {
        // Silent error
    }
}

function updateConversationList(conversations) {
    conversations.forEach(conv => {
        const row = document.querySelector(`tr[data-conversation-id="${conv.id}"]`);

        if (!row) {
            return;
        }

        let hasChanges = false;

        // 1. Mise à jour du dernier message
        const msgCell = row.querySelector('.last-message-cell');
        if (msgCell) {
            // Nettoyer le texte actuel (enlever <em>, espaces, etc.)
            const currentText = msgCell.textContent.trim().replace(/\s+/g, ' ');
            const newText = conv.last_message.trim();

            if (currentText !== newText) {
                // Pour le front (avec div.text-truncate)
                const truncateDiv = msgCell.querySelector('.text-truncate');
                if (truncateDiv) {
                    truncateDiv.textContent = newText;
                } else {
                    // Pour l'admin (remplacer tout le contenu)
                    msgCell.innerHTML = newText;
                }

                hasChanges = true;
            }
        }

        // 2. Mise à jour de la date
        const dateCell = row.querySelector('.date-cell');
        if (dateCell && conv.date) {
            const currentDate = dateCell.textContent.trim();
            if (currentDate !== conv.date) {
                dateCell.textContent = conv.date;
                hasChanges = true;
            }
        }

        // 3. Mise à jour du statut (pour admin seulement)
        const statusCell = row.querySelector('.status-cell');
        if (statusCell && conv.status_label) {
            const currentStatus = statusCell.textContent.trim();
            if (currentStatus !== conv.status_label) {
                statusCell.innerHTML = `<span class="badge ${conv.status_class} status-badge rounded-pill">${conv.status_label}</span>`;
                hasChanges = true;
            }
        }

        // 4. Style non-lu (pour front)
        if (typeof conv.unread_count !== 'undefined') {
            if (conv.unread_count > 0) {
                if (!row.classList.contains('fw-bold')) {
                    row.classList.add('fw-bold', 'bg-light');
                    hasChanges = true;
                }
            } else {
                if (row.classList.contains('fw-bold')) {
                    row.classList.remove('fw-bold', 'bg-light');
                    hasChanges = true;
                }
            }
        }

        // Animation flash si changement
        if (hasChanges) {
            row.classList.add('table-warning');
            setTimeout(() => row.classList.remove('table-warning'), 1500);
        }
    });
}

// ==========================================
// POLLING CHAT
// ==========================================

function startPolling() {
    pollServer();
    setInterval(pollServer, MessengerConfig.pollingInterval);
}

async function pollServer() {
    await Promise.all([
        checkNewMessages(),
        checkTypingStatus()
    ]);
}

async function checkNewMessages() {
    try {
        const url = `${MessengerConfig.baseUrl}/check-new-messages/${MessengerConfig.conversationId}?last_update=${MessengerConfig.lastUpdate}&_=${Date.now()}`;

        const response = await fetch(url);
        if (!response.ok) {
            return;
        }

        const data = await response.json();

        if (data.success && data.full_refresh && data.messages) {

            // 1. Identifier les messages reçus
            const receivedIds = new Set(data.messages.map(m => m.id));

            // 2. Supprimer les messages qui ne sont plus dans la liste (Suppression)
            document.querySelectorAll('.message-row[data-message-id]').forEach(row => {
                const id = parseInt(row.dataset.messageId);
                if (!receivedIds.has(id)) {
                    row.remove();
                    MessengerConfig.existingMessageIds.delete(id);
                }
            });

            // 3. Ajouter ou Mettre à jour les messages
            data.messages.forEach(msg => {
                if (MessengerConfig.existingMessageIds.has(msg.id)) {
                    // UPDATE: Vérifier si le contenu a changé
                    const existingRow = document.querySelector(`.message-row[data-message-id="${msg.id}"]`);
                    const contentDiv = existingRow.querySelector('.message-text');

                    // Simple nettoyage pour comparaison
                    const currentContent = contentDiv.innerText.replace(/\s+/g, ' ').trim();
                    const newContent = msg.content.replace(/\s+/g, ' ').trim();

                    if (currentContent !== newContent) {
                        contentDiv.innerHTML = escapeHtml(msg.content).replace(/\n/g, '<br>');

                        // Petit effet visuel
                        const bubble = existingRow.querySelector('.message-bubble');
                        bubble.style.transition = 'background-color 0.3s';
                        const originalBg = bubble.style.backgroundColor;
                        bubble.style.backgroundColor = '#fff3cd'; // Highlight jaune léger
                        setTimeout(() => bubble.style.backgroundColor = originalBg, 1000);
                    }
                } else {
                    // CREATE: Nouveau message
                    const messageHtml = createMessageHtml(msg);
                    const container = document.getElementById('messages-container');
                    const typingIndicator = document.getElementById('typing-indicator');

                    if (typingIndicator && typingIndicator.parentElement === container) {
                        typingIndicator.insertAdjacentHTML('beforebegin', messageHtml);
                    } else {
                        container.insertAdjacentHTML('beforeend', messageHtml);
                    }

                    MessengerConfig.existingMessageIds.add(msg.id);
                }
            });

            MessengerConfig.lastUpdate = data.last_update;

            // Si nouveaux messages (plus que ce qu'on avait), scroller
            if (data.messages.length > MessengerConfig.existingMessageIds.size) {
                scrollToBottom();
            }
        }
    } catch (e) {
        // Silent error
    }
}

async function checkTypingStatus() {
    try {
        const url = `${MessengerConfig.baseUrl}/check-typing/${MessengerConfig.conversationId}?_=${Date.now()}`;
        const response = await fetch(url);

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        updateTypingUI(data.typing);
    } catch (e) {
        // Silent error
    }
}

// ==========================================
// UI UPDATES
// ==========================================

function updateTypingUI(isOtherTyping) {
    const indicator = document.getElementById('typing-indicator');
    if (!indicator) return;

    const isCurrentlyVisible = indicator.style.display !== 'none';

    if (isOtherTyping && !isCurrentlyVisible) {
        indicator.style.display = 'block';
        scrollToBottom();
    } else if (!isOtherTyping && isCurrentlyVisible) {
        indicator.style.display = 'none';
    }
}

function scrollToBottom() {
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTo({
            top: container.scrollHeight,
            behavior: 'smooth'
        });
    }
}

// ==========================================
// USER INTERACTIONS
// ==========================================

function setupTypingDetection() {
    const input = document.getElementById('message_content');
    if (!input) return;

    input.addEventListener('input', () => {

        if (!MessengerConfig.isTyping) {
            MessengerConfig.isTyping = true;
            sendTypingStatus(true);
        }

        clearTimeout(MessengerConfig.typingTimeout);
        MessengerConfig.typingTimeout = setTimeout(() => {
            MessengerConfig.isTyping = false;
            sendTypingStatus(false);
        }, 1000);
    });
}

async function sendTypingStatus(isTyping) {
    try {
        const url = `${MessengerConfig.baseUrl}/typing-status`;
        const formData = new URLSearchParams();
        formData.append('conversation_id', MessengerConfig.conversationId);
        formData.append('is_typing', isTyping ? 'true' : 'false');

        await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData.toString()
        });
    } catch (e) {
        // Silent error
    }
}

function setupMessageForm() {
    const form = document.getElementById('message-form') || document.querySelector('form[name="message"]');
    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const input = document.getElementById('message_content');
        const content = input.value.trim();

        if (!content) return;

        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');

        if (submitBtn) submitBtn.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();

                if (data.success && data.message) {
                    input.value = '';
                    input.style.height = 'auto';

                    MessengerConfig.isTyping = false;
                    sendTypingStatus(false);

                    // Manually append the message securely
                    // Logic handled by poll next time OR rely on local append if needed
                    // But here we rely on the response for immediate feedback
                    // We can reuse the "edit/create" logic from poll or just force a poll
                    // To keep it simple and responsive:

                    const messageHtml = createMessageHtml(data.message);
                    const container = document.getElementById('messages-container');
                    const typingIndicator = document.getElementById('typing-indicator');

                    if (typingIndicator && typingIndicator.parentElement === container) {
                        typingIndicator.insertAdjacentHTML('beforebegin', messageHtml);
                    } else {
                        container.insertAdjacentHTML('beforeend', messageHtml);
                    }

                    MessengerConfig.existingMessageIds.add(data.message.id);
                    MessengerConfig.lastUpdate = data.last_update;

                    scrollToBottom();
                }
            } else {
                // Fail silently or visual indication (optional)
            }
        } catch (e) {
            // Error
        } finally {
            if (submitBtn) submitBtn.disabled = false;
            input.focus();
        }
    });
}

// ==========================================
// HTML GENERATOR
// ==========================================

function createMessageHtml(message) {
    const isMine = message.sender_id == MessengerConfig.currentUserId;
    const rowClass = isMine ? 'mine' : 'theirs';
    const alignClass = isMine ? 'align-items-end' : 'align-items-start';

    const avatar = !isMine ? `
        <div class="avatar-circle me-2 d-none d-md-flex" style="width: 28px; height: 28px; font-size: 0.8rem;">
            ${(message.sender_name || 'U').charAt(0).toUpperCase()}
        </div>` : '';

    const actions = isMine ? `
        <div class="message-actions bg-white shadow-sm border px-2 py-1 rounded ms-2 no-print">
            <a href="${MessengerConfig.baseUrl}/message/${message.id}/edit" class="text-primary text-decoration-none me-2" title="Modifier">
                <i class="bi bi-pencil-square"></i>
            </a>
            <a href="${MessengerConfig.baseUrl}/message/${message.id}/delete" class="text-danger text-decoration-none" title="Supprimer">
                <i class="bi bi-trash"></i>
            </a>
        </div>` : '';

    const aiAnalysis = message.ai_analysis && message.ai_analysis.success ? `
        <div class="mt-2 pt-2 border-top border-white border-opacity-25 small no-print">
            <i class="bi bi-magic me-1"></i>
            IA: ${message.ai_analysis.summary?.document_type || 'Analyse'}
        </div>` : '';

    const timestamp = message.created_at ?
        new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) :
        new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    return `
        <div class="message-row ${rowClass}" data-message-id="${message.id}">
            ${avatar}
            <div class="d-flex flex-column ${alignClass}" style="max-width: 100%;">
                <div class="message-bubble">
                    <div class="message-text">${escapeHtml(message.content).replace(/\n/g, '<br>')}</div>
                    ${aiAnalysis}
                </div>
                <div class="message-meta">
                    <span>${timestamp}</span>
                    ${actions}
                </div>
            </div>
        </div>
    `;
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}