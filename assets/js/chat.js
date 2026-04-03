/**
 * SETE — chat.js
 * Complete chat functionality
 */

'use strict';

// ── Markdown parser ───────────────────────────────────────────────
function parseMarkdown(text) {
  if (!text) return '';
  let html = escHtml(text);

  // Code blocks
  html = html.replace(/```(\w*)\n?([\s\S]*?)```/g, (_, lang, code) => {
    return `<pre><code class="lang-${escHtml(lang)}">${code.trim()}</code></pre>`;
  });

  // Inline code
  html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>');

  // Bold+italic
  html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');

  // Bold
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');

  // Italic
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
  html = html.replace(/_(.+?)_/g, '<em>$1</em>');

  // URLs
  html = html.replace(/(https?:\/\/[^\s<>"]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');

  // Newlines
  html = html.replace(/\n/g, '<br>');

  return html;
}

// ── ChatManager ───────────────────────────────────────────────────
const ChatManager = {
  characters: [],
  activeCharacter: null,
  eventSource: null,
  pendingFile: null,
  emojiVisible: false,
  lastDateLabel: '',

  init() {
    this.bindInputBar();
    this.bindEmojiPicker();
    this.bindScrollButton();
    this.bindCharacterModal();
    this.loadCharacters();
  },

  // ── Load characters list ────────────────────────────────────────
  async loadCharacters() {
    try {
      const data = await apiGet('api/characters.php');
      this.characters = data.characters || [];
      this.renderContactList();
    } catch (e) {
      showToast('Erro ao carregar personagens.', 'error');
    }
  },

  // ── Render contact list ─────────────────────────────────────────
  renderContactList(filter = '') {
    const list = document.getElementById('contacts-list');
    if (!list) return;

    const filtered = filter
      ? this.characters.filter(c =>
          c.name.toLowerCase().includes(filter.toLowerCase())
        )
      : this.characters;

    list.innerHTML = '';

    if (!filtered.length) {
      list.innerHTML = `<div class="text-center text-muted" style="padding:24px;font-size:.9rem;">
        ${filter ? 'Nenhum resultado.' : 'Nenhum personagem criado ainda.'}
      </div>`;
      return;
    }

    filtered.forEach(char => {
      const item = document.createElement('div');
      item.className = 'contact-item' + (this.activeCharacter?.id === char.id ? ' active' : '');
      item.dataset.id = char.id;

      const initials = char.name.slice(0, 2).toUpperCase();
      const avatarHtml = char.avatar
        ? `<img src="${escHtml(char.avatar)}" alt="${escHtml(char.name)}">`
        : initials;

      const unread = parseInt(char.unread_count || 0);
      const unreadHtml = unread > 0
        ? `<span class="unread-badge">${unread > 99 ? '99+' : unread}</span>`
        : '';

      const lastMsg = char.last_message
        ? escHtml(char.last_message.substring(0, 40)) + (char.last_message.length > 40 ? '…' : '')
        : '<em style="opacity:.6">Sem mensagens</em>';

      item.innerHTML = `
        <div class="contact-avatar">${avatarHtml}</div>
        <div class="contact-info">
          <div class="contact-name">${escHtml(char.name)}</div>
          <div class="contact-last-msg">${lastMsg}</div>
        </div>
        <div class="contact-meta">
          <span class="contact-time">${formatTime(char.last_message_time)}</span>
          ${unreadHtml}
        </div>
        <div class="contact-item-actions">
          <button class="btn-edit-char btn btn-sm btn-outline" data-id="${char.id}" title="Editar"><i class="fa-solid fa-edit" style="color:#00BCD4"></i></button>
          <button class="btn-delete-char btn btn-sm" data-id="${char.id}" title="Excluir"><i class="fa-solid fa-trash-alt" style="color:#E91E63"></i></button>
        </div>
      `;

      item.addEventListener('click', (e) => {
        if (e.target.closest('.contact-item-actions')) return;
        this.openChat(char);
        closeModal('modal-contacts');
      });

      item.querySelector('.btn-edit-char')?.addEventListener('click', (e) => {
        e.stopPropagation();
        this.openCharacterModal(char);
      });

      item.querySelector('.btn-delete-char')?.addEventListener('click', (e) => {
        e.stopPropagation();
        this.deleteCharacter(char);
      });

      list.appendChild(item);
    });
  },

  // ── Open chat with character ────────────────────────────────────
  async openChat(char) {
    this.activeCharacter = char;

    const welcome  = document.getElementById('welcome-screen');
    const chatView = document.getElementById('chat-view');
    if (welcome)  welcome.style.display = 'none';
    if (chatView) chatView.style.display = 'flex';

    // Update header
    const initials   = char.name.slice(0, 2).toUpperCase();
    const headerAvatar = document.getElementById('chat-header-avatar');
    const headerName   = document.getElementById('chat-header-name');
    const headerStatus = document.getElementById('chat-header-status');

    if (headerAvatar) {
      headerAvatar.innerHTML = char.avatar
        ? `<img src="${escHtml(char.avatar)}" alt="${escHtml(char.name)}">`
        : initials;
    }
    if (headerName)   headerName.textContent = char.name;
    if (headerStatus) headerStatus.textContent = char.description || 'Personagem IA';

    // Load history
    await this.loadHistory(char.id);

    // Refresh contact list
    this.renderContactList();

    // Focus input
    const input = document.getElementById('message-input');
    if (input) input.focus();
  },

  // ── Load message history ────────────────────────────────────────
  async loadHistory(charId) {
    const container = document.getElementById('messages-container');
    if (!container) return;

    container.innerHTML = '<div class="text-center text-muted" style="padding:20px;"><div class="spinner"></div></div>';
    this.lastDateLabel = '';

    try {
      const data = await apiGet(`api/chat.php?action=history&character_id=${charId}`);
      container.innerHTML = '';

      const msgs = data.messages || [];
      if (!msgs.length) {
        container.innerHTML = `<div class="text-center text-muted" style="padding:32px;font-size:.9rem;">
          Nenhuma mensagem ainda. Diga olá! 👋
        </div>`;
        return;
      }

      msgs.forEach(msg => this.appendMessage(msg, false));
      this.scrollToBottom(true);

      // Update unread counts in characters list
      const char = this.characters.find(c => c.id == charId);
      if (char) char.unread_count = 0;

    } catch (e) {
      container.innerHTML = '<div class="text-center text-muted" style="padding:20px;">Erro ao carregar histórico.</div>';
    }
  },

  // ── Append message bubble ───────────────────────────────────────
  appendMessage(msg, shouldScroll = true) {
    const container = document.getElementById('messages-container');
    if (!container) return null;

    // Empty state removal
    const empty = container.querySelector('.text-muted');
    if (empty && container.children.length <= 1) container.innerHTML = '';

    // Date divider
    const dateLabel = formatDateLabel(msg.created_at);
    if (dateLabel && dateLabel !== this.lastDateLabel) {
      this.lastDateLabel = dateLabel;
      const divider = document.createElement('div');
      divider.className = 'msg-date-divider';
      divider.innerHTML = `<span class="msg-date-text">${escHtml(dateLabel)}</span>`;
      container.appendChild(divider);
    }

    const wrapper = document.createElement('div');
    wrapper.className = `message-wrapper ${msg.role}`;
    wrapper.dataset.msgId = msg.id || '';

    const char = this.activeCharacter;
    const initials = char ? char.name.slice(0, 2).toUpperCase() : 'IA';
    const avatarSrc = char?.avatar;

    const avatarHtml = msg.role === 'assistant'
      ? `<div class="msg-avatar">${avatarSrc ? `<img src="${escHtml(avatarSrc)}" alt="">` : initials}</div>`
      : '';

    let contentHtml = parseMarkdown(msg.content);

    // File attachment
    let fileHtml = '';
    if (msg.file_url) {
      const isImage = msg.file_type && msg.file_type.startsWith('image/');
      if (isImage) {
        fileHtml = `<img src="${escHtml(msg.file_url)}" alt="${escHtml(msg.file_name || 'imagem')}" class="file-preview" onclick="window.open('${escHtml(msg.file_url)}')">`;
      } else {
        fileHtml = `<div class="msg-file-link">📎 <span>${escHtml(msg.file_name || msg.file_url)}</span></div>`;
      }
    }

    const time = msg.created_at
      ? new Date(msg.created_at.replace(' ', 'T')).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
      : '';

    const audioBtn = msg.role === 'assistant' && char?.voice_enabled
      ? `<button class="msg-audio-btn" onclick="ChatManager.speakMessage(this)" data-text="${escHtml(msg.content)}">🔊 Ouvir</button>`
      : '';

    wrapper.innerHTML = `
      ${avatarHtml}
      <div class="msg-bubble">
        ${fileHtml}
        <div class="msg-content">${contentHtml}</div>
        ${audioBtn}
        <div class="msg-time">${escHtml(time)}</div>
      </div>
    `;

    container.appendChild(wrapper);

    if (shouldScroll) this.scrollToBottom();
    return wrapper;
  },

  // ── Streaming message ───────────────────────────────────────────
  startStreamMessage() {
    const container = document.getElementById('messages-container');
    if (!container) return null;

    const char    = this.activeCharacter;
    const initials = char ? char.name.slice(0, 2).toUpperCase() : 'IA';
    const avatarSrc = char?.avatar;

    const wrapper = document.createElement('div');
    wrapper.className = 'message-wrapper assistant streaming';

    wrapper.innerHTML = `
      <div class="msg-avatar">${avatarSrc ? `<img src="${escHtml(avatarSrc)}" alt="">` : initials}</div>
      <div class="msg-bubble">
        <div class="msg-content streaming-cursor"></div>
        <div class="msg-time"></div>
      </div>
    `;

    container.appendChild(wrapper);
    this.scrollToBottom();
    return wrapper.querySelector('.msg-content');
  },

  finalizeStreamMessage(contentEl, fullText) {
    if (!contentEl) return;
    const wrapper = contentEl.closest('.message-wrapper');
    if (wrapper) wrapper.classList.remove('streaming');
    contentEl.classList.remove('streaming-cursor');
    contentEl.innerHTML = parseMarkdown(fullText);

    const timeEl = wrapper?.querySelector('.msg-time');
    if (timeEl) {
      timeEl.textContent = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    const char = this.activeCharacter;
    if (char?.voice_enabled && char?.auto_audio) {
      AudioManager.speak(fullText, {
        type: char.voice_type,
        speed: char.voice_speed,
        pitch: char.voice_pitch,
      });
    }

    if (char?.voice_enabled) {
      const audioBtn = document.createElement('button');
      audioBtn.className = 'msg-audio-btn';
      audioBtn.dataset.text = fullText;
      audioBtn.textContent = '🔊 Ouvir';
      audioBtn.addEventListener('click', function() {
        ChatManager.speakMessage(this);
      });
      wrapper?.querySelector('.msg-bubble')?.insertBefore(audioBtn, timeEl);
    }
  },

  speakMessage(btn) {
    // If this button's audio is currently playing, toggle pause/resume
    if (AudioManager.currentBtn === btn && AudioManager.isSpeaking()) {
      if (AudioManager.isPaused()) {
        AudioManager.togglePause();
        btn.textContent = '⏸️ Pausar';
        btn.title = 'Pausar';
      } else {
        AudioManager.togglePause();
        btn.textContent = '▶️ Continuar';
        btn.title = 'Continuar';
      }
      return;
    }

    // Stop any previous audio and reset its button
    if (AudioManager.currentBtn && AudioManager.currentBtn !== btn) {
      AudioManager.currentBtn.textContent = '🔊 Ouvir';
      AudioManager.currentBtn.title = 'Ouvir';
    }
    AudioManager.stop();

    const text = btn.dataset.text;
    const char = this.activeCharacter;
    btn.textContent = '⏸️ Pausar';
    btn.title = 'Pausar';
    AudioManager.currentBtn = btn;

    AudioManager.speak(text, {
      type: char?.voice_type || 'feminina_adulta',
      speed: char?.voice_speed || 1.0,
      pitch: char?.voice_pitch || 1.0,
    }, () => {
      btn.textContent = '🔊 Ouvir';
      btn.title = 'Ouvir';
      AudioManager.currentBtn = null;
    });
  },

  // ── Send message ────────────────────────────────────────────────
  async sendMessage() {
    const input = document.getElementById('message-input');
    if (!input || !this.activeCharacter) return;

    const text = input.value.trim();
    if (!text && !this.pendingFile) return;

    if (this.eventSource) {
      showToast('Aguarde a resposta atual terminar.', 'warning');
      return;
    }

    const message = text || '(arquivo)';
    input.value = '';
    input.style.height = 'auto';

    // Append user message locally
    const userMsg = {
      role: 'user',
      content: message,
      created_at: new Date().toISOString().replace('T', ' ').split('.')[0],
      file_url: this.pendingFile?.url || null,
      file_name: this.pendingFile?.name || null,
      file_type: this.pendingFile?.mime || null,
    };
    this.appendMessage(userMsg);
    this.hidePendingFile();

    // Show typing
    this.showTyping();

    // Build SSE URL
    const params = new URLSearchParams({
      stream: '1',
      character_id: this.activeCharacter.id,
      message: message,
    });

    if (userMsg.file_url)  params.append('file_url', userMsg.file_url);
    if (userMsg.file_name) params.append('file_name', userMsg.file_name);
    if (userMsg.file_type) params.append('file_type', userMsg.file_type);

    const url = `api/chat.php?${params.toString()}`;
    let streamEl = null;
    let fullText = '';

    // Use fetch + ReadableStream for reliable SSE streaming
    this.eventSource = true; // mark as streaming in progress

    try {
      const response = await fetch(url, { credentials: 'same-origin' });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const reader  = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer    = '';

      this.hideTyping();
      streamEl = this.startStreamMessage();

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop(); // keep incomplete last line

        for (const line of lines) {
          const trimmed = line.trim();
          if (!trimmed || !trimmed.startsWith('data: ')) continue;

          const payload = trimmed.slice(6);
          if (payload === '[DONE]') {
            this.finalizeStreamMessage(streamEl, fullText);
            this.updateCharacterLastMessage(this.activeCharacter.id, fullText);
            this.eventSource = null;
            return;
          }

          try {
            const parsed = JSON.parse(payload);
            if (parsed.error) {
              showToast(parsed.error, 'error');
              this.eventSource = null;
              return;
            }
            if (parsed.content) {
              fullText += parsed.content;
              if (streamEl) {
                streamEl.innerHTML = parseMarkdown(fullText) + '<span class="streaming-cursor" style="display:inline-block"></span>';
                this.scrollToBottom();
              }
            }
          } catch (_) {}
        }
      }

      // Stream ended without [DONE]
      if (fullText) {
        this.finalizeStreamMessage(streamEl, fullText);
        this.updateCharacterLastMessage(this.activeCharacter.id, fullText);
      }
    } catch (e) {
      this.hideTyping();
      if (!fullText) {
        showToast('Erro na conexão com a IA.', 'error');
      } else if (streamEl) {
        this.finalizeStreamMessage(streamEl, fullText);
      }
    } finally {
      this.eventSource = null;
    }
  },

  updateCharacterLastMessage(charId, msg) {
    const char = this.characters.find(c => c.id == charId);
    if (char) {
      char.last_message = msg;
      char.last_message_time = new Date().toISOString();
    }
  },

  // ── Typing indicator ────────────────────────────────────────────
  showTyping() {
    const container = document.getElementById('messages-container');
    if (!container) return;

    // Remove any existing typing indicator
    const existing = container.querySelector('.typing-indicator-wrapper');
    if (existing) existing.remove();

    const char     = this.activeCharacter;
    const initials = char ? char.name.slice(0, 2).toUpperCase() : 'IA';
    const avatarSrc = char?.avatar;

    const wrapper = document.createElement('div');
    wrapper.className = 'message-wrapper assistant typing-indicator-wrapper';
    wrapper.innerHTML = `
      <div class="msg-avatar">${avatarSrc ? `<img src="${escHtml(avatarSrc)}" alt="">` : escHtml(initials)}</div>
      <div class="msg-bubble typing-bubble">
        <div class="typing-indicator">
          <span class="typing-text">digitando</span>
          <div class="dot"></div>
          <div class="dot"></div>
          <div class="dot"></div>
        </div>
      </div>
    `;
    container.appendChild(wrapper);
    this.scrollToBottom();
  },

  hideTyping() {
    const container = document.getElementById('messages-container');
    if (!container) return;
    const existing = container.querySelector('.typing-indicator-wrapper');
    if (existing) existing.remove();
  },

  // ── Scroll ──────────────────────────────────────────────────────
  scrollToBottom(instant = false) {
    const container = document.getElementById('messages-container');
    if (!container) return;
    if (instant) {
      container.scrollTop = container.scrollHeight;
    } else {
      requestAnimationFrame(() => {
        container.scrollTop = container.scrollHeight;
      });
    }
  },

  bindScrollButton() {
    const container = document.getElementById('messages-container');
    const btn       = document.getElementById('scroll-bottom-btn');
    if (!container || !btn) return;

    container.addEventListener('scroll', () => {
      const fromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
      btn.classList.toggle('visible', fromBottom > 200);
    });

    btn.addEventListener('click', () => this.scrollToBottom());
  },

  // ── Input bar ───────────────────────────────────────────────────
  bindInputBar() {
    const input   = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-btn');
    const fileBtn = document.getElementById('btn-attach');
    const voiceBtn = document.getElementById('btn-voice');
    const fileInput = document.getElementById('file-input');
    const emojiBtn  = document.getElementById('btn-emoji');
    const clearBtn  = document.getElementById('btn-clear-chat');

    if (input) {
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          this.sendMessage();
        }
      });

      input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
      });
    }

    if (sendBtn) sendBtn.addEventListener('click', () => this.sendMessage());

    if (fileBtn && fileInput) {
      fileBtn.addEventListener('click', () => fileInput.click());
      fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) this.uploadFile(file);
        fileInput.value = '';
      });
    }

    if (voiceBtn) {
      voiceBtn.addEventListener('click', () => this.toggleVoiceInput(voiceBtn));
    }

    if (emojiBtn) {
      emojiBtn.addEventListener('click', () => this.toggleEmojiPicker());
    }

    if (clearBtn) {
      clearBtn.addEventListener('click', () => this.clearChat());
    }
  },

  // ── Emoji picker ────────────────────────────────────────────────
  bindEmojiPicker() {
    const picker = document.getElementById('emoji-picker');
    if (!picker) return;

    const emojis = [
      '😀','😂','😊','😍','🥰','😎','😭','😅','🤔','😏',
      '😒','😩','😤','😡','🥺','😢','😳','🤩','🤗','😴',
      '👍','👎','❤️','🔥','✨','🎉','👏','🙏','💪','🤝',
      '🌟','💯','🎯','🚀','💡','✅','❌','⚠️','💬','📎',
      '🐱','🐶','🦋','🌺','🍕','🎮','🎵','📚','💻','🌙',
    ];

    const grid = document.createElement('div');
    grid.className = 'emoji-grid';

    emojis.forEach(emoji => {
      const btn = document.createElement('button');
      btn.className = 'emoji-btn';
      btn.textContent = emoji;
      btn.addEventListener('click', () => {
        const input = document.getElementById('message-input');
        if (input) {
          const pos = input.selectionStart || input.value.length;
          input.value = input.value.slice(0, pos) + emoji + input.value.slice(pos);
          input.focus();
        }
        this.toggleEmojiPicker(false);
      });
      grid.appendChild(btn);
    });

    picker.appendChild(grid);

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!picker.contains(e.target) && e.target.id !== 'btn-emoji') {
        picker.classList.remove('visible');
        this.emojiVisible = false;
      }
    });
  },

  toggleEmojiPicker(force) {
    const picker = document.getElementById('emoji-picker');
    if (!picker) return;
    this.emojiVisible = force !== undefined ? force : !this.emojiVisible;
    picker.classList.toggle('visible', this.emojiVisible);
  },

  // ── Voice input ──────────────────────────────────────────────────
  toggleVoiceInput(btn) {
    if (AudioManager.isRecording) {
      AudioManager.stopRecording();
      btn.classList.remove('active');
      return;
    }

    const started = AudioManager.startRecording(
      (transcript, isFinal) => {
        const input = document.getElementById('message-input');
        if (input) input.value = transcript;
        if (isFinal) {
          btn.classList.remove('active');
        }
      },
      (err) => {
        btn.classList.remove('active');
        showToast(err, 'error');
      }
    );

    if (started) btn.classList.add('active');
  },

  // ── File upload ──────────────────────────────────────────────────
  async uploadFile(file) {
    const fd = new FormData();
    fd.append('file', file);

    showToast('Enviando arquivo…', 'info', 2000);

    try {
      const data = await apiPostFile('api/upload.php', fd);
      if (data.error) {
        showToast(data.error, 'error');
        return;
      }

      this.pendingFile = {
        url: data.url,
        name: data.filename,
        mime: data.mime,
        category: data.category,
      };

      this.showPendingFile(file, data);
    } catch (e) {
      showToast('Erro ao enviar arquivo.', 'error');
    }
  },

  showPendingFile(file, data) {
    const bar = document.getElementById('file-preview-bar');
    if (!bar) return;
    bar.classList.add('visible');
    bar.innerHTML = '';

    const item = document.createElement('div');
    item.className = 'file-preview-item';

    if (data.category === 'image') {
      const img = document.createElement('img');
      img.src = data.url;
      img.className = 'file-preview-thumb';
      item.appendChild(img);
    } else {
      item.textContent = '📎 ';
    }

    const nameSpan = document.createElement('span');
    nameSpan.textContent = data.filename;
    item.appendChild(nameSpan);

    const removeBtn = document.createElement('span');
    removeBtn.className = 'file-preview-remove';
    removeBtn.textContent = '×';
    removeBtn.addEventListener('click', () => this.hidePendingFile());
    item.appendChild(removeBtn);

    bar.appendChild(item);
  },

  hidePendingFile() {
    this.pendingFile = null;
    const bar = document.getElementById('file-preview-bar');
    if (bar) {
      bar.classList.remove('visible');
      bar.innerHTML = '';
    }
  },

  // ── Clear chat ───────────────────────────────────────────────────
  async clearChat() {
    if (!this.activeCharacter) return;
    const confirmed = await confirmAction('Apagar toda a conversa com ' + this.activeCharacter.name + '?');
    if (!confirmed) return;

    try {
      const data = await apiPost('api/chat.php', {
        action: 'clear',
        character_id: this.activeCharacter.id,
      });
      if (data.error) {
        showToast(data.error, 'error');
        return;
      }
      const container = document.getElementById('messages-container');
      if (container) container.innerHTML = `
        <div class="text-center text-muted" style="padding:32px;font-size:.9rem;">
          Nenhuma mensagem ainda. Diga olá! 👋
        </div>`;
      this.lastDateLabel = '';
      showToast('Conversa apagada.', 'success');
    } catch (e) {
      showToast('Erro ao apagar conversa.', 'error');
    }
  },

  // ── Character modal ──────────────────────────────────────────────
  bindCharacterModal() {
    // Tabs
    document.querySelectorAll('.char-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.char-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.char-tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        const target = document.getElementById('tab-' + tab.dataset.tab);
        if (target) target.classList.add('active');
      });
    });

    // New character button
    const newBtn = document.getElementById('btn-new-character');
    if (newBtn) {
      newBtn.addEventListener('click', () => {
        this.openCharacterModal(null);
      });
    }

    // Save character
    const saveBtn = document.getElementById('btn-save-character');
    if (saveBtn) {
      saveBtn.addEventListener('click', () => this.saveCharacter());
    }

    // Cancel
    const cancelBtn = document.getElementById('btn-cancel-character');
    if (cancelBtn) {
      cancelBtn.addEventListener('click', () => closeModal('modal-character'));
    }

    // Avatar upload for character
    const charAvatarArea = document.getElementById('char-avatar-preview');
    const charAvatarFile = document.getElementById('char-avatar-file');
    if (charAvatarArea && charAvatarFile) {
      charAvatarArea.addEventListener('click', () => charAvatarFile.click());
      charAvatarFile.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        await this.uploadCharacterAvatar(file);
        charAvatarFile.value = '';
      });
    }
  },

  async uploadCharacterAvatar(file) {
    const fd = new FormData();
    fd.append('avatar', file);
    showToast('Enviando imagem…', 'info', 1500);
    try {
      const data = await apiPostFile('api/upload.php', fd);
      if (data.error) { showToast(data.error, 'error'); return; }

      const input = document.getElementById('char-avatar');
      if (input) input.value = data.url;

      const preview = document.getElementById('char-avatar-preview');
      if (preview) {
        preview.style.backgroundImage = `url('${escHtml(data.url)}?t=${Date.now()}')`;
        preview.innerHTML = '';
      }
      showToast('Foto carregada!', 'success');
    } catch (e) {
      showToast('Erro ao enviar imagem.', 'error');
    }
  },

  openCharacterModal(char) {
    const modal  = document.getElementById('modal-character');
    const title  = document.getElementById('char-modal-title');
    if (!modal) return;

    // Reset form
    const fields = ['char-id','char-name','char-description','char-personality',
                    'char-voice-example','char-bubble-color','char-voice-type',
                    'char-voice-speed','char-voice-pitch','char-elevenlabs-id',
                    'char-context-messages'];
    fields.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = el.type === 'color' ? '#dcf8c6' : (el.tagName === 'SELECT' ? el.options[0]?.value : '');
    });

    const checkboxes = ['char-voice-enabled','char-can-read-files','char-long-memory','char-auto-audio','char-can-generate-images'];
    checkboxes.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.checked = ['char-can-read-files','char-long-memory'].includes(id);
    });

    const ctxEl = document.getElementById('char-context-messages');
    if (ctxEl) ctxEl.value = '20';

    // Reset avatar
    const charAvatarInput = document.getElementById('char-avatar');
    if (charAvatarInput) charAvatarInput.value = '';
    const charAvatarPreview = document.getElementById('char-avatar-preview');
    if (charAvatarPreview) {
      charAvatarPreview.innerHTML = '';
      charAvatarPreview.style.backgroundImage = '';
    }
    const charAvatarFileInput = document.getElementById('char-avatar-file');
    if (charAvatarFileInput) charAvatarFileInput.value = '';

    if (char) {
      if (title) title.textContent = 'Editar Personagem';
      const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
      const setCheck = (id, val) => { const el = document.getElementById(id); if (el) el.checked = !!parseInt(val); };
      set('char-id', char.id);
      set('char-name', char.name);
      set('char-description', char.description);
      set('char-personality', char.personality);
      set('char-voice-example', char.voice_example);
      set('char-bubble-color', char.bubble_color || '#dcf8c6');
      set('char-voice-type', char.voice_type || 'feminina_adulta');
      set('char-voice-speed', char.voice_speed || 1.0);
      set('char-voice-pitch', char.voice_pitch || 1.0);
      set('char-elevenlabs-id', char.elevenlabs_id || '');
      set('char-context-messages', char.context_messages || 20);
      setCheck('char-voice-enabled', char.voice_enabled);
      setCheck('char-can-read-files', char.can_read_files);
      setCheck('char-long-memory', char.long_memory);
      setCheck('char-auto-audio', char.auto_audio);
      setCheck('char-can-generate-images', char.can_generate_images);

      // Restore avatar preview
      if (charAvatarInput) charAvatarInput.value = char.avatar || '';
      if (charAvatarPreview && char.avatar) {
        charAvatarPreview.style.backgroundImage = `url('${escHtml(char.avatar)}')`;
        charAvatarPreview.innerHTML = '';
      } else if (charAvatarPreview) {
        charAvatarPreview.innerHTML = escHtml((char.name || '?').slice(0, 2).toUpperCase());
      }
    } else {
      if (title) title.textContent = 'Novo Personagem';
      if (charAvatarPreview) charAvatarPreview.innerHTML = '📷';
    }

    // Reset to first tab
    document.querySelectorAll('.char-tab').forEach((t, i) => t.classList.toggle('active', i === 0));
    document.querySelectorAll('.char-tab-content').forEach((c, i) => c.classList.toggle('active', i === 0));

    openModal('modal-character');
  },

  async saveCharacter() {
    const id = document.getElementById('char-id')?.value;
    const name = document.getElementById('char-name')?.value.trim();

    if (!name) {
      showToast('Nome é obrigatório.', 'error');
      return;
    }

    const payload = {
      action: id ? 'update' : 'create',
      id: id || undefined,
      name,
      description:         document.getElementById('char-description')?.value || '',
      personality:         document.getElementById('char-personality')?.value || '',
      voice_example:       document.getElementById('char-voice-example')?.value || '',
      bubble_color:        document.getElementById('char-bubble-color')?.value || '#dcf8c6',
      voice_type:          document.getElementById('char-voice-type')?.value || 'feminina_adulta',
      voice_speed:         document.getElementById('char-voice-speed')?.value || '1.0',
      voice_pitch:         document.getElementById('char-voice-pitch')?.value || '1.0',
      elevenlabs_id:       document.getElementById('char-elevenlabs-id')?.value || '',
      context_messages:    document.getElementById('char-context-messages')?.value || '20',
      voice_enabled:       document.getElementById('char-voice-enabled')?.checked ? '1' : '0',
      can_read_files:      document.getElementById('char-can-read-files')?.checked ? '1' : '0',
      long_memory:         document.getElementById('char-long-memory')?.checked ? '1' : '0',
      auto_audio:          document.getElementById('char-auto-audio')?.checked ? '1' : '0',
      can_generate_images: document.getElementById('char-can-generate-images')?.checked ? '1' : '0',
      avatar:              document.getElementById('char-avatar')?.value || '',
    };

    try {
      const data = await apiPost('api/characters.php', payload);
      if (data.error) {
        showToast(data.error, 'error');
        return;
      }
      showToast(id ? 'Personagem atualizado!' : 'Personagem criado!', 'success');
      closeModal('modal-character');
      await this.loadCharacters();

      if (!id && data.character) {
        this.openChat(data.character);
        closeModal('modal-contacts');
      }
    } catch (e) {
      showToast('Erro ao salvar personagem.', 'error');
    }
  },

  async deleteCharacter(char) {
    const confirmed = await confirmAction(`Excluir "${char.name}"? Todas as mensagens serão apagadas.`);
    if (!confirmed) return;

    try {
      const data = await apiPost('api/characters.php', { action: 'delete', id: char.id });
      if (data.error) {
        showToast(data.error, 'error');
        return;
      }
      showToast('Personagem excluído.', 'success');

      if (this.activeCharacter?.id === char.id) {
        this.activeCharacter = null;
        const welcome  = document.getElementById('welcome-screen');
        const chatView = document.getElementById('chat-view');
        if (welcome)  welcome.style.display = '';
        if (chatView) chatView.style.display = 'none';
      }

      await this.loadCharacters();
    } catch (e) {
      showToast('Erro ao excluir personagem.', 'error');
    }
  },
};

// ── Contact search ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('contact-search');
  if (searchInput) {
    searchInput.addEventListener('input', debounce((e) => {
      ChatManager.renderContactList(e.target.value);
    }, 200));
  }
});

window.ChatManager = ChatManager;
