<?php
session_start();
error_reporting(0);

include_once("../AegisCore/conexao.php");
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Verificar login
if (empty($_SESSION['usuario_id']) && empty($_SESSION['usuario_login'])) {
    header('Location: ../index.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_login = $_SESSION['usuario_login'];
$usuario_senha = $_SESSION['usuario_senha'];
$usuario_limite = $_SESSION['usuario_limite'];
$usuario_expira = $_SESSION['usuario_expira'];
$usuario_byid = $_SESSION['usuario_byid'];

// Buscar dados atualizados do usuário
$sql = "SELECT * FROM ssh_accounts WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $usuario_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$row = mysqli_fetch_assoc($result);
$usuario_login = $row['login'];
$usuario_senha = $row['senha'];
$usuario_limite = $row['limite'];
$usuario_expira = $row['expira'];
$usuario_valor_proprio = floatval($row['valormensal'] ?? 0);
$usuario_notas = $row['lastview'] ?? '';
$usuario_whatsapp = $row['whatsapp'] ?? '';
$usuario_uuid = $row['uuid'] ?? '';

// Buscar dados do revendedor
$sql_rev = "SELECT * FROM accounts WHERE id = ?";
$stmt_rev = mysqli_prepare($conn, $sql_rev);
mysqli_stmt_bind_param($stmt_rev, "i", $usuario_byid);
mysqli_stmt_execute($stmt_rev);
$result_rev = mysqli_stmt_get_result($stmt_rev);

$revendedor_nome = 'Revendedor';
$revendedor_email = '';

if (mysqli_num_rows($result_rev) > 0) {
    $rev = mysqli_fetch_assoc($result_rev);
    $revendedor_nome = $rev['nome'] ?? 'Revendedor';
    $revendedor_email = $rev['contato'] ?? '';
}

// Calcular dias restantes
$hoje = time();
$expiracao = strtotime($usuario_expira);
$dias_restantes = floor(($expiracao - $hoje) / (60 * 60 * 24));
if ($dias_restantes < 0) $dias_restantes = 0;
$expirado = $dias_restantes <= 0;

// Processar atualização de perfil
$msg_sucesso = '';
$msg_erro = '';

if (isset($_POST['atualizar_perfil'])) {
    $novo_whatsapp = anti_sql($_POST['whatsapp'] ?? '');
    $novas_notas = anti_sql($_POST['notas'] ?? '');
    
    // Validar WhatsApp (remover caracteres não numéricos)
    $novo_whatsapp = preg_replace('/[^0-9]/', '', $novo_whatsapp);
    
    $sql_update = "UPDATE ssh_accounts SET whatsapp = ?, lastview = ? WHERE id = ?";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "ssi", $novo_whatsapp, $novas_notas, $usuario_id);
    
    if (mysqli_stmt_execute($stmt_update)) {
        $msg_sucesso = "✅ Perfil atualizado com sucesso!";
        $usuario_whatsapp = $novo_whatsapp;
        $usuario_notas = $novas_notas;
    } else {
        $msg_erro = "❌ Erro ao atualizar perfil: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt_update);
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) { return ''; }, $input);
    $seg = trim($seg); $seg = strip_tags($seg); $seg = addslashes($seg);
    return $seg;
}

// Configurações do painel
$result_cfg = $conn->query("SELECT * FROM configs");
$cfg = $result_cfg->fetch_assoc();
$nomepainel = $cfg['nomepainel'] ?? 'Painel';
$logo = $cfg['logo'] ?? '';
$icon = $cfg['icon'] ?? '';
$csspersonali = $cfg['corfundologo'] ?? '';

