<?php
/**
 * WhatsappJUJU — Página principal
 * Login + App de chat em um único arquivo
 */
define('WHATSAPPJUJU', true);
require_once __DIR__ . '/config.php';

// Inicializa banco na primeira visita
$db = getDB();

$isLoggedIn = !empty($_SESSION['user_id']);
$isAdmin    = ($isLoggedIn && ($_SESSION['user_role'] ?? '') === 'admin');

// Dados do usuário para JS
$userData = null;
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT id, name, email, role, avatar, theme, status, force_password_change FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $userData = $stmt->fetch();
    if ($userData) {
        // Atualiza sessão com dados frescos
        $_SESSION['user_name']  = $userData['name'];
        $_SESSION['user_email'] = $userData['email'];
        $_SESSION['user_role']  = $userData['role'];
    }
}

$theme = $userData['theme'] ?? 'verde';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <meta name="theme-color" content="#075E54">
  <title>WhatsappJUJU</title>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

  <!-- Estilos -->
  <link rel="stylesheet" href="assets/css/themes.css">
  <link rel="stylesheet" href="assets/css/app.css">

  <style>
    /* Garante que o tema carregue antes de qualquer flash */
    body { visibility: visible; }
  </style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════════
     TELA DE LOGIN (exibida quando não logado)
     ══════════════════════════════════════════════════════════ -->
<?php if (!$isLoggedIn): ?>
<div id="login-screen">
  <div class="login-box">
    <div class="login-logo">
      <div class="login-logo-icon"><i class="fab fa-whatsapp"></i></div>
      <h1>WhatsappJUJU</h1>
      <p>Chat com IA no estilo WhatsApp</p>
    </div>

    <div class="login-tabs">
      <div class="login-tab active" data-tab="login">Entrar</div>
      <div class="login-tab" data-tab="register">Cadastrar</div>
    </div>

    <!-- Form de login -->
    <form id="login-form" class="login-form active" autocomplete="off">
      <div id="login-error" class="login-error"></div>
      <div class="form-group">
        <label class="form-label">E-mail</label>
        <input type="email" id="login-email" class="form-control" placeholder="seu@email.com" required autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label">Senha</label>
        <div class="input-password-wrap">
          <input type="password" id="login-password" class="form-control" placeholder="Sua senha" required autocomplete="current-password">
          <span class="toggle-password" data-target="login-password"><i class="fas fa-eye"></i></span>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px">
        <i class="fas fa-sign-in-alt"></i> Entrar
      </button>
    </form>

    <!-- Form de registro -->
    <form id="register-form" class="login-form" autocomplete="off">
      <div id="register-error" class="login-error"></div>
      <div class="form-group">
        <label class="form-label">Nome</label>
        <input type="text" id="register-name" class="form-control" placeholder="Seu nome" required>
      </div>
      <div class="form-group">
        <label class="form-label">E-mail</label>
        <input type="email" id="register-email" class="form-control" placeholder="seu@email.com" required>
      </div>
      <div class="form-group">
        <label class="form-label">Senha</label>
        <div class="input-password-wrap">
          <input type="password" id="register-password" class="form-control" placeholder="Mínimo 6 caracteres" required>
          <span class="toggle-password" data-target="register-password"><i class="fas fa-eye"></i></span>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px">
        <i class="fas fa-user-plus"></i> Criar conta
      </button>
    </form>
  </div>
</div><!-- /login-screen -->

<?php else: ?>

<!-- ══════════════════════════════════════════════════════════
     APP PRINCIPAL (exibido quando logado)
     ══════════════════════════════════════════════════════════ -->
