/**
 * WhatsappJUJU — Modal de Contatos (contacts.js)
 */

const Contacts = (() => {
  let searchTimer = null;

  // ── Abrir modal ───────────────────────────────────────────
  function open() {
    $('#contacts-modal').addClass('open');
    $('body').css('overflow', 'hidden');
    load();
  }

  // ── Fechar modal ──────────────────────────────────────────
  function close() {
    $('#contacts-modal').removeClass('open');
    $('body').css('overflow', '');
  }

  // ── Carregar lista ────────────────────────────────────────
  function load(search = '') {
    const $list = $('#contacts-list');
    $list.html('<div style="text-align:center;padding:30px;color:var(--text-secondary)"><i class="fas fa-spinner fa-spin"></i></div>');

    $.get('api/chat.php', { action: 'conversations' }, data => {
      if (!data.success) {
        $list.html('<div style="text-align:center;padding:30px;color:var(--text-secondary)">Erro ao carregar contatos.</div>');
        return;
      }

      let convs = data.conversations || [];

      if (search) {
        const q = search.toLowerCase();
        convs = convs.filter(c => c.character_name.toLowerCase().includes(q));
      }

      if (convs.length === 0) {
        $list.html('<div style="text-align:center;padding:30px;color:var(--text-secondary)"><i class="fas fa-robot" style="font-size:32px;margin-bottom:8px;display:block;opacity:0.4"></i>Nenhum personagem criado.<br>Clique em + para criar.</div>');
        return;
      }

      $list.empty();
      convs.forEach(conv => renderCard(conv, $list));
    }).fail(() => {
      $list.html('<div style="text-align:center;padding:30px;color:#f44336">Erro ao carregar. Tente novamente.</div>');
    });
  }

  // ── Renderiza card ────────────────────────────────────────
  function renderCard(conv, $container) {
    const esc   = App.escHtml;
    const name  = esc(conv.character_name);
    const lastMsg = conv.last_message
      ? (conv.last_message_type === 'image' ? '📷 Imagem'
        : conv.last_message_type === 'file'  ? '📎 Arquivo'
        : esc(conv.last_message.substring(0, 50)))
      : '<em>Sem mensagens</em>';
    const timeStr = conv.last_message_at ? App.formatTime(conv.last_message_at) : '';
    const unread  = parseInt(conv.unread_count) || 0;

    const avatarHtml = conv.character_avatar
      ? `<div class="contact-avatar"><img src="${esc(conv.character_avatar)}" alt="${name}"></div>`
      : `<div class="contact-avatar">${esc(conv.character_name.charAt(0).toUpperCase())}</div>`;

    const badgeHtml = unread > 0
      ? `<span class="unread-badge">${unread > 99 ? '99+' : unread}</span>`
      : '';

    const $card = $(`
      <div class="contact-card" data-conv-id="${conv.id}" data-char-id="${conv.character_id}">
        <div class="contact-card-top">
          ${avatarHtml}
          <div class="contact-info">
            <div class="contact-name">${name}</div>
            <div class="contact-last-msg">${lastMsg}</div>
          </div>
          <div class="contact-meta">
            <span class="contact-time">${timeStr}</span>
            ${badgeHtml}
          </div>
        </div>
        <div class="contact-card-actions">
          <button class="contact-action-btn btn-open" data-conv-id="${conv.id}">
            <i class="fas fa-comment"></i> Abrir
          </button>
          <button class="contact-action-btn btn-edit" data-char-id="${conv.character_id}">
            <i class="fas fa-pen"></i> Editar
          </button>
          <button class="contact-action-btn btn-del" data-char-id="${conv.character_id}">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>
    `);

    // Eventos
    $card.find('.btn-open').on('click', e => {
      e.stopPropagation();
      openChat(conv);
    });

    $card.find('.btn-edit').on('click', e => {
      e.stopPropagation();
      Characters.edit(conv.character_id);
    });

    $card.find('.btn-del').on('click', e => {
      e.stopPropagation();
      deleteCharacter(conv.character_id, name);
    });

    // Clique no card abre o chat
    $card.on('click', () => openChat(conv));

    $container.append($card);
  }

  // ── Abrir chat ────────────────────────────────────────────
  function openChat(conv) {
    close();
    App.openConversation(conv.id, conv);
  }

  // ── Deletar personagem ────────────────────────────────────
  function deleteCharacter(charId, name) {
    if (!confirm(`Deletar o personagem "${name}" e todo seu histórico?`)) return;

    $.post('api/characters.php', { action: 'delete', id: charId }, data => {
      if (data.success) {
        App.showToast('Personagem deletado!', 'success');
        load();
        // Se estava aberto, volta para boas-vindas
        if (App.currentCharacter && App.currentCharacter.character_id == charId) {
          $('#active-chat').hide();
          $('#welcome-screen').show();
        }
      } else {
        App.showToast(data.error || 'Erro ao deletar', 'error');
      }
    });
  }

  // ── Atualizar badges sem abrir modal ─────────────────────
  function refreshBadges() {
    if (!$('#contacts-modal').hasClass('open')) return;
    load($('#contacts-search').val());
  }

  // ── Bind ──────────────────────────────────────────────────
  $(document).ready(() => {
    // Overlay fecha modal
    $('#contacts-overlay').on('click', close);
    $('#btn-close-contacts').on('click', close);

    // Busca em tempo real
    $('#contacts-search').on('input', function() {
      clearTimeout(searchTimer);
      const q = $(this).val();
      searchTimer = setTimeout(() => load(q), 300);
    });

    // Novo personagem
    $('#btn-new-character').on('click', () => {
      Characters.create();
    });
  });

  return { open, close, load, refreshBadges };
})();
