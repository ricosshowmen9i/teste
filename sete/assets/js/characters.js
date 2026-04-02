/**
 * WhatsappJUJU — Modal de Criação e Edição de Personagens (characters.js)
 */

const Characters = (() => {
  let editingId     = null;
  let currentAvatar = null;

  // ── Abrir para criar ─────────────────────────────────────
  function create() {
    editingId     = null;
    currentAvatar = null;
    resetForm();
    $('#char-modal-title').text('Novo Personagem');
    openModal();
  }

  // ── Abrir para editar ─────────────────────────────────────
  function edit(charId) {
    editingId = charId;
    resetForm();
    $('#char-modal-title').text('Editar Personagem');

    $.get('api/characters.php', { action: 'get', id: charId }, data => {
      if (!data.success) {
        App.showToast('Erro ao carregar personagem', 'error');
        return;
      }
      fillForm(data.character);
      openModal();
    });
  }

  // ── Preenche form ─────────────────────────────────────────
  function fillForm(char) {
    $('#char-name').val(char.name || '');
    $('#char-description').val(char.description || '');
    $('#char-personality').val(char.personality || '');
    $('#char-voice-example').val(char.voice_example || '');
    $('#char-bubble-color').val(char.bubble_color || '#dcf8c6');
    $('#char-voice-type').val(char.voice_type || 'feminina_adulta');
    $('#char-voice-speed').val(char.voice_speed || 1.0);
    $('#char-voice-pitch').val(char.voice_pitch || 1.0);
    $('#char-voice-speed-val').text(char.voice_speed || 1.0);
    $('#char-voice-pitch-val').text(char.voice_pitch || 1.0);
    $('#char-memory-context').val(char.memory_context || 20);
    $('#char-memory-val').text(char.memory_context || 20);
    $('#char-voice-enabled').prop('checked', !!parseInt(char.voice_enabled));
    $('#char-can-read').prop('checked', !!parseInt(char.can_read_files));
    $('#char-can-images').prop('checked', !!parseInt(char.can_generate_images));
    $('#char-long-memory').prop('checked', !!parseInt(char.long_memory));
    $('#char-auto-audio').prop('checked', !!parseInt(char.auto_audio));
    $('#char-elevenlabs').val(char.elevenlabs_voice_id || '');

    currentAvatar = char.avatar || null;
    updateAvatarPreview(char.avatar);
  }

  // ── Reseta form ───────────────────────────────────────────
  function resetForm() {
    $('#character-form')[0].reset();
    $('#char-bubble-color').val('#dcf8c6');
    $('#char-voice-speed').val(1.0);
    $('#char-voice-pitch').val(1.0);
    $('#char-voice-speed-val').text('1.0');
    $('#char-voice-pitch-val').text('1.0');
    $('#char-memory-context').val(20);
    $('#char-memory-val').text('20');
    $('#char-voice-enabled').prop('checked', true);
    $('#char-can-read').prop('checked', true);
    currentAvatar = null;
    updateAvatarPreview(null);
    switchTab('identity');
  }

  // ── Abre modal ────────────────────────────────────────────
  function openModal() {
    $('#character-modal').addClass('open');
    $('body').css('overflow', 'hidden');
  }

  // ── Fecha modal ───────────────────────────────────────────
  function closeModal() {
    $('#character-modal').removeClass('open');
    $('body').css('overflow', '');
  }

  // ── Tabs ──────────────────────────────────────────────────
  function switchTab(tab) {
    $('.char-tab').removeClass('active');
    $('.char-tab-panel').removeClass('active');
    $(`.char-tab[data-tab="${tab}"]`).addClass('active');
    $(`#char-panel-${tab}`).addClass('active');
  }

  // ── Preview do avatar ─────────────────────────────────────
  function updateAvatarPreview(url) {
    if (url) {
      $('#char-avatar-preview').html(`<img src="${App.escHtml(url)}" alt="Avatar"><div class="avatar-preview-overlay"><i class="fas fa-camera"></i></div>`);
    } else {
      $('#char-avatar-preview').html(`<i class="fas fa-robot avatar-preview-icon"></i><div class="avatar-preview-overlay"><i class="fas fa-camera"></i></div>`);
    }
  }

  // ── Salvar ────────────────────────────────────────────────
  function save() {
    const name = $('#char-name').val().trim();
    if (!name) {
      App.showToast('Nome é obrigatório!', 'error');
      switchTab('identity');
      $('#char-name').focus();
      return;
    }

    const $btn = $('#char-save-btn').addClass('loading');

    const formData = {
      action:              editingId ? 'update' : 'create',
      name:                name,
      description:         $('#char-description').val().trim(),
      personality:         $('#char-personality').val().trim(),
      voice_example:       $('#char-voice-example').val().trim(),
      bubble_color:        $('#char-bubble-color').val(),
      voice_type:          $('#char-voice-type').val(),
      voice_speed:         $('#char-voice-speed').val(),
      voice_pitch:         $('#char-voice-pitch').val(),
      memory_context:      $('#char-memory-context').val(),
      voice_enabled:       $('#char-voice-enabled').is(':checked') ? 1 : 0,
      can_read_files:      $('#char-can-read').is(':checked') ? 1 : 0,
      can_generate_images: $('#char-can-images').is(':checked') ? 1 : 0,
      long_memory:         $('#char-long-memory').is(':checked') ? 1 : 0,
      auto_audio:          $('#char-auto-audio').is(':checked') ? 1 : 0,
      elevenlabs_voice_id: $('#char-elevenlabs').val().trim(),
    };

    if (currentAvatar) {
      formData.avatar = currentAvatar;
    }

    if (editingId) {
      formData.id = editingId;
    }

    $.post('api/characters.php', formData, data => {
      $btn.removeClass('loading');
      if (data.success) {
        App.showToast(editingId ? 'Personagem atualizado!' : 'Personagem criado!', 'success');
        closeModal();
        Contacts.load();
      } else {
        App.showToast(data.error || 'Erro ao salvar', 'error');
      }
    }).fail(() => {
      $btn.removeClass('loading');
      App.showToast('Erro de conexão', 'error');
    });
  }

  // ── Testar voz ────────────────────────────────────────────
  function testVoice() {
    const config = {
      voice_type:  $('#char-voice-type').val(),
      voice_speed: parseFloat($('#char-voice-speed').val()),
      voice_pitch: parseFloat($('#char-voice-pitch').val()),
    };
    const name = $('#char-name').val().trim() || 'Personagem';
    Audio.speakText(`Olá! Eu sou ${name}. Essa é a minha voz.`, config);
  }

  // ── Upload de avatar ──────────────────────────────────────
  function uploadAvatar(file) {
    if (!file) return;

    const formData = new FormData();
    formData.append('avatar', file);
    formData.append('type', 'avatar');

    $.ajax({
      url:         'api/upload.php',
      type:        'POST',
      data:        formData,
      processData: false,
      contentType: false,
      success: data => {
        if (data.success) {
          currentAvatar = data.url;
          updateAvatarPreview(data.url);
          App.showToast('Foto atualizada!', 'success');
        } else {
          App.showToast(data.error || 'Erro no upload', 'error');
        }
      },
      error: () => App.showToast('Erro ao enviar foto', 'error'),
    });
  }

  // ── Bind ──────────────────────────────────────────────────
  $(document).ready(() => {
    // Fechar modal
    $('#btn-close-char-modal, #char-cancel-btn').on('click', closeModal);
    $('#character-modal > .modal-overlay').on('click', function(e) {
      if (e.target === this) closeModal();
    });

    // Tabs
    $(document).on('click', '.char-tab', function() {
      switchTab($(this).data('tab'));
    });

    // Salvar
    $('#char-save-btn').on('click', save);

    // Sliders
    $('#char-voice-speed').on('input', function() { $('#char-voice-speed-val').text(parseFloat(this.value).toFixed(1)); });
    $('#char-voice-pitch').on('input', function() { $('#char-voice-pitch-val').text(parseFloat(this.value).toFixed(1)); });
    $('#char-memory-context').on('input', function() { $('#char-memory-val').text(this.value); });

    // Testar voz
    $('#char-test-voice-btn').on('click', testVoice);

    // Upload de avatar (clique no preview)
    $('#char-avatar-preview').on('click', () => $('#char-avatar-file').click());
    $('#char-avatar-file').on('change', function() {
      if (this.files && this.files[0]) {
        uploadAvatar(this.files[0]);
      }
    });
  });

  return { create, edit, closeModal };
})();
