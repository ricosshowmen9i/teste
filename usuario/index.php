<?php
session_start();
error_reporting(0);

include_once("../AegisCore/conexao.php");
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) { return ''; }, $input);
    $seg = trim($seg); $seg = strip_tags($seg); $seg = addslashes($seg);
    return $seg;
}

if (empty($_SESSION['usuario_id']) && empty($_SESSION['usuario_login'])) {
    header('Location: ../index.php');
    exit;
}

$usuario_id    = $_SESSION['usuario_id'];
$usuario_login = $_SESSION['usuario_login'];
$usuario_senha = $_SESSION['usuario_senha'];
$usuario_limite = $_SESSION['usuario_limite'];
$usuario_expira = $_SESSION['usuario_expira'];
$usuario_byid  = $_SESSION['usuario_byid'];

$sql  = "SELECT * FROM ssh_accounts WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $usuario_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$row             = mysqli_fetch_assoc($result);
$usuario_login   = $row['login'];
$usuario_senha   = $row['senha'];
$usuario_limite  = $row['limite'];
$usuario_expira  = $row['expira'];
$usuario_valor_proprio = floatval($row['valormensal'] ?? 0);

// ✅ STATUS DO USUÁRIO
$mainid      = $row['mainid']  ?? '';
$status_conta = $row['status'] ?? '';

$sql_rev  = "SELECT * FROM accounts WHERE id = ?";
$stmt_rev = mysqli_prepare($conn, $sql_rev);
mysqli_stmt_bind_param($stmt_rev, "i", $usuario_byid);
mysqli_stmt_execute($stmt_rev);
$result_rev = mysqli_stmt_get_result($stmt_rev);

$revendedor_nome      = 'Revendedor';
$revendedor_email     = '';
$revendedor_mp_token  = '';
$revendedor_mp_public_key = '';
$valor_padrao         = 0;

if (mysqli_num_rows($result_rev) > 0) {
    $rev = mysqli_fetch_assoc($result_rev);
    $revendedor_nome          = $rev['nome']            ?? 'Revendedor';
    $revendedor_email         = $rev['contato']         ?? '';
    $revendedor_mp_token      = $rev['mp_access_token'] ?? '';
    $revendedor_mp_public_key = $rev['mp_public_key']   ?? '';
    $valor_padrao             = floatval($rev['valorusuario'] ?? 0);
}

$valor_renovacao = ($usuario_valor_proprio > 0) ? $usuario_valor_proprio : $valor_padrao;

$_SESSION['login']                    = $usuario_login;
$_SESSION['senha']                    = $usuario_senha;
$_SESSION['expira']                   = $usuario_expira;
$_SESSION['limite']                   = $usuario_limite;
$_SESSION['id']                       = $usuario_id;
$_SESSION['revendedor_id']            = $usuario_byid;
$_SESSION['revendedor_email']         = $revendedor_email;
$_SESSION['revendedor_mp_token']      = $revendedor_mp_token;
$_SESSION['revendedor_mp_public_key'] = $revendedor_mp_public_key;
$_SESSION['valor_renovacao']          = $valor_renovacao;
$_SESSION['usuario_valor_proprio']    = $usuario_valor_proprio;
$_SESSION['usuario_renovacao']        = true;

$hoje          = time();
$expiracao     = strtotime($usuario_expira);
$dias_restantes = floor(($expiracao - $hoje) / (60 * 60 * 24));
if ($dias_restantes < 0) $dias_restantes = 0;
$expirado = ($dias_restantes <= 0);

// ✅ DETERMINAR STATUS
if ($mainid == 'Suspenso' || $mainid == 'Limite Ultrapassado') {
    $status_label = $mainid;
    $status_cor   = 'red';
    $status_icon  = 'bx-block';
    $status_bg    = 'orange';
} elseif ($expirado) {
    $status_label = 'Expirado';
    $status_cor   = 'red';
    $status_icon  = 'bx-time-five';
    $status_bg    = 'orange';
} elseif ($status_conta == 'Online') {
    $status_label = 'Online';
    $status_cor   = 'green';
    $status_icon  = 'bx-wifi';
    $status_bg    = 'green';
} else {
    $status_label = 'Ativo';
    $status_cor   = 'green';
    $status_icon  = 'bx-check-circle';
    $status_bg    = 'green';
}