include_once("../AegisCore/temas.php");
$temaUsuario = initTemas($conn);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($nomepainel); ?> - Meu Perfil</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $icon; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        <?php echo $csspersonali; ?>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0c29, #1e1b4b, #0f172a);
            min-height: 100vh;
        }
        
        .dashboard-container { display: flex; min-height: 100vh; }
        
        /* ========== SIDEBAR MENU MODERNO ========== */
        .sidebar {
            width: 260px;
            background: rgba(15, 25, 35, 0.92);
            backdrop-filter: blur(15px);
            border-radius: 24px;
            margin: 16px 0 16px 16px;
            padding: 18px 0;
            position: fixed;
            height: calc(100vh - 32px);
            overflow-y: auto;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(16,185,129,0.3); border-radius: 4px; }
        
        .sidebar-logo { text-align: center; padding: 0 20px 18px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 16px; }
        .sidebar-logo img { max-height: 45px; max-width: 160px; }
        
        .sidebar-nav { padding: 0 12px; }
        
        .nav-item {
            display: flex; align-items: center; gap: 12px; padding: 9px 14px;
            color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 12px;
            margin-bottom: 4px; transition: all 0.3s; font-size: 13px; font-weight: 500;
        }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: white; transform: translateX(4px); }
        .nav-item.active { background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(5,150,105,0.1)); color: #10b981; border-left: 3px solid #10b981; }
        
        .nav-item:nth-child(1) i { color: #3b82f6; }
        .nav-item:nth-child(2) i { color: #f59e0b; }
        .nav-item:nth-child(3) i { color: #ec489a; }
        .nav-item:nth-child(4) i { color: #8b5cf6; }
        .nav-item:nth-child(5) i { color: #ef4444; }
        .nav-item i { font-size: 18px; width: 22px; transition: all 0.3s; }
        .nav-item:hover i { transform: scale(1.1); }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 260px;
                height: auto;
                max-height: 85vh;
                border-radius: 24px;
                margin: 0;
                position: fixed;
                top: 16px;
                left: 16px;
                transform: translateX(-120%);
                transition: transform 0.3s ease;
            }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; padding-top: 65px; }
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px 24px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: white;
            margin-bottom: 8px;
        }
        
        .page-header p {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
        }
        
        /* ========== NOVO PERFIL CARD ========== */
        .profile-header-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 28px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .profile-avatar-big {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #10b981, #3b82f6);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            box-shadow: 0 15px 35px rgba(16,185,129,0.3);
        }
        
        .profile-welcome {
            flex: 1;
        }
        
        .profile-welcome h2 {
            font-size: 28px;
            font-weight: 800;
            color: white;
            margin-bottom: 8px;
        }
        
        .profile-welcome p {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
        }
        
        .profile-badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16,185,129,0.2);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            color: #10b981;
            margin-top: 10px;
        }
        
        /* ========== GRID 2x2 DE INFORMAÇÕES ========== */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card-horizontal {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 18px;
            transition: all 0.3s;
        }
        
        .info-card-horizontal:hover {
            background: rgba(255,255,255,0.06);
            transform: translateY(-3px);
            border-color: rgba(16,185,129,0.3);
        }
        
        .info-card-icon {
            width: 55px;
            height: 55px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }
        
        .info-card-icon.blue { background: rgba(59,130,246,0.15); color: #3b82f6; }
        .info-card-icon.green { background: rgba(16,185,129,0.15); color: #10b981; }
        .info-card-icon.purple { background: rgba(139,92,246,0.15); color: #8b5cf6; }
        .info-card-icon.orange { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .info-card-icon.cyan { background: rgba(6,182,212,0.15); color: #06b6d4; }
        .info-card-icon.pink { background: rgba(236,72,153,0.15); color: #ec489a; }
        
        .info-card-content {
            flex: 1;
        }
        
        .info-card-label {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        
        .info-card-value {
            font-size: 18px;
            font-weight: 700;
            color: white;
        }
        
        .info-card-value.small {
            font-size: 14px;
        }
        
        .info-card-value.green {
            color: #10b981;
        }
        
        .info-card-value.red {
            color: #f87171;
        }
        
        /* ========== FORMULÁRIO ========== */
        .form-section {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .form-section-title {
            font-size: 18px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        
        .form-section-title i {
            color: #10b981;
            font-size: 22px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: white;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #10b981;
            background: rgba(255,255,255,0.12);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn-salvar {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            padding: 12px 28px;
            border-radius: 40px;
            color: white;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-salvar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16,185,129,0.4);
        }
        
        .alert-success {
            background: rgba(16,185,129,0.15);
            border: 1px solid rgba(16,185,129,0.3);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #10b981;
            font-size: 13px;
        }
        
        .alert-error {
            background: rgba(220,38,38,0.15);
            border: 1px solid rgba(220,38,38,0.3);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #f87171;
            font-size: 13px;
        }
        
        .info-note {
            margin-top: 20px;
            padding: 12px 16px;
            background: rgba(16,185,129,0.05);
            border-radius: 12px;
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            border-left: 3px solid #10b981;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.3);
            font-size: 12px;
            margin-top: 30px;
        }
        
        /* ========== RESPONSIVIDADE ========== */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; padding-top: 65px; }
            .page-header h1 { font-size: 24px; }
            
            .profile-header-content { flex-direction: column; text-align: center; }
            .profile-avatar-big { width: 80px; height: 80px; font-size: 36px; }
            .profile-welcome h2 { font-size: 22px; }
            
            .info-grid { 
                grid-template-columns: repeat(2, 1fr); 
                gap: 12px;
            }
            .info-card-horizontal { 
                padding: 12px 14px;
                gap: 12px;
            }
            .info-card-icon {
                width: 45px;
                height: 45px;
                font-size: 22px;
            }
            .info-card-value {
                font-size: 14px;
            }
            .info-card-value.small {
                font-size: 12px;
            }
            
            .form-section { padding: 20px; }
        }
        
        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .info-card-horizontal {
                padding: 10px 12px;
            }
            .info-card-icon {
                width: 38px;
                height: 38px;
                font-size: 18px;
            }
            .info-card-value {
                font-size: 12px;
            }
        }
        
        .menu-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 1001;
            background: rgba(16,185,129,0.2);
            border: 1px solid rgba(16,185,129,0.3);
            border-radius: 14px;
            padding: 10px 12px;
            color: white;
            cursor: pointer;
            backdrop-filter: blur(8px);
            font-size: 20px;
        }
        
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
        }

    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaUsuario)); ?>">
<?php echo getFundoPersonalizadoCSS($conn, $temaUsuario); ?>
<button class="menu-toggle" id="menuToggle" onclick="toggleMenu()">
    <i class='bx bx-menu'></i>
</button>

<div class="dashboard-container">
    <!-- Sidebar Moderno -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <?php if (!empty($logo)): ?>
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="logo">
            <?php else: ?>
                <div style="color: white; font-size: 16px; font-weight: 700;"><?php echo htmlspecialchars($nomepainel); ?></div>
            <?php endif; ?>
        </div>
        
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item">
                <i class='bx bx-home'></i>
                <span>Página Inicial</span>
            </a>
            <a href="historico.php" class="nav-item">
                <i class='bx bx-list-ul'></i>
                <span>Listar pagamentos</span>
            </a>
            <a href="planos_disponiveis.php" class="nav-item">
                <i class='bx bx-crown'></i>
                <span>Planos</span>
            </a>
            <a href="perfil.php" class="nav-item active">
                <i class='bx bx-user'></i>
                <span>Perfil</span>
            </a>
            <a href="../logout_usuario.php" class="nav-item">
                <i class='bx bx-log-out'></i>
                <span>Sair</span>
            </a>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1>Meu Perfil</h1>
            <p>Gerencie suas informações pessoais</p>
        </div>
        
        <!-- Header do Perfil -->
        <div class="profile-header-card">
            <div class="profile-header-content">
                <div class="profile-avatar-big">
                    <i class='bx bx-user-circle'></i>
                </div>
                <div class="profile-welcome">
                    <h2>Olá, <?php echo htmlspecialchars($usuario_login); ?>!</h2>
                    <p>Bem-vindo à sua área pessoal. Aqui você pode visualizar e editar suas informações.</p>
                    <div class="profile-badge-status">
                        <i class='bx bx-check-circle'></i> Conta Verificada
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Grid 2x2 de Informações -->
        <div class="info-grid">
            <div class="info-card-horizontal">
                <div class="info-card-icon blue"><i class='bx bx-user'></i></div>
                <div class="info-card-content">
                    <div class="info-card-label">USUÁRIO</div>
                    <div class="info-card-value"><?php echo htmlspecialchars($usuario_login); ?></div>
                </div>
            </div>
            <div class="info-card-horizontal">
                <div class="info-card-icon purple"><i class='bx bx-lock-alt'></i></div>
                <div class="info-card-content">
                    <div class="info-card-label">SENHA</div>
                    <div class="info-card-value small">••••••••</div>
                </div>
            </div>
            <div class="info-card-horizontal">
                <div class="info-card-icon green"><i class='bx bx-wifi'></i></div>
                <div class="info-card-content">
                    <div class="info-card-label">LIMITE DE CONEXÕES</div>
                    <div class="info-card-value"><?php echo $usuario_limite; ?> simultâneas</div>
                </div>
            </div>
            <div class="info-card-horizontal">
                <div class="info-card-icon orange"><i class='bx bx-calendar'></i></div>
                <div class="info-card-content">
                    <div class="info-card-label">VALIDADE</div>
                    <div class="info-card-value <?php echo $expirado ? 'red' : 'green'; ?>">
                        <?php echo date('d/m/Y', strtotime($usuario_expira)); ?>
                        <span style="font-size: 11px;">(<?php echo $dias_restantes; ?> dias)</span>
                    </div>
                </div>
            </div>
            <div class="info-card-horizontal">
                <div class="info-card-icon cyan"><i class='bx bx-store'></i></div>
                <div class="info-card-content">
                    <div class="info-card-label">REVENDEDOR</div>
                    <div class="info-card-value small"><?php echo htmlspecialchars($revendedor_nome); ?></div>
                </div>
            </div>
            <div class="info-card-horizontal">
                <div class="info-card-icon pink"><i class='bx bx-money'></i></div>
                <div class="info-card-content">
                    <div class="info-card-label">VALOR MENSAL</div>
                    <div class="info-card-value green">R$ <?php echo number_format($usuario_valor_proprio > 0 ? $usuario_valor_proprio : 0, 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Formulário de Edição -->
        <div class="form-section">
            <div class="form-section-title">
                <i class='bx bx-edit'></i>
                Editar Informações
            </div>
            
            <?php if (!empty($msg_sucesso)): ?>
            <div class="alert-success">
                <i class='bx bx-check-circle'></i>
                <?php echo $msg_sucesso; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($msg_erro)): ?>
            <div class="alert-error">
                <i class='bx bx-error-circle'></i>
                <?php echo $msg_erro; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class='bx bxl-whatsapp' style="color: #25D366;"></i> WhatsApp</label>
                    <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($usuario_whatsapp); ?>">
                    <small style="color: rgba(255,255,255,0.4); font-size: 10px;">Digite apenas números, exemplo: 5511999999999</small>
                </div>
                
                <div class="form-group">
                    <label><i class='bx bx-note'></i> Notas / Observações</label>
                    <textarea class="form-control" name="notas" rows="3" placeholder="Adicione observações sobre sua conta..."><?php echo htmlspecialchars($usuario_notas); ?></textarea>
                </div>
                
                <button type="submit" name="atualizar_perfil" class="btn-salvar">
                    <i class='bx bx-save'></i> Salvar Alterações
                </button>
            </form>
            
            <div class="info-note">
                <i class='bx bx-info-circle'></i>
                <strong>Informação:</strong> A senha não pode ser alterada aqui. Para alterar sua senha, entre em contato com o suporte.
                <?php if (!empty($usuario_uuid) && $usuario_uuid != 'Não Gerado'): ?>
                <br><br>
                <i class='bx bx-shield-quarter'></i>
                <strong>UUID V2Ray:</strong> <span style="font-family: monospace; font-size: 11px;"><?php echo htmlspecialchars($usuario_uuid); ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <?php echo htmlspecialchars($nomepainel); ?> &copy; <?php echo date('Y'); ?> - Todos os direitos reservados
        </div>
    </main>
</div>

<script>
function toggleMenu() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
    
    if (sidebar.classList.contains('open')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    const isMobile = window.innerWidth <= 768;
    
    if (isMobile && sidebar.classList.contains('open')) {
        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            toggleMenu();
        }
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        if (sidebar.classList.contains('open')) {
            toggleMenu();
        }
    }
});
</script>
</body>
</html>