/**
 * SETE — admin.js
 * Admin panel functionality
 */

'use strict';

const AdminManager = {
  currentPanel: 'stats',
  users: [],

  init() {
    this.bindNav();
    this.showPanel('stats');
  },

  bindNav() {
    document.querySelectorAll('.admin-nav-item').forEach(item => {
      item.addEventListener('click', () => {
        const panel = item.dataset.panel;
        if (panel) this.showPanel(panel);
      });
    });

    // Close admin
    const closeBtn = document.getElementById('btn-close-admin');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => closeModal('modal-admin'));
    }
  },

  showPanel(name) {
    this.currentPanel = name;

    document.querySelectorAll('.admin-nav-item').forEach(item => {
      item.classList.toggle('active', item.dataset.panel === name);
    });

    document.querySelectorAll('.admin-panel').forEach(panel => {
      panel.classList.toggle('active', panel.id === 'admin-panel-' + name);
    });

    const titleEl = document.getElementById('admin-panel-title');
    const titles = {
      stats: '📊 Dashboard',
      config: '🤖 Configuração IA',
      users: '👥 Usuários',
      appearance: '🎨 Aparência',
    };
    if (titleEl) titleEl.textContent = titles[name] || name;

    switch (name) {
      case 'stats':      this.loadStats(); break;
      case 'config':     this.loadConfig(); break;
      case 'users':      this.loadUsers(); break;
      case 'appearance': this.loadAppearance(); break;
    }
  },

  // ── Stats ─────────────────────────────────────────────────────
  async loadStats() {
    try {
      const data = await apiGet('api/admin.php?action=stats');

      const set = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val ?? '—';
      };

      set('stat-users', data.user_count);
      set('stat-chars', data.char_count);
      set('stat-messages', data.today_messages);
      set('stat-provider', data.provider || '—');
      set('stat-model', data.model || '—');

      const tbody = document.getElementById('last-logins-tbody');
      if (tbody) {
        tbody.innerHTML = '';
        (data.last_logins || []).forEach(u => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${escHtml(u.name)}</td>
            <td>${escHtml(u.email)}</td>
            <td>${escHtml(u.last_login || '—')}</td>
          `;
          tbody.appendChild(tr);
        });
      }
    } catch (e) {
      showToast('Erro ao carregar estatísticas.', 'error');
    }
  },

  // ── AI Config ─────────────────────────────────────────────────
  async loadConfig() {
    try {
      const data = await apiGet('api/admin.php?action=config');
      const cfg  = data.config || {};

      const set = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.value = val ?? '';
      };

      set('cfg-provider', cfg.provider || 'openrouter');
      set('cfg-api-key', cfg.api_key || '');
      set('cfg-base-url', cfg.base_url || '');
      set('cfg-model', cfg.model || '');
      set('cfg-model-mode', cfg.model_mode || 'fixed');

      this.updateProviderDefaults(cfg.provider || 'openrouter', false);
      this.setConnectionStatus('idle');

    } catch (e) {
      showToast('Erro ao carregar configuração.', 'error');
    }

    // Bind events (idempotent)
    this.bindConfigEvents();
  },

  bindConfigEvents() {
    const providerSel = document.getElementById('cfg-provider');
    if (providerSel && !providerSel._bound) {
      providerSel._bound = true;
      providerSel.addEventListener('change', () => {
        this.updateProviderDefaults(providerSel.value, true);
      });
    }

    const saveBtn = document.getElementById('btn-save-config');
    if (saveBtn && !saveBtn._bound) {
      saveBtn._bound = true;
      saveBtn.addEventListener('click', () => this.saveConfig());
    }

    const testBtn = document.getElementById('btn-test-connection');
    if (testBtn && !testBtn._bound) {
      testBtn._bound = true;
      testBtn.addEventListener('click', () => this.testConnection());
    }

    const toggleApiKeyBtn = document.getElementById('btn-toggle-api-key');
    if (toggleApiKeyBtn && !toggleApiKeyBtn._bound) {
      toggleApiKeyBtn._bound = true;
      toggleApiKeyBtn.addEventListener('click', () => {
        const input = document.getElementById('cfg-api-key');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
      });
    }
  },

  setConnectionStatus(state, message = '') {
    const el = document.getElementById('ai-connection-status');
    if (!el) return;
    if (state === 'testing') {
      el.textContent = '🟡 Testando conexão...';
      return;
    }
    if (state === 'success') {
      el.textContent = `🟢 Conectado${message ? ' — ' + message : ''}`;
      return;
    }
    if (state === 'error') {
      el.textContent = `🔴 Erro${message ? ' — ' + message : ''}`;
      return;
    }
    el.textContent = '🟡 Não testado';
  },

  updateProviderDefaults(provider, updateFields) {
    const defaults = {
      openrouter: { url: 'https://openrouter.ai/api/v1',   model: 'openai/gpt-3.5-turbo' },
      groq:       { url: 'https://api.groq.com/openai/v1', model: 'llama3-8b-8192' },
      openai:     { url: 'https://api.openai.com/v1',       model: 'gpt-3.5-turbo' },
      mistral:    { url: 'https://api.mistral.ai/v1',       model: 'mistral-small-latest' },
      together:   { url: 'https://api.together.xyz/v1',     model: 'togethercomputer/llama-2-7b-chat' },
      ollama:     { url: 'http://localhost:11434/api',       model: 'llama3' },
      gemini:     { url: 'https://generativelanguage.googleapis.com/v1beta', model: 'gemini-1.5-flash' },
    };

    const d = defaults[provider];
    if (!d) return;

    const suggestions = {
      openrouter: ['openai/gpt-3.5-turbo', 'meta-llama/llama-3-8b-instruct:free', 'mistralai/mistral-7b-instruct:free'],
      groq:       ['llama3-8b-8192', 'mixtral-8x7b-32768', 'gemma-7b-it'],
      openai:     ['gpt-3.5-turbo', 'gpt-4o-mini', 'gpt-4o'],
      mistral:    ['mistral-small-latest', 'open-mistral-7b', 'mistral-medium-latest'],
      together:   ['togethercomputer/llama-2-7b-chat', 'mistralai/Mixtral-8x7B-Instruct-v0.1'],
      ollama:     ['llama3', 'mistral', 'gemma', 'phi3'],
      gemini:     ['gemini-1.5-flash', 'gemini-1.0-pro'],
    };

    const datalist = document.getElementById('model-suggestions');
    if (datalist) {
      datalist.innerHTML = '';
      (suggestions[provider] || []).forEach(m => {
        const opt = document.createElement('option');
        opt.value = m;
        datalist.appendChild(opt);
      });
    }

    if (updateFields) {
      const urlEl = document.getElementById('cfg-base-url');
      const mdlEl = document.getElementById('cfg-model');
      if (urlEl && !urlEl.value) urlEl.value = d.url;
      if (urlEl && updateFields) urlEl.value = d.url;
      if (mdlEl && updateFields) mdlEl.value = d.model;
    }
  },

  async saveConfig() {
    const get = (id) => document.getElementById(id)?.value?.trim() || '';

    const payload = {
      action:              'save_config',
      provider:            get('cfg-provider'),
      api_key:             get('cfg-api-key'),
      base_url:            get('cfg-base-url'),
      model:               get('cfg-model'),
      model_mode:          get('cfg-model-mode'),
    };

    try {
      const data = await apiPost('api/admin.php', payload);
      if (data.error) {
        showToast(data.error, 'error');
        return;
      }
      showToast('Configuração salva com sucesso!', 'success');
    } catch (e) {
      showToast('Erro ao salvar configuração.', 'error');
    }
  },

  async testConnection() {
    const btn = document.getElementById('btn-test-connection');
    if (btn) {
      btn.textContent = '⏳ Testando…';
      btn.disabled = true;
    }
    this.setConnectionStatus('testing');

    try {
      // Save first
      await this.saveConfig();

      const data = await apiPost('api/admin.php', { action: 'test_connection' });

      if (data.success) {
        showToast('✅ ' + (data.message || 'Conexão bem sucedida!'), 'success', 4000);
        this.setConnectionStatus('success', data.message || '');
      } else {
        showToast('❌ ' + (data.error || 'Falha na conexão.'), 'error', 5000);
        this.setConnectionStatus('error', data.error || '');
      }
    } catch (e) {
      showToast('Erro ao testar conexão.', 'error');
      this.setConnectionStatus('error', 'Falha inesperada');
    } finally {
      if (btn) {
        btn.textContent = '🔌 Testar Conexão';
        btn.disabled = false;
      }
    }
  },

  // ── Users ─────────────────────────────────────────────────────
  async loadUsers(filter = '') {
    try {
      const data = await apiGet('api/admin.php?action=users');
      this.users = data.users || [];
      this.renderUsersTable(filter);
    } catch (e) {
      showToast('Erro ao carregar usuários.', 'error');
    }

    this.bindUsersEvents();
  },

  renderUsersTable(filter = '') {
    const tbody = document.getElementById('users-tbody');
    if (!tbody) return;

    const filtered = filter
      ? this.users.filter(u =>
          u.name.toLowerCase().includes(filter.toLowerCase()) ||
          u.email.toLowerCase().includes(filter.toLowerCase())
        )
      : this.users;

    tbody.innerHTML = '';
    filtered.forEach(u => {
      const tr = document.createElement('tr');
      const avatarCell = u.avatar
        ? `<img src="${escHtml(u.avatar)}" alt="${escHtml(u.name)}" style="width:34px;height:34px;border-radius:50%;object-fit:cover;">`
        : `<div style="width:34px;height:34px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;">${escHtml((u.name || 'U').slice(0,2).toUpperCase())}</div>`;
      tr.innerHTML = `
        <td>${avatarCell}</td>
        <td>${escHtml(u.name)}</td>
        <td>${escHtml(u.email)}</td>
        <td><span class="badge badge-${u.role}">${escHtml(u.role)}</span></td>
        <td><span class="badge badge-${u.active ? 'active' : 'inactive'}">${u.active ? 'Ativo' : 'Inativo'}</span></td>
        <td>${escHtml((u.created_at || '').toString().slice(0, 16).replace('T', ' '))}</td>
        <td>${escHtml(u.last_login || '—')}</td>
        <td>
          <button class="btn btn-sm btn-outline btn-edit-user" data-id="${u.id}">✏️ Editar</button>
          <button class="btn btn-sm btn-danger btn-delete-user" data-id="${u.id}" style="margin-left:4px">🗑️</button>
        </td>
      `;

      tr.querySelector('.btn-edit-user').addEventListener('click', () => {
        this.openUserModal(u);
      });

      tr.querySelector('.btn-delete-user').addEventListener('click', () => {
        this.deleteUser(u);
      });

      tbody.appendChild(tr);
    });
  },

  bindUsersEvents() {
    const searchInput = document.getElementById('user-search');
    if (searchInput && !searchInput._bound) {
      searchInput._bound = true;
      searchInput.addEventListener('input', debounce((e) => {
        this.renderUsersTable(e.target.value);
      }, 200));
    }

    const newBtn = document.getElementById('btn-new-user');
    if (newBtn && !newBtn._bound) {
      newBtn._bound = true;
      newBtn.addEventListener('click', () => this.openUserModal(null));
    }

    const saveBtn = document.getElementById('btn-save-user');
    if (saveBtn && !saveBtn._bound) {
      saveBtn._bound = true;
      saveBtn.addEventListener('click', () => this.saveUser());
    }

    const cancelBtn = document.getElementById('btn-cancel-user');
    if (cancelBtn && !cancelBtn._bound) {
      cancelBtn._bound = true;
      cancelBtn.addEventListener('click', () => {
        document.getElementById('user-modal-overlay')?.classList.remove('open');
      });
    }
  },

  openUserModal(user) {
    const overlay = document.getElementById('user-modal-overlay');
    const title   = document.getElementById('user-modal-title');
    if (!overlay) return;

    const setVal = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.value = val ?? '';
    };

    if (user) {
      if (title) title.textContent = 'Editar Usuário';
      setVal('user-modal-id', user.id);
      setVal('user-modal-name', user.name);
      setVal('user-modal-email', user.email);
      setVal('user-modal-role', user.role);
      setVal('user-modal-active', user.active);
      setVal('user-modal-password', '');
    } else {
      if (title) title.textContent = 'Novo Usuário';
      setVal('user-modal-id', '');
      setVal('user-modal-name', '');
      setVal('user-modal-email', '');
      setVal('user-modal-role', 'user');
      setVal('user-modal-active', '1');
      setVal('user-modal-password', '');
    }

    overlay.classList.add('open');
  },

  async saveUser() {
    const id       = document.getElementById('user-modal-id')?.value;
    const name     = document.getElementById('user-modal-name')?.value.trim();
    const email    = document.getElementById('user-modal-email')?.value.trim();
    const role     = document.getElementById('user-modal-role')?.value;
    const active   = document.getElementById('user-modal-active')?.value;
    const password = document.getElementById('user-modal-password')?.value;

    if (!name || !email) {
      showToast('Nome e email são obrigatórios.', 'error');
      return;
    }

    const payload = {
      action: id ? 'update_user' : 'create_user',
      id: id || undefined,
      name, email, role, active,
    };

    if (password) payload.password = password;
    if (!id && !password) {
      showToast('Senha é obrigatória para novo usuário.', 'error');
      return;
    }

    try {
      const data = await apiPost('api/admin.php', payload);
      if (data.error) {
        showToast(data.error, 'error');
        return;
      }
      showToast(id ? 'Usuário atualizado!' : 'Usuário criado!', 'success');
      document.getElementById('user-modal-overlay')?.classList.remove('open');
      await this.loadUsers();
    } catch (e) {
      showToast('Erro ao salvar usuário.', 'error');
    }
  },

  async deleteUser(user) {
    if (!confirmAction(`Excluir usuário "${user.name}"? Esta ação não pode ser desfeita.`)) return;

    try {
      const data = await apiPost('api/admin.php', { action: 'delete_user', id: user.id });
      if (data.error) {
        showToast(data.error, 'error');
        return;
      }
      showToast('Usuário excluído.', 'success');
      await this.loadUsers();
    } catch (e) {
      showToast('Erro ao excluir usuário.', 'error');
    }
  },

  async loadAppearance() {
    try {
      const data = await apiGet('api/admin.php?action=config');
      const logo = data.config?.app_logo;
      this.renderLogoPreview(logo);
    } catch (e) { /* ignore */ }

    const input = document.getElementById('logo-upload-input');
    if (input && !input._bound) {
      input._bound = true;
      input.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) this.uploadLogo(file);
        e.target.value = '';
      });
    }

    const btnRemove = document.getElementById('btn-remove-logo');
    if (btnRemove && !btnRemove._bound) {
      btnRemove._bound = true;
      btnRemove.addEventListener('click', () => this.removeLogo());
    }
  },

  renderLogoPreview(url) {
    const img   = document.getElementById('logo-preview-img');
    const empty = document.getElementById('logo-preview-empty');
    const btnRemove = document.getElementById('btn-remove-logo');
    if (!img) return;
    if (url) {
      img.src = url + '?t=' + Date.now();
      img.style.display = 'block';
      if (empty) empty.style.display = 'none';
      if (btnRemove) btnRemove.style.display = '';
    } else {
      img.style.display = 'none';
      if (empty) empty.style.display = '';
      if (btnRemove) btnRemove.style.display = 'none';
    }
  },

  async uploadLogo(file) {
    const fd = new FormData();
    fd.append('logo', file);
    fd.append('action', 'upload_logo');
    try {
      const data = await apiPostFile('api/admin.php', fd);
      if (data.error) { showToast(data.error, 'error'); return; }
      showToast('Logo atualizado com sucesso!', 'success');
      this.renderLogoPreview(data.logo);
    } catch (e) {
      showToast('Erro ao enviar logo.', 'error');
    }
  },

  async removeLogo() {
    try {
      const data = await apiPost('api/admin.php', { action: 'save_config', app_logo: '' });
      showToast('Logo removido.', 'success');
      this.renderLogoPreview(null);
    } catch (e) {
      showToast('Erro ao remover logo.', 'error');
    }
  },
};

window.AdminManager = AdminManager;