$sql_planos   = "SELECT * FROM planos_pagamento WHERE byid = ? AND status = 1 ORDER BY valor ASC";
$stmt_planos  = mysqli_prepare($conn, $sql_planos);
mysqli_stmt_bind_param($stmt_planos, "i", $usuario_byid);
mysqli_stmt_execute($stmt_planos);
$result_planos = mysqli_stmt_get_result($stmt_planos);
$planos = [];
while ($plano = mysqli_fetch_assoc($result_planos)) {
    $planos[] = $plano;
}

$result_cfg = $conn->query("SELECT * FROM configs");
$cfg        = $result_cfg->fetch_assoc();
$nomepainel   = $cfg['nomepainel']   ?? 'Painel';
$logo         = $cfg['logo']         ?? '';
$icon         = $cfg['icon']         ?? '';
$csspersonali = $cfg['corfundologo'] ?? '';
// ✅ ESTATÍSTICAS DE PAGAMENTOS
$sql_stats = "SELECT 
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as total_aprovados,
    SUM(CASE WHEN status = 'approved' THEN CAST(valor AS DECIMAL(10,2)) ELSE 0 END) as valor_aprovados,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as total_pendentes,
    SUM(CASE WHEN status = 'pending' THEN CAST(valor AS DECIMAL(10,2)) ELSE 0 END) as valor_pendentes,
    SUM(CASE WHEN status NOT IN ('approved','pending') THEN 1 ELSE 0 END) as total_cancelados,
    SUM(CASE WHEN status NOT IN ('approved','pending') THEN CAST(valor AS DECIMAL(10,2)) ELSE 0 END) as valor_cancelados
FROM pagamentos_unificado WHERE user_id = ?";
$stmt_stats = mysqli_prepare($conn, $sql_stats);
mysqli_stmt_bind_param($stmt_stats, "i", $usuario_id);
mysqli_stmt_execute($stmt_stats);
$result_stats = mysqli_stmt_get_result($stmt_stats);
$stats = mysqli_fetch_assoc($result_stats);

$total_aprovados  = intval($stats['total_aprovados']  ?? 0);
$valor_aprovados  = floatval($stats['valor_aprovados'] ?? 0);
$total_pendentes  = intval($stats['total_pendentes']  ?? 0);
$valor_pendentes  = floatval($stats['valor_pendentes'] ?? 0);
$total_cancelados = intval($stats['total_cancelados'] ?? 0);
$valor_cancelados = floatval($stats['valor_cancelados'] ?? 0);

