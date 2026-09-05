/**
 * Smart Nursery Management System - Advanced Live Chat Engine (WhatsApp / Messenger Style)
 * Real-time messaging with live polling, date grouping, audio chimes, optimistic updates & emoji tray.
 */

class SmartNurseryChat {
    constructor(config) {
        this.conversationId = config.conversationId;
        this.currentUserId = config.currentUserId;
        this.baseUrl = config.baseUrl;
        this.messagesContainer = document.getElementById('chat-messages-box');
        this.chatForm = document.getElementById('chat-send-form');
        this.messageInput = document.getElementById('chat-message-input');
        this.emojiToggleBtn = document.getElementById('chat-emoji-toggle');
        this.emojiPickerTray = document.getElementById('chat-emoji-picker');
        this.lastMessageId = 0;
        this.lastDateLabel = '';
        this.pollInterval = null;
        this.audioCtx = null;

        this.init();
    }

    init() {
        if (!this.messagesContainer || !this.chatForm) return;

        // Form Submit Handler
        this.chatForm.addEventListener('submit', (e) => this.handleSendMessage(e));

        // Keyboard handler for Enter to send (Shift+Enter for new line)
        if (this.messageInput) {
            this.messageInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.chatForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
            });
        }

        // Setup Conversation Search Filter
        this.setupConversationSearch();

        // Initial Load and Polling (every 2.5 seconds)
        this.fetchMessages(true);
        this.pollInterval = setInterval(() => this.fetchMessages(false), 2500);

        // Auto scroll to bottom
        this.scrollToBottom();
    }

    setupEmojiPicker() {
        // Handled via robust global event delegation
    }

    setupQuickChips() {
        // Handled via robust global event delegation
    }


    setupConversationSearch() {
        const searchInput = document.getElementById('chat-conv-search');
        if (!searchInput) return;

        searchInput.addEventListener('input', () => {
            const q = searchInput.value.toLowerCase().trim();
            document.querySelectorAll('.conv-list-item').forEach(item => {
                const title = (item.getAttribute('data-title') || '').toLowerCase();
                const preview = (item.getAttribute('data-preview') || '').toLowerCase();
                if (title.includes(q) || preview.includes(q)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    async fetchMessages(isFirstLoad = false) {
        try {
            const url = `${this.baseUrl}/ajax_handler.php?action=get_messages&conversation_id=${this.conversationId}&last_id=${this.lastMessageId}`;
            const res = await fetch(url);
            if (!res.ok) return;

            const data = await res.json();
            if (data.success && data.messages && data.messages.length > 0) {
                let hasNewIncoming = false;

                data.messages.forEach(msg => {
                    if (this.appendMessage(msg)) {
                        if (!isFirstLoad && parseInt(msg.sender_id) !== parseInt(this.currentUserId)) {
                            hasNewIncoming = true;
                        }
                    }
                    if (msg.id > this.lastMessageId) {
                        this.lastMessageId = msg.id;
                    }
                });

                this.scrollToBottom();

                // Play soft chime sound on new incoming message
                if (hasNewIncoming) {
                    this.playAudioChime('receive');
                }
            }
        } catch (err) {
            // Silently handle polling hiccups
        }
    }

    async handleSendMessage(e) {
        e.preventDefault();
        const text = this.messageInput.value.trim();
        if (!text) return;

        // Clear input immediately for snappy responsive feel
        this.messageInput.value = '';
        if (this.emojiPickerTray) this.emojiPickerTray.classList.add('hidden');

        // Optimistic UI append
        const tempId = 'temp-' + Date.now();
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        const tempMsg = {
            id: tempId,
            sender_id: this.currentUserId,
            sender_name: 'You',
            message_text: text,
            is_read: 0,
            date_label: 'Today',
            time_formatted: timeStr,
            is_temp: true
        };

        this.appendMessage(tempMsg);
        this.scrollToBottom();
        this.playAudioChime('send');

        try {
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('conversation_id', this.conversationId);
            formData.append('message', text);

            const res = await fetch(`${this.baseUrl}/ajax_handler.php`, {
                method: 'POST',
                body: formData
            });

            const data = await res.json();
            if (data.success && data.message) {
                // Replace temp message element with confirmed ID
                const tempEl = document.getElementById(`msg-${tempId}`);
                if (tempEl) {
                    tempEl.id = `msg-${data.message.id}`;
                    const checkmarkSpan = tempEl.querySelector('.wa-checkmarks');
                    if (checkmarkSpan) {
                        checkmarkSpan.innerHTML = '<i class="fa-solid fa-check-double text-emerald-200"></i>';
                    }
                }
                if (data.message.id > this.lastMessageId) {
                    this.lastMessageId = data.message.id;
                }
            } else if (data.error) {
                alert('Failed to send message: ' + data.error);
            }
        } catch (err) {
            console.warn('Network error while sending message.');
        }
    }

    appendMessage(msg) {
        // Prevent duplicate rendering
        if (document.getElementById(`msg-${msg.id}`)) return false;

        const isMe = parseInt(msg.sender_id) === parseInt(this.currentUserId);
        const dateLabel = msg.date_label || 'Today';

        // Check if date grouping pill is needed
        if (dateLabel !== this.lastDateLabel) {
            this.lastDateLabel = dateLabel;
            const datePill = document.createElement('div');
            datePill.className = 'flex justify-center my-3';
            datePill.innerHTML = `
                <span class="px-3.5 py-1 rounded-full text-[10px] font-bold tracking-wider uppercase bg-white/80 dark:bg-slate-800/90 text-slate-500 dark:text-slate-400 shadow-xs border border-slate-200/60 dark:border-slate-700/60 backdrop-blur-xs">
                    ${this.escape(dateLabel)}
                </span>
            `;
            this.messagesContainer.appendChild(datePill);
        }

        const msgDiv = document.createElement('div');
        msgDiv.id = `msg-${msg.id}`;
        msgDiv.className = `flex flex-col ${isMe ? 'items-end' : 'items-start'} mb-2.5 animate-msg-pop`;

        const bubbleClass = isMe
            ? 'wa-bubble-out max-w-[80%] sm:max-w-[70%] px-4 py-2.5 text-white'
            : 'wa-bubble-in max-w-[80%] sm:max-w-[70%] px-4 py-2.5 text-slate-800';

        const checkmarkHtml = isMe
            ? (msg.is_read
                ? '<i class="fa-solid fa-check-double text-emerald-300"></i>'
                : '<i class="fa-solid fa-check-double text-emerald-200/80"></i>')
            : '';

        msgDiv.innerHTML = `
            <div class="${bubbleClass}">
                ${!isMe && msg.sender_name ? `<span class="text-[11px] font-bold text-emerald-700 block mb-0.5">${this.escape(msg.sender_name)}</span>` : ''}
                <p class="text-xs sm:text-sm break-words leading-relaxed whitespace-pre-wrap">${this.escape(msg.message_text)}</p>
                <div class="flex items-center justify-end space-x-1.5 mt-1 text-[10px] ${isMe ? 'text-emerald-100/90' : 'text-slate-400'}">
                    <span>${msg.time_formatted || 'just now'}</span>
                    <span class="wa-checkmarks">${checkmarkHtml}</span>
                </div>
            </div>
        `;

        this.messagesContainer.appendChild(msgDiv);
        return true;
    }

    playAudioChime(type = 'send') {
        try {
            if (!window.AudioContext && !window.webkitAudioContext) return;
            if (!this.audioCtx) {
                this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }

            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.connect(gain);
            gain.connect(this.audioCtx.destination);

            const now = this.audioCtx.currentTime;
            if (type === 'send') {
                osc.frequency.setValueAtTime(580, now);
                osc.frequency.exponentialRampToValueAtTime(880, now + 0.08);
                gain.gain.setValueAtTime(0.08, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.12);
                osc.start(now);
                osc.stop(now + 0.12);
            } else {
                osc.frequency.setValueAtTime(880, now);
                osc.frequency.exponentialRampToValueAtTime(660, now + 0.1);
                gain.gain.setValueAtTime(0.1, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.14);
                osc.start(now);
                osc.stop(now + 0.14);
            }
        } catch (e) {
            // Silently skip if audio context cannot start
        }
    }

    scrollToBottom() {
        if (this.messagesContainer) {
            this.messagesContainer.scrollTo({
                top: this.messagesContainer.scrollHeight,
                behavior: 'smooth'
            });
        }
    }

    escape(str) {
        if (!str) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return str.replace(/[&<>"']/g, m => map[m]);
    }

    destroy() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
    }
}

// --------------------------------------------------------------------------
// 🌐 Global Helper Functions for Direct 1-Click Emoji & Quick Replies
// --------------------------------------------------------------------------
window.toggleEmojiTray = function(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    const tray = document.getElementById('chat-emoji-picker');
    if (tray) {
        tray.classList.toggle('hidden');
    }
};

window.insertEmoji = function(emoji) {
    const input = document.getElementById('chat-message-input');
    if (input) {
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? input.value.length;
        const val = input.value;
        input.value = val.substring(0, start) + emoji + val.substring(end);
        input.focus();
        const newPos = start + emoji.length;
        if (input.setSelectionRange) {
            input.setSelectionRange(newPos, newPos);
        }
    }
};

window.setQuickReply = function(btn) {
    const input = document.getElementById('chat-message-input');
    if (input && btn) {
        const text = btn.getAttribute('data-text') || btn.textContent.trim();
        input.value = text;
        input.focus();
    }
};

// Global click-outside listener to auto-close emoji tray
document.addEventListener('click', (e) => {
    const tray = document.getElementById('chat-emoji-picker');
    const toggleBtn = document.getElementById('chat-emoji-toggle');
    if (tray && !tray.classList.contains('hidden')) {
        if (!tray.contains(e.target) && (!toggleBtn || !toggleBtn.contains(e.target))) {
            tray.classList.add('hidden');
        }
    }
});



