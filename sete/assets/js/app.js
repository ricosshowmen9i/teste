/**
 * WhatsappJUJU — Lógica Principal do Chat (app.js)
 */

const App = (() => {
  // ── Estado ──────────────────────────────────────────────
  let currentConversationId = null;
  let currentCharacter      = null;
  let isStreaming           = false;
  let emojiPickerOpen       = false;
  let uploadOptionsOpen     = false;
  let chatMenuOpen          = false;
  let autoScrollEnabled     = true;
  let pendingFileUrl        = null;
  let pendingFileName       = null;
  let pendingFileType       = 'text';

  // ── Init ─────────────────────────────────────────────────
  function init() {
    bindEvents();
    loadConversations();
    initEmojis();
    checkScrollBtn();
  }

  // ── Bind de eventos ──────────────────────────────────────
  function bindEvents() {
    // Header
    $('#btn-contacts').on('click', () => Contacts.open());
    $('#btn-profile').on('click',  () => $('#profile-modal').addClass('open') && $('body').css('overflow','hidden'));
    $('#btn-admin').on('click',    () => Admin.open());

    // Input
    $('#message-input')
      .on('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
      })
      .on('input', autoResizeTextarea);

    $('#send-btn').on('click', sendMessage);
    $('#emoji-btn').on('click', toggleEmojiPicker);
    $('#attach-btn').on('click', toggleUploadOptions);
    $('#mic-btn').on('mousedown', Audio.startRecording).on('mouseup touchend', Audio.stopRecording);

    // Scroll
    $('#messages-container').on('scroll', onMessagesScroll);
    $('#scroll-down-btn').on('click', scrollToBottom);

    // Chat menu
    $('#chat-menu-btn').on('click', e => { e.stopPropagation(); toggleChatMenu(); });

    // Fechar menus ao clicar fora
    $(document).on('click', () => {
      if (emojiPickerOpen)    closeEmojiPicker();
      if (uploadOptionsOpen)  closeUploadOptions();
      if (chatMenuOpen)       closeChatMenu();
    });

    $('#emoji-picker, #upload-options, #chat-menu-btn').on('click', e => e.stopPropagation());

    // Lightbox
    $('#lightbox').on('click', () => $('#lightbox').removeClass('open'));
    $('#lightbox-close').on('click', () => $('#lightbox').removeClass('open'));
  }

  // ── Enviar mensagem ───────────────────────────────────────
  function sendMessage() {
    if (!currentConversationId || isStreaming) return;

    const $input  = $('#message-input');
    let content   = $input.val().trim();
    if (!content && !pendingFileUrl) return;

    // Montagem da mensagem do usuário
    const msgType = pendingFileUrl ? pendingFileType : 'text';
    const fileUrl = pendingFileUrl;
    const fileName= pendingFileName;

    if (!content && fileUrl) content = fileName || 'Arquivo';

    $input.val('').css('height', 'auto');
    clearPendingFile();

    // Exibe mensagem do usuário imediatamente
    appendUserMessage(content, msgType, fileUrl, fileName);
    showTypingIndicator();
    scrollToBottom();

    isStreaming = true;
    streamMessage(content, msgType, fileUrl, fileName);
  }

  // ── Stream SSE ────────────────────────────────────────────
  function streamMessage(content, msgType, fileUrl, fileName) {
    const params = new URLSearchParams({
      action:          'stream',
      conversation_id: currentConversationId,
      content:         content,
      message_type:    msgType,
    });
    if (fileUrl)  params.append('file_url',  fileUrl);
    if (fileName) params.append('file_name', fileName);

    const evtSource = new EventSource(`api/stream.php?${params.toString()}`);
    let   aiText    = '';
    let   $bubble   = null;

    evtSource.addEventListener('user_message', () => {});

    evtSource.addEventListener('token', e => {
      hideTypingIndicator();
      const data  = JSON.parse(e.data);
      const token = data.token || '';
      aiText += token;

      if (!$bubble) {
        $bubble = appendAIMessageBubble('');
        scrollToBottom();
      }

      const html = parseMarkdown(aiText);
      $bubble.find('.bubble-content').html(html + '<span class="streaming-cursor"></span>');
      if (autoScrollEnabled) scrollToBottom();
    });

    evtSource.addEventListener('done', e => {
      evtSource.close();
      isStreaming = false;

      const data = JSON.parse(e.data);
      const id   = data.id;

      if ($bubble) {
        const html = parseMarkdown(aiText);
        $bubble.find('.bubble-content').html(html);
        $bubble.attr('data-id', id);
        attachTTSButton($bubble, aiText);
      } else {
        appendAIMessage({ id, content: data.content || aiText });
      }

      hideTypingIndicator();
      scrollToBottom();
      Contacts.refreshBadges();
    });

    evtSource.addEventListener('error', e => {
      evtSource.close();
      isStreaming = false;
      hideTypingIndicator();
      try {
        const msg = JSON.parse(e.data).message || 'Erro ao obter resposta.';
        showToast(msg, 'error');
      } catch (_) {
        showToast('Erro de conexão com a IA.', 'error');
      }
    });

    evtSource.onerror = () => {
      if (evtSource.readyState === EventSource.CLOSED) {
        isStreaming = false;
        hideTypingIndicator();
      }
    };
  }

  // ── Abrir conversa ────────────────────────────────────────
  function openConversation(convId, character) {
    currentConversationId = convId;
    currentCharacter      = character;

    // Aplica cor do balão da IA
    const style = document.getElementById('ai-bubble-style') || (() => {
      const s = document.createElement('style');
      s.id = 'ai-bubble-style';
      document.head.appendChild(s);
      return s;
    })();
    style.textContent = `.message-wrapper.ai .message-bubble { background: ${character.bubble_color || 'var(--bubble-ai)'}; }`;

    // Atualiza header do chat
    updateChatHeader(character);

    // Mostra área de chat
    $('#welcome-screen').hide();
    $('#active-chat').show();
    $('#messages-container').empty();

    loadHistory(convId);
    $('#message-input').focus();
  }

  function updateChatHeader(character) {
    const avatarHtml = character.character_avatar
      ? `<img src="${escHtml(character.character_avatar)}" class="chat-header-avatar" alt="">`
      : `<div class="chat-header-avatar">${escHtml(character.character_name.charAt(0).toUpperCase())}</div>`;

    $('#chat-avatar-wrap').html(avatarHtml);
    $('#chat-char-name').text(character.character_name);
    $('#chat-char-desc').text(character.character_description || 'Personagem IA');
  }

  // ── Histórico ─────────────────────────────────────────────
  function loadHistory(convId) {
    $.get('api/chat.php', { action: 'history', conversation_id: convId, limit: 50 }, data => {
      if (!data.success) return;
      data.messages.forEach(msg => {
        if (msg.role === 'user') {
          appendUserMessage(msg.content, msg.message_type, msg.file_url, msg.file_name, msg.created_at);
        } else {
          appendAIMessage(msg);
        }
      });
      scrollToBottom();
    });
  }

  // ── Renderização de mensagens ─────────────────────────────
  function appendUserMessage(content, type = 'text', fileUrl = null, fileName = null, time = null) {
    const timeStr = formatTime(time || new Date());
    let   inner   = '';

    if (type === 'image' && fileUrl) {
      inner = `<img src="${escHtml(fileUrl)}" class="bubble-image" onclick="App.openLightbox('${escHtml(fileUrl)}')" alt="Imagem">`;
    } else if (type === 'file' && fileUrl) {
      inner = `<div class="file-card" onclick="Upload.downloadFile('${escHtml(fileUrl)}','${escHtml(fileName||'arquivo')}')">
        <i class="fas fa-file"></i>
        <div class="file-info"><div class="file-name">${escHtml(fileName||'arquivo')}</div></div>
        <i class="fas fa-download"></i>
      </div>`;
    } else {
      inner = `<div class="bubble-content">${parseMarkdown(content)}</div>`;
    }

    const html = `
      <div class="message-wrapper user">
        <div class="message-bubble">
          ${inner}
          <div class="message-time">${timeStr}</div>
        </div>
      </div>`;

    $('#messages-container').append(html);
  }

  function appendAIMessage(msg) {
    const content = msg.content || '';
    const timeStr = formatTime(msg.created_at || new Date());

    const $wrapper = appendAIMessageBubble(content);
    $wrapper.attr('data-id', msg.id);
    attachTTSButton($wrapper, content);

    return $wrapper;
  }

  function appendAIMessageBubble(content) {
    const avtr = currentCharacter
      ? (currentCharacter.character_avatar
          ? `<img src="${escHtml(currentCharacter.character_avatar)}" class="msg-avatar" alt="">`
          : `<div class="msg-avatar">${escHtml((currentCharacter.character_name||'?').charAt(0).toUpperCase())}</div>`)
      : `<div class="msg-avatar">?</div>`;

    const html = `
      <div class="message-wrapper ai">
        ${avtr}
        <div class="message-bubble">
          <div class="bubble-content">${parseMarkdown(content)}</div>
          <div class="message-time">
            <span class="msg-time-text">${formatTime(new Date())}</span>
          </div>
        </div>
      </div>`;

    const $el = $(html);
    $('#messages-container').append($el);
    return $el;
  }

  function attachTTSButton($wrapper, text) {
    if (!currentCharacter || !currentCharacter.voice_enabled) return;
    const $time = $wrapper.find('.message-time');
    const $btn  = $('<button class="msg-tts-btn" title="Ouvir"><i class="fas fa-volume-up"></i></button>');
    $btn.on('click', () => Audio.speakText(text, currentCharacter));
    $time.prepend($btn);

    // Auto-audio
    if (currentCharacter.auto_audio) {
      setTimeout(() => Audio.speakText(text, currentCharacter), 300);
    }
  }

  // ── Typing indicator ──────────────────────────────────────
  function showTypingIndicator() {
    const avtr = currentCharacter
      ? (currentCharacter.character_avatar
          ? `<img src="${escHtml(currentCharacter.character_avatar)}" class="msg-avatar" alt="">`
          : `<div class="msg-avatar">${escHtml((currentCharacter.character_name||'?').charAt(0).toUpperCase())}</div>`)
      : `<div class="msg-avatar">?</div>`;

    $('#typing-indicator').html(`
      ${avtr}
      <div class="typing-dots">
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
      </div>
    `).show();
    $('#chat-header-status').addClass('typing-indicator-text').text('digitando...');
  }

  function hideTypingIndicator() {
    $('#typing-indicator').hide().empty();
    $('#chat-header-status').removeClass('typing-indicator-text')
      .text(currentCharacter ? (currentCharacter.character_description || 'Personagem IA') : '');
  }

  // ── Scroll ────────────────────────────────────────────────
  function scrollToBottom(smooth = false) {
    const $c = $('#messages-container')[0];
    if (!$c) return;
    $c.scrollTop = $c.scrollHeight;
  }

  function onMessagesScroll() {
    const $c = $('#messages-container')[0];
    const fromBottom = $c.scrollHeight - $c.scrollTop - $c.clientHeight;
    autoScrollEnabled = fromBottom < 100;
    $('#scroll-down-btn').toggle(fromBottom > 200);
  }

  function checkScrollBtn() { $('#scroll-down-btn').hide(); }

  // ── Emoji picker ──────────────────────────────────────────
  const EMOJIS = ['😀','😂','🥰','😎','🤔','😅','🙏','🔥','💯','❤️','👍','👏','🎉','✅','⭐','🤖','💬','📎','🎵','🌟',
                  '😍','🤣','😊','😇','🥺','😭','🤯','🥳','😤','🙄','😴','🤐','😋','😜','🤩','😏','😒','🙂','😌','😔',
                  '👋','🤝','💪','🖐️','👀','💡','📌','🔑','🗓️','📝','💰','🎯','🚀','💻','📱','🎮','🍕','☕','🌈','🦋'];

  function initEmojis() {
    const $picker = $('#emoji-picker');
    EMOJIS.forEach(e => {
      $picker.append(`<span class="emoji-item">${e}</span>`);
    });
    $picker.on('click', '.emoji-item', function() {
      const $input = $('#message-input');
      $input.val($input.val() + $(this).text());
      $input.focus();
    });
  }

  function toggleEmojiPicker() {
    emojiPickerOpen = !emojiPickerOpen;
    $('#emoji-picker').toggleClass('open', emojiPickerOpen);
    if (uploadOptionsOpen) closeUploadOptions();
  }

  function closeEmojiPicker() {
    emojiPickerOpen = false;
    $('#emoji-picker').removeClass('open');
  }

  // ── Upload options ────────────────────────────────────────
  function toggleUploadOptions() {
    uploadOptionsOpen = !uploadOptionsOpen;
    $('#upload-options').toggleClass('open', uploadOptionsOpen);
    if (emojiPickerOpen) closeEmojiPicker();
  }

  function closeUploadOptions() {
    uploadOptionsOpen = false;
    $('#upload-options').removeClass('open');
  }

  // ── Chat menu ─────────────────────────────────────────────
  function toggleChatMenu() {
    chatMenuOpen = !chatMenuOpen;
    $('#chat-dropdown').toggleClass('open', chatMenuOpen);
  }

  function closeChatMenu() {
    chatMenuOpen = false;
    $('#chat-dropdown').removeClass('open');
  }

  // ── Ações do menu do chat ─────────────────────────────────
  function clearChat() {
    if (!currentConversationId) return;
    if (!confirm('Limpar todas as mensagens desta conversa?')) return;
    $.post('api/chat.php', { action: 'clear', conversation_id: currentConversationId }, data => {
      if (data.success) {
        $('#messages-container').empty();
        showToast('Conversa limpa!', 'success');
      }
    });
    closeChatMenu();
  }

  // ── Lightbox ──────────────────────────────────────────────
  function openLightbox(src) {
    $('#lightbox img').attr('src', src);
    $('#lightbox').addClass('open');
  }

  // ── Textarea auto-resize ──────────────────────────────────
  function autoResizeTextarea() {
    const el = this;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
  }

  // ── Markdown parser simples ───────────────────────────────
  function parseMarkdown(text) {
    if (!text) return '';
    let html = escHtml(text);

    // Blocos de código
    html = html.replace(/```([^`]*?)```/gs, (_, code) =>
      `<pre><code>${code.trim()}</code></pre>`);

    // Código inline
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Negrito
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

    // Itálico
    html = html.replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');
    html = html.replace(/_([^_]+)_/g, '<em>$1</em>');

    // Links
    html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/g,
      '<a href="$2" target="_blank" rel="noopener">$1</a>');

    // Quebras de linha
    html = html.replace(/\n/g, '<br>');

    return html;
  }

  // ── Formatação de hora ────────────────────────────────────
  function formatTime(dateStr) {
    const d = dateStr instanceof Date ? dateStr : new Date(dateStr);
    if (isNaN(d)) return '';
    const now   = new Date();
    const today = now.toDateString();
    if (d.toDateString() === today) {
      return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
  }

  // ── Toast notifications ───────────────────────────────────
  function showToast(message, type = 'info', duration = 3000) {
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle' };
    const icon  = icons[type] || icons.info;
    const $toast = $(`
      <div class="toast ${type}">
        <i class="fas ${icon} toast-icon"></i>
        <span>${escHtml(message)}</span>
      </div>
    `);
    $('#toast-container').append($toast);
    setTimeout(() => $toast.fadeOut(300, () => $toast.remove()), duration);
  }

  // ── Escape HTML ───────────────────────────────────────────
  function escHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // ── Carregar conversas (inicialização) ───────────────────
  function loadConversations() {
    // Delegado para Contacts.js
  }

  // ── Arquivo pendente (upload antes de enviar) ─────────────
  function setPendingFile(url, name, type) {
    pendingFileUrl  = url;
    pendingFileName = name;
    pendingFileType = type || 'file';
  }

  function clearPendingFile() {
    pendingFileUrl  = null;
    pendingFileName = null;
    pendingFileType = 'text';
    $('#pending-file-preview').remove();
  }

  // ── API pública ───────────────────────────────────────────
  return {
    init,
    openConversation,
    showToast,
    clearChat,
    openLightbox,
    parseMarkdown,
    formatTime,
    escHtml,
    scrollToBottom,
    setPendingFile,
    clearPendingFile,
    get currentConversationId() { return currentConversationId; },
    get currentCharacter()      { return currentCharacter; },
  };
})();

// ── Inicializa quando DOM estiver pronto ─────────────────────
$(document).ready(() => App.init());