include_once("../AegisCore/temas.php");
$temaUsuario = initTemas($conn);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($nomepainel); ?> - Dashboard</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $icon; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        <?php echo $csspersonali; ?>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0f0c29, #1e1b4b, #0f172a); min-height: 100vh; }

        .dashboard-container { display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px; background: rgba(15,25,35,0.92); backdrop-filter: blur(15px);
            border-radius: 24px; margin: 16px 0 16px 16px; padding: 18px 0;
            position: fixed; height: calc(100vh - 32px); overflow-y: auto;
            border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transition: transform 0.3s ease; z-index: 1000;
        }
        @media (max-width: 768px) {
            .sidebar { width: 260px; height: auto; max-height: 85vh; border-radius: 24px; margin: 0; position: fixed; top: 16px; left: 16px; transform: translateX(-120%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; padding: 16px; padding-top: 65px; }
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(16,185,129,0.3); border-radius: 4px; }
        .sidebar-logo { text-align: center; padding: 0 20px 18px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 16px; }
        .sidebar-logo img { max-height: 45px; max-width: 160px; }
        .sidebar-nav { padding: 0 12px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 9px 14px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 12px; margin-bottom: 4px; transition: all 0.3s; font-size: 13px; font-weight: 500; }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: white; transform: translateX(4px); }
        .nav-item.active { background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(5,150,105,0.1)); color: #10b981; border-left: 3px solid #10b981; }
        .nav-item:nth-child(1) i { color: #3b82f6; }
        .nav-item:nth-child(2) i { color: #f59e0b; }
        .nav-item:nth-child(3) i { color: #ec489a; }
        .nav-item:nth-child(4) i { color: #8b5cf6; }
        .nav-item:nth-child(5) i { color: #ef4444; }
        .nav-item i { font-size: 18px; width: 22px; }

        .main-content { flex: 1; margin-left: 280px; padding: 20px 24px; }

        /* ========== PERFIL CARD ========== */
        .profile-card {
            background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(59,130,246,0.08));
            backdrop-filter: blur(10px); border-radius: 24px; padding: 18px 20px;
            margin-bottom: 24px; border: 1px solid rgba(255,255,255,0.1);
        }
        .profile-header { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
        .profile-avatar {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, #10b981, #3b82f6);
            border-radius: 22px; display: flex; align-items: center; justify-content: center;
            font-size: 32px; font-weight: 700; color: white;
            box-shadow: 0 8px 20px rgba(16,185,129,0.25); flex-shrink: 0;
        }
        .profile-info-grid { flex: 1; display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .info-item-sm { background: rgba(255,255,255,0.04); border-radius: 14px; padding: 8px 12px; border: 1px solid rgba(255,255,255,0.06); }
        .info-label-sm { font-size: 9px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px; display: flex; align-items: center; gap: 5px; }
        .info-label-sm i { font-size: 11px; }
        .info-value-sm { font-size: 14px; font-weight: 700; color: white; }
        .info-value-sm.small { font-size: 12px; }
        .info-value-sm.green { color: #10b981; }
        .info-value-sm.red { color: #f87171; }
        .info-value-sm.blue { color: #3b82f6; }
        .info-value-sm.yellow { color: #fbbf24; }
        .info-item-sm:nth-child(1) i { color: #3b82f6; }
        .info-item-sm:nth-child(2) i { color: #8b5cf6; }
        .info-item-sm:nth-child(3) i { color: #10b981; }
        .info-item-sm:nth-child(4) i { color: #f59e0b; }
        .info-item-sm:nth-child(5) i { color: #ec489a; }
        .info-item-sm:nth-child(6) i { color: #60a5fa; }

        /* Badge de status inline no perfil */
        .status-badge-inline {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
        }
        .status-badge-inline.green { background: rgba(16,185,129,0.2); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
        .status-badge-inline.red   { background: rgba(248,113,113,0.2); color: #f87171; border: 1px solid rgba(248,113,113,0.3); }
        .status-badge-inline.yellow { background: rgba(251,191,36,0.2); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); }

        .profile-actions { display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap; }
        .btn-profile-sm { padding: 7px 18px; border-radius: 30px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; transition: all 0.3s; text-decoration: none; border: none; cursor: pointer; }
        .btn-renovar-sm      { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-renovar-sm:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(16,185,129,0.3); }
        .btn-mudar-plano-sm  { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        .btn-mudar-plano-sm:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(139,92,246,0.3); }
        .btn-logout-sm       { background: rgba(239,68,68,0.2); border: 1px solid rgba(239,68,68,0.3); color: #f87171; }
        .btn-logout-sm:hover { background: rgba(239,68,68,0.4); color: white; }

        /* ========== CARDS 2x2 ========== */
        .cards-grid-2x2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px; }
        .card-sm { background: rgba(255,255,255,0.03); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; padding: 14px 16px; transition: all 0.3s; }
        .card-sm:hover { transform: translateY(-2px); border-color: rgba(16,185,129,0.3); }
        .card-icon-sm { width: 40px; height: 40px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; font-size: 20px; }
        .card-icon-sm.blue   { background: rgba(59,130,246,0.15); color: #3b82f6; }
        .card-icon-sm.green  { background: rgba(16,185,129,0.15); color: #10b981; }
        .card-icon-sm.purple { background: rgba(139,92,246,0.15); color: #8b5cf6; }
        .card-icon-sm.orange { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .card-icon-sm.red    { background: rgba(248,113,113,0.15); color: #f87171; }
        .card-title-sm { font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px; }
        .card-value-sm { font-size: 18px; font-weight: 700; color: white; margin-bottom: 2px; }
        .card-value-sm.small { font-size: 14px; }
        .card-value-sm.green { color: #10b981; }
        .card-value-sm.red   { color: #f87171; }
        .card-sub-sm { font-size: 9px; color: rgba(255,255,255,0.4); }

        /* ========== ALERTA SUSPENSO / EXPIRADO ========== */
        .alert-status {
            border-radius: 16px; padding: 14px 18px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 12px;
            border: 1px solid;
        }
        .alert-status.suspended { background: rgba(248,113,113,0.1); border-color: rgba(248,113,113,0.3); color: #f87171; }
        .alert-status.expired   { background: rgba(251,191,36,0.1);  border-color: rgba(251,191,36,0.3);  color: #fbbf24; }
        .alert-status i { font-size: 26px; flex-shrink: 0; }
        .alert-status .alert-text h4 { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
        .alert-status .alert-text p  { font-size: 12px; opacity: 0.8; }
        .alert-status .alert-btn {
            margin-left: auto; padding: 7px 16px; border-radius: 20px; font-size: 12px;
            font-weight: 700; text-decoration: none; white-space: nowrap; flex-shrink: 0;
        }
        .alert-status.suspended .alert-btn { background: rgba(248,113,113,0.3); color: #f87171; border: 1px solid rgba(248,113,113,0.5); }
        .alert-status.expired   .alert-btn { background: rgba(251,191,36,0.3);  color: #fbbf24;  border: 1px solid rgba(251,191,36,0.5); }

        /* ========== ESTATÍSTICAS ========== */
        .stats-section-sm { background: rgba(255,255,255,0.03); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; padding: 16px 20px; margin-bottom: 24px; }
        .stats-title-sm { font-size: 13px; font-weight: 600; color: white; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .stats-title-sm i { color: #10b981; font-size: 18px; }
        .stats-grid-sm { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .stat-card-sm { text-align: center; padding: 12px; background: rgba(255,255,255,0.02); border-radius: 16px; }
        .stat-number-sm { font-size: 24px; font-weight: 700; color: white; }
        .stat-label-sm  { font-size: 10px; color: rgba(255,255,255,0.5); margin-top: 4px; }
        .stat-total-sm  { font-size: 10px; color: #10b981; margin-top: 4px; }

        /* ========== RENOVAR SECTION ========== */
        .renovar-section-sm { background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(5,150,105,0.05)); border: 1px solid rgba(16,185,129,0.2); border-radius: 20px; padding: 20px; text-align: center; }
        .renovar-section-sm h3 { color: white; font-size: 18px; margin-bottom: 8px; }
        .renovar-section-sm p  { color: rgba(255,255,255,0.6); font-size: 12px; margin-bottom: 16px; }
        .btn-renovar-grande-sm { background: linear-gradient(135deg, #10b981, #059669); border: none; padding: 10px 28px; border-radius: 40px; color: white; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; text-decoration: none; }
        .btn-renovar-grande-sm:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(16,185,129,0.3); }

        .footer-sm { text-align: center; padding: 16px; border-top: 1px solid rgba(255,255,255,0.05); color: rgba(255,255,255,0.3); font-size: 11px; margin-top: 24px; }

        /* ========== MOBILE ========== */
        @media (max-width: 768px) {
            .profile-header { flex-direction: column; text-align: center; }
            .profile-info-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; width: 100%; }
            .info-item-sm { padding: 6px 10px; }
            .info-value-sm { font-size: 13px; }
            .cards-grid-2x2 { gap: 12px; }
            .card-sm { padding: 12px; }
            .card-value-sm { font-size: 16px; }
            .stats-grid-sm { gap: 8px; }
            .stat-number-sm { font-size: 20px; }
            .profile-actions { justify-content: center; }
            .btn-profile-sm { padding: 6px 14px; font-size: 11px; }
            .alert-status { flex-wrap: wrap; margin-top: 45px;}
            .alert-status .alert-btn { margin-left:05; width: 100%; text-align: center; margin-top: 8px; }
        }

        .menu-toggle-sm { display: none; position: fixed; top: 16px; left: 16px; z-index: 1001; background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.3); border-radius: 14px; padding: 10px 12px; color: white; cursor: pointer; backdrop-filter: blur(8px); font-size: 20px; }
        @media (max-width: 768px) { .menu-toggle-sm { display: block; } }

    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaUsuario)); ?>">
<?php echo getFundoPersonalizadoCSS($conn, $temaUsuario); ?>
<button class="menu-toggle-sm" id="menuToggle" onclick="toggleMenu()">
    <i class='bx bx-menu'></i>
</button>

<div class="dashboard-container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <?php if (!empty($logo)): ?>
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="logo">
            <?php else: ?>
                <div style="color:white;font-size:16px;font-weight:700;"><?php echo htmlspecialchars($nomepainel); ?></div>
            <?php endif; ?>
        </div>
        <nav class="sidebar-nav">
            <a href="#"                      class="nav-item active"><i class='bx bx-home'></i><span>Página Inicial</span></a>
            <a href="historico.php"          class="nav-item"><i class='bx bx-list-ul'></i><span>Listar pagamentos</span></a>
            <a href="planos_disponiveis.php" class="nav-item"><i class='bx bx-crown'></i><span>Planos</span></a>
            <a href="perfil.php"             class="nav-item"><i class='bx bx-user'></i><span>Perfil</span></a>
            <a href="../logout_usuario.php"  class="nav-item"><i class='bx bx-log-out'></i><span>Sair</span></a>
        </nav>
    </aside>

    <main class="main-content">

        <!-- ✅ ALERTA DE STATUS — só aparece se suspenso ou expirado -->
        <?php if ($mainid == 'Suspenso' || $mainid == 'Limite Ultrapassado'): ?>
        <div class="alert-status suspended">
            <i class='bx bx-block'></i>
            <div class="alert-text">
                <h4>Conta <?php echo htmlspecialchars($status_label); ?>!</h4>
                <p>Sua conta está bloqueada. Adquira um plano para reativar o acesso.</p>
            </div>
            <a href="planos_disponiveis.php" class="alert-btn">Reativar Agora</a>
        </div>
        <?php elseif ($expirado): ?>
        <div class="alert-status expired">
            <i class='bx bx-time-five'></i>
            <div class="alert-text">
                <h4>Conta Expirada!</h4>
                <p>Sua conta venceu. Renove agora para continuar usando.</p>
            </div>
            <a href="pagamento_renovacao.php" class="alert-btn">Renovar Agora</a>
        </div>
        <?php endif; ?>

        <!-- PERFIL CARD -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar"><i class='bx bx-user-circle'></i></div>
                <div class="profile-info-grid">
                    <div class="info-item-sm">
                        <div class="info-label-sm"><i class='bx bx-user'></i> USUÁRIO</div>
                        <div class="info-value-sm"><?php echo htmlspecialchars($usuario_login); ?></div>
                    </div>
                    <div class="info-item-sm">
                        <div class="info-label-sm"><i class='bx bx-lock-alt'></i> SENHA</div>
                        <div class="info-value-sm small">••••••••</div>
                    </div>
                    <div class="info-item-sm">
                        <div class="info-label-sm"><i class='bx bx-calendar'></i> VALIDADE</div>
                        <div class="info-value-sm <?php echo $expirado ? 'red' : 'green'; ?>">
                            <?php echo date('d/m/Y', strtotime($usuario_expira)); ?>
                        </div>
                    </div>
                    <div class="info-item-sm">
                        <div class="info-label-sm"><i class='bx bx-time'></i> DIAS RESTANTES</div>
                        <div class="info-value-sm <?php echo $expirado ? 'red' : 'green'; ?>">
                            <?php echo $dias_restantes; ?> dias
                        </div>
                    </div>
                    <div class="info-item-sm">
                        <div class="info-label-sm"><i class='bx bx-wifi'></i> LIMITE</div>
                        <div class="info-value-sm blue"><?php echo $usuario_limite; ?> conexões</div>
                    </div>
                    <!-- ✅ STATUS NO PERFIL -->
                    <div class="info-item-sm">
                        <div class="info-label-sm"><i class='bx <?php echo $status_icon; ?>'></i> STATUS</div>
                        <div class="info-value-sm">
                            <span class="status-badge-inline <?php echo $status_cor; ?>">
                                <i class='bx <?php echo $status_icon; ?>'></i>
                                <?php echo htmlspecialchars($status_label); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="profile-actions">
                <a href="pagamento_renovacao.php" class="btn-profile-sm btn-renovar-sm"><i class='bx bx-refresh'></i> Renovar</a>
                <a href="planos_disponiveis.php"  class="btn-profile-sm btn-mudar-plano-sm"><i class='bx bx-transfer'></i> Mudar Plano</a>
                <a href="../logout_usuario.php"   class="btn-profile-sm btn-logout-sm"><i class='bx bx-log-out'></i> Sair</a>
            </div>
        </div>

        <!-- CARDS 2x2 -->
        <div class="cards-grid-2x2">
            <div class="card-sm">
                <div class="card-icon-sm blue"><i class='bx bx-calendar'></i></div>
                <div class="card-title-sm">VENCIMENTO</div>
                <div class="card-value-sm"><?php echo date('d/m/Y', strtotime($usuario_expira)); ?></div>
                <div class="card-sub-sm">próxima renovação</div>
            </div>
            <div class="card-sm">
                <div class="card-icon-sm green"><i class='bx bx-wifi'></i></div>
                <div class="card-title-sm">LIMITE</div>
                <div class="card-value-sm"><?php echo $usuario_limite; ?></div>
                <div class="card-sub-sm">conexões simultâneas</div>
            </div>
            <div class="card-sm">
                <div class="card-icon-sm purple"><i class='bx bx-money'></i></div>
                <div class="card-title-sm">MENSALIDADE</div>
                <div class="card-value-sm small">R$ <?php echo number_format($valor_renovacao, 2, ',', '.'); ?></div>
                <div class="card-sub-sm"><?php echo ($usuario_valor_proprio > 0) ? 'personalizado' : 'padrão'; ?></div>
            </div>
            

        </div>
        <!-- ESTATÍSTICAS -->
<div class="stats-section-sm">
    <div class="stats-title-sm"><i class='bx bx-chart'></i> Estatísticas de Pagamentos</div>
    <div class="stats-grid-sm">
        <div class="stat-card-sm">
            <div class="stat-number-sm" style="color:#10b981;"><?php echo $total_aprovados; ?></div>
            <div class="stat-label-sm">APROVADOS</div>
            <div class="stat-total-sm">R$ <?php echo number_format($valor_aprovados, 2, ',', '.'); ?></div>
        </div>
        <div class="stat-card-sm">
            <div class="stat-number-sm" style="color:#fbbf24;"><?php echo $total_pendentes; ?></div>
            <div class="stat-label-sm">PENDENTES</div>
            <div class="stat-total-sm" style="color:#fbbf24;">R$ <?php echo number_format($valor_pendentes, 2, ',', '.'); ?></div>
        </div>
        <div class="stat-card-sm">
            <div class="stat-number-sm" style="color:#f87171;"><?php echo $total_cancelados; ?></div>
            <div class="stat-label-sm">CANCELADOS</div>
            <div class="stat-total-sm" style="color:#f87171;">R$ <?php echo number_format($valor_cancelados, 2, ',', '.'); ?></div>
        </div>
    </div>
</div>

        <!-- RENOVAR PLANO -->
        <div class="renovar-section-sm">
            <h3><?php echo ($mainid == 'Suspenso' || $mainid == 'Limite Ultrapassado') ? 'Reativar Conta' : 'Renovar Plano'; ?></h3>
            <p><?php echo ($mainid == 'Suspenso' || $mainid == 'Limite Ultrapassado') ? 'Sua conta está suspensa. Adquira um plano para reativar.' : 'Mantenha sua conta sempre ativa'; ?></p>
            <?php if ($mainid == 'Suspenso' || $mainid == 'Limite Ultrapassado'): ?>
                <a href="planos_disponiveis.php" class="btn-renovar-grande-sm"><i class='bx bx-transfer'></i> Ver Planos</a>
            <?php else: ?>
                <a href="pagamento_renovacao.php" class="btn-renovar-grande-sm"><i class='bx bx-refresh'></i> Renovar Agora</a>
            <?php endif; ?>
        </div>

        <div class="footer-sm">
            <?php echo htmlspecialchars($nomepainel); ?> &copy; <?php echo date('Y'); ?>
        </div>
    </main>
</div>

<script>
function toggleMenu() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
}
document.addEventListener('click', function(event) {
    const sidebar    = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) toggleMenu();
    }
});
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && document.getElementById('sidebar').classList.contains('open')) toggleMenu();
});
</script>
</body>
</html>