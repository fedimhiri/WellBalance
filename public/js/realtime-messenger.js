/**
 * Système de messagerie temps réel - Version optimisée basse latence
 */

const MessengerConfig = {
    conversationId: null,
    currentUserId: null,
    baseUrl: null,
    lastUpdate: 0,
    isTyping: false,
    typingTimeout: null,
    existingMessageIds: new Set(),
    listState: null,

    // Polling adaptatif
    POLL_FAST: 600,      // ms quand l'utilisateur est actif
    POLL_SLOW: 1500,     // ms quand inactif
    TYPING_POLL: 1000,   // ms pour le polling du statut "en train d'écrire"
    LIST_POLL: 3000,     // ms pour la liste des conversations
    lastActivity: 0,     // timestamp de la dernière activité
    ACTIVITY_WINDOW: 30000, // 30s considéré "actif"
    pollingTimer: null,
    typingTimer: null,
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

// ==============================================
// MODE CHAT (Conversation individuelle)
// ==============================================

function initChatMode(container) {
    MessengerConfig.conversationId = container.dataset.conversationId;
    MessengerConfig.currentUserId = container.dataset.userId;
    MessengerConfig.baseUrl = container.dataset.baseUrl;
    MessengerConfig.lastUpdate = parseInt(container.dataset.lastUpdate) || 0;

    if (!MessengerConfig.conversationId || !MessengerConfig.currentUserId || !MessengerConfig.baseUrl) {
        console.error('Configuration manquante');
        return;
    }

    collectExistingMessageIds();
    setupMessageForm();
    setupTypingDetection();
    setupAttachmentHandling();
    scrollToBottom(false); // Scroll instantané au chargement

    // Démarrer le polling adaptatif
    scheduleNextPoll();
    scheduleTypingPoll();

    // Marquer l'activité lors des interactions
    document.addEventListener('click', markActivity);
    document.addEventListener('keydown', markActivity);
}

function markActivity() {
    MessengerConfig.lastActivity = Date.now();
}

function isUserActive() {
    return (Date.now() - MessengerConfig.lastActivity) < MessengerConfig.ACTIVITY_WINDOW;
}

function collectExistingMessageIds() {
    document.querySelectorAll('.message-row[data-message-id]').forEach(row => {
        const id = parseInt(row.dataset.messageId);
        if (id) MessengerConfig.existingMessageIds.add(id);
    });
}

// ==============================================
// POLLING ADAPTATIF
// ==============================================

function scheduleNextPoll() {
    const delay = isUserActive() ? MessengerConfig.POLL_FAST : MessengerConfig.POLL_SLOW;
    clearTimeout(MessengerConfig.pollingTimer);
    MessengerConfig.pollingTimer = setTimeout(async () => {
        await checkNewMessages();
        scheduleNextPoll();
    }, delay);
}

function scheduleTypingPoll() {
    MessengerConfig.typingTimer = setTimeout(async () => {
        await checkTypingStatus();
        scheduleTypingPoll();
    }, MessengerConfig.TYPING_POLL);
}

// ==============================================
// GESTION DES PIÈCES JOINTES
// ==============================================

function setupAttachmentHandling() {
    const attachmentBtn = document.getElementById('attachment-btn');
    const attachmentInput = document.getElementById('message_attachment');
    const attachmentPreview = document.getElementById('attachment-preview');
    const attachmentName = document.getElementById('attachment-name');

    if (!attachmentBtn || !attachmentInput) return;

    attachmentBtn.addEventListener('click', function (e) {
        e.preventDefault();
        markActivity();
        attachmentInput.click();
    });

    attachmentInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            if (attachmentName) attachmentName.textContent = file.name;
            if (attachmentPreview) {
                attachmentPreview.classList.remove('d-none');
                attachmentPreview.classList.add('d-flex');
            }
        } else {
            clearAttachment();
        }
    });

    window.clearAttachment = function () {
        if (attachmentInput) attachmentInput.value = '';
        if (attachmentPreview) {
            attachmentPreview.classList.add('d-none');
            attachmentPreview.classList.remove('d-flex');
        }
    };
}

