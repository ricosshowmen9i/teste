<?php

session_start();

require_once __DIR__ . '/db/init.php';

$isLoggedIn     = !empty($_SESSION['user_id']);
$forcePasswordChange = false;
$currentUser    = null;

if ($isLoggedIn) {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND active = 1 LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch();

        if (!$currentUser) {
            session_destroy();
            $isLoggedIn = false;
        } else {
            $forcePasswordChange = (bool)$currentUser['force_password_change'];
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

$theme = $currentUser['theme'] ?? 'green';

// Load app config for logo
$appLogo = null;
try {
    $dbForLogo = getDB();
    $cfgRow = $dbForLogo->query("SELECT app_logo FROM ai_config ORDER BY id DESC LIMIT 1")->fetch();
    $appLogo = $cfgRow['app_logo'] ?? null;
} catch (Exception $e) { /* ignore */ }

?><!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="What JUJU — Chat com personagens de IA">
  <title>What JUJU</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🤖</text></svg>">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body<?= !$isLoggedIn ? ' class="login-page"' : '' ?>>

<?php if (!$isLoggedIn): ?>
<!-- ═══ LOGIN PAGE ═══════════════════════════════════════════════ -->
<div class="login-card">
  <div class="login-logo">
    <?php if ($appLogo): ?>
      <img src="<?= htmlspecialchars($appLogo) ?>?t=<?= time() ?>" alt="Logo" class="login-logo-img">
    <?php else: ?>
      <div class="login-logo-emoji">🤖</div>
      <h1>What JUJU</h1>
    <?php endif; ?>
    <p>Converse com personagens de IA</p>
  </div>
  <form class="login-form" id="login-form" novalidate>
    <div class="form-group">
      <label for="login-email">Email</label>
      <input type="email" id="login-email" name="email" placeholder="seu@email.com" autocomplete="email" required>
    </div>
    <div class="form-group">
      <label for="login-password">Senha</label>
      <input type="password" id="login-password" name="password" placeholder="••••••••" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn-login" id="btn-login">Entrar</button>
    <div class="login-error" id="login-error"></div>
  </form>
</div>

<script>
document.getElementById('login-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn   = document.getElementById('btn-login');
  const errEl = document.getElementById('login-error');
  const email = document.getElementById('login-email').value.trim();
  const pass  = document.getElementById('login-password').value;

  errEl.style.display = 'none';
  btn.disabled = true;
  btn.textContent = 'Entrando…';

  try {
    const fd = new FormData();
    fd.append('action', 'login');
    fd.append('email', email);
    fd.append('password', pass);

    const res  = await fetch('api/auth.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.error) {
      errEl.textContent = data.error;
      errEl.style.display = 'block';
    } else {
      location.reload();
    }
  } catch (err) {
    errEl.textContent = 'Erro de rede. Tente novamente.';
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Entrar';
  }
});
</script>

<?php elseif ($forcePasswordChange): ?>
<!-- ═══ FORCE PASSWORD CHANGE ════════════════════════════════════ -->
<div class="force-pw-card" style="margin:auto;margin-top:10vh;">
  <div class="login-logo">
    <?php if ($appLogo): ?>
      <img src="<?= htmlspecialchars($appLogo) ?>?t=<?= time() ?>" alt="Logo" class="login-logo-img">
    <?php else: ?>
      <div class="login-logo-emoji">🤖</div>
      <h1>What JUJU</h1>
    <?php endif; ?>
    <p>Altere sua senha para continuar</p>
  </div>
  <form id="force-pw-form" class="login-form" novalidate>
    <div class="form-group">
      <label>Nova senha</label>
      <input type="password" id="fp-new" placeholder="Mínimo 6 caracteres" required>
    </div>
    <div class="form-group">
      <label>Confirmar senha</label>
      <input type="password" id="fp-confirm" placeholder="Repita a senha" required>
    </div>
    <button type="submit" class="btn-login" id="btn-fp">Alterar senha</button>
    <div class="login-error" id="fp-error"></div>
  </form>
</div>

<script>
document.getElementById('force-pw-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn    = document.getElementById('btn-fp');
  const errEl  = document.getElementById('fp-error');
  const newPw  = document.getElementById('fp-new').value;
  const conf   = document.getElementById('fp-confirm').value;

  errEl.style.display = 'none';

  if (newPw.length < 6) {
    errEl.textContent = 'A senha deve ter pelo menos 6 caracteres.';
    errEl.style.display = 'block';
    return;
  }
  if (newPw !== conf) {
    errEl.textContent = 'As senhas não coincidem.';
    errEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Salvando…';

  try {
    const fd = new FormData();
    fd.append('action', 'change_password');
    fd.append('new_password', newPw);
    fd.append('confirm_password', conf);

    const res  = await fetch('api/auth.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.error) {
      errEl.textContent = data.error;
      errEl.style.display = 'block';
    } else {
      location.reload();
    }
  } catch (err) {
    errEl.textContent = 'Erro. Tente novamente.';
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Alterar senha';
  }
});
</script>

<?php else: ?>
<!-- ═══ MAIN APP ══════════════════════════════════════════════════ -->

<div id="app">

  <!-- ── Header ──────────────────────────────────────────────────── -->
  <header id="main-header">
    <div class="header-logo">
      <div class="header-user-avatar" id="header-user-avatar" onclick="openModal('modal-profile'); ProfileManager.load();" title="Meu Perfil">
        <span id="header-user-initials"><?= htmlspecialchars(mb_substr($currentUser['name'] ?? 'U', 0, 2)) ?></span>
        <?php if (!empty($currentUser['avatar'])): ?>
        <img src="<?= htmlspecialchars($currentUser['avatar']) ?>?t=<?= time() ?>" alt="">
        <?php endif; ?>
      </div>
      <span class="header-logo-text">What JUJU</span>
    </div>
    <div class="header-actions">
      <button id="btn-contacts" title="Contatos" class="hbtn hbtn-contacts">
        <span class="hbtn-icon">👥</span> <span>Contatos</span>
      </button>
      <button id="btn-profile" title="Perfil" class="hbtn hbtn-profile">
        <span class="hbtn-icon">👤</span>
      </button>
      <?php if ($currentUser['role'] === 'admin'): ?>
      <button id="btn-admin" title="Administração" class="hbtn hbtn-admin">
        <span class="hbtn-icon">⚙️</span>
      </button>
      <?php endif; ?>
      <button id="btn-logout" title="Sair" class="hbtn hbtn-logout">
        <span class="hbtn-icon">🚪</span>
      </button>
    </div>
  </header>

  <!-- ── Chat Area ──────────────────────────────────────────────── -->
  <main id="chat-area">

    <!-- Welcome -->
    <div id="welcome-screen">
      <div class="welcome-icon">💬</div>
      <h2>Bem-vindo ao What JUJU</h2>
      <p>Selecione um personagem em <strong>Contatos</strong> para começar a conversar, ou crie um novo.</p>
      <button class="btn btn-primary" onclick="openModal('modal-contacts'); ChatManager.loadCharacters();" style="margin-top:8px;">
        👥 Abrir Contatos
      </button>
    </div>

    <!-- Chat View -->
    <div id="chat-view" style="display:none">

      <!-- Chat Header -->
      <div id="chat-header">
        <div class="chat-header-avatar" id="chat-header-avatar">??</div>
        <div class="chat-header-info">
          <div class="chat-header-name" id="chat-header-name">Personagem</div>
          <div class="chat-header-status" id="chat-header-status">IA</div>
        </div>
        <div class="chat-header-actions">
          <button id="btn-clear-chat" title="Apagar conversa" class="chat-header-actions">🗑️</button>
          <button onclick="openModal('modal-contacts'); ChatManager.loadCharacters();" title="Mudar personagem">👥</button>
        </div>
      </div>

      <!-- Messages -->
      <div id="messages-container">
        <!-- Messages rendered here by JS -->

        <!-- Typing indicator (inside messages so it appears near last message) -->
        <div id="typing-indicator">
          <div class="typing-avatar" id="typing-avatar">IA</div>
          <div class="typing-dots">
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
          </div>
        </div>
      </div>

      <!-- Scroll to bottom -->
      <button id="scroll-bottom-btn" title="Ir para o final">⬇️</button>

      <!-- Emoji Picker -->
      <div id="emoji-picker"></div>

      <!-- File preview bar -->
      <div id="file-preview-bar"></div>

      <!-- Input Bar -->
      <div id="input-bar">
        <button class="input-bar-btn" id="btn-emoji" title="Emoji">😊</button>
        <button class="input-bar-btn" id="btn-attach" title="Anexar arquivo">📎</button>
        <input type="file" id="file-input" style="display:none"
          accept="image/*,.pdf,.txt,.docx,.csv,.json,.md,.js,.php,.py,.html,.css">

        <div id="message-input-wrap">
          <textarea id="message-input" placeholder="Digite uma mensagem…" rows="1"></textarea>
        </div>

        <button class="input-bar-btn" id="btn-voice" title="Entrada de voz">🎙️</button>
        <button class="send-btn" id="send-btn" title="Enviar">➤</button>
      </div>

    </div><!-- /chat-view -->
  </main>
</div><!-- /app -->

<!-- ═══ MODAL: Contacts ════════════════════════════════════════════ -->
<div id="modal-contacts" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h2>👥 Contatos</h2>
      <button class="modal-close" onclick="closeModal('modal-contacts')">✕</button>
    </div>

    <div class="contacts-modal-tabs">
      <button class="contacts-modal-tab active" id="tab-btn-contacts" onclick="ContactsModal.showTab('contacts')">👥 Contatos</button>
      <button class="contacts-modal-tab" id="tab-btn-groups" onclick="ContactsModal.showTab('groups')">🎭 Grupos</button>
    </div>

    <div id="contacts-tab-panel" style="display:flex; flex-direction:column; flex:1; overflow:hidden;">
      <div class="contacts-new-btn" id="btn-new-character">
        ➕ Novo Personagem
      </div>

      <div class="contact-search-wrap">
        <input type="text" id="contact-search" placeholder="🔍 Pesquisar…">
      </div>

      <div class="contacts-list" id="contacts-list">
        <div class="text-center text-muted" style="padding:24px;">
          <div class="spinner"></div>
        </div>
      </div>
    </div>

    <div id="groups-tab-panel" style="display:none; flex-direction:column; flex:1; overflow:hidden;">
      <div class="contacts-new-btn" id="btn-new-group">➕ Novo Grupo</div>
      <div class="contacts-list" id="groups-list">
        <div class="text-center text-muted" style="padding:24px;"><div class="spinner"></div></div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Group Create / Edit ════════════════════════════════ -->
<div id="modal-group" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="group-modal-title">Novo Grupo</h2>
      <button class="modal-close" onclick="closeModal('modal-group')">✕</button>
    </div>
    <input type="hidden" id="group-id">
    <div class="modal-body" style="overflow-y:auto;max-height:70vh;">
      <div class="form-group">
        <label for="group-name">Nome do grupo *</label>
        <input type="text" id="group-name" class="form-control" placeholder="Ex: Família, Amigos, Aventureiros…">
      </div>
      <div class="form-group">
        <label for="group-description">Descrição</label>
        <textarea id="group-description" class="form-control" rows="2" placeholder="Sobre este grupo…"></textarea>
      </div>
      <div class="form-group">
        <label for="group-story">Roteiro / Contexto</label>
        <textarea id="group-story" class="form-control" rows="4"
          placeholder="Descreva a história ou o contexto que os personagens devem seguir. Ex: 'Somos uma equipe de detetives resolvendo um misterioso crime em 1920...'"></textarea>
        <div class="form-hint">Os personagens vão agir de acordo com este roteiro.</div>
      </div>
      <div class="form-group">
        <label for="group-interaction-mode">Modo de interação</label>
        <select id="group-interaction-mode" class="form-control">
          <option value="random">🎲 Aleatório — 1 a 2 personagens respondem por vez</option>
          <option value="topic">💬 Por assunto — 1 a 3 personagens comentam o tópico</option>
          <option value="story">📖 Roteiro — todos respondem em ordem</option>
        </select>
      </div>
      <div class="form-group">
        <label>Membros do grupo</label>
        <div id="group-members-list" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;min-height:40px;border:1px dashed var(--border);border-radius:8px;padding:8px;">
          <span class="text-muted" style="font-size:.85rem;">Nenhum membro adicionado</span>
        </div>
      </div>
      <div class="form-group">
        <label>Adicionar personagem</label>
        <select id="group-add-member-select" class="form-control">
          <option value="">— selecione —</option>
        </select>
        <button class="btn btn-outline btn-sm" id="btn-add-member-to-group" style="margin-top:6px;">➕ Adicionar ao grupo</button>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-group')">Cancelar</button>
      <button class="btn btn-primary" id="btn-save-group">💾 Salvar Grupo</button>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Character Create / Edit ════════════════════════════ -->
<div id="modal-character" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="char-modal-title">Novo Personagem</h2>
      <button class="modal-close" onclick="closeModal('modal-character')">✕</button>
    </div>

    <input type="hidden" id="char-id">

    <!-- Tabs -->
    <div class="char-tabs">
      <div class="char-tab active" data-tab="basic">🧑 Básico</div>
      <div class="char-tab" data-tab="voice">🔊 Voz</div>
      <div class="char-tab" data-tab="advanced">⚙️ Avançado</div>
    </div>

    <div class="modal-body" style="overflow-y:auto;max-height:60vh;">

      <!-- Tab: Basic -->
      <div class="char-tab-content active" id="tab-basic">

        <div class="char-avatar-upload-wrap">
          <div class="char-avatar-preview" id="char-avatar-preview" onclick="document.getElementById('char-avatar-file').click()">
            <span id="char-avatar-initials">?</span>
            <img id="char-avatar-img" src="" alt="" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%;">
            <div class="profile-avatar-overlay">📷</div>
          </div>
          <input type="file" id="char-avatar-file" accept="image/*" style="display:none">
          <div style="font-size:.8rem;color:var(--text-muted);">Clique para adicionar foto</div>
        </div>

        <div class="form-group">
          <label for="char-name">Nome *</label>
          <input type="text" id="char-name" class="form-control" placeholder="Ex: Luna, Max, Aria…">
        </div>
        <div class="form-group">
          <label for="char-description">Descrição</label>
          <textarea id="char-description" class="form-control" rows="2"
            placeholder="Quem é este personagem? Qual seu papel?"></textarea>
        </div>
        <div class="form-group">
          <label for="char-personality">Personalidade</label>
          <textarea id="char-personality" class="form-control" rows="3"
            placeholder="Como o personagem fala e se comporta?"></textarea>
        </div>
        <div class="form-group">
          <label for="char-bubble-color">Cor da bolha</label>
          <input type="color" id="char-bubble-color" value="#dcf8c6" style="height:38px;padding:2px 6px;">
        </div>
      </div>

      <!-- Tab: Voice -->
      <div class="char-tab-content" id="tab-voice">
        <div class="form-group">
          <label>
            <input type="checkbox" id="char-voice-enabled"> Habilitar voz (TTS)
          </label>
        </div>
        <div class="form-group">
          <label for="char-voice-type">Tipo de voz</label>
          <select id="char-voice-type" class="form-control">
            <option value="feminina_adulta">Feminina adulta</option>
            <option value="masculina_adulto">Masculina adulta</option>
            <option value="crianca_menina">Criança (menina)</option>
            <option value="crianca_menino">Criança (menino)</option>
            <option value="idosa">Idosa</option>
            <option value="idoso">Idoso</option>
            <option value="robotica">Robótica</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="char-voice-speed">Velocidade: <span id="speed-val">1.0</span></label>
            <input type="range" id="char-voice-speed" min="0.5" max="2.0" step="0.1" value="1.0"
              oninput="document.getElementById('speed-val').textContent=this.value">
          </div>
          <div class="form-group">
            <label for="char-voice-pitch">Tom: <span id="pitch-val">1.0</span></label>
            <input type="range" id="char-voice-pitch" min="0.5" max="2.0" step="0.1" value="1.0"
              oninput="document.getElementById('pitch-val').textContent=this.value">
          </div>
        </div>
        <div class="form-group">
          <label>
            <input type="checkbox" id="char-auto-audio"> Reproduzir áudio automaticamente
          </label>
        </div>
        <div class="form-group">
          <label for="char-voice-example">Exemplo de fala (instrução)</label>
          <textarea id="char-voice-example" class="form-control" rows="2"
            placeholder="Fale de forma animada e energética, usando gírias jovens…"></textarea>
        </div>
        <div class="form-group">
          <label for="char-elevenlabs-id">ElevenLabs Voice ID (opcional)</label>
          <input type="text" id="char-elevenlabs-id" class="form-control" placeholder="voice_id do ElevenLabs">
        </div>
      </div>

      <!-- Tab: Advanced -->
      <div class="char-tab-content" id="tab-advanced">
        <div class="form-group">
          <label for="char-context-messages">Mensagens de contexto</label>
          <input type="number" id="char-context-messages" class="form-control" min="1" max="100" value="20">
          <div class="form-hint">Quantas mensagens anteriores enviar para a IA</div>
        </div>
        <div class="form-group">
          <label>
            <input type="checkbox" id="char-long-memory" checked> Memória longa
          </label>
        </div>
        <div class="form-group">
          <label>
            <input type="checkbox" id="char-can-read-files" checked> Pode ler arquivos enviados
          </label>
        </div>
        <div class="form-group">
          <label>
            <input type="checkbox" id="char-can-generate-images"> Pode gerar imagens
          </label>
        </div>
      </div>

    </div><!-- /modal-body -->

    <div class="modal-footer">
      <button class="btn btn-ghost" id="btn-cancel-character">Cancelar</button>
      <button class="btn btn-primary" id="btn-save-character">💾 Salvar</button>
    </div>
  </div>
</div>

<!-- ═══ MODAL: User Profile ════════════════════════════════════════ -->
<div id="modal-profile" class="modal-overlay modal-centered">
  <div class="modal-box">
    <div class="modal-header">
      <h2>👤 Meu Perfil</h2>
      <button class="modal-close" onclick="closeModal('modal-profile')">✕</button>
    </div>
    <div class="modal-body">

      <div class="profile-avatar-wrap">
        <div class="profile-avatar" id="profile-avatar" onclick="document.getElementById('avatar-upload').click()">
          <span id="profile-avatar-initials"><?= htmlspecialchars(mb_substr($currentUser['name'] ?? 'U', 0, 2)) ?></span>
          <img id="profile-avatar-img" src="" alt="" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%;">
          <div class="profile-avatar-overlay">📷</div>
        </div>
        <input type="file" id="avatar-upload" accept="image/*" style="display:none">
        <div style="font-size:.8rem;color:var(--text-muted);">Clique para alterar a foto</div>
      </div>

      <div class="form-group">
        <label for="profile-name">Nome</label>
        <input type="text" id="profile-name" class="form-control"
          value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" readonly
          style="opacity:.7;cursor:not-allowed;">
      </div>

      <div class="form-group">
        <label for="profile-status">Status</label>
        <input type="text" id="profile-status" class="form-control" maxlength="60"
          value="<?= htmlspecialchars($currentUser['status'] ?? 'Disponível') ?>">
      </div>

      <div class="form-group">
        <label>Tema</label>
        <div class="theme-selector">
          <div class="theme-swatch theme-green<?= $theme === 'green' ? ' selected' : '' ?>"
            data-theme="green" title="Verde (padrão)" onclick="ProfileManager.selectTheme('green', this)"></div>
          <div class="theme-swatch theme-darkblue<?= $theme === 'darkblue' ? ' selected' : '' ?>"
            data-theme="darkblue" title="Azul escuro" onclick="ProfileManager.selectTheme('darkblue', this)"></div>
          <div class="theme-swatch theme-pink<?= $theme === 'pink' ? ' selected' : '' ?>"
            data-theme="pink" title="Rosa" onclick="ProfileManager.selectTheme('pink', this)"></div>
          <div class="theme-swatch theme-darkorange<?= $theme === 'darkorange' ? ' selected' : '' ?>"
            data-theme="darkorange" title="Laranja escuro" onclick="ProfileManager.selectTheme('darkorange', this)"></div>
          <div class="theme-swatch theme-light<?= $theme === 'light' ? ' selected' : '' ?>"
            data-theme="light" title="Claro" onclick="ProfileManager.selectTheme('light', this)"></div>
        </div>
        <input type="hidden" id="profile-theme" value="<?= htmlspecialchars($theme) ?>">
      </div>

      <hr style="border:none;border-top:1px solid var(--border);margin:16px 0;">

      <details>
        <summary style="cursor:pointer;font-size:.9rem;font-weight:600;color:var(--text-secondary);margin-bottom:12px;">
          🔑 Alterar Senha
        </summary>
        <div class="form-group">
          <label for="pw-current">Senha atual</label>
          <input type="password" id="pw-current" class="form-control" placeholder="••••••">
        </div>
        <div class="form-group">
          <label for="pw-new">Nova senha</label>
          <input type="password" id="pw-new" class="form-control" placeholder="Mínimo 6 caracteres">
        </div>
        <div class="form-group">
          <label for="pw-confirm">Confirmar</label>
          <input type="password" id="pw-confirm" class="form-control" placeholder="Repita a senha">
        </div>
        <button class="btn btn-outline w-full" id="btn-change-password">Alterar Senha</button>
      </details>

    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-profile')">Cancelar</button>
      <button class="btn btn-primary" id="btn-save-profile">💾 Salvar</button>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Admin Panel ══════════════════════════════════════════ -->
<?php if ($currentUser['role'] === 'admin'): ?>
<div id="modal-admin" class="modal-overlay">
  <div class="modal-box">

    <!-- Sidebar -->
    <div class="admin-sidebar">
      <div class="admin-sidebar-logo">
        <span class="admin-logo-icon">🛡️</span>
        <span>ADMIN</span>
      </div>
      <div class="admin-nav-item active" data-panel="stats">
        <span class="admin-nav-icon" style="background:#4caf50;">📊</span>
        <span class="nav-label">Dashboard</span>
      </div>
      <div class="admin-nav-item" data-panel="config">
        <span class="admin-nav-icon" style="background:#2196f3;">🤖</span>
        <span class="nav-label">Config IA</span>
      </div>
      <div class="admin-nav-item" data-panel="users">
        <span class="admin-nav-icon" style="background:#9c27b0;">👥</span>
        <span class="nav-label">Usuários</span>
      </div>
      <div class="admin-nav-item" data-panel="appearance">
        <span class="admin-nav-icon" style="background:#ff5722;">🎨</span>
        <span class="nav-label">Aparência</span>
      </div>
    </div>

    <!-- Content -->
    <div class="admin-content">
      <div class="admin-content-header">
        <h2 id="admin-panel-title">📊 Dashboard</h2>
        <button class="btn btn-ghost" id="btn-close-admin">✕ Fechar</button>
      </div>

      <!-- Stats Panel -->
      <div class="admin-panel active" id="admin-panel-stats">
        <div class="admin-panel-body">
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-label">Usuários</div>
              <div class="stat-value" id="stat-users">—</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Personagens</div>
              <div class="stat-value" id="stat-chars">—</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Msgs hoje</div>
              <div class="stat-value" id="stat-messages">—</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Provider atual</div>
              <div class="stat-value" id="stat-provider" style="font-size:1.1rem;">—</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Modelo em uso</div>
              <div class="stat-value" id="stat-model" style="font-size:1.1rem;">—</div>
            </div>
          </div>

          <h3 style="margin-bottom:12px;font-size:.95rem;color:var(--text-secondary);">Últimos Acessos</h3>
          <div class="data-table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>Email</th>
                  <th>Último acesso</th>
                </tr>
              </thead>
              <tbody id="last-logins-tbody">
                <tr><td colspan="3" class="text-center text-muted">Carregando…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- AI Config Panel -->
      <div class="admin-panel" id="admin-panel-config">
        <div class="admin-panel-body">
          <div style="max-width:560px;">

            <div class="form-group">
              <label for="cfg-provider">Provider</label>
              <select id="cfg-provider" class="form-control">
                <option value="openrouter">OpenRouter</option>
                <option value="groq">Groq</option>
                <option value="openai">OpenAI</option>
                <option value="mistral">Mistral</option>
                <option value="together">Together AI</option>
                <option value="ollama">Ollama (local)</option>
                <option value="gemini">Google Gemini</option>
              </select>
            </div>

            <div class="form-group">
              <label for="cfg-api-key">API Key</label>
              <div style="display:flex;gap:8px;">
                <input type="password" id="cfg-api-key" class="form-control" placeholder="sk-…">
                <button class="btn btn-outline" id="btn-toggle-api-key" type="button">👁️</button>
              </div>
            </div>

            <div class="form-group">
              <label for="cfg-base-url">Base URL</label>
              <input type="text" id="cfg-base-url" class="form-control" placeholder="https://…">
            </div>

            <div class="form-group">
              <label for="cfg-model">Modelo</label>
              <input type="text" id="cfg-model" class="form-control" list="model-suggestions" placeholder="ex: gpt-3.5-turbo">
              <datalist id="model-suggestions"></datalist>
            </div>

            <div class="form-group">
              <label for="cfg-model-mode">Modo do modelo</label>
              <select id="cfg-model-mode" class="form-control">
                <option value="fixed">Fixo (usar modelo configurado)</option>
                <option value="random">Aleatório (modelos gratuitos)</option>
              </select>
            </div>

            <div style="display:flex;gap:10px;margin-top:8px;">
              <button class="btn btn-primary" id="btn-save-config">💾 Salvar</button>
              <button class="btn btn-outline" id="btn-test-connection">🔌 Testar Conexão</button>
            </div>
            <div id="ai-connection-status" style="margin-top:10px;font-size:.9rem;color:var(--text-secondary);">
              🟡 Não testado
            </div>
          </div>
        </div>
      </div>

      <!-- Users Panel -->
      <div class="admin-panel" id="admin-panel-users">
        <div class="admin-panel-body">

          <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
            <input type="text" id="user-search" class="form-control" placeholder="🔍 Pesquisar usuários…" style="max-width:280px;">
            <button class="btn btn-primary" id="btn-new-user">➕ Novo Usuário</button>
          </div>

          <div class="data-table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Avatar</th>
                  <th>Nome</th>
                  <th>Email</th>
                  <th>Papel</th>
                  <th>Status</th>
                  <th>Criado em</th>
                  <th>Último acesso</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody id="users-tbody">
                <tr><td colspan="8" class="text-center text-muted">Carregando…</td></tr>
              </tbody>
            </table>
          </div>

        </div>
      </div>

      <!-- Appearance Panel -->
      <div class="admin-panel" id="admin-panel-appearance">
        <div class="admin-panel-body">
          <div style="max-width:480px;">
            <h3 style="margin-bottom:16px;font-size:1rem;">🎨 Logo da Página de Login</h3>
            <div class="logo-upload-preview" id="logo-preview-wrap">
              <img id="logo-preview-img" src="" alt="" style="display:none;max-height:80px;border-radius:8px;margin-bottom:12px;">
              <div id="logo-preview-empty" style="color:var(--text-muted);font-size:.9rem;margin-bottom:12px;">Nenhum logo configurado — será exibido o emoji 🤖</div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
              <label class="btn btn-outline" style="cursor:pointer;">
                📤 Enviar logo
                <input type="file" id="logo-upload-input" accept="image/*" style="display:none">
              </label>
              <button class="btn btn-ghost" id="btn-remove-logo" style="display:none;">🗑️ Remover</button>
            </div>
            <div class="form-hint" style="margin-top:8px;">PNG, JPG ou SVG. Recomendado: 200×60px. Máx. 2MB.</div>
          </div>
        </div>
      </div>

    </div><!-- /admin-content -->
  </div><!-- /modal-box -->
</div>
<div id="user-modal-overlay" class="modal-overlay modal-centered" style="z-index:400;">
  <div class="modal-box" style="border-radius:14px;width:90%;max-width:460px;max-height:90vh;">
    <div class="modal-header">
      <h2 id="user-modal-title">Usuário</h2>
      <button class="modal-close" id="btn-cancel-user">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="user-modal-id">
      <div class="form-row">
        <div class="form-group">
          <label for="user-modal-name">Nome *</label>
          <input type="text" id="user-modal-name" class="form-control">
        </div>
        <div class="form-group">
          <label for="user-modal-email">Email *</label>
          <input type="email" id="user-modal-email" class="form-control">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="user-modal-role">Papel</label>
          <select id="user-modal-role" class="form-control">
            <option value="user">Usuário</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label for="user-modal-active">Status</label>
          <select id="user-modal-active" class="form-control">
            <option value="1">Ativo</option>
            <option value="0">Inativo</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label for="user-modal-password">Senha (deixe em branco para manter)</label>
        <input type="password" id="user-modal-password" class="form-control" placeholder="Mínimo 6 caracteres">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="btn-cancel-user-footer" onclick="document.getElementById('user-modal-overlay').classList.remove('open')">Cancelar</button>
      <button class="btn btn-primary" id="btn-save-user">💾 Salvar</button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ═══ Toast container ════════════════════════════════════════════ -->
<div id="toast-container"></div>

<!-- ═══ JS ════════════════════════════════════════════════════════ -->
<script>
window.SETE_USER  = <?= json_encode([
  'id'    => (int)$currentUser['id'],
  'name'  => $currentUser['name'],
  'role'  => $currentUser['role'],
  'theme' => $currentUser['theme'],
  'avatar'=> $currentUser['avatar'],
]) ?>;
window.SETE_CSRF  = <?= json_encode($_SESSION['csrf_token']) ?>;
</script>

<script src="assets/js/app.js"></script>
<script src="assets/js/audio.js"></script>
<script src="assets/js/chat.js"></script>
<?php if ($currentUser['role'] === 'admin'): ?>
<script src="assets/js/admin.js"></script>
<?php endif; ?>
<script src="assets/js/groups.js"></script>

<script>
// ── Profile Manager ──────────────────────────────────────────────
const ProfileManager = {
  selectedTheme: <?= json_encode($theme) ?>,

  load() {
    const avatar = window.SETE_USER.avatar;
    if (avatar) {
      const img = document.getElementById('profile-avatar-img');
      const ini = document.getElementById('profile-avatar-initials');
      if (img) { img.src = avatar; img.style.display = 'block'; }
      if (ini) ini.style.display = 'none';
    }
  },

  selectTheme(theme, el) {
    this.selectedTheme = theme;
    document.querySelectorAll('.theme-swatch').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('profile-theme').value = theme;
    applyTheme(theme);
  },

  async save() {
    const name   = document.getElementById('profile-name')?.value.trim();
    const status = document.getElementById('profile-status')?.value.trim();
    const theme  = document.getElementById('profile-theme')?.value;

    if (!name) { showToast('Nome é obrigatório.', 'error'); return; }

    try {
      const data = await apiPost('api/profile.php', {
        action: 'update',
        name, status, theme,
      });
      if (data.error) { showToast(data.error, 'error'); return; }
      showToast('Perfil salvo!', 'success');
      applyTheme(theme);
      closeModal('modal-profile');
    } catch (e) {
      showToast('Erro ao salvar perfil.', 'error');
    }
  },

  async changePassword() {
    const current = document.getElementById('pw-current')?.value;
    const newPw   = document.getElementById('pw-new')?.value;
    const confirm = document.getElementById('pw-confirm')?.value;

    if (!current || !newPw || !confirm) {
      showToast('Preencha todos os campos de senha.', 'error');
      return;
    }
    if (newPw.length < 6) {
      showToast('Nova senha deve ter pelo menos 6 caracteres.', 'error');
      return;
    }
    if (newPw !== confirm) {
      showToast('As senhas não coincidem.', 'error');
      return;
    }

    try {
      const data = await apiPost('api/profile.php', {
        action: 'change_password',
        current_password: current,
        new_password: newPw,
        confirm_password: confirm,
      });
      if (data.error) { showToast(data.error, 'error'); return; }
      showToast('Senha alterada com sucesso!', 'success');
      document.getElementById('pw-current').value = '';
      document.getElementById('pw-new').value = '';
      document.getElementById('pw-confirm').value = '';
    } catch (e) {
      showToast('Erro ao alterar senha.', 'error');
    }
  },

  async uploadAvatar(file) {
    const fd = new FormData();
    fd.append('avatar', file);
    fd.append('action', 'upload_avatar');

    try {
      const data = await apiPostFile('api/profile.php', fd);
      if (data.error) { showToast(data.error, 'error'); return; }

      const img = document.getElementById('profile-avatar-img');
      const ini = document.getElementById('profile-avatar-initials');
      if (img) { img.src = data.avatar + '?t=' + Date.now(); img.style.display = 'block'; }
      if (ini) ini.style.display = 'none';
      window.SETE_USER.avatar = data.avatar;

      // Sync header avatar
      const headerAvatar = document.getElementById('header-user-avatar');
      if (headerAvatar) {
        let headerImg = headerAvatar.querySelector('img');
        if (!headerImg) {
          headerImg = document.createElement('img');
          headerAvatar.appendChild(headerImg);
        }
        headerImg.src = data.avatar + '?t=' + Date.now();
        headerImg.alt = '';
        headerImg.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%;';
        const ini2 = document.getElementById('header-user-initials');
        if (ini2) ini2.style.display = 'none';
      }

      showToast('Avatar atualizado!', 'success');
    } catch (e) {
      showToast('Erro ao enviar avatar.', 'error');
    }
  },
};

window.ProfileManager = ProfileManager;

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btn-save-profile')?.addEventListener('click', () => ProfileManager.save());
  document.getElementById('btn-change-password')?.addEventListener('click', () => ProfileManager.changePassword());
  document.getElementById('avatar-upload')?.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) ProfileManager.uploadAvatar(file);
    e.target.value = '';
  });
});
</script>

<?php endif; ?>
</body>
</html>
