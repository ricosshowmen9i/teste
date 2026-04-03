/**
 * WhatsappJUJU — Painel Admin (admin.js)
 */

const Admin = (() => {
  let currentPanel = 'ai';

  // ── Abrir modal ───────────────────────────────────────────
  function open() {
    $('#admin-modal').addClass('open');
    $('body').css('overflow', 'hidden');
    switchPanel('ai');
    loadAI();
  }

  // ── Fechar modal ──────────────────────────────────────────
  function close() {
    $('#admin-modal').removeClass('open');
    $('body').css('overflow', '');
  }

  // ── Troca de painel ───────────────────────────────────────
  function switchPanel(panel) {
    currentPanel = panel;
    $('.admin-nav-item').removeClass('active');
    $(`.admin-nav-item[data-panel="${panel}"]`).addClass('active');
    $('.admin-panel').removeClass('active');
    $(`#admin-panel-${panel}`).addClass('active');

    if (panel === 'users')  loadUsers();
    if (panel === 'stats')  loadStats();
  }

  // ── URLs padrão por provider ──────────────────────────────
  const PROVIDER_URLS = {
    openrouter: 'https://openrouter.ai/api/v1',
    groq:       'https://api.groq.com/openai/v1',
    gemini:     'https://generativelanguage.googleapis.com',
    ollama:     'http://localhost:11434/api',
    openai:     'https://api.openai.com/v1',
    mistral:    'https://api.mistral.ai/v1',
    together:   'https://api.together.xyz/v1',
  };

  const PROVIDER_MODELS = {
    openrouter: [
      'nvidia/nemotron-nano-12b-v2-vl:free',
      'nvidia/nemotron-3-super-120b-a12b:free',
      'stepfun/step-3.5-flash:free',
      'qwen/qwen3.6-plus-preview:free',
      'arcee-ai/trinity-large-preview:free',
      'z-ai/glm-4.5-air:free',
      'nvidia/nemotron-3-nano-30b-a3b:free',
      'arcee-ai/trinity-mini:free',
      'nvidia/nemotron-nano-9b-v2:free',
      'minimax/minimax-m2.5:free',
      'qwen/qwen3-coder:free',
      'qwen/qwen3.6-plus:free',
      'google/gemma-3-27b-it:free',
      'google/gemma-3-4b-it:free',
      'nvidia/llama-nemotron-embed-vl-1b-v2:free',
    ],
    groq:       ['llama3-8b-8192', 'llama3-70b-8192', 'mixtral-8x7b-32768', 'gemma-7b-it'],
    gemini:     ['gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-pro'],
    ollama:     ['llama3', 'mistral', 'phi3', 'gemma2'],
    openai:     ['gpt-4o-mini', 'gpt-4o', 'gpt-3.5-turbo'],
    mistral:    ['mistral-small-latest', 'mistral-medium-latest', 'mistral-large-latest'],
    together:   ['togethercomputer/llama-3-8b-instruct', 'mistralai/Mixtral-8x7B-Instruct-v0.1'],
  };

  // ── Carregar config de IA ─────────────────────────────────
  function loadAI() {
    $.get('api/admin.php', { action: 'get_ai' }, data => {
      if (!data.success || !data.config) return;
      const cfg = data.config;
      if (cfg.provider) $('#ai-provider').val(cfg.provider);
      if (cfg.base_url) $('#ai-base-url').val(cfg.base_url);
      if (cfg.model)    $('#ai-model').val(cfg.model);
      updateModelSuggestions(cfg.provider || 'openrouter');
      updateConnectionStatus('idle');

      // Aplica modo de seleção de modelo
      const mode = cfg.model_mode || 'random';
      $(`input[name="ai-model-mode"][value="${mode}"]`).prop('checked', true);
      updateModelModeUI(mode);
    }).fail(() => {
      console.warn('[Admin] loadAI falhou — usando valores padrão do formulário');
    });
  }

  // ── Atualiza UI conforme o modo de modelo ────────────────
  function updateModelModeUI(mode) {
    const fixedMode = (mode === 'fixed');
    $('#ai-model').prop('disabled', !fixedMode).css('opacity', fixedMode ? 1 : 0.5);
  }

  // ── Salvar IA ─────────────────────────────────────────────
  function saveAI() {
    const $btn = $('#ai-save-btn').addClass('loading');
    const mode = $('input[name="ai-model-mode"]:checked').val() || 'random';
    const provider = $('#ai-provider').val() || 'openrouter';

    const payload = {
      action:     'save_ai',
      provider:   provider,
      api_key:    $('#ai-api-key').val(),
      base_url:   $('#ai-base-url').val() || 'https://openrouter.ai/api/v1',
      model:      $('#ai-model').val() || 'mistralai/mistral-7b-instruct:free',
      model_mode: mode,
    };

    console.log('[Admin] saveAI payload:', { ...payload, api_key: payload.api_key ? '***' : '(empty)' });

    $.post('api/admin.php', payload, data => {
      $btn.removeClass('loading');
      console.log('[Admin] saveAI response:', data);
      if (data.success) {
        App.showToast('Configuração salva!', 'success');
        $('#ai-api-key').val('');
      } else {
        App.showToast(data.error || data.message || 'Erro ao salvar configurações de IA', 'error');
      }
    }).fail((xhr, status, err) => {
      $btn.removeClass('loading');
      console.error('[Admin] saveAI FAIL:', status, err, xhr.responseText);
      let errMsg = 'Erro de conexão ao salvar';
      try {
        const resp = JSON.parse(xhr.responseText);
        errMsg = resp.error || resp.message || errMsg;
      } catch (e) { /* ignorar */ }
      App.showToast(errMsg, 'error');
    });
  }

  // ── Testar conexão ────────────────────────────────────────
  function testAI() {
    updateConnectionStatus('testing');
    const $btn = $('#ai-test-btn').addClass('loading');
    const mode = $('input[name="ai-model-mode"]:checked').val() || 'random';

    // Salva primeiro, depois testa
    $.post('api/admin.php', {
      action:     'save_ai',
      provider:   $('#ai-provider').val() || 'openrouter',
      api_key:    $('#ai-api-key').val(),
      base_url:   $('#ai-base-url').val() || 'https://openrouter.ai/api/v1',
      model:      $('#ai-model').val() || 'mistralai/mistral-7b-instruct:free',
      model_mode: mode,
    }, () => {
      $.post('api/admin.php', { action: 'test_ai' }, data => {
        $btn.removeClass('loading');
        if (data.success) {
          updateConnectionStatus('ok');
          App.showToast(data.message, 'success');
        } else {
          updateConnectionStatus('error');
          App.showToast(data.message || 'Erro na conexão com a IA', 'error');
        }
      }).fail((xhr) => {
        $btn.removeClass('loading');
        updateConnectionStatus('error');
        let msg = 'Erro ao testar conexão';
        try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
        App.showToast(msg, 'error');
      });
    }).fail((xhr) => {
      $btn.removeClass('loading');
      updateConnectionStatus('error');
      let msg = 'Erro ao salvar antes de testar';
      try { msg = JSON.parse(xhr.responseText).error || msg; } catch(e) {}
      App.showToast(msg, 'error');
    });
  }

  function updateConnectionStatus(state) {
    const $dot  = $('#connection-dot');
    const $text = $('#connection-text');
    const map = {
      idle:    { cls: '',       text: 'Não testado' },
      testing: { cls: 'yellow', text: 'Testando...' },
      ok:      { cls: 'green',  text: 'Conectado' },
      error:   { cls: 'red',    text: 'Erro' },
    };
    const s = map[state] || map.idle;
    $dot.attr('class', 'status-dot ' + s.cls);
    $text.text(s.text);
  }

  function updateModelSuggestions(provider) {
    const models  = PROVIDER_MODELS[provider] || [];
    const $datalist = $('#ai-model-list');
    $datalist.empty();
    models.forEach(m => $datalist.append(`<option value="${m}">`));
  }

  // ── Usuários ──────────────────────────────────────────────
  let usersSearchTimer = null;

  function loadUsers(search = '') {
    const $table = $('#users-tbody');
    $table.html('<tr><td colspan="7" style="text-align:center;padding:20px"><i class="fas fa-spinner fa-spin"></i></td></tr>');

    $.get('api/users.php', { action: 'list', search }, data => {
      if (!data.success) {
        $table.html('<tr><td colspan="7" style="color:#f44336;padding:20px">Erro ao carregar</td></tr>');
        return;
      }

      if (!data.users.length) {
        $table.html('<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-secondary)">Nenhum usuário encontrado</td></tr>');
        return;
      }

      $table.empty();
      data.users.forEach(u => {
        const avatar = u.avatar
          ? `<img src="${App.escHtml(u.avatar)}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">`
          : `<div style="width:32px;height:32px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px">${App.escHtml(u.name.charAt(0).toUpperCase())}</div>`;

        const roleBadge = `<span class="role-badge ${u.role}">${u.role}</span>`;
        const createdAt = new Date(u.created_at).toLocaleDateString('pt-BR');

        $table.append(`
          <tr>
            <td>${avatar}</td>
            <td>${App.escHtml(u.name)}</td>
            <td>${App.escHtml(u.email)}</td>
            <td>${roleBadge}</td>
            <td>${App.escHtml(u.status || 'online')}</td>
            <td>${createdAt}</td>
            <td>
              <button class="btn btn-secondary btn-sm" onclick="Admin.editUser(${u.id})" style="padding:4px 10px;font-size:12px;margin-right:4px"><i class="fas fa-pen"></i></button>
              <button class="btn btn-danger btn-sm" onclick="Admin.deleteUser(${u.id},'${App.escHtml(u.name)}')" style="padding:4px 10px;font-size:12px"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
        `);
      });
    });
  }

  function editUser(id) {
    $.get('api/users.php', { action: 'get', id }, data => {
      if (!data.success) { App.showToast('Usuário não encontrado', 'error'); return; }
      const u = data.user;
      $('#edit-user-id').val(u.id);
      $('#edit-user-name').val(u.name);
      $('#edit-user-email').val(u.email);
      $('#edit-user-role').val(u.role);
      $('#edit-user-password').val('');
      $('#user-edit-modal').addClass('open');
    });
  }

  function saveUser() {
    const $btn = $('#save-user-btn').addClass('loading');
    $.post('api/users.php', {
      action:   'update',
      id:       $('#edit-user-id').val(),
      name:     $('#edit-user-name').val(),
      email:    $('#edit-user-email').val(),
      role:     $('#edit-user-role').val(),
      password: $('#edit-user-password').val(),
    }, data => {
      $btn.removeClass('loading');
      if (data.success) {
        App.showToast('Usuário atualizado!', 'success');
        $('#user-edit-modal').removeClass('open');
        loadUsers();
      } else {
        App.showToast(data.error || 'Erro ao salvar', 'error');
      }
    });
  }

  function createUser() {
    $('#edit-user-id').val('');
    $('#edit-user-name').val('');
    $('#edit-user-email').val('');
    $('#edit-user-role').val('user');
    $('#edit-user-password').val('');
    $('#user-edit-modal').addClass('open');
  }

  function saveNewUser() {
    const id = $('#edit-user-id').val();
    if (id) { saveUser(); return; }

    const $btn = $('#save-user-btn').addClass('loading');
    $.post('api/users.php', {
      action:   'create',
      name:     $('#edit-user-name').val(),
      email:    $('#edit-user-email').val(),
      role:     $('#edit-user-role').val(),
      password: $('#edit-user-password').val(),
    }, data => {
      $btn.removeClass('loading');
      if (data.success) {
        App.showToast('Usuário criado!', 'success');
        $('#user-edit-modal').removeClass('open');
        loadUsers();
      } else {
        App.showToast(data.error || 'Erro ao criar', 'error');
      }
    });
  }

  function deleteUser(id, name) {
    if (!confirm(`Deletar usuário "${name}"?`)) return;
    $.post('api/users.php', { action: 'delete', id }, data => {
      if (data.success) {
        App.showToast('Usuário deletado!', 'success');
        loadUsers();
      } else {
        App.showToast(data.error || 'Erro ao deletar', 'error');
      }
    });
  }

  // ── Estatísticas ──────────────────────────────────────────
  function loadStats() {
    $.get('api/users.php', { action: 'stats' }, data => {
      if (!data.success) return;
      $('#stat-users').text(data.total_users);
      $('#stat-chars').text(data.total_chars);
      $('#stat-msgs').text(data.today_msgs);
      $('#stat-provider').text(data.ai_provider + ' / ' + data.ai_model);

      const $logins = $('#last-logins');
      $logins.empty();
      (data.last_logins || []).forEach(u => {
        $logins.append(`
          <tr>
            <td>${App.escHtml(u.name)}</td>
            <td>${App.escHtml(u.email)}</td>
            <td>${App.formatTime(u.last_seen)}</td>
          </tr>
        `);
      });
    });
  }

  // ── Bind ──────────────────────────────────────────────────
  $(document).ready(() => {
    $('#btn-close-admin, #admin-modal > .modal-overlay').on('click', function(e) {
      if (e.target === this || $(e.target).is('#btn-close-admin')) close();
    });

    $(document).on('click', '.admin-nav-item', function() {
      switchPanel($(this).data('panel'));
    });

    // IA
    $('#ai-save-btn').on('click', saveAI);
    $('#ai-test-btn').on('click', testAI);

    $('#ai-provider').on('change', function() {
      const p = $(this).val();
      $('#ai-base-url').val(PROVIDER_URLS[p] || '');
      updateModelSuggestions(p);
      updateConnectionStatus('idle');
    });

    // Toggle modo de seleção de modelo
    $('input[name="ai-model-mode"]').on('change', function() {
      updateModelModeUI($(this).val());
    });

    // Toggle API key visibility
    $('#toggle-api-key').on('click', function() {
      const $input = $('#ai-api-key');
      const show   = $input.attr('type') === 'password';
      $input.attr('type', show ? 'text' : 'password');
      $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });

    // Usuários
    $('#btn-new-user').on('click', createUser);
    $('#save-user-btn').on('click', saveNewUser);
    $('#btn-close-user-edit').on('click', () => $('#user-edit-modal').removeClass('open'));

    $('#users-search').on('input', function() {
      clearTimeout(usersSearchTimer);
      const q = $(this).val();
      usersSearchTimer = setTimeout(() => loadUsers(q), 300);
    });
  });

  return { open, close, editUser, deleteUser, saveUser: saveNewUser };
})();