// ==============================================
// MODE LISTE
// ==============================================

function initListMode(table) {
    const baseUrl = table.dataset.baseUrl;
    const listUrl = table.dataset.listUrl;
    if (!baseUrl || !listUrl) return;

    MessengerConfig.listState = {
        table,
        baseUrl,
        listUrl,
        viewerRole: table.dataset.viewerRole || 'admin',
        search: table.dataset.search || '',
        sortBy: table.dataset.sortBy || 'updatedAt',
        sortOrder: table.dataset.sortOrder || 'DESC',
        emptyEl: document.getElementById('conversations-empty'),
        countEl: document.querySelector('[data-conversation-count]'),
        debounceTimer: null
    };

    setupListFilters(MessengerConfig.listState);
    pollGlobalStatus(baseUrl);
    setInterval(() => pollGlobalStatus(baseUrl), MessengerConfig.LIST_POLL);
}

function setupListFilters(state) {
    const searchInput = document.querySelector('[data-conversation-search]');
    if (searchInput) {
        searchInput.value = state.search;
        searchInput.addEventListener('input', () => {
            clearTimeout(state.debounceTimer);
            state.debounceTimer = setTimeout(() => {
                state.search = searchInput.value.trim();
                fetchConversationList(state);
            }, 300);
        });

        const form = searchInput.closest('form');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                state.search = searchInput.value.trim();
                fetchConversationList(state);
            });
        }
    }

    const sortBySelect = document.querySelector('[data-conversation-sortby]');
    const sortOrderSelect = document.querySelector('[data-conversation-sortorder]');
    if (sortBySelect) {
        sortBySelect.value = state.sortBy;
        sortBySelect.addEventListener('change', () => {
            state.sortBy = sortBySelect.value;
            fetchConversationList(state);
        });
    }
    if (sortOrderSelect) {
        sortOrderSelect.value = state.sortOrder;
        sortOrderSelect.addEventListener('change', () => {
            state.sortOrder = sortOrderSelect.value;
            fetchConversationList(state);
        });
    }

    const sortLinks = document.querySelectorAll('[data-conversation-sort]');
    if (sortLinks.length > 0) {
        updateSortLinks(sortLinks, state);
        sortLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const sortBy = link.dataset.sortBy || 'updatedAt';
                const sortOrder = link.dataset.sortOrder || 'DESC';
                state.sortBy = sortBy;
                state.sortOrder = sortOrder;
                updateSortLinks(sortLinks, state);
                fetchConversationList(state);
            });
        });
    }
}

function updateSortLinks(links, state) {
    links.forEach(link => {
        const isActive = link.dataset.sortBy === state.sortBy && link.dataset.sortOrder === state.sortOrder;
        if (isActive) link.classList.add('active');
        else link.classList.remove('active');
    });
}

