/**
 * SETE — groups.js
 * Group chat management
 */

'use strict';

// ── ContactsModal tab switcher ────────────────────────────────────
const ContactsModal = {
  showTab(tab) {
    const contactsPanel = document.getElementById('contacts-tab-panel');
    const groupsPanel   = document.getElementById('groups-tab-panel');
    const btnContacts   = document.getElementById('tab-btn-contacts');
    const btnGroups     = document.getElementById('tab-btn-groups');

    if (contactsPanel) contactsPanel.style.display = tab === 'contacts' ? 'flex' : 'none';
    if (groupsPanel)   groupsPanel.style.display   = tab === 'groups'   ? 'flex' : 'none';
    if (btnContacts)   btnContacts.classList.toggle('active', tab === 'contacts');
    if (btnGroups)     btnGroups.classList.toggle('active', tab === 'groups');

    if (tab === 'groups') GroupManager.loadGroups();
  },
};
window.ContactsModal = ContactsModal;

// ── GroupManager ──────────────────────────────────────────────────
const GroupManager = {
  groups: [],
  activeGroup: null,
  eventSource: null,
  /** pending chips in create/edit modal: [{id, name, avatar}] */
  _modalMembers: [],

  init() {
    document.getElementById('btn-new-group')?.addEventListener('click', () => {
      GroupManager.openGroupModal(null);
    });

    document.getElementById('btn-save-group')?.addEventListener('click', () => {
      GroupManager.saveGroup();
    });

    document.getElementById('btn-add-member-to-group')?.addEventListener('click', () => {
      GroupManager._addMemberFromSelect();
    });
  },

  // ── Load groups ─────────────────────────────────────────────────
  async loadGroups() {
    try {
      const data = await apiGet('api/groups.php');
      this.groups = data.groups || [];
      this.renderGroupList();
    } catch (e) {
      showToast('Erro ao carregar grupos.', 'error');
    }
  },

  // ── Render group list ────────────────────────────────────────────
  renderGroupList(filter = '') {
    const list = document.getElementById('groups-list');
    if (!list) return;

    const filtered = filter
      ? this.groups.filter(g => g.name.toLowerCase().includes(filter.toLowerCase()))
      : this.groups;

    list.innerHTML = '';

    if (!filtered.length) {
      list.innerHTML = `<div class="text-center text-muted" style="padding:24px;font-size:.9rem;">
        ${filter ? 'Nenhum resultado.' : 'Nenhum grupo criado ainda.'}
      </div>`;
      return;
    }

    filtered.forEach(group => {
      const item = document.createElement('div');
      item.className = 'group-item';
      item.dataset.id = group.id;

      const members = group.members || [];
      const stackHtml = members.slice(0, 3).map(m => {
        const initials = m.name.slice(0, 2).toUpperCase();
        return m.avatar
          ? `<div class="ga"><img src="${escHtml(m.avatar)}" alt="${escHtml(m.name)}"></div>`
          : `<div class="ga">${escHtml(initials)}</div>`;
      }).join('');

      const modeLabels = { random: '🎲', topic: '💬', story: '📖' };
      const modeIcon   = modeLabels[group.interaction_mode] || '🎲';
      const lastMsg    = group.last_message
        ? escHtml(group.last_message.slice(0, 50)) + (group.last_message.length > 50 ? '…' : '')
        : '<em>Sem mensagens</em>';

      item.innerHTML = `
        <div class="group-avatar-stack">${stackHtml || '<div class="ga">G</div>'}</div>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:700;font-size:.95rem;">${escHtml(group.name)} ${modeIcon}</div>
          <div style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${lastMsg}</div>
          <div style="font-size:.72rem;color:var(--text-muted);">${members.length} membro${members.length !== 1 ? 's' : ''}</div>
        </div>
        <div class="group-item-actions">
          <button title="Editar" onclick="event.stopPropagation(); GroupManager.openGroupModal(GroupManager.groups.find(g=>g.id==${group.id}))">✏️</button>
          <button title="Apagar" onclick="event.stopPropagation(); GroupManager.deleteGroup(GroupManager.groups.find(g=>g.id==${group.id}))">🗑️</button>
        </div>
      `;

      item.addEventListener('click', () => {
        GroupManager.openGroupChat(group);
        closeModal('modal-contacts');
      });

      list.appendChild(item);
    });
  },

  // ── Open create/edit modal ───────────────────────────────────────
  openGroupModal(group) {
    const titleEl = document.getElementById('group-modal-title');
    const idEl    = document.getElementById('group-id');
    const nameEl  = document.getElementById('group-name');
    const descEl  = document.getElementById('group-description');
    const storyEl = document.getElementById('group-story');
    const modeEl  = document.getElementById('group-interaction-mode');

    if (titleEl) titleEl.textContent = group ? 'Editar Grupo' : 'Novo Grupo';
    if (idEl)    idEl.value          = group ? group.id : '';
    if (nameEl)  nameEl.value        = group ? group.name : '';
    if (descEl)  descEl.value        = group ? (group.description || '') : '';
    if (storyEl) storyEl.value       = group ? (group.story || '') : '';
    if (modeEl)  modeEl.value        = group ? (group.interaction_mode || 'random') : 'random';

    // Populate member chips
    this._modalMembers = group ? (group.members || []).map(m => ({ id: m.id, name: m.name, avatar: m.avatar })) : [];
    this._renderMemberChips();

    // Populate character select from ChatManager
    const sel = document.getElementById('group-add-member-select');
    if (sel) {
      sel.innerHTML = '<option value="">— selecione —</option>';
      const chars = (window.ChatManager && ChatManager.characters) ? ChatManager.characters : [];
      chars.forEach(c => {
        const opt = document.createElement('option');
        opt.value       = c.id;
        opt.textContent = c.name;
        sel.appendChild(opt);
      });
    }

    openModal('modal-group');
  },

  _renderMemberChips() {
    const container = document.getElementById('group-members-list');
    if (!container) return;

    if (!this._modalMembers.length) {
      container.innerHTML = '<span class="text-muted" style="font-size:.85rem;">Nenhum membro adicionado</span>';
      return;
    }

    container.innerHTML = '';
    this._modalMembers.forEach(m => {
      const chip = document.createElement('div');
      chip.className = 'group-member-chip';
      const initials  = m.name.slice(0, 2).toUpperCase();
      const avatarHtml = m.avatar
        ? `<img src="${escHtml(m.avatar)}" alt="${escHtml(m.name)}">`
        : escHtml(initials);

      chip.innerHTML = `
        <div class="chip-avatar">${avatarHtml}</div>
        <span>${escHtml(m.name)}</span>
        <span class="chip-remove" data-id="${m.id}">✕</span>
      `;
      chip.querySelector('.chip-remove').addEventListener('click', () => {
        this._modalMembers = this._modalMembers.filter(x => x.id !== m.id);
        this._renderMemberChips();
      });
      container.appendChild(chip);
    });
  },

  _addMemberFromSelect() {
    const sel  = document.getElementById('group-add-member-select');
    const id   = parseInt(sel?.value || '0', 10);
    if (!id) return;

    if (this._modalMembers.find(m => m.id === id)) {
      showToast('Personagem já adicionado.', 'warning');
      return;
    }

    const chars = (window.ChatManager && ChatManager.characters) ? ChatManager.characters : [];
    const char  = chars.find(c => c.id == id);
    if (!char) return;

    this._modalMembers.push({ id: char.id, name: char.name, avatar: char.avatar || null });
    this._renderMemberChips();
    if (sel) sel.value = '';
  },

  // ── Save group (create or update) ───────────────────────────────
  async saveGroup() {
    const id    = document.getElementById('group-id')?.value || '';
    const name  = document.getElementById('group-name')?.value.trim() || '';
    const desc  = document.getElementById('group-description')?.value.trim() || '';
    const story = document.getElementById('group-story')?.value.trim() || '';
    const mode  = document.getElementById('group-interaction-mode')?.value || 'random';

    if (!name) { showToast('Nome é obrigatório.', 'error'); return; }

    try {
      let data;
      if (id) {
        data = await apiPost('api/groups.php', {
          action: 'update',
          id, name, description: desc, story, interaction_mode: mode,
        });
      } else {
        data = await apiPost('api/groups.php', {
          action: 'create',
          name, description: desc, story, interaction_mode: mode,
          member_ids: JSON.stringify(this._modalMembers.map(m => m.id)),
        });
      }

      if (data.error) { showToast(data.error, 'error'); return; }

      // If editing, sync members
      if (id && data.success) {
        const existing = this.groups.find(g => g.id == id);
        const existingIds  = (existing?.members || []).map(m => m.id);
        const desiredIds   = this._modalMembers.map(m => m.id);

        // Add new members
        for (const charId of desiredIds) {
          if (!existingIds.includes(charId)) {
            await apiPost('api/groups.php', { action: 'add_member', group_id: id, character_id: charId });
          }
        }
        // Remove removed members
        for (const charId of existingIds) {
          if (!desiredIds.includes(charId)) {
            await apiPost('api/groups.php', { action: 'remove_member', group_id: id, character_id: charId });
          }
        }
      }

      showToast(id ? 'Grupo atualizado!' : 'Grupo criado!', 'success');
      closeModal('modal-group');
      await this.loadGroups();
    } catch (e) {
      showToast('Erro ao salvar grupo.', 'error');
    }
  },

  // ── Delete group ─────────────────────────────────────────────────
  async deleteGroup(group) {
    if (!group) return;
    if (!confirmAction('Apagar o grupo "' + group.name + '" e todas as mensagens?')) return;

    try {
      const data = await apiPost('api/groups.php', { action: 'delete', id: group.id });
      if (data.error) { showToast(data.error, 'error'); return; }

      if (this.activeGroup?.id === group.id) {
        this.activeGroup = null;
        this._showWelcome();
      }

      showToast('Grupo apagado.', 'success');
      await this.loadGroups();
    } catch (e) {
      showToast('Erro ao apagar grupo.', 'error');
    }
  },

  // ── Open group chat ──────────────────────────────────────────────
  async openGroupChat(group) {
    this.activeGroup = group;

    // Stop any playing audio
    if (window.AudioManager) AudioManager.stop();

    // Deactivate individual character
    if (window.ChatManager) {
      ChatManager.activeCharacter = null;
      document.querySelectorAll('.contact-item').forEach(el => el.classList.remove('active'));
    }

    // Show chat view
    const welcome  = document.getElementById('welcome-screen');
    const chatView = document.getElementById('chat-view');
    if (welcome)  welcome.style.display  = 'none';
    if (chatView) chatView.style.display = 'flex';

    // Update header
    this._updateChatHeader(group);

    // Load history
    await this.loadGroupHistory(group.id);
  },

  _updateChatHeader(group) {
    const nameEl   = document.getElementById('chat-header-name');
    const statusEl = document.getElementById('chat-header-status');
    const avatarEl = document.getElementById('chat-header-avatar');

    if (nameEl) nameEl.textContent = group.name;

    const modeLabels = { random: '🎲 Aleatório', topic: '💬 Por assunto', story: '📖 Roteiro' };
    const members    = group.members || [];
    const modeLabel  = modeLabels[group.interaction_mode] || '🎲 Aleatório';
    if (statusEl) statusEl.textContent = `${members.length} membro${members.length !== 1 ? 's' : ''} · ${modeLabel}`;

    // Replace avatar area with stacked group avatars
    if (avatarEl) {
      // Remove any old stack
      document.getElementById('group-header-avatar-stack')?.remove();

      const stack = document.createElement('div');
      stack.id        = 'group-header-avatar-stack';
      stack.className = 'group-header-avatars';

      (members.slice(0, 4)).forEach(m => {
        const div = document.createElement('div');
        div.className = 'ga';
        if (m.avatar) {
          const img = document.createElement('img');
          img.src = m.avatar;
          img.alt = m.name;
          div.appendChild(img);
        } else {
          div.textContent = m.name.slice(0, 2).toUpperCase();
        }
        stack.appendChild(div);
      });

      avatarEl.style.display = 'none';
      avatarEl.parentNode.insertBefore(stack, avatarEl);
    }

    // Update typing avatar
    const typingAvatar = document.getElementById('typing-avatar');
    if (typingAvatar) typingAvatar.textContent = '🎭';
  },

  // ── Load group history ───────────────────────────────────────────
  async loadGroupHistory(groupId) {
    const container = document.getElementById('messages-container');
    if (container) container.innerHTML = '<div class="text-center text-muted" style="padding:32px;"><div class="spinner"></div></div>';

    try {
      const data = await apiGet(`api/groups.php?action=history&group_id=${groupId}`);
      const msgs = data.messages || [];

      if (container) container.innerHTML = '';

      if (!msgs.length) {
        if (container) container.innerHTML = '<div class="text-center text-muted" style="padding:32px;font-size:.9rem;">Nenhuma mensagem ainda. Diga olá! 👋</div>';
        return;
      }

      msgs.forEach(msg => this.appendGroupMessage(msg));
      this.scrollToBottom();
    } catch (e) {
      showToast('Erro ao carregar mensagens do grupo.', 'error');
    }
  },

  // ── Append a group message bubble ───────────────────────────────
  appendGroupMessage(msg) {
    const container = document.getElementById('messages-container');
    if (!container) return;

    const isUser = msg.sender_type === 'user';
    const wrapper = document.createElement('div');
    wrapper.className = `message-wrapper ${isUser ? 'user' : 'assistant'}`;

    if (!isUser) {
      const avatarDiv = document.createElement('div');
      avatarDiv.className = 'msg-avatar';
      if (msg.character_avatar) {
        const img = document.createElement('img');
        img.src = msg.character_avatar;
        img.alt = msg.character_name || '';
        avatarDiv.appendChild(img);
      } else {
        avatarDiv.textContent = (msg.character_name || 'A').slice(0, 2).toUpperCase();
      }
      wrapper.appendChild(avatarDiv);
    }

    const bubble = document.createElement('div');
    bubble.className = 'msg-bubble';

    if (!isUser && msg.character_name) {
      const nameEl = document.createElement('div');
      nameEl.className   = 'msg-sender-name';
      nameEl.textContent = msg.character_name;
      bubble.appendChild(nameEl);
    }

    // Reply-to quote
    if (msg.reply_to_name && msg.reply_to_snippet) {
      const replyEl = document.createElement('div');
      replyEl.className = 'group-reply-quote';
      replyEl.innerHTML = `<span class="reply-quote-name">${escHtml(msg.reply_to_name)}</span><span class="reply-quote-text">${escHtml(msg.reply_to_snippet)}</span>`;
      bubble.appendChild(replyEl);
    }

    const textEl = document.createElement('div');
    textEl.className = 'msg-content';
    textEl.innerHTML  = parseMarkdown(msg.content || '');
    bubble.appendChild(textEl);

    // Audio button for character messages
    if (!isUser && msg.content) {
      bubble.appendChild(this._createGroupAudioBtn(msg.content, msg.character_id));
    }

    const timeEl = document.createElement('div');
    timeEl.className   = 'msg-time';
    timeEl.textContent = formatTime ? formatTime(msg.created_at) : msg.created_at || '';
    bubble.appendChild(timeEl);

    wrapper.appendChild(bubble);

    // Insert before typing indicator if it exists inside container
    const typingEl = container.querySelector('#typing-indicator');
    if (typingEl) {
      container.insertBefore(wrapper, typingEl);
    } else {
      container.appendChild(wrapper);
    }
  },

  // ── Start a streaming bubble for a character ─────────────────────
  startGroupStreamBubble(charId, charName, charAvatar) {
    const container = document.getElementById('messages-container');
    if (!container) return null;

    const wrapper = document.createElement('div');
    wrapper.className = 'message-wrapper assistant';
    wrapper.dataset.streamChar = charId;

    const avatarDiv = document.createElement('div');
    avatarDiv.className = 'msg-avatar';
    if (charAvatar) {
      const img = document.createElement('img');
      img.src = charAvatar;
      img.alt = charName;
      avatarDiv.appendChild(img);
    } else {
      avatarDiv.textContent = (charName || 'A').slice(0, 2).toUpperCase();
    }
    wrapper.appendChild(avatarDiv);

    const bubble = document.createElement('div');
    bubble.className = 'msg-bubble';

    const nameEl = document.createElement('div');
    nameEl.className   = 'msg-sender-name';
    nameEl.textContent = charName;
    bubble.appendChild(nameEl);

    const textEl = document.createElement('div');
    textEl.className = 'msg-content';
    bubble.appendChild(textEl);

    wrapper.appendChild(bubble);

    // Insert before typing indicator if it's in the container
    const typingEl = container.querySelector('#typing-indicator');
    if (typingEl) {
      container.insertBefore(wrapper, typingEl);
    } else {
      container.appendChild(wrapper);
    }
    this.scrollToBottom();

    return wrapper;
  },

  // ── Send a message to the active group ───────────────────────────
  sendGroupMessage() {
    const input = document.getElementById('message-input');
    if (!input || !this.activeGroup) return;

    const text = input.value.trim();
    if (!text) return;

    if (this.eventSource) {
      showToast('Aguarde a resposta atual terminar.', 'warning');
      return;
    }

    const message = text;
    input.value   = '';
    input.style.height = 'auto';

    // Append user message locally
    const now = new Date().toISOString().replace('T', ' ').split('.')[0];
    this.appendGroupMessage({ sender_type: 'user', content: message, created_at: now });
    this.scrollToBottom();

    // Show typing
    if (window.ChatManager) ChatManager.showTyping();

    const params = new URLSearchParams({
      group_stream: '1',
      group_id: this.activeGroup.id,
      message,
    });

    const url = `api/chat.php?${params.toString()}`;
    let streamBubbles = {};

    this.eventSource = new EventSource(url);

    this.eventSource.onopen = () => {
      if (window.ChatManager) ChatManager.hideTyping();
    };

    this.eventSource.onmessage = (e) => {
      if (e.data === '[DONE]') {
        this.eventSource.close();
        this.eventSource = null;

        Object.entries(streamBubbles).forEach(([charId, b]) => {
          if (b.el) {
            b.el.querySelector('.streaming-cursor')?.remove();
            // Add audio button to finalized streaming bubble
            if (b.text) {
              const audioBtn = this._createGroupAudioBtn(b.text, charId);
              const bubble = b.el.querySelector('.msg-bubble');
              const timeEl = bubble?.querySelector('.msg-time');
              if (bubble && timeEl) bubble.insertBefore(audioBtn, timeEl);
              else if (bubble) bubble.appendChild(audioBtn);
            }
          }
        });
        streamBubbles = {};

        this.updateGroupLastMessage(this.activeGroup.id, message);
        return;
      }

      try {
        const parsed = JSON.parse(e.data);

        if (parsed.error) {
          if (window.ChatManager) ChatManager.hideTyping();
          showToast(parsed.error, 'error');
          this.eventSource.close();
          this.eventSource = null;
          return;
        }

        if (parsed.typing_char) {
          // Show which character is typing with their name
          const typingAvatar = document.getElementById('typing-avatar');
          if (typingAvatar) {
            if (parsed.character_avatar) {
              typingAvatar.innerHTML = `<img src="${escHtml(parsed.character_avatar)}" alt="">`;
            } else {
              typingAvatar.textContent = (parsed.character_name || 'IA').slice(0, 2).toUpperCase();
            }
          }
          if (window.ChatManager) ChatManager.showTyping();
          return;
        }

        if (parsed.content && parsed.char) {
          const charId = parsed.char.character_id;
          if (window.ChatManager) ChatManager.hideTyping();

          if (!streamBubbles[charId]) {
            streamBubbles[charId] = {
              el:          this.startGroupStreamBubble(charId, parsed.char.character_name, parsed.char.character_avatar),
              text:        '',
              replyToName:    parsed.char.reply_to_name || null,
              replyToSnippet: parsed.char.reply_to_snippet || null,
            };

            // Show reply-to quote in bubble if available
            if (streamBubbles[charId].el && parsed.char.reply_to_name && parsed.char.reply_to_snippet) {
              const bubble = streamBubbles[charId].el.querySelector('.msg-bubble');
              if (bubble) {
                const replyEl = document.createElement('div');
                replyEl.className = 'group-reply-quote';
                replyEl.innerHTML = `<span class="reply-quote-name">${escHtml(parsed.char.reply_to_name)}</span><span class="reply-quote-text">${escHtml(parsed.char.reply_to_snippet)}</span>`;
                const nameEl = bubble.querySelector('.msg-sender-name');
                if (nameEl && nameEl.nextSibling) {
                  bubble.insertBefore(replyEl, nameEl.nextSibling);
                } else {
                  bubble.insertBefore(replyEl, bubble.firstChild);
                }
              }
            }
          }

          streamBubbles[charId].text += parsed.content;
          if (streamBubbles[charId].el) {
            const contentEl = streamBubbles[charId].el.querySelector('.msg-content');
            if (contentEl) {
              contentEl.innerHTML =
                parseMarkdown(streamBubbles[charId].text) +
                '<span class="streaming-cursor" style="display:inline-block"></span>';
            }
            this.scrollToBottom();
          }
        }
      } catch (_) {}
    };

    this.eventSource.onerror = () => {
      if (window.ChatManager) ChatManager.hideTyping();
      if (this.eventSource) {
        this.eventSource.close();
        this.eventSource = null;
      }
      showToast('Erro na conexão com a IA.', 'error');
    };
  },

  // ── Show typing for a group character ────────────────────────────
  showGroupTyping(charName, charAvatar) {
    if (window.ChatManager) ChatManager.showTyping();
  },

  // ── Create an audio button for a group character message ─────────
  _createGroupAudioBtn(msgText, charId) {
    const charData = (window.ChatManager?.characters || []).find(c => Number(c.id) === Number(charId));
    const voiceConfig = {
      type: charData?.voice_type || 'feminina_adulta',
      speed: charData?.voice_speed || 0.95,
      pitch: charData?.voice_pitch || 1.05,
      elevenLabsId: charData?.elevenlabs_id || '',
    };
    const btn = document.createElement('button');
    btn.className = 'msg-audio-btn';
    btn.textContent = '\uD83D\uDD0A Ouvir';
    btn.addEventListener('click', function() {
      if (window.AudioManager) AudioManager.speak(msgText, voiceConfig, btn);
    });
    return btn;
  },

  // ── Update last message in local groups array ────────────────────
  updateGroupLastMessage(groupId, text) {
    const g = this.groups.find(g => g.id == groupId);
    if (g) {
      g.last_message      = text;
      g.last_message_time = new Date().toISOString();
    }
  },

  // ── Clear group chat ─────────────────────────────────────────────
  async clearGroupChat() {
    if (!this.activeGroup) return;
    if (!confirmAction('Apagar toda a conversa do grupo "' + this.activeGroup.name + '"?')) return;

    try {
      const data = await apiPost('api/groups.php', { action: 'clear', group_id: this.activeGroup.id });
      if (data.error) { showToast(data.error, 'error'); return; }

      const container = document.getElementById('messages-container');
      if (container) container.innerHTML = '<div class="text-center text-muted" style="padding:32px;font-size:.9rem;">Nenhuma mensagem ainda. Diga olá! 👋</div>';

      if (window.ChatManager) ChatManager.lastDateLabel = '';
      showToast('Conversa apagada.', 'success');
    } catch (e) {
      showToast('Erro ao apagar conversa.', 'error');
    }
  },

  // ── Show welcome screen ──────────────────────────────────────────
  _showWelcome() {
    if (window.AudioManager) AudioManager.stop();
    const welcome  = document.getElementById('welcome-screen');
    const chatView = document.getElementById('chat-view');
    if (chatView) chatView.style.display = 'none';
    if (welcome)  welcome.style.display  = 'flex';
  },

  // ── Scroll chat to bottom ────────────────────────────────────────
  scrollToBottom() {
    const container = document.getElementById('messages-container');
    if (container) container.scrollTop = container.scrollHeight;
  },
};

window.GroupManager = GroupManager;

// ── Init on DOMContentLoaded ──────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  GroupManager.init();
});