<div id="app">

  <!-- ── HEADER ────────────────────────────────────────────── -->
  <header id="main-header">
    <div class="header-logo">
      <div class="header-logo-icon"><i class="fab fa-whatsapp"></i></div>
      <h1>WhatsappJUJU</h1>
    </div>
    <div class="header-actions">
      <button class="header-btn" id="btn-contacts">
        <i class="fas fa-users"></i>
        <span class="hidden-mobile">Contatos</span>
      </button>
      <button class="header-icon-btn" id="btn-profile" title="Perfil">
        <i class="fas fa-user-circle"></i>
      </button>
      <?php if ($isAdmin): ?>
      <button class="header-icon-btn" id="btn-admin" title="Painel Admin">
        <i class="fas fa-cog"></i>
      </button>
      <?php endif; ?>
    </div>
  </header>

  <!-- ── ÁREA DE CHAT ───────────────────────────────────────── -->
  <div id="chat-area">

    <!-- Tela de boas-vindas -->
    <div id="welcome-screen">
      <div class="welcome-icon"><i class="fab fa-whatsapp"></i></div>
      <h2 class="welcome-title">WhatsappJUJU</h2>
      <p class="welcome-sub">Clique em <strong>Contatos</strong> para iniciar uma conversa com um personagem IA.</p>
      <button class="btn btn-primary" id="btn-welcome-contacts">
        <i class="fas fa-users"></i> Ver Contatos
      </button>
    </div>

    <!-- Chat ativo -->
    <div id="active-chat" style="display:none; flex:1; flex-direction:column; overflow:hidden;">

      <!-- Header do chat -->
      <div id="chat-header">
        <div id="chat-avatar-wrap">
          <div class="chat-header-avatar">?</div>
        </div>
        <div class="chat-header-info">
          <div id="chat-char-name" class="chat-header-name">Personagem</div>
          <div id="chat-header-status" class="chat-header-status">online</div>
        </div>
        <div style="position:relative;">
          <button class="chat-header-menu-btn" id="chat-menu-btn">
            <i class="fas fa-ellipsis-v"></i>
          </button>
          <div class="dropdown-menu" id="chat-dropdown">
            <div class="dropdown-item" onclick="App.clearChat()">
              <i class="fas fa-trash-alt"></i> Limpar conversa
            </div>
            <div class="dropdown-item" onclick="Contacts.open()">
              <i class="fas fa-users"></i> Ver contatos
            </div>
          </div>
        </div>
      </div>

      <!-- Mensagens -->
      <div id="messages-container"></div>

      <!-- Typing indicator -->
      <div id="typing-indicator" style="display:none; padding:8px 16px;"></div>

      <!-- Botão scroll down -->
      <button id="scroll-down-btn">
        <i class="fas fa-chevron-down"></i>
      </button>

      <!-- Área de arquivo pendente -->
      <div id="input-wrapper"></div>

      <!-- Input bar -->
      <div id="input-bar">
        <!-- Upload options -->
        <div id="upload-options" class="upload-options">
          <div class="upload-option" id="upload-image-btn">
            <i class="fas fa-image"></i> Imagem
          </div>
          <div class="upload-option" id="upload-file-btn">
            <i class="fas fa-file"></i> Arquivo / PDF
          </div>
        </div>

        <button class="input-action-btn" id="attach-btn" title="Anexar arquivo">
          <i class="fas fa-paperclip"></i>
        </button>

        <div class="input-wrapper" style="flex:1">
          <textarea id="message-input" placeholder="Digite uma mensagem..." rows="1"></textarea>
        </div>

        <!-- Emoji picker -->
        <div id="emoji-picker"></div>

        <button class="input-action-btn" id="emoji-btn" title="Emoji">
          <i class="far fa-smile"></i>
        </button>

        <button class="input-action-btn record-btn" id="mic-btn" title="Segurar para gravar">
          <i class="fas fa-microphone"></i>
        </button>

        <button class="send-btn" id="send-btn" title="Enviar">
          <i class="fas fa-paper-plane"></i>
        </button>

        <!-- Inputs de arquivo ocultos -->
        <input type="file" id="file-input-image" accept="image/*" style="display:none">
        <input type="file" id="file-input-file" accept=".pdf,.txt,.docx,.csv,.json,.md,.js,.php,.py,.html,.css" style="display:none">
      </div>
    </div><!-- /active-chat -->

  </div><!-- /chat-area -->

</div><!-- /app -->

<!-- ══════════════════════════════════════════════════════════
     MODAL DE CONTATOS
     ══════════════════════════════════════════════════════════ -->