async function fetchConversationList(state) {
    const params = new URLSearchParams();
    if (state.search) params.set('search', state.search);
    if (state.sortBy) params.set('sortBy', state.sortBy);
    if (state.sortOrder) params.set('sortOrder', state.sortOrder);
    params.set('ajax', '1');

    try {
        const response = await fetch(`${state.listUrl}?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        renderConversationTable(state, data.conversations || []);
        if (typeof data.total !== 'undefined' && state.countEl) {
            state.countEl.textContent = data.total;
        }
        updateListUrl(state);
    } catch (e) { }
}

function updateListUrl(state) {
    try {
        const url = new URL(window.location.href);
        if (state.search) url.searchParams.set('search', state.search);
        else url.searchParams.delete('search');
        if (state.sortBy) url.searchParams.set('sortBy', state.sortBy);
        else url.searchParams.delete('sortBy');
        if (state.sortOrder) url.searchParams.set('sortOrder', state.sortOrder);
        else url.searchParams.delete('sortOrder');
        window.history.replaceState({}, '', url);
    } catch (e) { }
}

function renderConversationTable(state, conversations) {
    const tbody = state.table.querySelector('tbody');
    if (!tbody) return;
    if (!conversations || conversations.length === 0) {
        tbody.innerHTML = '';
        state.table.classList.add('d-none');
        if (state.emptyEl) state.emptyEl.classList.remove('d-none');
        return;
    }

    state.table.classList.remove('d-none');
    if (state.emptyEl) state.emptyEl.classList.add('d-none');

    if (state.viewerRole === 'admin') {
        tbody.innerHTML = conversations.map(conv => {
            const lastMessageHtml = conv.has_message
                ? `${escapeHtml(conv.last_message || '')}`
                : `<em>Aucun message</em>`;
            const statusHtml = `<span class="badge ${conv.status_class} status-badge">${escapeHtml(conv.status_label || '')}</span>`;
            return `
                <tr class="conversation-row" data-conversation-id="${conv.id}">
                    <td>
                        ${escapeHtml(conv.user_name || '')}
                        <br>
                        <small class="text-muted">#${escapeHtml(String(conv.user_id || ''))}</small>
                    </td>
                    <td class="last-message-cell">${lastMessageHtml}</td>
                    <td class="status-cell">${statusHtml}</td>
                    <td class="date-cell">${escapeHtml(conv.date || '')}</td>
                    <td>
                        <a href="${conv.show_url}" class="btn btn-sm btn-info text-white">Voir</a>
                        <a href="${conv.delete_url}" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ?')">Supprimer</a>
                    </td>
                </tr>
            `;
        }).join('');
    } else {
        tbody.innerHTML = conversations.map(conv => {
            const unreadClass = conv.unread_count > 0 ? 'table-active fw-bold' : '';
            const lastMessageHtml = conv.has_message
                ? `<span class="text-secondary">${escapeHtml(conv.last_message || '')}</span>`
                : `<em class="text-muted small">Aucun message</em>`;
            const initial = (conv.medecin_name || 'M').charAt(0).toUpperCase();
            return `
                <tr class="conversation-row ${unreadClass}" data-conversation-id="${conv.id}" style="cursor: pointer;" onclick="window.location='${conv.show_url}'">
                    <td class="ps-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle me-3 bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 40px; height: 40px;">
                                ${escapeHtml(initial)}
                            </div>
                            <div>
                                <span class="d-block text-dark fw-bold">${escapeHtml(conv.medecin_name || '')}</span>
                                <small class="text-muted">${escapeHtml(conv.type || 'Général')}</small>
                            </div>
                        </div>
                    </td>
                    <td>${escapeHtml(conv.sujet || 'Aucun sujet')}</td>
                    <td class="last-message-cell">
                        <div class="text-truncate" style="max-width: 180px;">
                            ${lastMessageHtml}
                        </div>
                    </td>
                    <td class="date-cell text-nowrap text-muted small">${escapeHtml(conv.date || '')}</td>
                    <td class="text-end pe-3">
                        <a href="${conv.show_url}" class="btn btn-sm btn-light text-primary border rounded-pill px-3">Voir</a>
                    </td>
                </tr>
            `;
        }).join('');
    }
}

async function pollGlobalStatus(baseUrl) {
    try {
        const response = await fetch(`${baseUrl}/check-global-status?_=${Date.now()}`);
        if (!response.ok) return;
        const data = await response.json();
        if (data.conversations && Array.isArray(data.conversations)) {
            updateConversationList(data.conversations);
        }
    } catch (e) { /* Silencieux */ }
}

function updateConversationList(conversations) {
    conversations.forEach(conv => {
        const row = document.querySelector(`tr[data-conversation-id="${conv.id}"]`);
        if (!row) return;

        let hasChanges = false;

        const msgCell = row.querySelector('.last-message-cell');
        if (msgCell) {
            const truncateDiv = msgCell.querySelector('.text-truncate');
            const current = truncateDiv ? truncateDiv.textContent.trim() : msgCell.textContent.trim();
            if (current !== conv.last_message.trim()) {
                if (truncateDiv) truncateDiv.textContent = conv.last_message.trim();
                else msgCell.innerHTML = conv.last_message.trim();
                hasChanges = true;
            }
        }

        const dateCell = row.querySelector('.date-cell');
        if (dateCell && conv.date && dateCell.textContent.trim() !== conv.date) {
            dateCell.textContent = conv.date;
            hasChanges = true;
        }

        const statusCell = row.querySelector('.status-cell');
        if (statusCell && conv.status_label && statusCell.textContent.trim() !== conv.status_label) {
            statusCell.innerHTML = `<span class="badge ${conv.status_class} status-badge rounded-pill">${conv.status_label}</span>`;
            hasChanges = true;
        }

        if (typeof conv.unread_count !== 'undefined') {
            const isBold = row.classList.contains('fw-bold');
            if (conv.unread_count > 0 && !isBold) {
                row.classList.add('fw-bold', 'bg-light');
                hasChanges = true;
            } else if (conv.unread_count === 0 && isBold) {
                row.classList.remove('fw-bold', 'bg-light');
                hasChanges = true;
            }
        }

        if (hasChanges) {
            row.classList.add('table-warning');
            setTimeout(() => row.classList.remove('table-warning'), 1500);
        }
    });
}

// ==============================================
// VÉRIFICATION DES NOUVEAUX MESSAGES
// ==============================================

async function checkNewMessages() {
    try {
        const url = `${MessengerConfig.baseUrl}/check-new-messages/${MessengerConfig.conversationId}?last_update=${MessengerConfig.lastUpdate}&_=${Date.now()}`;
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;

        const data = await response.json();

        if (data.success && data.full_refresh && data.messages) {
            const receivedIds = new Set(data.messages.map(m => m.id));

            // Supprimer les messages supprimés
            document.querySelectorAll('.message-row[data-message-id]').forEach(row => {
                const id = parseInt(row.dataset.messageId);
                if (!receivedIds.has(id)) {
                    row.remove();
                    MessengerConfig.existingMessageIds.delete(id);
                }
            });

            // Ajouter ou mettre à jour
            data.messages.forEach(msg => {
                if (MessengerConfig.existingMessageIds.has(msg.id)) {
                    updateExistingMessage(msg);
                } else {
                    addNewMessage(msg, true); // true = scroll smooth pour les messages entrants
                }
            });

            MessengerConfig.lastUpdate = data.last_update;
        }
    } catch (e) { /* Silencieux */ }
}

function updateExistingMessage(msg) {
    const row = document.querySelector(`.message-row[data-message-id="${msg.id}"]`);
    if (!row) return;

    const contentDiv = row.querySelector('.message-text');
    if (contentDiv) {
        const newContent = escapeHtml(msg.content || '').replace(/\n/g, '<br>');
        if (contentDiv.innerHTML.trim() !== newContent.trim()) {
            contentDiv.innerHTML = newContent;
            const bubble = row.querySelector('.message-bubble');
            if (bubble) {
                bubble.style.transition = 'background-color 0.3s';
                const orig = window.getComputedStyle(bubble).backgroundColor;
                bubble.style.backgroundColor = '#fff3cd';
                setTimeout(() => { bubble.style.backgroundColor = orig; }, 1000);
            }
        }
    }

    // Mettre à jour l'attachment si nécessaire
    const bubble = row.querySelector('.message-bubble');
    if (msg.attachment) {
        const existingAttach = row.querySelector('.message-attachment');
        if (!existingAttach && bubble) {
            const attachDiv = document.createElement('div');
            attachDiv.className = 'message-attachment mb-2';
            attachDiv.innerHTML = createAttachmentHtml(msg.attachment);
            bubble.prepend(attachDiv);
        }
    }

    // Mettre à jour l'analyse OCR si nécessaire
    const container = document.getElementById('messages-container');
    const viewerRole = container ? container.dataset.viewerRole : null;
    const isSenderAdmin = msg.sender_role === 'ROLE_ADMIN';
    if (msg.ai_analysis && viewerRole === 'admin' && !isSenderAdmin) {
        const existingOcr = row.querySelector('.ocr-analysis-panel');
        if (!existingOcr && bubble) {
            // Un peu de redondance temporaire pour l'utiliser comme le createMessageHtml
            const analysis = msg.ai_analysis;
            let generatedOcrHtml = '';

            if (analysis.success) {
                const summary = analysis.summary || {};
                const keyInfo = summary.key_information || {};

                let textHtml = '';
                if (!summary.is_empty) {
                    const fullText = escapeHtml(summary.full_text || analysis.text || '');
                    textHtml = `<div class="mt-2"><button class="btn btn-sm w-100 d-flex align-items-center justify-content-between" style="background:#ede9fe; color:#5b21b6; border:1px solid #c4b5fd; border-radius:6px; font-size:0.8rem;" type="button" data-bs-toggle="collapse" data-bs-target="#ocr-text-js-upd-${msg.id}" aria-expanded="false"><span><i class="bi bi-file-text me-1"></i>Texte extrait</span><i class="bi bi-chevron-down"></i></button><div class="collapse mt-1" id="ocr-text-js-upd-${msg.id}"><pre class="m-0 p-2" style="background:#faf5ff; border:1px solid #ddd6fe; border-radius:6px; font-size:0.78rem; max-height:250px; overflow-y:auto; white-space:pre-wrap; word-break:break-word; color:#3b0764; font-family: 'Courier New', monospace;">${fullText}</pre></div></div>`;
                } else {
                    textHtml = `<div class="text-muted fst-italic" style="font-size:0.78rem;"><i class="bi bi-exclamation-triangle me-1"></i> Aucun texte lisible extrait</div>`;
                }

                generatedOcrHtml = `<div class="ocr-analysis-panel mt-3 no-print" style="background: linear-gradient(135deg, #f8f9ff 0%, #eef2ff 100%); border: 1px solid #c7d2fe; border-left: 4px solid #6366f1; border-radius: 10px; padding: 14px 16px; font-size: 0.82rem; color: #1e1b4b; max-width: 420px;"><div class="d-flex align-items-center justify-content-between mb-2"><div class="d-flex align-items-center gap-2 fw-bold" style="font-size:0.88rem; color:#4338ca;"><i class="bi bi-file-earmark-text-fill"></i> Analyse OCR automatique</div><span class="badge" style="background:#e0e7ff; color:#4338ca; font-size:0.7rem;">Patient</span></div><div class="mb-2 d-flex align-items-center gap-2"><i class="bi bi-tag-fill text-indigo" style="color:#6366f1;"></i><span class="fw-semibold">${escapeHtml(summary.document_type || 'Document général')}</span></div>${textHtml}</div>`;
            }

            if (generatedOcrHtml) {
                bubble.insertAdjacentHTML('beforeend', generatedOcrHtml);
            }
        }
    }
}

function addNewMessage(msg, smooth = false) {
    const container = document.getElementById('messages-container');
    if (!container) return;

    const messageHtml = createMessageHtml(msg);
    const typingIndicator = document.getElementById('typing-indicator');

    if (typingIndicator && typingIndicator.parentElement === container) {
        typingIndicator.insertAdjacentHTML('beforebegin', messageHtml);
    } else {
        container.insertAdjacentHTML('beforeend', messageHtml);
    }

    MessengerConfig.existingMessageIds.add(msg.id);
    scrollToBottom(smooth);
}

// ==============================================
// TYPING STATUS
// ==============================================

async function checkTypingStatus() {
    try {
        const url = `${MessengerConfig.baseUrl}/check-typing/${MessengerConfig.conversationId}?_=${Date.now()}`;
        const response = await fetch(url);
        if (!response.ok) return;
        const data = await response.json();
        updateTypingUI(data.typing);
    } catch (e) { /* Silencieux */ }
}

function updateTypingUI(isOtherTyping) {
    const indicator = document.getElementById('typing-indicator');
    if (!indicator) return;
    const isVisible = indicator.style.display !== 'none';
    if (isOtherTyping && !isVisible) {
        indicator.style.display = 'block';
        scrollToBottom(true);
    } else if (!isOtherTyping && isVisible) {
        indicator.style.display = 'none';
    }
}

// ==============================================
// SCROLL
// ==============================================

/**
 * @param {boolean} smooth - true = animé (messages entrants), false = instantané (envoi, chargement)
 */
function scrollToBottom(smooth = false) {
    const container = document.getElementById('messages-container');
    if (!container) return;
    container.scrollTo({
        top: container.scrollHeight,
        behavior: smooth ? 'smooth' : 'instant'
    });
}

// ==============================================
// FORMULAIRE D'ENVOI
// ==============================================

function setupTypingDetection() {
    const input = document.getElementById('message_content');
    if (!input) return;

    input.addEventListener('input', () => {
        markActivity();
        if (!MessengerConfig.isTyping) {
            MessengerConfig.isTyping = true;
            sendTypingStatus(true);
        }
        clearTimeout(MessengerConfig.typingTimeout);
        MessengerConfig.typingTimeout = setTimeout(() => {
            MessengerConfig.isTyping = false;
            sendTypingStatus(false);
        }, 1500);
    });
}

async function sendTypingStatus(isTyping) {
    try {
        const body = new URLSearchParams({
            conversation_id: MessengerConfig.conversationId,
            is_typing: isTyping ? 'true' : 'false'
        });
        await fetch(`${MessengerConfig.baseUrl}/typing-status`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
    } catch (e) { /* Silencieux */ }
}

function setupMessageForm() {
    const form = document.getElementById('message-form');
    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        markActivity();

        const input = document.getElementById('message_content');
        const content = input ? input.value.trim() : '';
        const attachmentInput = document.getElementById('message_attachment');
        const hasAttachment = attachmentInput && attachmentInput.files && attachmentInput.files.length > 0;

        if (!content && !hasAttachment) return;

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
                    // Vider le formulaire immédiatement
                    if (input) {
                        input.value = '';
                        input.style.height = 'auto';
                    }
                    clearAttachment();

                    MessengerConfig.isTyping = false;
                    sendTypingStatus(false);

                    // Afficher le message instantanément (scroll instant = pas d'animation)
                    addNewMessage(data.message, false);

                    // Mettre à jour lastUpdate si fourni
                    if (data.last_update) {
                        MessengerConfig.lastUpdate = data.last_update;
                    }
                }
            } else {
                console.error('Erreur serveur:', response.status);
            }
        } catch (err) {
            console.error('Erreur envoi:', err);
        } finally {
            if (submitBtn) submitBtn.disabled = false;
            if (input) input.focus();
        }
    });
}

// ==============================================
// GÉNÉRATEURS HTML
// ==============================================

function createAttachmentHtml(attachment) {
    if (!attachment) return '';
    const ext = attachment.split('.').pop().toLowerCase();
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
    const fileUrl = `/uploads/attachments/${attachment}`;

    if (isImage) {
        return `
            <div class="position-relative d-inline-block">
                <a href="${fileUrl}" target="_blank" title="Ouvrir l'image">
                    <img src="${fileUrl}" alt="Pièce jointe" class="img-fluid rounded border" style="max-width: 250px; max-height: 250px; object-fit: cover;">
                </a>
                <a href="${fileUrl}" download class="btn btn-sm btn-light position-absolute bottom-0 end-0 m-1 opacity-75" title="Télécharger">
                    <i class="bi bi-download"></i>
                </a>
            </div>`;
    } else {
        return `
            <a href="${fileUrl}" target="_blank" class="btn btn-light border d-flex align-items-center gap-2 text-decoration-none">
                <i class="bi bi-file-earmark-text fs-4 text-primary"></i>
                <div class="text-start">
                    <div class="small fw-bold text-truncate" style="max-width: 150px;">${escapeHtml(attachment)}</div>
                    <div class="tiny text-muted">Cliquer pour télécharger</div>
                </div>
                <i class="bi bi-download ms-auto text-secondary"></i>
            </a>`;
    }
}

function createMessageHtml(message) {
    const isMine = String(message.sender_id) === String(MessengerConfig.currentUserId);
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
            <a href="${MessengerConfig.baseUrl}/message/${message.id}/delete" class="text-danger text-decoration-none" title="Supprimer" onclick="return confirm('Supprimer ce message ?')">
                <i class="bi bi-trash"></i>
            </a>
        </div>` : '';

    const container = document.getElementById('messages-container');
    const viewerRole = container ? container.dataset.viewerRole : null;
    const isSenderAdmin = message.sender_role === 'ROLE_ADMIN';
    let aiAnalysisHtml = '';

    if (message.ai_analysis && viewerRole === 'admin' && !isSenderAdmin) {
        const analysis = message.ai_analysis;
        if (analysis.success) {
            const summary = analysis.summary || {};
            const keyInfo = summary.key_information || {};

            let datesHtml = '';
            if (keyInfo.dates && keyInfo.dates.length > 0) {
                datesHtml = `<div class="mb-2">
                    <span class="badge bg-warning text-dark me-1"><i class="bi bi-calendar3 me-1"></i>Dates</span>
                    ${keyInfo.dates.map(date => `<code style="background:#fef3c7; color:#92400e; padding:1px 5px; border-radius:4px; margin:1px;">${escapeHtml(date)}</code>`).join('')}
                </div>`;
            }

            let medsHtml = '';
            if (keyInfo.medications && keyInfo.medications.length > 0) {
                medsHtml = `<div class="mb-2">
                    <span class="badge bg-danger text-white me-1"><i class="bi bi-capsule me-1"></i>Médicaments / Termes</span>
                    <div class="mt-1" style="line-height:1.8;">
                    ${keyInfo.medications.slice(0, 12).map(med => `<code style="background:#fee2e2; color:#991b1b; padding:1px 6px; border-radius:4px; margin:1px 2px; display:inline-block;">${escapeHtml(med)}</code>`).join('')}
                    </div>
                </div>`;
            }

            let valuesHtml = '';
            if (keyInfo.values && keyInfo.values.length > 0) {
                valuesHtml = `<div class="mb-2">
                    <span class="badge bg-success text-white me-1"><i class="bi bi-graph-up me-1"></i>Valeurs médicales</span>
                    <div class="mt-1">
                    ${keyInfo.values.map(val => `<code style="background:#dcfce7; color:#166534; padding:1px 6px; border-radius:4px; margin:1px 2px; display:inline-block;">${escapeHtml(val)}</code>`).join('')}
                    </div>
                </div>`;
            }

            let doctorsHtml = '';
            if (keyInfo.doctors && keyInfo.doctors.length > 0) {
                doctorsHtml = `<div class="mb-2">
                    <span class="badge bg-info text-white me-1"><i class="bi bi-person-badge me-1"></i>Médecins</span>
                    ${keyInfo.doctors.map(doc => `<span style="background:#e0f2fe; color:#075985; padding:1px 7px; border-radius:10px; margin:1px; display:inline-block;">${escapeHtml(doc)}</span>`).join('')}
                </div>`;
            }

            let textHtml = '';
            if (!summary.is_empty) {
                const fullText = escapeHtml(summary.full_text || analysis.text || '');
                textHtml = `<div class="mt-2">
                    <button class="btn btn-sm w-100 d-flex align-items-center justify-content-between"
                        style="background:#ede9fe; color:#5b21b6; border:1px solid #c4b5fd; border-radius:6px; font-size:0.8rem;"
                        type="button" data-bs-toggle="collapse" data-bs-target="#ocr-text-js-${message.id}" aria-expanded="false">
                        <span><i class="bi bi-file-text me-1"></i>Texte extrait</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="collapse mt-1" id="ocr-text-js-${message.id}">
                        <pre class="m-0 p-2" style="background:#faf5ff; border:1px solid #ddd6fe; border-radius:6px; font-size:0.78rem; max-height:250px; overflow-y:auto; white-space:pre-wrap; word-break:break-word; color:#3b0764; font-family: 'Courier New', monospace;">${fullText}</pre>
                    </div>
                </div>`;
            } else {
                textHtml = `<div class="text-muted fst-italic" style="font-size:0.78rem;">
                    <i class="bi bi-exclamation-triangle me-1"></i> Aucun texte lisible extrait (image floue ou document non textuel)
                </div>`;
            }

            aiAnalysisHtml = `<div class="ocr-analysis-panel mt-3 no-print" style="background: linear-gradient(135deg, #f8f9ff 0%, #eef2ff 100%); border: 1px solid #c7d2fe; border-left: 4px solid #6366f1; border-radius: 10px; padding: 14px 16px; font-size: 0.82rem; color: #1e1b4b; max-width: 420px;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2 fw-bold" style="font-size:0.88rem; color:#4338ca;">
                        <i class="bi bi-file-earmark-text-fill"></i> Analyse OCR automatique
                    </div>
                    <span class="badge" style="background:#e0e7ff; color:#4338ca; font-size:0.7rem;">Patient</span>
                </div>
                <div class="mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-tag-fill text-indigo" style="color:#6366f1;"></i>
                    <span class="fw-semibold">${escapeHtml(summary.document_type || 'Document général')}</span>
                </div>
                <div class="d-flex gap-3 mb-2" style="color:#6b7280; font-size:0.78rem;">
                    <span><i class="bi bi-fonts me-1"></i>${summary.word_count || 0} mots</span>
                    <span><i class="bi bi-list-ol me-1"></i>${summary.line_count || 0} lignes</span>
                    <span><i class="bi bi-clock me-1"></i>${escapeHtml(analysis.analyzed_at || '')}</span>
                </div>
                ${datesHtml}
                ${medsHtml}
                ${valuesHtml}
                ${doctorsHtml}
                ${textHtml}
            </div>`;
        } else {
            aiAnalysisHtml = `<div class="ocr-analysis-panel mt-3 no-print" style="background: linear-gradient(135deg, #f8f9ff 0%, #eef2ff 100%); border: 1px solid #c7d2fe; border-left: 4px solid #6366f1; border-radius: 10px; padding: 14px 16px; font-size: 0.82rem; color: #1e1b4b; max-width: 420px;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2 fw-bold" style="font-size:0.88rem; color:#4338ca;">
                        <i class="bi bi-file-earmark-text-fill"></i> Analyse OCR
                    </div>
                </div>
                <div class="text-muted fst-italic" style="font-size:0.78rem;">
                    <i class="bi bi-exclamation-circle me-1 text-warning"></i>
                    ${escapeHtml(analysis.message || 'Analyse non disponible')}
                </div>
            </div>`;
        }
    }

    const timestamp = message.created_at
        ? new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    const attachmentHtml = message.attachment
        ? `<div class="message-attachment mb-2">${createAttachmentHtml(message.attachment)}</div>`
        : '';

    const contentHtml = (message.content || '').trim()
        ? `<div class="message-text">${escapeHtml(message.content).replace(/\n/g, '<br>')}</div>`
        : '';

    return `
        <div class="message-row ${rowClass}" data-message-id="${message.id}">
            ${avatar}
            <div class="d-flex flex-column ${alignClass}" style="max-width: 100%;">
                <div class="message-bubble">
                    ${attachmentHtml}
                    ${contentHtml}
                    ${aiAnalysisHtml}
                </div>
                <div class="message-meta">
                    <span>${timestamp}</span>
                    ${actions}
                </div>
            </div>
        </div>`;
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));
}
