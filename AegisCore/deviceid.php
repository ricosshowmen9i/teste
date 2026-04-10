<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
$kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
function aleatorio653751($input) {
?>
<script src="../app-assets/sweetalert.min.js"></script>
<?php
error_reporting(0);
session_start();
include('conexao.php');
include('header2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\)/i", function($m){ return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

if (!file_exists('../admin/suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once 'suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

$sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$sql5 = $conn->query($sql5);
$row5 = $sql5->fetch_assoc();
$validade = $row5['expira'];
$_SESSION['tipodeconta'] = $row5['tipo'];
date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta está vencida')</script>";
        echo "<script>window.location.href = '../home.php'</script>";
        exit;
    }
}

$sql44 = "SELECT * FROM configs";
$result44 = $conn->query($sql44);
while ($row44 = $result44->fetch_assoc()) {
    $nomepainel   = $row44['nomepainel'];
    $logo         = $row44['logo'];
    $icon         = $row44['icon'];
    $csspersonali = $row44['corfundologo'];
}

// ===== AÇÃO: DELETAR device direto pelo nome_user =====
$msg_sucesso = '';
$msg_erro    = '';

if (isset($_POST['deletar_device_direto'])) {
    $login_alvo = $conn->real_escape_string(anti_sql($_POST['nome_user'] ?? ''));
    $chk = $conn->query("SELECT id FROM ssh_accounts WHERE login='$login_alvo' AND byid='" . $_SESSION['iduser'] . "'");
    if ($chk && $chk->num_rows > 0) {
        $conn->query("DELETE FROM atlasdeviceid WHERE nome_user = '$login_alvo'");
        $conn->query("DELETE FROM userlimiter   WHERE nome_user = '$login_alvo'");
        $msg_sucesso = "Device ID de <strong>" . $login_alvo . "</strong> removido com sucesso!";
    } else {
        $msg_erro = "Usuário não encontrado ou sem permissão.";
    }
}

// ===== BUSCA =====
$busca = anti_sql($_GET['busca'] ?? '');
$where = "a.byid = '" . $_SESSION['iduser'] . "'";
if (!empty($busca)) {
    $where .= " AND (d.nome_user LIKE '%$busca%' OR d.deviceid LIKE '%$busca%')";
}

// ===== PAGINAÇÃO =====
$por_pagina = 20;
$pagina     = max(1, intval($_GET['pagina'] ?? 1));
$offset     = ($pagina - 1) * $por_pagina;

$sql_total = "SELECT COUNT(*) as total FROM atlasdeviceid d\n              INNER JOIN ssh_accounts a ON a.login = d.nome_user\n              WHERE $where";
$total_res = mysqli_fetch_assoc(mysqli_query($conn, $sql_total));
$total     = $total_res['total'] ?? 0;
$total_pag = ceil($total / $por_pagina);

$sql_list = "SELECT d.*, a.id as conta_id, a.expira, a.status\n             FROM atlasdeviceid d\n             INNER JOIN ssh_accounts a ON a.login = d.nome_user\n             WHERE $where\n             ORDER BY d.id DESC\n             LIMIT $por_pagina OFFSET $offset";
$devices = [];
$res_list = mysqli_query($conn, $sql_list);
while ($row = mysqli_fetch_assoc($res_list)) { $devices[] = $row; }

// ===== STATS =====
$res_total_devices = mysqli_query($conn,
    "SELECT COUNT(*) as t FROM atlasdeviceid d INNER JOIN ssh_accounts a ON a.login=d.nome_user WHERE a.byid='" . $_SESSION['iduser'] . "'");
$row_total_devices = mysqli_fetch_assoc($res_total_devices);
$total_devices = $row_total_devices['t'] ?? 0;

$res_total_usuarios = mysqli_query($conn,
    "SELECT COUNT(DISTINCT d.nome_user) as t FROM atlasdeviceid d INNER JOIN ssh_accounts a ON a.login=d.nome_user WHERE a.byid='" . $_SESSION['iduser'] . "'");
$row_total_usuarios = mysqli_fetch_assoc($res_total_usuarios);
$total_usuarios = $row_total_usuarios['t'] ?? 0;

$res_total_userlim = mysqli_query($conn,
    "SELECT COUNT(*) as t FROM userlimiter u INNER JOIN ssh_accounts a ON a.login=u.nome_user WHERE a.byid='" . $_SESSION['iduser'] . "'");
$row_total_userlim = mysqli_fetch_assoc($res_total_userlim);
$total_userlim = $row_total_userlim['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nomepainel; ?> — Device ID</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $icon; ?>">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        <?php
        }
        ?>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
        }

        /* Ajustes para o menu lateral */
        .app-content {
            margin-left: 240px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 1640px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }

        /* Info badge */
        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: #2c3e50 !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 10px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--cyan) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i { 
            font-size: 20px !important; 
            color: var(--cyan) !important; 
        }

        /* Page Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }
        
        .page-header h1 {
            font-size: 20px;
            font-weight: 800;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-header h1 i {
            font-size: 28px;
            color: var(--cyan);
        }
        
        .page-header p {
            font-size: 12px;
            color: rgba(255,255,255,0.35);
            margin-top: 3px;
        }

        /* Filters Card */
        .filters-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            padding: 18px 20px !important;
            margin-bottom: 20px !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            position: relative !important;
            overflow: hidden !important;
        }
        
        .filters-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 30%, rgba(6,182,212,0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .filters-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 1;
        }
        
        .filters-title i {
            color: var(--cyan);
            font-size: 18px;
        }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
            position: relative;
            z-index: 1;
        }
        
        .filter-item {
            flex: 1 1 280px;
            min-width: 200px;
        }
        
        .filter-label {
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-input {
            width: 100%;
            padding: 10px 14px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            font-size: 13px;
            transition: all 0.3s;
            color: white;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: rgba(6,182,212,0.6);
            background: rgba(255,255,255,0.09);
        }
        
        .filter-input::placeholder {
            color: rgba(255,255,255,0.3);
        }
        
        .btn-search {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            color: white;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(6,182,212,0.4);
        }
        
        .btn-clear-s {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 12px;
            padding: 10px 18px;
            color: #f87171;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        
        .btn-clear-s:hover {
            background: rgba(239,68,68,0.25);
            transform: translateY(-1px);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 18px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid rgba(255,255,255,0.07);
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            border-color: rgba(6,182,212,0.3);
        }
        
        .sc-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
            border-radius: 18px;
        }
        
        .sc-shapes svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .sc-icon, .stat-card > div:last-child {
            position: relative;
            z-index: 1;
        }
        
        .sc-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            flex-shrink: 0;
        }
        
        .sc-lbl {
            font-size: 10px;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .sc-val {
            font-size: 24px;
            font-weight: 700;
            color: white;
        }
        
        .sc-sub {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
            margin-top: 2px;
        }

        /* Table Card */
        .table-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.08);
            overflow: hidden;
            position: relative;
        }
        
        .tc-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
            border-radius: 20px;
        }
        
        .tc-shapes svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .table-header {
            padding: 18px 22px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            position: relative;
            z-index: 1;
        }
        
        .table-title {
            font-size: 15px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-title i {
            font-size: 20px;
            color: var(--cyan);
        }
        
        .table-wrap {
            overflow-x: auto;
            position: relative;
            z-index: 1;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead th {
            padding: 12px 18px;
            font-size: 11px;
            font-weight: 700;
            color: rgba(255,255,255,0.45);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            white-space: nowrap;
            text-align: left;
        }
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: rgba(255,255,255,0.03);
        }
        
        tbody td {
            padding: 14px 18px;
            font-size: 13px;
            color: rgba(255,255,255,0.7);
            border-bottom: 1px solid rgba(255,255,255,0.04);
            white-space: nowrap;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-ok {
            background: rgba(16,185,129,0.15);
            color: #34d399;
            border: 1px solid rgba(16,185,129,0.25);
        }
        
        .badge-exp {
            background: rgba(239,68,68,0.15);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.25);
        }
        
        .device-code {
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 11px;
            background: rgba(255,255,255,0.07);
            padding: 4px 10px;
            border-radius: 8px;
            color: var(--cyan);
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
            vertical-align: middle;
        }
        
        .btn-del {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            border: none;
            color: white;
            border-radius: 10px;
            padding: 7px 16px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: inherit;
        }
        
        .btn-del:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(239,68,68,0.4);
        }
        
        /* Alertas */
        .alerta {
            padding: 14px 18px;
            border-radius: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            font-weight: 600;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alerta-ok {
            background: rgba(16,185,129,0.12);
            border: 1px solid rgba(16,185,129,0.3);
            color: #34d399;
        }
        
        .alerta-err {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.3);
            color: #f87171;
        }
        
        .alerta i {
            font-size: 22px;
            flex-shrink: 0;
        }
        
        /* Paginação */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 20px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .pag-btn {
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: rgba(255,255,255,0.6);
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            transition: all 0.2s;
        }
        
        .pag-btn:hover {
            background: rgba(6,182,212,0.3);
            color: white;
        }
        
        .pag-btn.active {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
            border-color: transparent;
        }
        
        .pag-btn.disabled {
            opacity: 0.3;
            pointer-events: none;
        }
        
        /* Empty state */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .empty-state i {
            font-size: 56px;
            color: rgba(255,255,255,0.08);
            display: block;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: rgba(255,255,255,0.4);
            font-size: 14px;
        }
        
        .pagination-info {
            text-align: center;
            margin-top: 18px;
            margin-bottom: 10px;
            color: rgba(255,255,255,0.4);
            font-size: 12px;
            font-weight: 500;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            
            .filter-item {
                min-width: 100% !important;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            tbody td {
                font-size: 11px;
                padding: 10px 12px;
            }
            
            thead th {
                padding: 10px 12px;
                font-size: 9px;
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
$kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
function aleatorio653751($input) {
?>
<script src="../app-assets/sweetalert.min.js"></script>
<?php
error_reporting(0);
session_start();
include('conexao.php');
include('header2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\)/i", function($m){ return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

if (!file_exists('../admin/suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once 'suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

$sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$sql5 = $conn->query($sql5);
$row5 = $sql5->fetch_assoc();
$validade = $row5['expira'];
$_SESSION['tipodeconta'] = $row5['tipo'];
date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta está vencida')</script>";
        echo "<script>window.location.href = '../home.php'</script>";
        exit;
    }
}

$sql44 = "SELECT * FROM configs";
$result44 = $conn->query($sql44);
while ($row44 = $result44->fetch_assoc()) {
    $nomepainel   = $row44['nomepainel'];
    $logo         = $row44['logo'];
    $icon         = $row44['icon'];
    $csspersonali = $row44['corfundologo'];
}

// ===== AÇÃO: DELETAR device direto pelo nome_user =====
$msg_sucesso = '';
$msg_erro    = '';

if (isset($_POST['deletar_device_direto'])) {
    $login_alvo = $conn->real_escape_string(anti_sql($_POST['nome_user'] ?? ''));
    $chk = $conn->query("SELECT id FROM ssh_accounts WHERE login='$login_alvo' AND byid='" . $_SESSION['iduser'] . "'");
    if ($chk && $chk->num_rows > 0) {
        $conn->query("DELETE FROM atlasdeviceid WHERE nome_user = '$login_alvo'");
        $conn->query("DELETE FROM userlimiter   WHERE nome_user = '$login_alvo'");
        $msg_sucesso = "Device ID de <strong>" . $login_alvo . "</strong> removido com sucesso!";
    } else {
        $msg_erro = "Usuário não encontrado ou sem permissão.";
    }
}

// ===== BUSCA =====
$busca = anti_sql($_GET['busca'] ?? '');
$where = "a.byid = '" . $_SESSION['iduser'] . "'";
if (!empty($busca)) {
    $where .= " AND (d.nome_user LIKE '%$busca%' OR d.deviceid LIKE '%$busca%')";
}

// ===== PAGINAÇÃO =====
$por_pagina = 20;
$pagina     = max(1, intval($_GET['pagina'] ?? 1));
$offset     = ($pagina - 1) * $por_pagina;

$sql_total = "SELECT COUNT(*) as total FROM atlasdeviceid d\n              INNER JOIN ssh_accounts a ON a.login = d.nome_user\n              WHERE $where";
$total_res = mysqli_fetch_assoc(mysqli_query($conn, $sql_total));
$total     = $total_res['total'] ?? 0;
$total_pag = ceil($total / $por_pagina);

$sql_list = "SELECT d.*, a.id as conta_id, a.expira, a.status\n             FROM atlasdeviceid d\n             INNER JOIN ssh_accounts a ON a.login = d.nome_user\n             WHERE $where\n             ORDER BY d.id DESC\n             LIMIT $por_pagina OFFSET $offset";
$devices = [];
$res_list = mysqli_query($conn, $sql_list);
while ($row = mysqli_fetch_assoc($res_list)) { $devices[] = $row; }

// ===== STATS =====
$res_total_devices = mysqli_query($conn,
    "SELECT COUNT(*) as t FROM atlasdeviceid d INNER JOIN ssh_accounts a ON a.login=d.nome_user WHERE a.byid='" . $_SESSION['iduser'] . "'");
$row_total_devices = mysqli_fetch_assoc($res_total_devices);
$total_devices = $row_total_devices['t'] ?? 0;

$res_total_usuarios = mysqli_query($conn,
    "SELECT COUNT(DISTINCT d.nome_user) as t FROM atlasdeviceid d INNER JOIN ssh_accounts a ON a.login=d.nome_user WHERE a.byid='" . $_SESSION['iduser'] . "'");
$row_total_usuarios = mysqli_fetch_assoc($res_total_usuarios);
$total_usuarios = $row_total_usuarios['t'] ?? 0;

$res_total_userlim = mysqli_query($conn,
    "SELECT COUNT(*) as t FROM userlimiter u INNER JOIN ssh_accounts a ON a.login=u.nome_user WHERE a.byid='" . $_SESSION['iduser'] . "'");
$row_total_userlim = mysqli_fetch_assoc($res_total_userlim);
$total_userlim = $row_total_userlim['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nomepainel; ?> — Device ID</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $icon; ?>">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        <?php
        }
        ?>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
        }

        /* Ajustes para o menu lateral */
        .app-content {
            margin-left: 240px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 1640px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }

        /* Info badge */
        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: #2c3e50 !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 10px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--cyan) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i { 
            font-size: 20px !important; 
            color: var(--cyan) !important; 
        }

        /* Page Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }
        
        .page-header h1 {
            font-size: 20px;
            font-weight: 800;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-header h1 i {
            font-size: 28px;
            color: var(--cyan);
        }
        
        .page-header p {
            font-size: 12px;
            color: rgba(255,255,255,0.35);
            margin-top: 3px;
        }

        /* Filters Card */
        .filters-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            padding: 18px 20px !important;
            margin-bottom: 20px !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            position: relative !important;
            overflow: hidden !important;
        }
        
        .filters-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 30%, rgba(6,182,212,0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .filters-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 1;
        }
        
        .filters-title i {
            color: var(--cyan);
            font-size: 18px;
        }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
            position: relative;
            z-index: 1;
        }
        
        .filter-item {
            flex: 1 1 280px;
            min-width: 200px;
        }
        
        .filter-label {
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-input {
            width: 100%;
            padding: 10px 14px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            font-size: 13px;
            transition: all 0.3s;
            color: white;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: rgba(6,182,212,0.6);
            background: rgba(255,255,255,0.09);
        }
        
        .filter-input::placeholder {
            color: rgba(255,255,255,0.3);
        }
        
        .btn-search {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            color: white;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(6,182,212,0.4);
        }
        
        .btn-clear-s {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 12px;
            padding: 10px 18px;
            color: #f87171;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        
        .btn-clear-s:hover {
            background: rgba(239,68,68,0.25);
            transform: translateY(-1px);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 18px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid rgba(255,255,255,0.07);
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            border-color: rgba(6,182,212,0.3);
        }
        
        .sc-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
            border-radius: 18px;
        }
        
        .sc-shapes svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .sc-icon, .stat-card > div:last-child {
            position: relative;
            z-index: 1;
        }
        
        .sc-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            flex-shrink: 0;
        }
        
        .sc-lbl {
            font-size: 10px;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .sc-val {
            font-size: 24px;
            font-weight: 700;
            color: white;
        }
        
        .sc-sub {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
            margin-top: 2px;
        }

        /* Table Card */
        .table-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.08);
            overflow: hidden;
            position: relative;
        }
        
        .tc-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
            border-radius: 20px;
        }
        
        .tc-shapes svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .table-header {
            padding: 18px 22px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            position: relative;
            z-index: 1;
        }
        
        .table-title {
            font-size: 15px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-title i {
            font-size: 20px;
            color: var(--cyan);
        }
        
        .table-wrap {
            overflow-x: auto;
            position: relative;
            z-index: 1;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead th {
            padding: 12px 18px;
            font-size: 11px;
            font-weight: 700;
            color: rgba(255,255,255,0.45);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            white-space: nowrap;
            text-align: left;
        }
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: rgba(255,255,255,0.03);
        }
        
        tbody td {
            padding: 14px 18px;
            font-size: 13px;
            color: rgba(255,255,255,0.7);
            border-bottom: 1px solid rgba(255,255,255,0.04);
            white-space: nowrap;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-ok {
            background: rgba(16,185,129,0.15);
            color: #34d399;
            border: 1px solid rgba(16,185,129,0.25);
        }
        
        .badge-exp {
            background: rgba(239,68,68,0.15);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.25);
        }
        
        .device-code {
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 11px;
            background: rgba(255,255,255,0.07);
            padding: 4px 10px;
            border-radius: 8px;
            color: var(--cyan);
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
            vertical-align: middle;
        }
        
        .btn-del {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            border: none;
            color: white;
            border-radius: 10px;
            padding: 7px 16px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: inherit;
        }
        
        .btn-del:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(239,68,68,0.4);
        }
        
        /* Alertas */
        .alerta {
            padding: 14px 18px;
            border-radius: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            font-weight: 600;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alerta-ok {
            background: rgba(16,185,129,0.12);
            border: 1px solid rgba(16,185,129,0.3);
            color: #34d399;
        }
        
        .alerta-err {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.3);
            color: #f87171;
        }
        
        .alerta i {
            font-size: 22px;
            flex-shrink: 0;
        }
        
        /* Paginação */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 20px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .pag-btn {
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: rgba(255,255,255,0.6);
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            transition: all 0.2s;
        }
        
        .pag-btn:hover {
            background: rgba(6,182,212,0.3);
            color: white;
        }
        
        .pag-btn.active {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
            border-color: transparent;
        }
        
        .pag-btn.disabled {
            opacity: 0.3;
            pointer-events: none;
        }
        
        /* Empty state */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .empty-state i {
            font-size: 56px;
            color: rgba(255,255,255,0.08);
            display: block;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: rgba(255,255,255,0.4);
            font-size: 14px;
        }
        
        .pagination-info {
            text-align: center;
            margin-top: 18px;
            margin-bottom: 10px;
            color: rgba(255,255,255,0.4);
            font-size: 12px;
            font-weight: 500;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            
            .filter-item {
                min-width: 100% !important;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            tbody td {
                font-size: 11px;
                padding: 10px 12px;
            }
            
            thead th {
                padding: 10px 12px;
                font-size: 9px;
            }
        }
    </style>
</head>
<body>

    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">

            <div class="content-header row">
                <div class="col-12">
                    <div class="info-badge">
                        <i class='bx bx-devices'></i>
                        <span>Gerenciar Device IDs dos usuários</span>
                    </div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class='bx bx-devices'></i> Device ID</h1>
                    <p>Gerencie os dispositivos vinculados aos seus usuários</p>
                </div>
            </div>

            <!-- Alertas -->
            <?php if (!empty($msg_sucesso)): ?>
            <div class="alerta alerta-ok">
                <i class='bx bx-check-circle'></i> 
                <?php echo $msg_sucesso; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($msg_erro)): ?>
            <div class="alerta alerta-err">
                <i class='bx bx-error-circle'></i> 
                <?php echo $msg_erro; ?>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="sc-shapes">
                        <svg xmlns="http://www.w3.org/2000/svg">
                            <circle cx="88%" cy="16%" r="22" fill="rgba(6,182,212,0.20)"/>
                            <circle cx="95%" cy="74%" r="12" fill="rgba(6,182,212,0.10)"/>
                        </svg>
                    </div>
                    <div class="sc-icon" style="background:linear-gradient(135deg,#06b6d4,#0891b2)">
                        <i class='bx bx-devices'></i>
                    </div>
                    <div>
                        <div class="sc-lbl">Total Devices</div>
                        <div class="sc-val"><?php echo $total_devices; ?></div>
                        <div class="sc-sub">Registrados</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="sc-shapes">
                        <svg xmlns="http://www.w3.org/2000/svg">
                            <circle cx="90%" cy="13%" r="20" fill="rgba(65,88,208,0.20)"/>
                            <circle cx="94%" cy="72%" r="10" fill="rgba(165,180,252,0.10)"/>
                        </svg>
                    </div>
                    <div class="sc-icon" style="background:linear-gradient(135deg,#4158D0,#6366f1)">
                        <i class='bx bx-user-check'></i>
                    </div>
                    <div>
                        <div class="sc-lbl">Usuários</div>
                        <div class="sc-val"><?php echo $total_usuarios; ?></div>
                        <div class="sc-sub">Com device vinculado</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="sc-shapes">
                        <svg xmlns="http://www.w3.org/2000/svg">
                            <circle cx="91%" cy="14%" r="21" fill="rgba(245,158,11,0.20)"/>
                            <circle cx="95%" cy="70%" r="13" fill="rgba(253,224,71,0.10)"/>
                        </svg>
                    </div>
                    <div class="sc-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)">
                        <i class='bx bx-block'></i>
                    </div>
                    <div>
                        <div class="sc-lbl">Limitadores</div>
                        <div class="sc-val"><?php echo $total_userlim; ?></div>
                        <div class="sc-sub">Em userlimiter</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="sc-shapes">
                        <svg xmlns="http://www.w3.org/2000/svg">
                            <circle cx="87%" cy="15%" r="24" fill="rgba(52,211,153,0.18)"/>
                            <circle cx="93%" cy="78%" r="11" fill="rgba(110,231,183,0.10)"/>
                        </svg>
                    </div>
                    <div class="sc-icon" style="background:linear-gradient(135deg,#10b981,#34d399)">
                        <i class='bx bx-list-ul'></i>
                    </div>
                    <div>
                        <div class="sc-lbl">Nesta Página</div>
                        <div class="sc-val"><?php echo count($devices); ?></div>
                        <div class="sc-sub">de <?php echo $total; ?> total</div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-card">
                <div class="filters-title">
                    <i class='bx bx-filter-alt'></i> 
                    Filtros de Busca
                </div>
                <form method="GET" class="filter-group">
                    <div class="filter-item">
                        <div class="filter-label">BUSCAR POR LOGIN OU DEVICE ID</div>
                        <input type="text" name="busca" class="filter-input"
                               placeholder="Digite para buscar..."
                               value="<?php echo htmlspecialchars($busca); ?>">
                    </div>
                    <div style="display:flex;gap:10px;align-items:flex-end;">
                        <button type="submit" class="btn-search">
                            <i class='bx bx-search'></i> Buscar
                        </button>
                        <?php if (!empty($busca)): ?>
                        <a href="deviceid.php" class="btn-clear-s">
                            <i class='bx bx-x'></i> Limpar
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabela -->
            <div class="table-card">
                <div class="tc-shapes">
                    <svg xmlns="http://www.w3.org/2000/svg">
                        <circle cx="97%" cy="8%" r="60" fill="rgba(6,182,212,0.05)"/>
                        <circle cx="3%" cy="92%" r="40" fill="rgba(65,88,208,0.05)"/>
                    </svg>
                </div>

                <div class="table-header">
                    <div class="table-title">
                        <i class='bx bx-devices'></i> 
                        Devices Registrados
                    </div>
                </div>

                <?php if (empty($devices)): ?>
                <div class="empty-state">
                    <i class='bx bx-devices'></i>
                    <p>
                        <?php echo !empty($busca) ? 'Nenhum device encontrado para "' . htmlspecialchars($busca) . '".' : 'Nenhum device registrado ainda.'; ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Login</th>
                                <th>Device ID</th>
                                <th>Status Conta</th>
                                <th>Validade</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $num = $offset + 1; foreach ($devices as $d):
                            $exp     = strtotime($d['expira']);
                            $vencido = $exp < time();
                        ?>
                        <tr>
                            <td style="color:rgba(255,255,255,0.4);font-size:11px;"><?php echo $num++; ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($d['nome_user'],0,1)); ?>
                                    </div>
                                    <span style="color:white;font-weight:600;"><?php echo htmlspecialchars($d['nome_user']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="device-code" title="<?php echo htmlspecialchars($d['deviceid'] ?? $d['device_id'] ?? '—'); ?>">
                                    <?php echo htmlspecialchars($d['deviceid'] ?? $d['device_id'] ?? '—'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($vencido): ?>
                                <span class="badge badge-exp">
                                    <i class='bx bx-time'></i> Vencido
                                </span>
                                <?php else: ?>
                                <span class="badge badge-ok">
                                    <i class='bx bx-check-circle'></i> Ativo
                                </span>
                                <?php endif; ?>
                            </td>
                            <td style="color:<?php echo $vencido ? '#f87171' : 'rgba(255,255,255,0.7)'; ?>;">
                                <?php echo !empty($d['expira']) ? date('d/m/Y', strtotime($d['expira'])) : '—'; ?>
                            </td>
                            <td>
                                <button class="btn-del" onclick="removerDevice('<?php echo htmlspecialchars($d['nome_user']); ?>', this)">
                                    <i class='bx bx-trash'></i> Remover Device
                                </button>
                                <form method="POST" id="form-del-<?php echo htmlspecialchars($d['nome_user']); ?>" style="display:none;">
                                    <input type="hidden" name="nome_user" value="<?php echo htmlspecialchars($d['nome_user']); ?>">
                                    <input type="hidden" name="deletar_device_direto" value="1">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pag > 1): ?>
                <div class="pagination">
                    <a href="?busca=<?php echo urlencode($busca); ?>&pagina=<?php echo max(1,$pagina-1); ?>" class="pag-btn <?php echo $pagina<=1?'disabled':''; ?>">
                        <i class='bx bx-chevron-left'></i>
                    </a>
                    <?php
                    $inicio = max(1, $pagina - 2);
                    $fim    = min($total_pag, $pagina + 2);
                    if ($inicio > 1) echo '<span class="pag-btn disabled">…</span>';
                    for ($p = $inicio; $p <= $fim; $p++):
                    ?>
                    <a href="?busca=<?php echo urlencode($busca); ?>&pagina=<?php echo $p; ?>" class="pag-btn <?php echo $p==$pagina?'active':''; ?>"><?php echo $p; ?></a>
                    <?php endfor;
                    if ($fim < $total_pag) echo '<span class="pag-btn disabled">…</span>';
                    ?>
                    <a href="?busca=<?php echo urlencode($busca); ?>&pagina=<?php echo min($total_pag,$pagina+1); ?>" class="pag-btn <?php echo $pagina>=$total_pag?'disabled':''; ?>">
                        <i class='bx bx-chevron-right'></i>
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="pagination-info">
                Exibindo <?php echo count($devices); ?> de <?php echo $total; ?> device(s)
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function removerDevice(login, btn) {
            swal({
                title: "Tem certeza?",
                text: "Deseja remover o Device ID de " + login + "?",
                icon: "warning",
                buttons: true,
                dangerMode: true
            }).then(function(confirm) {
                if (confirm) {
                    document.getElementById('form-del-' + login).submit();
                } else {
                    swal("Cancelado!");
                }
            });
        }
    </script>
</body>
</html>
<?php
    }
    aleatorio653751($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
h2_tema ?? ['classe'=>'theme-dark'])); ?>">

    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">

            <div class="content-header row">
                <div class="col-12">
                    <div class="info-badge">
                        <i class='bx bx-devices'></i>
                        <span>Gerenciar Device IDs dos usuários</span>
                    </div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class='bx bx-devices'></i> Device ID</h1>
                    <p>Gerencie os dispositivos vinculados aos seus usuários</p>
                </div>
            </div>

            <!-- Alertas -->
            <?php if (!empty($msg_sucesso)): ?>
            <div class="alerta alerta-ok">
                <i class='bx bx-check-circle'></i> 
                <?php echo $msg_sucesso; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($msg_erro)): ?>
            <div class="alerta alerta-err">
                <i class='bx bx-error-circle'></i> 
                <?php echo $msg_erro; ?>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="sc-shapes">
                        <svg xmlns="http://www.w3.org/2000/svg">
                            <circle cx="88%" cy="16%" r="22" fill="rgba(6,182,212,0.20)"/>
                            <circle cx="95%" cy="74%" r="12" fill="rgba(6,182,212,0.10)"/>
                        </svg>
                    </div>
                    <div class="sc-icon" style="background:linear-gradient(135deg,#06b6d4,#0891b2)">
                        <i class='bx bx-devices'></i>
                    </div>
                    <div>
                        <div class="sc-lbl">Total Devices</div>
                        <div class="sc-val"><?php echo $total_devices; ?></div>
                        <div class="sc-sub">Registrados</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="sc-shapes">
                        <svg xmlns="http://www.w3.org/2000/svg">
                            <circle cx="90%" cy="13%" r="20" fill="rgba(65,88,208,0.20)"/>
                            <circle cx="94%" cy="72%" r="10" fill="rgba(165,180,252,0.10)"/>
                        </svg>
                    </div>
                    <div class="sc-icon" style="background:linear-gradient(135deg,#4158D0,#6366f1)">
                        <i class='bx bx-user-check'></i>
                    </div>
                    <div>
                        <div class="sc-lbl">Usuários</div>
                        <div class="sc-val"><?php echo $total_usuarios; ?></div>
                        <div class="sc-sub">Com device vinculado</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="sc-shapes">
                        <svg xmlns="http://www.w3.org/2000/svg">
                            <circle cx="91%" cy="14%" r="21" fill="rgba(245,158,11,0.20)"/>
                            <circle cx="95%" cy="70%" r="13" fill="rgba(253,224,71,0.10)"/>
                        </svg>
                    </div>
                    <div class="sc-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)">
                        <i class='bx bx-block'></i>
                    </div>
                    <div>
                        <div class="sc-lbl">Limitadores</div>
                        <div class="sc-val"><?php echo $total_userlim; ?></div>
                        <div class="sc-sub">Em userlimiter</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="sc-shapes">
                        <svg xmlns="http://www.w3.org/2000/svg">
                            <circle cx="87%" cy="15%" r="24" fill="rgba(52,211,153,0.18)"/>
                            <circle cx="93%" cy="78%" r="11" fill="rgba(110,231,183,0.10)"/>
                        </svg>
                    </div>
                    <div class="sc-icon" style="background:linear-gradient(135deg,#10b981,#34d399)">
                        <i class='bx bx-list-ul'></i>
                    </div>
                    <div>
                        <div class="sc-lbl">Nesta Página</div>
                        <div class="sc-val"><?php echo count($devices); ?></div>
                        <div class="sc-sub">de <?php echo $total; ?> total</div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-card">
                <div class="filters-title">
                    <i class='bx bx-filter-alt'></i> 
                    Filtros de Busca
                </div>
                <form method="GET" class="filter-group">
                    <div class="filter-item">
                        <div class="filter-label">BUSCAR POR LOGIN OU DEVICE ID</div>
                        <input type="text" name="busca" class="filter-input"
                               placeholder="Digite para buscar..."
                               value="<?php echo htmlspecialchars($busca); ?>">
                    </div>
                    <div style="display:flex;gap:10px;align-items:flex-end;">
                        <button type="submit" class="btn-search">
                            <i class='bx bx-search'></i> Buscar
                        </button>
                        <?php if (!empty($busca)): ?>
                        <a href="deviceid.php" class="btn-clear-s">
                            <i class='bx bx-x'></i> Limpar
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabela -->
            <div class="table-card">
                <div class="tc-shapes">
                    <svg xmlns="http://www.w3.org/2000/svg">
                        <circle cx="97%" cy="8%" r="60" fill="rgba(6,182,212,0.05)"/>
                        <circle cx="3%" cy="92%" r="40" fill="rgba(65,88,208,0.05)"/>
                    </svg>
                </div>

                <div class="table-header">
                    <div class="table-title">
                        <i class='bx bx-devices'></i> 
                        Devices Registrados
                    </div>
                </div>

                <?php if (empty($devices)): ?>
                <div class="empty-state">
                    <i class='bx bx-devices'></i>
                    <p>
                        <?php echo !empty($busca) ? 'Nenhum device encontrado para "' . htmlspecialchars($busca) . '".' : 'Nenhum device registrado ainda.'; ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Login</th>
                                <th>Device ID</th>
                                <th>Status Conta</th>
                                <th>Validade</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $num = $offset + 1; foreach ($devices as $d):
                            $exp     = strtotime($d['expira']);
                            $vencido = $exp < time();
                        ?>
                        <tr>
                            <td style="color:rgba(255,255,255,0.4);font-size:11px;"><?php echo $num++; ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($d['nome_user'],0,1)); ?>
                                    </div>
                                    <span style="color:white;font-weight:600;"><?php echo htmlspecialchars($d['nome_user']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="device-code" title="<?php echo htmlspecialchars($d['deviceid'] ?? $d['device_id'] ?? '—'); ?>">
                                    <?php echo htmlspecialchars($d['deviceid'] ?? $d['device_id'] ?? '—'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($vencido): ?>
                                <span class="badge badge-exp">
                                    <i class='bx bx-time'></i> Vencido
                                </span>
                                <?php else: ?>
                                <span class="badge badge-ok">
                                    <i class='bx bx-check-circle'></i> Ativo
                                </span>
                                <?php endif; ?>
                            </td>
                            <td style="color:<?php echo $vencido ? '#f87171' : 'rgba(255,255,255,0.7)'; ?>;">
                                <?php echo !empty($d['expira']) ? date('d/m/Y', strtotime($d['expira'])) : '—'; ?>
                            </td>
                            <td>
                                <button class="btn-del" onclick="removerDevice('<?php echo htmlspecialchars($d['nome_user']); ?>', this)">
                                    <i class='bx bx-trash'></i> Remover Device
                                </button>
                                <form method="POST" id="form-del-<?php echo htmlspecialchars($d['nome_user']); ?>" style="display:none;">
                                    <input type="hidden" name="nome_user" value="<?php echo htmlspecialchars($d['nome_user']); ?>">
                                    <input type="hidden" name="deletar_device_direto" value="1">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pag > 1): ?>
                <div class="pagination">
                    <a href="?busca=<?php echo urlencode($busca); ?>&pagina=<?php echo max(1,$pagina-1); ?>" class="pag-btn <?php echo $pagina<=1?'disabled':''; ?>">
                        <i class='bx bx-chevron-left'></i>
                    </a>
                    <?php
                    $inicio = max(1, $pagina - 2);
                    $fim    = min($total_pag, $pagina + 2);
                    if ($inicio > 1) echo '<span class="pag-btn disabled">…</span>';
                    for ($p = $inicio; $p <= $fim; $p++):
                    ?>
                    <a href="?busca=<?php echo urlencode($busca); ?>&pagina=<?php echo $p; ?>" class="pag-btn <?php echo $p==$pagina?'active':''; ?>"><?php echo $p; ?></a>
                    <?php endfor;
                    if ($fim < $total_pag) echo '<span class="pag-btn disabled">…</span>';
                    ?>
                    <a href="?busca=<?php echo urlencode($busca); ?>&pagina=<?php echo min($total_pag,$pagina+1); ?>" class="pag-btn <?php echo $pagina>=$total_pag?'disabled':''; ?>">
                        <i class='bx bx-chevron-right'></i>
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="pagination-info">
                Exibindo <?php echo count($devices); ?> de <?php echo $total; ?> device(s)
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function removerDevice(login, btn) {
            swal({
                title: "Tem certeza?",
                text: "Deseja remover o Device ID de " + login + "?",
                icon: "warning",
                buttons: true,
                dangerMode: true
            }).then(function(confirm) {
                if (confirm) {
                    document.getElementById('form-del-' + login).submit();
                } else {
                    swal("Cancelado!");
                }
            });
        }
    </script>
</body>
</html>
<?php
    }
    aleatorio653751($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