<div id="contacts-modal">
  <div id="contacts-overlay"></div>
  <div id="contacts-panel">
    <div class="contacts-header">
      <h2><i class="fas fa-users" style="margin-right:8px"></i>Contatos</h2>
      <button class="modal-close" id="btn-close-contacts"><i class="fas fa-times"></i></button>
    </div>
    <div class="contacts-search">
      <input type="text" id="contacts-search" placeholder="&#xF002; Buscar personagem..." style="font-family:inherit">
    </div>
    <button class="add-contact-btn" id="btn-new-character">
      <i class="fas fa-plus"></i> Novo Personagem
    </button>
    <div id="contacts-list"></div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL DE PERSONAGEM (criar/editar)
     ══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="character-modal">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-header">
      <span class="modal-title" id="char-modal-title">Novo Personagem</span>
      <button class="modal-close" id="btn-close-char-modal"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <form id="character-form" autocomplete="off">

        <!-- Tabs -->
        <div class="char-tabs">
          <div class="char-tab active" data-tab="identity"><i class="fas fa-id-card"></i> Identidade</div>
          <div class="char-tab" data-tab="voice"><i class="fas fa-microphone"></i> Voz</div>
          <div class="char-tab" data-tab="capabilities"><i class="fas fa-bolt"></i> Capacidades</div>
        </div>

        <!-- Tab: Identidade -->
        <div class="char-tab-panel active" id="char-panel-identity">
          <div class="avatar-upload-area">
            <div class="avatar-preview" id="char-avatar-preview">
              <i class="fas fa-robot avatar-preview-icon"></i>
              <div class="avatar-preview-overlay"><i class="fas fa-camera"></i></div>
            </div>
            <span style="font-size:12px;color:var(--text-secondary)">Clique para alterar a foto</span>
          </div>
          <input type="file" id="char-avatar-file" accept="image/*" style="display:none">

          <div class="form-group">
            <label class="form-label">Nome *</label>
            <input type="text" id="char-name" class="form-control" placeholder="Ex: Luna, Robô, Dra. Ana..." required>
          </div>
          <div class="form-group">
            <label class="form-label">Descrição curta</label>
            <input type="text" id="char-description" class="form-control" placeholder="Ex: Assistente criativa e animada">
          </div>
          <div class="form-group">
            <label class="form-label">Personalidade (system prompt)</label>
            <textarea id="char-personality" class="form-control" rows="5"
              placeholder="Descreva como este personagem deve se comportar, seus traços de personalidade, área de expertise..."></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Exemplos de como fala</label>
            <textarea id="char-voice-example" class="form-control" rows="3"
              placeholder="Ex: 'Caramba! Que ótima pergunta!' ou fala formal..."></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Cor do balão de mensagem</label>
            <div class="color-row">
              <input type="color" id="char-bubble-color" value="#dcf8c6">
              <span style="font-size:13px;color:var(--text-secondary)">Cor das mensagens deste personagem</span>
            </div>
          </div>
        </div>

        <!-- Tab: Voz -->
        <div class="char-tab-panel" id="char-panel-voice">
          <div class="toggle-row">
            <span class="toggle-label">Ativar voz neste personagem</span>
            <label class="toggle-switch">
              <input type="checkbox" id="char-voice-enabled" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="form-group" style="margin-top:14px">
            <label class="form-label">Tipo de voz</label>
            <select id="char-voice-type" class="form-control">
              <option value="feminina_adulta">Feminina adulta</option>
              <option value="feminina_jovem">Feminina jovem</option>
              <option value="feminina_idosa">Feminina idosa</option>
              <option value="masculina_adulto">Masculina adulto</option>
              <option value="masculino_jovem">Masculino jovem</option>
              <option value="masculino_idoso">Masculino idoso</option>
              <option value="crianca_menina">Criança menina</option>
              <option value="crianca_menino">Criança menino</option>
              <option value="robotica">Robótica</option>
              <option value="dramatica">Dramática</option>
              <option value="sussurro">Sussurro</option>
            </select>
          </div>

          <div class="range-row">
            <div class="range-label">
              <span>Velocidade</span>
              <span id="char-voice-speed-val">1.0</span>
            </div>
            <input type="range" id="char-voice-speed" min="0.5" max="2" step="0.1" value="1.0">
          </div>

          <div class="range-row">
            <div class="range-label">
              <span>Tom (pitch)</span>
              <span id="char-voice-pitch-val">1.0</span>
            </div>
            <input type="range" id="char-voice-pitch" min="0.5" max="2" step="0.1" value="1.0">
          </div>

          <button type="button" class="btn btn-secondary" id="char-test-voice-btn" style="margin-top:10px">
            <i class="fas fa-volume-up"></i> Testar voz
          </button>

          <div class="form-group" style="margin-top:16px">
            <label class="form-label">Voice ID ElevenLabs (opcional)</label>
            <input type="text" id="char-elevenlabs" class="form-control" placeholder="ID da voz ElevenLabs">
          </div>
        </div>

        <!-- Tab: Capacidades -->
        <div class="char-tab-panel" id="char-panel-capabilities">
          <div class="toggle-row">
            <span class="toggle-label">Pode ler arquivos enviados</span>
            <label class="toggle-switch">
              <input type="checkbox" id="char-can-read" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="toggle-row">
            <span class="toggle-label">Pode gerar imagens</span>
            <label class="toggle-switch">
              <input type="checkbox" id="char-can-images">
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="toggle-row">
            <span class="toggle-label">Lembra contexto longo</span>
            <label class="toggle-switch">
              <input type="checkbox" id="char-long-memory">
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="toggle-row">
            <span class="toggle-label">Responde em áudio automaticamente</span>
            <label class="toggle-switch">
              <input type="checkbox" id="char-auto-audio">
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="range-row" style="margin-top:16px">
            <div class="range-label">
              <span>Mensagens anteriores consideradas</span>
              <span id="char-memory-val">20</span>
            </div>
            <input type="range" id="char-memory-context" min="1" max="50" step="1" value="20">
          </div>
        </div>

      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" id="char-cancel-btn">Cancelar</button>
      <button class="btn btn-primary" id="char-save-btn">
        <i class="fas fa-save"></i> Salvar Personagem
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL DE PERFIL
     ══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="profile-modal">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header">
      <span class="modal-title">Meu Perfil</span>
      <button class="modal-close" id="btn-close-profile"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="profile-avatar-area">
        <div class="profile-avatar" id="profile-avatar-preview">
          <?php if ($userData['avatar'] ?? null): ?>
          <img src="<?= htmlspecialchars($userData['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="">
          <?php else: ?>
          <span class="profile-avatar-icon"><i class="fas fa-user-circle"></i></span>
          <?php endif; ?>
          <div class="avatar-preview-overlay"><i class="fas fa-camera"></i></div>
        </div>
        <input type="file" id="profile-avatar-file" accept="image/*" style="display:none">
        <span style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($userData['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <div class="form-group">
        <label class="form-label">Nome</label>
        <input type="text" id="profile-name" class="form-control"
          value="<?= htmlspecialchars($userData['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <input type="text" id="profile-status" class="form-control"
          value="<?= htmlspecialchars($userData['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          placeholder="Ex: Disponível para conversar">
      </div>

      <!-- Temas -->
      <div class="form-group">
        <label class="form-label">Tema Visual</label>
        <div class="themes-grid">
          <?php
          $themes = [
            'verde'       => '🌿 Verde',
            'dark_blue'   => '🌙 Dark Blue',
            'dark_orange' => '🔥 Orange',
            'rosa'        => '🌸 Rosa',
            'light'       => '☁️ Light',
          ];
          foreach ($themes as $key => $label):
          ?>
          <div class="theme-btn <?= $theme === $key ? 'active' : '' ?>" data-theme-key="<?= $key ?>">
            <div class="theme-preview" data-theme="<?= $key ?>"></div>
            <span class="theme-name"><?= $label ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="btn btn-primary" id="profile-save-btn" style="width:100%;justify-content:center">
        <i class="fas fa-save"></i> Salvar
      </button>

      <button class="logout-btn" id="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Sair da conta
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL ADMIN
     ══════════════════════════════════════════════════════════ -->
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="admin-modal">
  <div class="modal-box" style="max-width:800px;height:90vh;display:flex;flex-direction:column;">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-cog" style="margin-right:8px"></i>Painel Admin</span>
      <button class="modal-close" id="btn-close-admin"><i class="fas fa-times"></i></button>
    </div>
    <div class="admin-layout" style="flex:1;overflow:hidden">
      <!-- Sidebar -->
      <div class="admin-sidebar">
        <div class="admin-nav-item active" data-panel="ai">
          <i class="fas fa-robot"></i> Configurar IA
        </div>
        <div class="admin-nav-item" data-panel="users">
          <i class="fas fa-users"></i> Usuários
        </div>
        <div class="admin-nav-item" data-panel="stats">
          <i class="fas fa-chart-bar"></i> Estatísticas
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="admin-content">

        <!-- Painel: IA -->
        <div class="admin-panel active" id="admin-panel-ai">
          <h3 style="margin-bottom:16px">Configuração de IA</h3>

          <div class="form-group">
            <label class="form-label">Provider</label>
            <select id="ai-provider" class="form-control">
              <option value="openrouter">OpenRouter</option>
              <option value="groq">Groq</option>
              <option value="gemini">Gemini (Google)</option>
              <option value="ollama">Ollama (local)</option>
              <option value="openai">OpenAI</option>
              <option value="mistral">Mistral AI</option>
              <option value="together">Together AI</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">API Key</label>
            <div class="input-password-wrap">
              <input type="password" id="ai-api-key" class="form-control" placeholder="Cole sua API Key aqui">
              <span class="toggle-password" id="toggle-api-key"><i class="fas fa-eye"></i></span>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">URL Base</label>
            <input type="text" id="ai-base-url" class="form-control" value="https://openrouter.ai/api/v1">
          </div>

          <div class="form-group">
            <label class="form-label">Modelo</label>
            <input type="text" id="ai-model" class="form-control"
              list="ai-model-list"
              value="mistralai/mistral-7b-instruct:free"
              placeholder="Nome do modelo">
            <datalist id="ai-model-list"></datalist>
          </div>

          <div class="form-group">
            <label class="form-label">Modo de seleção de modelo</label>
            <div style="display:flex;align-items:center;gap:16px;margin-top:4px;flex-wrap:wrap">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                <input type="radio" name="ai-model-mode" id="ai-model-mode-random" value="random" checked style="accent-color:var(--accent)">
                <span>🎲 Aleatório <small style="color:var(--text-secondary)">(sorteia um modelo gratuito a cada mensagem)</small></span>
              </label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                <input type="radio" name="ai-model-mode" id="ai-model-mode-fixed" value="fixed" style="accent-color:var(--accent)">
                <span>📌 Fixo <small style="color:var(--text-secondary)">(usa sempre o modelo acima)</small></span>
              </label>
            </div>
          </div>

          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <button class="btn btn-primary" id="ai-save-btn">
              <i class="fas fa-save"></i> Salvar
            </button>
            <button class="btn btn-secondary" id="ai-test-btn">
              <i class="fas fa-plug"></i> Testar Conexão
            </button>
            <div class="connection-status">
              <div class="status-dot" id="connection-dot"></div>
              <span id="connection-text">Não testado</span>
            </div>
          </div>
        </div>

        <!-- Painel: Usuários -->
        <div class="admin-panel" id="admin-panel-users">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
            <h3>Usuários</h3>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="text" id="users-search" class="form-control" style="width:180px;padding:6px 12px" placeholder="Buscar...">
              <button class="btn btn-primary" id="btn-new-user" style="padding:7px 14px">
                <i class="fas fa-plus"></i> Novo
              </button>
            </div>
          </div>
          <div class="users-table-wrap">
            <table class="users-table">
              <thead>
                <tr>
                  <th>Avatar</th>
                  <th>Nome</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Criado em</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody id="users-tbody">
                <tr><td colspan="7" style="text-align:center;padding:20px"><i class="fas fa-spinner fa-spin"></i></td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Painel: Estatísticas -->
        <div class="admin-panel" id="admin-panel-stats">
          <h3 style="margin-bottom:16px">Estatísticas</h3>
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-number" id="stat-users">—</div>
              <div class="stat-label">Usuários</div>
            </div>
            <div class="stat-card">
              <div class="stat-number" id="stat-chars">—</div>
              <div class="stat-label">Personagens</div>
            </div>
            <div class="stat-card">
              <div class="stat-number" id="stat-msgs">—</div>
              <div class="stat-label">Mensagens hoje</div>
            </div>
          </div>
          <div class="stat-card" style="margin-bottom:16px">
            <div class="stat-label">Provider / Modelo ativo</div>
            <div id="stat-provider" style="font-weight:600;margin-top:4px">—</div>
          </div>
          <h4 style="margin-bottom:8px">Últimos acessos</h4>
          <div class="users-table-wrap">
            <table class="users-table">
              <thead><tr><th>Nome</th><th>Email</th><th>Último acesso</th></tr></thead>
              <tbody id="last-logins"></tbody>
            </table>
          </div>
        </div>

      </div><!-- /admin-content -->
    </div><!-- /admin-layout -->
  </div>
</div>

<!-- Modal de edição de usuário (dentro do admin) -->
<div class="modal-overlay" id="user-edit-modal" style="z-index:600">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header">
      <span class="modal-title">Editar Usuário</span>
      <button class="modal-close" id="btn-close-user-edit"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="edit-user-id">
      <div class="form-group">
        <label class="form-label">Nome</label>
        <input type="text" id="edit-user-name" class="form-control">
      </div>
      <div class="form-group">
        <label class="form-label">E-mail</label>
        <input type="email" id="edit-user-email" class="form-control">
      </div>
      <div class="form-group">
        <label class="form-label">Nova senha (deixe em branco para manter)</label>
        <input type="password" id="edit-user-password" class="form-control" placeholder="Nova senha">
      </div>
      <div class="form-group">
        <label class="form-label">Role</label>
        <select id="edit-user-role" class="form-control">
          <option value="user">Usuário</option>
          <option value="admin">Admin</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" id="cancel-user-edit-btn">Cancelar</button>
      <button class="btn btn-primary" id="save-user-btn">
        <i class="fas fa-save"></i> Salvar
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     MODAL TROCAR SENHA (admin forçado no primeiro login)
     ══════════════════════════════════════════════════════════ -->
<div class="modal-overlay <?= ($userData['force_password_change'] ?? 0) ? 'open' : '' ?>" id="change-password-modal" style="z-index:700">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header">
      <span class="modal-title">Defina sua nova senha</span>
    </div>
    <div class="modal-body">
      <p style="font-size:14px;color:var(--text-secondary);margin-bottom:16px">
        Por segurança, você deve trocar a senha padrão antes de continuar.
      </p>
      <div id="change-pw-error" class="login-error"></div>
      <div class="form-group">
        <label class="form-label">Nova senha</label>
        <div class="input-password-wrap">
          <input type="password" id="new-password" class="form-control" placeholder="Mínimo 6 caracteres">
          <span class="toggle-password" data-target="new-password"><i class="fas fa-eye"></i></span>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Confirmar senha</label>
        <input type="password" id="confirm-password" class="form-control" placeholder="Repita a senha">
      </div>
      <button class="btn btn-primary" id="change-pw-btn" style="width:100%;justify-content:center">
        <i class="fas fa-key"></i> Salvar Nova Senha
      </button>
    </div>
  </div>
</div>

<?php endif; // fim isLoggedIn ?>

<!-- ══════════════════════════════════════════════════════════
     ELEMENTOS GLOBAIS
     ══════════════════════════════════════════════════════════ -->

<!-- Lightbox -->
<div id="lightbox">
  <span id="lightbox-close"><i class="fas fa-times"></i></span>
  <img src="" alt="Imagem">
</div>

<!-- Toast container -->
<div id="toast-container"></div>

<!-- ══════════════════════════════════════════════════════════
     SCRIPTS
     ══════════════════════════════════════════════════════════ -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js" crossorigin="anonymous"></script>

<?php if ($isLoggedIn): ?>
<!-- Módulos do app -->
<script src="assets/js/audio.js"></script>
<script src="assets/js/upload.js"></script>
<script src="assets/js/characters.js"></script>
<script src="assets/js/contacts.js"></script>
<script src="assets/js/admin.js"></script>
<script src="assets/js/app.js"></script>
<?php endif; ?>

<script>
<?php if (!$isLoggedIn): ?>
/* ── Lógica de login/registro ─────────────────────────────── */
$(document).ready(function() {
  // Tabs login/register
  $('.login-tab').on('click', function() {
    const tab = $(this).data('tab');
    $('.login-tab').removeClass('active');
    $(this).addClass('active');
    $('.login-form').removeClass('active');
    $(`#${tab}-form`).addClass('active');
  });

  // Toggle senha
  $(document).on('click', '.toggle-password', function() {
    const target = $(this).data('target');
    const $inp   = $(`#${target}`);
    const show   = $inp.attr('type') === 'password';
    $inp.attr('type', show ? 'text' : 'password');
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
  });

  // Login
  $('#login-form').on('submit', function(e) {
    e.preventDefault();
    const $btn = $(this).find('[type=submit]').addClass('loading');
    $('#login-error').hide().text('');
    $.post('api/auth.php', {
      action:   'login',
      email:    $('#login-email').val(),
      password: $('#login-password').val(),
    }, function(data) {
      $btn.removeClass('loading');
      if (data.success) {
        window.location.reload();
      } else {
        $('#login-error').text(data.error || 'Erro ao fazer login').show();
      }
    }).fail(function() {
      $btn.removeClass('loading');
      $('#login-error').text('Erro de conexão. Tente novamente.').show();
    });
  });

  // Registro
  $('#register-form').on('submit', function(e) {
    e.preventDefault();
    const $btn = $(this).find('[type=submit]').addClass('loading');
    $('#register-error').hide().text('');
    $.post('api/auth.php', {
      action:   'register',
      name:     $('#register-name').val(),
      email:    $('#register-email').val(),
      password: $('#register-password').val(),
    }, function(data) {
      $btn.removeClass('loading');
      if (data.success) {
        $('#register-error').css('background','rgba(76,175,80,0.1)').css('color','#4caf50')
          .text('Conta criada! Faça login.').show();
        $('.login-tab[data-tab="login"]').click();
      } else {
        $('#register-error').text(data.error || 'Erro ao criar conta').show();
      }
    }).fail(function() {
      $btn.removeClass('loading');
      $('#register-error').text('Erro de conexão.').show();
    });
  });
});

<?php else: ?>
/* ── Lógica do app (usuário logado) ──────────────────────────── */
$(document).ready(function() {

  // Toggle senha em qualquer lugar
  $(document).on('click', '.toggle-password', function() {
    const target = $(this).data('target');
    const $inp   = $(`#${target}`);
    const show   = $inp.attr('type') === 'password';
    $inp.attr('type', show ? 'text' : 'password');
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
  });

  // Fechar modais ao clicar no overlay
  $(document).on('click', '.modal-overlay', function(e) {
    if ($(e.target).is('.modal-overlay') && !$(this).is('#change-password-modal')) {
      $(this).removeClass('open');
      $('body').css('overflow', '');
    }
  });

  // Botão boas-vindas → abre contatos
  $('#btn-welcome-contacts').on('click', function() {
    Contacts.open();
  });

  // ── Perfil ──────────────────────────────────────────────
  let profileAvatarUrl = <?= json_encode($userData['avatar'] ?? null) ?>;

  $('#btn-close-profile').on('click', function() {
    $('#profile-modal').removeClass('open');
    $('body').css('overflow', '');
  });

  // Upload avatar de perfil
  $('#profile-avatar-preview').on('click', () => $('#profile-avatar-file').click());
  $('#profile-avatar-file').on('change', function() {
    if (!this.files[0]) return;
    const fd = new FormData();
    fd.append('avatar', this.files[0]);
    // Não definir Content-Type — o browser define com boundary automaticamente
    $.ajax({
      url: 'api/upload.php', type: 'POST', data: fd,
      processData: false, contentType: false,
      success: function(data) {
        if (data.success) {
          profileAvatarUrl = data.url;
          $('#profile-avatar-preview').html(`<img src="${data.url}" alt=""><div class="avatar-preview-overlay"><i class="fas fa-camera"></i></div>`);
          App.showToast('Foto atualizada!', 'success');
        } else {
          console.error('[Profile] avatar upload error:', data);
          App.showToast(data.error || 'Erro no upload da foto', 'error');
        }
      },
      error: function(xhr) {
        console.error('[Profile] avatar upload FAIL:', xhr.responseText);
        let msg = 'Erro ao enviar foto';
        try { msg = JSON.parse(xhr.responseText).error || msg; } catch(e) {}
        App.showToast(msg, 'error');
      }
    });
  });

  // Salvar perfil
  $('#profile-save-btn').on('click', function() {
    const $btn = $(this).addClass('loading');
    $.post('api/admin.php', {
      action: 'update_profile',
      name:   $('#profile-name').val(),
      status: $('#profile-status').val(),
      avatar: profileAvatarUrl || '',
    }, function(data) {
      $btn.removeClass('loading');
      if (data.success) {
        App.showToast('Perfil salvo!', 'success');
        $('#profile-modal').removeClass('open');
        $('body').css('overflow', '');
      } else {
        App.showToast(data.error || 'Erro ao salvar perfil', 'error');
      }
    }).fail(function(xhr) {
      $btn.removeClass('loading');
      let msg = 'Erro de conexão ao salvar perfil';
      try { msg = JSON.parse(xhr.responseText).error || msg; } catch(e) {}
      App.showToast(msg, 'error');
    });
  });

  // Temas
  $('.theme-btn').on('click', function() {
    const theme = $(this).data('theme-key');
    $.post('api/admin.php', { action: 'update_theme', theme }, function(data) {
      if (data.success) {
        $('html').attr('data-theme', data.theme);
        $('.theme-btn').removeClass('active');
        $(`.theme-btn[data-theme-key="${data.theme}"]`).addClass('active');
        App.showToast('Tema aplicado!', 'success');
      }
    });
  });

  // Logout
  $('#logout-btn').on('click', function() {
    if (!confirm('Sair da conta?')) return;
    $.post('api/auth.php', { action: 'logout' }, function() {
      window.location.reload();
    });
  });

  // ── Troca obrigatória de senha ──────────────────────────
  $('#change-pw-btn').on('click', function() {
    const newPw  = $('#new-password').val();
    const confPw = $('#confirm-password').val();
    $('#change-pw-error').hide().text('');

    if (newPw.length < 6) {
      $('#change-pw-error').text('Senha deve ter ao menos 6 caracteres').show();
      return;
    }
    if (newPw !== confPw) {
      $('#change-pw-error').text('As senhas não coincidem').show();
      return;
    }

    const $btn = $(this).addClass('loading');
    $.post('api/auth.php', { action: 'change_password', new_password: newPw }, function(data) {
      $btn.removeClass('loading');
      if (data.success) {
        App.showToast('Senha alterada com sucesso!', 'success');
        $('#change-password-modal').removeClass('open');
        $('body').css('overflow', '');
      } else {
        $('#change-pw-error').text(data.error || 'Erro ao alterar senha').show();
      }
    });
  });

  // ── Admin: fechar modal de edição de usuário ────────────
  $('#cancel-user-edit-btn').on('click', function() {
    $('#user-edit-modal').removeClass('open');
  });

  // ── Profile modal: abrir ─────────────────────────────────
  $('#btn-profile').on('click', function() {
    $('#profile-modal').addClass('open');
    $('body').css('overflow', 'hidden');
  });

});
<?php endif; ?>
</script>

</body>
</html>
