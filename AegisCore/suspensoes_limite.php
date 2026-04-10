<?php
error_reporting(0);
session_start();
include('conexao.php');
include('header2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

function anti_sql($input)
{
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
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
        echo "<script>alert('Token InvÃ¡lido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// VerificaÃ§Ã£o de validade da conta do revendedor
$sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$res5 = $conn->query($sql5);
$row5 = $res5->fetch_assoc();
$_SESSION['tipodeconta'] = $row5['tipo'];
date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($row5['expira'] < $hoje) {
        echo "<script>alert('Sua conta estÃ¡ vencida')</script>";
        echo "<script>window.location.href = '../home.php'</script>";
        exit;
    }
}

// ===== CRIAR TABELA SE NÃƒO EXISTIR =====
$conn->query("CREATE TABLE IF NOT EXISTS suspensoes_limite (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(100) NOT NULL,
    limite INT(11) NOT NULL DEFAULT 1,
    conexoes INT(11) NOT NULL DEFAULT 0,
    byid VARCHAR(100) NOT NULL,
    motivo VARCHAR(255) DEFAULT 'Excedeu limite de dispositivos',
    data_suspensao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reativado TINYINT(1) NOT NULL DEFAULT 0,
    data_reativacao DATETIME NULL,
    INDEX idx_login (login),
    INDEX idx_byid (byid),
    INDEX idx_reativado (reativado)
)");

// ===== LIMPAR AUTOMATICAMENTE REGISTROS REATIVADOS COM MAIS DE 7 DIAS =====
$conn->query("DELETE FROM suspensoes_limite WHERE reativado = 1 AND data_reativacao < DATE_SUB(NOW(), INTERVAL 7 DAY)");

// ===== AÃ‡ÃƒO: LIMPAR HISTÃ“RICO MANUAL =====
$msg_ok  = '';
$msg_err = '';

if (isset($_POST['limpar_historico'])) {
    $conn->query("DELETE FROM suspensoes_limite WHERE byid = '{$_SESSION['iduser']}' AND reativado = 1");
    $msg_ok = "HistÃ³rico de reativados limpo com sucesso!";
}

// ===== BUSCA E FILTROS =====
$busca = anti_sql($_GET['busca'] ?? '');
$filtro = anti_sql($_GET['filtro'] ?? 'todos');

$where = "s.byid = '{$_SESSION['iduser']}'";
if (!empty($busca)) {
    $where .= " AND s.login LIKE '%$busca%'";
}
if ($filtro === 'ativos') {
    $where .= " AND s.reativado = 0";
} elseif ($filtro === 'reativados') {
    $where .= " AND s.reativado = 1";
}

// ===== PAGINAÃ‡ÃƒO =====
$por_pagina = 12;
$pagina     = max(1, intval($_GET['pagina'] ?? 1));
$offset     = ($pagina - 1) * $por_pagina;

$total_res = $conn->query("SELECT COUNT(*) as t FROM suspensoes_limite s WHERE $where");
$total     = $total_res->fetch_assoc()['t'] ?? 0;
$total_pag = ceil($total / $por_pagina);

$registros = [];
$res_list  = $conn->query("SELECT s.*, a.id as user_id, a.expira, a.senha, a.limite as limite_atual, a.status as status_conta, a.uuid, a.categoriaid
                            FROM suspensoes_limite s
                            LEFT JOIN ssh_accounts a ON a.login = s.login AND a.byid = s.byid
                            WHERE $where
                            ORDER BY s.data_suspensao DESC
                            LIMIT $por_pagina OFFSET $offset");
if ($res_list) {
    while ($r = $res_list->fetch_assoc()) $registros[] = $r;
}

// ===== STATS =====
$stat_total    = $conn->query("SELECT COUNT(*) as t FROM suspensoes_limite WHERE byid='{$_SESSION['iduser']}'")->fetch_assoc()['t'] ?? 0;
$stat_ativos   = $conn->query("SELECT COUNT(*) as t FROM suspensoes_limite WHERE byid='{$_SESSION['iduser']}' AND reativado=0")->fetch_assoc()['t'] ?? 0;
$stat_reat     = $conn->query("SELECT COUNT(*) as t FROM suspensoes_limite WHERE byid='{$_SESSION['iduser']}' AND reativado=1")->fetch_assoc()['t'] ?? 0;
$stat_hoje     = $conn->query("SELECT COUNT(*) as t FROM suspensoes_limite WHERE byid='{$_SESSION['iduser']}' AND DATE(data_suspensao)=CURDATE()")->fetch_assoc()['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuspensÃµes por Limite</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuspensÃµes por Limite</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        :root {
            --primary: #4158D0;
            --secondary: #C850C0;
            --tertiary: #FFCC70;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #2c3e50;
            --light: #f8fafc;
            --border: #eef2f6;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }

        .app-content {
            margin-left: 240px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 1650px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-header {
            display: none !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--danger) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--danger);
        }

        .alerta {
            padding: 12px 18px;
            border-radius: 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            animation: slideDown .3s ease;
        }
        @keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
        .alerta-ok  { background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.3); color: #34d399; }
        .alerta-err { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); color: #f87171; }

        .filters-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 14px !important;
            padding: 14px !important;
            margin-bottom: 16px !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
        }

        .filters-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filters-title i { color: var(--tertiary); font-size: 16px; }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
        }

        .filter-item { flex: 1 1 200px; min-width: 160px; }
        .filter-label {
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-input, .filter-select {
            width: 100%;
            padding: 7px 12px;
            background: #1e293b;
            border: 1.5px solid #334155;
            border-radius: 10px;
            font-size: 13px;
            color: white;
            transition: all 0.3s;
        }
        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--danger);
            background: #1e293b;
        }
        .filter-input::placeholder { color: #94a3b8; }
        .filter-select {
            cursor: pointer;
            background-color: #1e293b;
            color: white;
        }
        .filter-select option {
            background: #1e293b;
            color: white;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 14px;
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
        }

        .sc-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }
        .sc-icon.red { background: linear-gradient(135deg, #ef4444, #b91c1c); }
        .sc-icon.orange { background: linear-gradient(135deg, #f97316, #ea580c); }
        .sc-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
        .sc-icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }

        .sc-body { flex: 1; }
        .sc-lbl { font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 3px; }
        .sc-val { font-size: 24px; font-weight: 700; color: white; line-height: 1; }
        .sc-sub { font-size: 10px; color: rgba(255,255,255,0.5); margin-top: 2px; }

        /* GRID DE CARDS */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
            margin-top: 14px;
            width: 100%;
        }

        .user-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 16px !important;
            overflow: hidden !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            animation: fadeIn 0.4s ease !important;
            position: relative !important;
        }

        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 80% 20%, rgba(239,68,68,0.1) 0%, transparent 60%);
            pointer-events: none;
        }

        .user-card:hover {
            transform: translateY(-3px) !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .user-header {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .user-header.reativado {
            background: linear-gradient(135deg, #10b981, #059669) !important;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .user-text {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 14px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 4px;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .v2ray-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }

        .user-body {
            padding: 12px;
            position: relative;
            z-index: 1;
        }

        .info-row {
            display: flex;
            align-items: center;
            padding: 7px 10px;
            background: rgba(255,255,255,0.03);
            border-radius: 9px;
            margin-bottom: 6px;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
        }

        .info-row:hover {
            border-color: var(--danger);
            background: rgba(255,255,255,0.05);
        }

        .info-icon {
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.03);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-size: 14px;
            border: 1px solid rgba(255,255,255,0.05);
            flex-shrink: 0;
        }

        .info-content {
            flex: 1;
            min-width: 0;
        }

        .info-label {
            font-size: 9px;
            color: rgba(255,255,255,0.4);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 1px;
        }

        .info-value {
            font-size: 12px;
            font-weight: 600;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }
        .badge-danger { background: rgba(239,68,68,.15); color: #f87171; border: 1px solid rgba(239,68,68,.25); }
        .badge-success { background: rgba(16,185,129,.15); color: #34d399; border: 1px solid rgba(16,185,129,.25); }
        .badge-warning { background: rgba(249,115,22,.15); color: #fb923c; border: 1px solid rgba(249,115,22,.25); }

        .conexoes-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .bar-track {
            flex: 1;
            height: 6px;
            background: rgba(255,255,255,.08);
            border-radius: 10px;
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, #ef4444, #b91c1c);
        }

        .user-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .action-btn {
            flex: 1;
            min-width: 60px;
            padding: 5px 8px;
            border: none;
            border-radius: 20px;
            font-weight: 600;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            color: white;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
        }

        .action-btn i { font-size: 12px; }

        .btn-reat {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .btn-reat:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(16,185,129,0.4);
        }
        .btn-reat:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-limpar {
            background: rgba(239,68,68,.12);
            border: 1px solid rgba(239,68,68,.25);
            border-radius: 10px;
            padding: 7px 14px;
            color: #f87171;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all .2s;
            text-decoration: none;
        }
        .btn-limpar:hover { background: rgba(239,68,68,.22); transform: translateY(-1px); }

        .table-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table-title {
            font-size: 15px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .table-title i { font-size: 20px; color: var(--danger); }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 18px;
            flex-wrap: wrap;
        }
        .pag-btn {
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            color: rgba(255,255,255,0.5);
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            transition: all .2s;
        }
        .pag-btn:hover { background: rgba(239,68,68,.2); color: white; }
        .pag-btn.active { background: linear-gradient(135deg, #ef4444, #b91c1c); color: white; border-color: transparent; }
        .pag-btn.disabled { opacity: .3; pointer-events: none; }

        .pag-info {
            text-align: center;
            margin-top: 6px;
            margin-bottom: 10px;
            color: rgba(255,255,255,0.5);
            font-size: 12px;
        }

        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.08);
            color: white;
        }
        .empty-state i { font-size: 48px; color: rgba(255,255,255,0.2); margin-bottom: 15px; }
        .empty-state h3 { color: white; font-size: 16px; margin-bottom: 5px; }
        .empty-state p { color: rgba(255,255,255,0.3); font-size: 13px; }

        /* Modais */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }
        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.9) translateY(-30px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header-custom.success { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header-custom.error { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header-custom h5 { margin: 0; display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 600; }
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
        }
        .modal-close:hover { opacity: 1; }
        .modal-body-custom { padding: 24px; color: white; }
        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .modal-info-card {
            background: rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom: none; }
        .modal-info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-info-value {
            font-size: 13px;
            font-weight: 700;
            color: white;
        }
        .modal-info-value.credential {
            background: rgba(0,0,0,0.3);
            padding: 4px 10px;
            border-radius: 8px;
            font-family: monospace;
        }
        .modal-success-icon { text-align: center; margin-bottom: 20px; }
        .modal-success-icon i { font-size: 70px; color: #10b981; filter: drop-shadow(0 0 15px rgba(16, 185, 129, 0.5)); }
        .btn-modal {
            padding: 9px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            color: white;
        }
        .btn-modal-cancel { background: linear-gradient(135deg, #64748b, #475569); }
        .btn-modal-ok { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-modal-cancel:hover, .btn-modal-ok:hover { transform: translateY(-2px); }
        .processing-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            padding: 10px 0 20px;
        }
        .spinner-ring {
            width: 64px;
            height: 64px;
            border: 4px solid rgba(255,255,255,0.1);
            border-top-color: #10b981;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner-text { color: rgba(255,255,255,0.7); font-size: 14px; font-weight: 500; }

        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { padding: 10px !important; }
            .users-grid { grid-template-columns: 1fr; gap: 12px; }
            .user-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .action-btn { width: 100%; }
            .modal-container { width: 95%; }
            .modal-info-row { flex-direction: column; align-items: flex-start; gap: 6px; }
            .modal-footer-custom { flex-direction: column; }
            .btn-modal { width: 100%; justify-content: center; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .table-title { font-size: 13px; }
        }
    </style>
</head>
<body>
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <div class="info-badge">
        <i class='bx bx-shield-x'></i>
        <span>UsuÃ¡rios suspensos por limite de acesso</span>
    </div>

    <?php if (!empty($msg_ok)): ?>
    <div class="alerta alerta-ok"><i class='bx bx-check-circle'></i> <?php echo $msg_ok; ?></div>
    <?php endif; ?>
    <?php if (!empty($msg_err)): ?>
    <div class="alerta alerta-err"><i class='bx bx-error-circle'></i> <?php echo $msg_err; ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="sc-icon red"><i class='bx bx-shield-x'></i></div>
            <div class="sc-body">
                <div class="sc-lbl">Total SuspensÃµes</div>
                <div class="sc-val"><?php echo $stat_total; ?></div>
                <div class="sc-sub">HistÃ³rico completo</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="sc-icon orange"><i class='bx bx-lock'></i></div>
            <div class="sc-body">
                <div class="sc-lbl">Ainda Suspensos</div>
                <div class="sc-val"><?php echo $stat_ativos; ?></div>
                <div class="sc-sub">Aguardando reativaÃ§Ã£o</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="sc-icon green"><i class='bx bx-check-shield'></i></div>
            <div class="sc-body">
                <div class="sc-lbl">Reativados</div>
                <div class="sc-val"><?php echo $stat_reat; ?></div>
                <div class="sc-sub">ApÃ³s suspensÃ£o</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="sc-icon blue"><i class='bx bx-calendar-exclamation'></i></div>
            <div class="sc-body">
                <div class="sc-lbl">Hoje</div>
                <div class="sc-val"><?php echo $stat_hoje; ?></div>
                <div class="sc-sub">Suspensos hoje</div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <div class="filters-title">
            <i class='bx bx-filter-alt'></i>
            Filtros
        </div>
        <div class="filter-group">
            <div class="filter-item">
                <div class="filter-label">BUSCAR POR LOGIN</div>
                <input type="text" class="filter-input" id="searchInput"
                       placeholder="Digite para buscar automaticamente..."
                       value="<?php echo htmlspecialchars($busca); ?>"
                       onkeyup="filtrarBusca()">
            </div>
            <div class="filter-item">
                <div class="filter-label">STATUS</div>
                <select class="filter-select" id="statusFilter" onchange="filtrarStatus()">
                    <option value="todos" <?php echo $filtro == 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="ativos" <?php echo $filtro == 'ativos' ? 'selected' : ''; ?>>Ainda Suspensos</option>
                    <option value="reativados" <?php echo $filtro == 'reativados' ? 'selected' : ''; ?>>Reativados</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tabela/Cards -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">
                <i class='bx bx-list-ul'></i>
                HistÃ³rico de SuspensÃµes por Limite
                <span style="font-size: 10px; background: rgba(16,185,129,0.2); padding: 2px 8px; border-radius: 20px;">
                    <i class='bx bx-calendar'></i> Limpa reativados apÃ³s 7 dias
                </span>
            </div>
            <form method="POST" onsubmit="return confirm('Limpar registros jÃ¡ reativados?')">
                <input type="hidden" name="limpar_historico" value="1">
                <button type="submit" class="btn-limpar">
                    <i class='bx bx-trash'></i> Limpar reativados
                </button>
            </form>
        </div>

        <!-- Grid de Cards -->
        <div class="users-grid" id="usersGrid">
            <?php if (empty($registros)): ?>
            <div class="empty-state">
                <i class='bx bx-shield-x'></i>
                <h3>Nenhum usuÃ¡rio suspenso</h3>
                <p>
                    <?php echo !empty($busca) 
                        ? 'Nenhum resultado para "' . htmlspecialchars($busca) . '".' 
                        : 'NÃ£o hÃ¡ usuÃ¡rios suspensos por limite de dispositivos.'; ?>
                </p>
            </div>
            <?php else: ?>
            <?php foreach ($registros as $r):
                $pct = $r['limite'] > 0 ? min(100, round(($r['conexoes'] / $r['limite']) * 100)) : 100;
                $expira_formatada = !empty($r['expira']) ? date('d/m/Y', strtotime($r['expira'])) : 'Nunca';
                $dias_restantes = 0;
                if (!empty($r['expira']) && $r['expira'] != 'Nunca') {
                    $data_validade = strtotime($r['expira']);
                    $data_atual = time();
                    $diferenca = $data_validade - $data_atual;
                    $dias_restantes = floor($diferenca / (60 * 60 * 24));
                }
            ?>
            <div class="user-card" 
                 data-login="<?php echo strtolower($r['login']); ?>"
                 data-status="<?php echo $r['reativado'] ? 'reativado' : 'suspenso'; ?>"
                 data-user-id="<?php echo $r['user_id']; ?>"
                 data-login-nome="<?php echo htmlspecialchars($r['login']); ?>"
                 data-senha="<?php echo htmlspecialchars($r['senha'] ?? ''); ?>"
                 data-expira="<?php echo $expira_formatada; ?>">

                <div class="user-header <?php echo $r['reativado'] ? 'reativado' : ''; ?>">
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class='bx bx-user'></i>
                        </div>
                        <div class="user-text">
                            <div class="user-name">
                                <?php echo htmlspecialchars($r['login']); ?>
                                <?php if (!empty($r['uuid'])): ?>
                                    <span class="v2ray-badge">V2RAY</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="user-body">
                    <!-- Status e Motivo -->
                    <div class="info-row">
                        <div class="info-icon"><i class='bx bx-info-circle'></i></div>
                        <div class="info-content">
                            <div class="info-label">STATUS / MOTIVO</div>
                            <div class="info-value">
                                <?php if ($r['reativado']): ?>
                                    <span class="badge badge-success"><i class='bx bx-check-circle'></i> Reativado</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class='bx bx-lock'></i> Suspenso</span>
                                <?php endif; ?>
                                <span class="badge badge-warning" style="margin-left: 5px;">
                                    <i class='bx bx-error-circle'></i> <?php echo htmlspecialchars($r['motivo'] ?? 'Excedeu limite'); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                

                    <!-- ConexÃµes e Limite -->
                    <div class="info-row">
                        <div class="info-icon"><i class='bx bx-group'></i></div>
                        <div class="info-content">
                            <div class="info-label">CONEXÃ•ES / LIMITE</div>
                            <div class="info-value">
                                <div class="conexoes-bar">
                                    <span style="color:var(--danger);font-weight:700;"><?php echo $r['conexoes']; ?></span>
                                    <span>/ <?php echo $r['limite']; ?></span>
                                    <div class="bar-track">
                                        <div class="bar-fill" style="width:<?php echo $pct; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data SuspensÃ£o -->
                    <div class="info-row">
                        <div class="info-icon"><i class='bx bx-calendar-exclamation'></i></div>
                        <div class="info-content">
                            <div class="info-label">SUSPENSO EM</div>
                            <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($r['data_suspensao'])); ?></div>
                        </div>
                    </div>

                    <!-- Validade -->
                    <div class="info-row">
                        <div class="info-icon"><i class='bx bx-calendar-check'></i></div>
                        <div class="info-content">
                            <div class="info-label">VALIDA ATÃ‰</div>
                            <div class="info-value">
                                <?php echo $expira_formatada; ?>
                                <?php if ($dias_restantes > 0 && $dias_restantes <= 5): ?>
                                    <span style="color:#fbbf24; font-size:10px;"> (<?php echo $dias_restantes; ?> dias)</span>
                                <?php elseif ($dias_restantes < 0): ?>
                                    <span style="color:#f87171; font-size:10px;"> (Expirado)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- BotÃ£o Reativar -->
                    <div class="user-actions">
                        <?php if (!$r['reativado'] && !empty($r['user_id'])): ?>
                        <button class="action-btn btn-reat" onclick="reativarSuspenso(<?php echo $r['user_id']; ?>, this)">
                            <i class='bx bx-refresh'></i> Reativar
                        </button>
                        <?php elseif (!$r['reativado'] && empty($r['user_id'])): ?>
                        <button class="action-btn btn-reat" disabled style="background: #64748b;">
                            <i class='bx bx-error-circle'></i> UsuÃ¡rio nÃ£o encontrado
                        </button>
                        <?php else: ?>
                        <button class="action-btn btn-reat" disabled>
                            <i class='bx bx-check'></i> Reativado
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pag > 1): ?>
        <div class="pagination">
            <a href="?busca=<?php echo urlencode($busca); ?>&filtro=<?php echo $filtro; ?>&pagina=<?php echo max(1,$pagina-1); ?>"
               class="pag-btn <?php echo $pagina<=1?'disabled':''; ?>">
                <i class='bx bx-chevron-left'></i>
            </a>
            <?php
            $ini = max(1,$pagina-2); $fim = min($total_pag,$pagina+2);
            if ($ini>1) echo '<span class="pag-btn disabled">â€¦</span>';
            for ($p=$ini; $p<=$fim; $p++):
            ?>
            <a href="?busca=<?php echo urlencode($busca); ?>&filtro=<?php echo $filtro; ?>&pagina=<?php echo $p; ?>"
               class="pag-btn <?php echo $p==$pagina?'active':''; ?>"><?php echo $p; ?></a>
            <?php endfor;
            if ($fim<$total_pag) echo '<span class="pag-btn disabled">â€¦</span>';
            ?>
            <a href="?busca=<?php echo urlencode($busca); ?>&filtro=<?php echo $filtro; ?>&pagina=<?php echo min($total_pag,$pagina+1); ?>"
               class="pag-btn <?php echo $pagina>=$total_pag?'disabled':''; ?>">
                <i class='bx bx-chevron-right'></i>
            </a>
        </div>
        <?php endif; ?>

        <div class="pag-info">
            Exibindo <?php echo count($registros); ?> de <?php echo $total; ?> registro(s)
        </div>
    </div>

</div>
</div>

<!-- Modal ConfirmaÃ§Ã£o ReativaÃ§Ã£o -->
<div id="modalConfirmarReativacao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5>
                    <i class='bx bx-refresh'></i>
                    Confirmar ReativaÃ§Ã£o
                </h5>
                <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarReativacao')">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-success-icon">
                    <i class='bx bx-refresh'></i>
                </div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label">
                            <i class='bx bx-user'></i> UsuÃ¡rio
                        </div>
                        <div class="modal-info-value credential" id="reativar-login">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label">
                            <i class='bx bx-lock-alt'></i> Senha
                        </div>
                        <div class="modal-info-value credential" id="reativar-senha">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label">
                            <i class='bx bx-calendar'></i> Validade
                        </div>
                        <div class="modal-info-value" id="reativar-expira">â€”</div>
                    </div>
                </div>
                <p style="text-align:center; color: rgba(255,255,255,0.5); font-size: 12px;">
                    O usuÃ¡rio serÃ¡ reativado e poderÃ¡ acessar normalmente.
                </p>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarReativacao')">
                    <i class='bx bx-x'></i> Cancelar
                </button>
                <button type="button" class="btn-modal btn-modal-ok" id="btnConfirmarReativacao">
                    <i class='bx bx-refresh'></i> Reativar Agora
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Processando -->
<div id="modalProcessando" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom" style="background: linear-gradient(135deg, #4158D0, #C850C0);">
                <h5>
                    <i class='bx bx-loader-alt bx-spin'></i>
                    Processando
                </h5>
            </div>
            <div class="modal-body-custom">
                <div class="processing-spinner">
                    <div class="spinner-ring"></div>
                    <p class="spinner-text">Aguarde enquanto processamos sua solicitaÃ§Ã£o...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sucesso -->
<div id="modalSucessoOperacao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5>
                    <i class='bx bx-check-circle'></i>
                    OperaÃ§Ã£o Realizada!
                </h5>
                <button type="button" class="modal-close" onclick="fecharModalSucessoOperacao()">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-success-icon">
                    <i class='bx bx-check-circle'></i>
                </div>
                <h3 style="color:white; text-align:center; margin-bottom:10px;" id="sucesso-titulo">Sucesso!</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="sucesso-mensagem">OperaÃ§Ã£o realizada com sucesso!</p>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal btn-modal-ok" onclick="fecharModalSucessoOperacao()">
                    <i class='bx bx-check'></i> OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Erro -->
<div id="modalErro" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5>
                    <i class='bx bx-error-circle'></i>
                    Erro!
                </h5>
                <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <div style="text-align:center; margin-bottom: 20px;">
                    <i class='bx bx-error-circle' style="font-size:70px; color:#dc2626;"></i>
                </div>
                <h3 style="color:white; margin-bottom:10px; text-align:center;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="erro-mensagem">Erro ao processar solicitaÃ§Ã£o!</p>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal" style="background:linear-gradient(135deg,#dc2626,#b91c1c);" onclick="fecharModal('modalErro')">
                    <i class='bx bx-check'></i> Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
    // VariÃ¡veis globais
    let _reativacaoData = {};
    
    // Busca automÃ¡tica
    let searchTimeout;
    
    function filtrarBusca() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            let busca = document.getElementById('searchInput').value;
            let status = document.getElementById('statusFilter').value;
            let url = 'suspensoes_limite.php?busca=' + encodeURIComponent(busca) + '&filtro=' + status;
            window.location.href = url;
        }, 500);
    }
    
    function filtrarStatus() {
        let busca = document.getElementById('searchInput').value;
        let status = document.getElementById('statusFilter').value;
        let url = 'suspensoes_limite.php?busca=' + encodeURIComponent(busca) + '&filtro=' + status;
        window.location.href = url;
    }
    
    // FunÃ§Ãµes de modal
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }
    
    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }
    
    function fecharModalSucessoOperacao() {
        fecharModal('modalSucessoOperacao');
        location.reload();
    }
    
    function mostrarProcessando() {
        abrirModal('modalProcessando');
    }
    
    function esconderProcessando() {
        fecharModal('modalProcessando');
    }
    
    function mostrarSucesso(titulo, mensagem) {
        document.getElementById('sucesso-titulo').textContent = titulo;
        document.getElementById('sucesso-mensagem').textContent = mensagem;
        abrirModal('modalSucessoOperacao');
    }
    
    function mostrarErro(mensagem) {
        document.getElementById('erro-mensagem').textContent = mensagem;
        abrirModal('modalErro');
    }
    
    // ==================== REATIVAR ====================
    function reativarSuspenso(userId, buttonElement) {
        // Busca os dados no card
        const card = buttonElement.closest('.user-card');
        const login = card.getAttribute('data-login-nome');
        const senha = card.getAttribute('data-senha');
        const expira = card.getAttribute('data-expira');
        
        _reativacaoData = { id: userId, login, senha, expira };
        
        document.getElementById('reativar-login').textContent = login;
        document.getElementById('reativar-senha').textContent = senha;
        document.getElementById('reativar-expira').textContent = expira;
        
        // Remove evento anterior e adiciona novo
        const btnConfirmar = document.getElementById('btnConfirmarReativacao');
        const novoBtn = btnConfirmar.cloneNode(true);
        btnConfirmar.parentNode.replaceChild(novoBtn, btnConfirmar);
        
        novoBtn.onclick = function() {
            fecharModal('modalConfirmarReativacao');
            executarReativacaoSuspenso(userId);
        };
        
        abrirModal('modalConfirmarReativacao');
    }
    
    function executarReativacaoSuspenso(userId) {
        mostrarProcessando();
        
        $.ajax({
            url: 'reativar.php?id=' + userId,
            type: 'GET',
            success: function(data) {
                esconderProcessando();
                data = data.replace(/(\r\n|\n|\r)/gm, "");
                
                if (data == 'reativado com sucesso') {
                    // Atualiza o histÃ³rico de suspensÃµes
                    $.ajax({
                        url: 'atualizar_historico_reativacao.php',
                        type: 'POST',
                        data: { login: _reativacaoData.login },
                        success: function() {
                            mostrarSucesso('âœ… UsuÃ¡rio Reativado!', `UsuÃ¡rio ${_reativacaoData.login} foi reativado com sucesso!`);
                        },
                        error: function() {
                            mostrarSucesso('âœ… UsuÃ¡rio Reativado!', `UsuÃ¡rio ${_reativacaoData.login} foi reativado com sucesso!`);
                        }
                    });
                } else if (data == 'usuario nao encontrado') {
                    mostrarErro('UsuÃ¡rio nÃ£o encontrado no sistema!');
                } else if (data == 'erro no servidor') {
                    mostrarErro('Erro nos servidores! Verifique se estÃ£o online.');
                } else {
                    mostrarErro('Erro ao reativar usuÃ¡rio!');
                }
            },
            error: function(xhr, status, error) {
                esconderProcessando();
                mostrarErro('Erro ao conectar com o servidor: ' + error);
            }
        });
    }
    
    // Fechar modais ao clicar fora
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modalId = e.target.id;
            if (modalId === 'modalSucessoOperacao') {
                fecharModalSucessoOperacao();
            } else {
                e.target.classList.remove('show');
            }
        }
    });
    
    // ESC fecha modais
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('modalSucessoOperacao').classList.contains('show')) {
                fecharModalSucessoOperacao();
            } else {
                document.querySelectorAll('.modal-overlay.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        }
    });
</script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <div class="info-badge">
        <i class='bx bx-shield-x'></i>
        <span>UsuÃ¡rios suspensos por limite de acesso</span>
    </div>

    <?php if (!empty($msg_ok)): ?>
    <div class="alerta alerta-ok"><i class='bx bx-check-circle'></i> <?php echo $msg_ok; ?></div>
    <?php endif; ?>
    <?php if (!empty($msg_err)): ?>
    <div class="alerta alerta-err"><i class='bx bx-error-circle'></i> <?php echo $msg_err; ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="sc-icon red"><i class='bx bx-shield-x'></i></div>
            <div class="sc-body">
                <div class="sc-lbl">Total SuspensÃµes</div>
                <div class="sc-val"><?php echo $stat_total; ?></div>
                <div class="sc-sub">HistÃ³rico completo</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="sc-icon orange"><i class='bx bx-lock'></i></div>
            <div class="sc-body">
                <div class="sc-lbl">Ainda Suspensos</div>
                <div class="sc-val"><?php echo $stat_ativos; ?></div>
                <div class="sc-sub">Aguardando reativaÃ§Ã£o</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="sc-icon green"><i class='bx bx-check-shield'></i></div>
            <div class="sc-body">
                <div class="sc-lbl">Reativados</div>
                <div class="sc-val"><?php echo $stat_reat; ?></div>
                <div class="sc-sub">ApÃ³s suspensÃ£o</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="sc-icon blue"><i class='bx bx-calendar-exclamation'></i></div>
            <div class="sc-body">
                <div class="sc-lbl">Hoje</div>
                <div class="sc-val"><?php echo $stat_hoje; ?></div>
                <div class="sc-sub">Suspensos hoje</div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <div class="filters-title">
            <i class='bx bx-filter-alt'></i>
            Filtros
        </div>
        <div class="filter-group">
            <div class="filter-item">
                <div class="filter-label">BUSCAR POR LOGIN</div>
                <input type="text" class="filter-input" id="searchInput"
                       placeholder="Digite para buscar automaticamente..."
                       value="<?php echo htmlspecialchars($busca); ?>"
                       onkeyup="filtrarBusca()">
            </div>
            <div class="filter-item">
                <div class="filter-label">STATUS</div>
                <select class="filter-select" id="statusFilter" onchange="filtrarStatus()">
                    <option value="todos" <?php echo $filtro == 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="ativos" <?php echo $filtro == 'ativos' ? 'selected' : ''; ?>>Ainda Suspensos</option>
                    <option value="reativados" <?php echo $filtro == 'reativados' ? 'selected' : ''; ?>>Reativados</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tabela/Cards -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">
                <i class='bx bx-list-ul'></i>
                HistÃ³rico de SuspensÃµes por Limite
                <span style="font-size: 10px; background: rgba(16,185,129,0.2); padding: 2px 8px; border-radius: 20px;">
                    <i class='bx bx-calendar'></i> Limpa reativados apÃ³s 7 dias
                </span>
            </div>
            <form method="POST" onsubmit="return confirm('Limpar registros jÃ¡ reativados?')">
                <input type="hidden" name="limpar_historico" value="1">
                <button type="submit" class="btn-limpar">
                    <i class='bx bx-trash'></i> Limpar reativados
                </button>
            </form>
        </div>

        <!-- Grid de Cards -->
        <div class="users-grid" id="usersGrid">
            <?php if (empty($registros)): ?>
            <div class="empty-state">
                <i class='bx bx-shield-x'></i>
                <h3>Nenhum usuÃ¡rio suspenso</h3>
                <p>
                    <?php echo !empty($busca) 
                        ? 'Nenhum resultado para "' . htmlspecialchars($busca) . '".' 
                        : 'NÃ£o hÃ¡ usuÃ¡rios suspensos por limite de dispositivos.'; ?>
                </p>
            </div>
            <?php else: ?>
            <?php foreach ($registros as $r):
                $pct = $r['limite'] > 0 ? min(100, round(($r['conexoes'] / $r['limite']) * 100)) : 100;
                $expira_formatada = !empty($r['expira']) ? date('d/m/Y', strtotime($r['expira'])) : 'Nunca';
                $dias_restantes = 0;
                if (!empty($r['expira']) && $r['expira'] != 'Nunca') {
                    $data_validade = strtotime($r['expira']);
                    $data_atual = time();
                    $diferenca = $data_validade - $data_atual;
                    $dias_restantes = floor($diferenca / (60 * 60 * 24));
                }
            ?>
            <div class="user-card" 
                 data-login="<?php echo strtolower($r['login']); ?>"
                 data-status="<?php echo $r['reativado'] ? 'reativado' : 'suspenso'; ?>"
                 data-user-id="<?php echo $r['user_id']; ?>"
                 data-login-nome="<?php echo htmlspecialchars($r['login']); ?>"
                 data-senha="<?php echo htmlspecialchars($r['senha'] ?? ''); ?>"
                 data-expira="<?php echo $expira_formatada; ?>">

                <div class="user-header <?php echo $r['reativado'] ? 'reativado' : ''; ?>">
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class='bx bx-user'></i>
                        </div>
                        <div class="user-text">
                            <div class="user-name">
                                <?php echo htmlspecialchars($r['login']); ?>
                                <?php if (!empty($r['uuid'])): ?>
                                    <span class="v2ray-badge">V2RAY</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="user-body">
                    <!-- Status e Motivo -->
                    <div class="info-row">
                        <div class="info-icon"><i class='bx bx-info-circle'></i></div>
                        <div class="info-content">
                            <div class="info-label">STATUS / MOTIVO</div>
                            <div class="info-value">
                                <?php if ($r['reativado']): ?>
                                    <span class="badge badge-success"><i class='bx bx-check-circle'></i> Reativado</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class='bx bx-lock'></i> Suspenso</span>
                                <?php endif; ?>
                                <span class="badge badge-warning" style="margin-left: 5px;">
                                    <i class='bx bx-error-circle'></i> <?php echo htmlspecialchars($r['motivo'] ?? 'Excedeu limite'); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                

                    <!-- ConexÃµes e Limite -->
                    <div class="info-row">
                        <div class="info-icon"><i class='bx bx-group'></i></div>
                        <div class="info-content">
                            <div class="info-label">CONEXÃ•ES / LIMITE</div>
                            <div class="info-value">
                                <div class="conexoes-bar">
                                    <span style="color:var(--danger);font-weight:700;"><?php echo $r['conexoes']; ?></span>
                                    <span>/ <?php echo $r['limite']; ?></span>
                                    <div class="bar-track">
                                        <div class="bar-fill" style="width:<?php echo $pct; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data SuspensÃ£o -->
                    <div class="info-row">
                        <div class="info-icon"><i class='bx bx-calendar-exclamation'></i></div>
                        <div class="info-content">
                            <div class="info-label">SUSPENSO EM</div>
                            <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($r['data_suspensao'])); ?></div>
                        </div>
                    </div>

                    <!-- Validade -->
                    <div class="info-row">
                        <div class="info-icon"><i class='bx bx-calendar-check'></i></div>
                        <div class="info-content">
                            <div class="info-label">VALIDA ATÃ‰</div>
                            <div class="info-value">
                                <?php echo $expira_formatada; ?>
                                <?php if ($dias_restantes > 0 && $dias_restantes <= 5): ?>
                                    <span style="color:#fbbf24; font-size:10px;"> (<?php echo $dias_restantes; ?> dias)</span>
                                <?php elseif ($dias_restantes < 0): ?>
                                    <span style="color:#f87171; font-size:10px;"> (Expirado)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- BotÃ£o Reativar -->
                    <div class="user-actions">
                        <?php if (!$r['reativado'] && !empty($r['user_id'])): ?>
                        <button class="action-btn btn-reat" onclick="reativarSuspenso(<?php echo $r['user_id']; ?>, this)">
                            <i class='bx bx-refresh'></i> Reativar
                        </button>
                        <?php elseif (!$r['reativado'] && empty($r['user_id'])): ?>
                        <button class="action-btn btn-reat" disabled style="background: #64748b;">
                            <i class='bx bx-error-circle'></i> UsuÃ¡rio nÃ£o encontrado
                        </button>
                        <?php else: ?>
                        <button class="action-btn btn-reat" disabled>
                            <i class='bx bx-check'></i> Reativado
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pag > 1): ?>
        <div class="pagination">
            <a href="?busca=<?php echo urlencode($busca); ?>&filtro=<?php echo $filtro; ?>&pagina=<?php echo max(1,$pagina-1); ?>"
               class="pag-btn <?php echo $pagina<=1?'disabled':''; ?>">
                <i class='bx bx-chevron-left'></i>
            </a>
            <?php
            $ini = max(1,$pagina-2); $fim = min($total_pag,$pagina+2);
            if ($ini>1) echo '<span class="pag-btn disabled">â€¦</span>';
            for ($p=$ini; $p<=$fim; $p++):
            ?>
            <a href="?busca=<?php echo urlencode($busca); ?>&filtro=<?php echo $filtro; ?>&pagina=<?php echo $p; ?>"
               class="pag-btn <?php echo $p==$pagina?'active':''; ?>"><?php echo $p; ?></a>
            <?php endfor;
            if ($fim<$total_pag) echo '<span class="pag-btn disabled">â€¦</span>';
            ?>
            <a href="?busca=<?php echo urlencode($busca); ?>&filtro=<?php echo $filtro; ?>&pagina=<?php echo min($total_pag,$pagina+1); ?>"
               class="pag-btn <?php echo $pagina>=$total_pag?'disabled':''; ?>">
                <i class='bx bx-chevron-right'></i>
            </a>
        </div>
        <?php endif; ?>

        <div class="pag-info">
            Exibindo <?php echo count($registros); ?> de <?php echo $total; ?> registro(s)
        </div>
    </div>

</div>
</div>

<!-- Modal ConfirmaÃ§Ã£o ReativaÃ§Ã£o -->
<div id="modalConfirmarReativacao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5>
                    <i class='bx bx-refresh'></i>
                    Confirmar ReativaÃ§Ã£o
                </h5>
                <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarReativacao')">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-success-icon">
                    <i class='bx bx-refresh'></i>
                </div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label">
                            <i class='bx bx-user'></i> UsuÃ¡rio
                        </div>
                        <div class="modal-info-value credential" id="reativar-login">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label">
                            <i class='bx bx-lock-alt'></i> Senha
                        </div>
                        <div class="modal-info-value credential" id="reativar-senha">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label">
                            <i class='bx bx-calendar'></i> Validade
                        </div>
                        <div class="modal-info-value" id="reativar-expira">â€”</div>
                    </div>
                </div>
                <p style="text-align:center; color: rgba(255,255,255,0.5); font-size: 12px;">
                    O usuÃ¡rio serÃ¡ reativado e poderÃ¡ acessar normalmente.
                </p>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarReativacao')">
                    <i class='bx bx-x'></i> Cancelar
                </button>
                <button type="button" class="btn-modal btn-modal-ok" id="btnConfirmarReativacao">
                    <i class='bx bx-refresh'></i> Reativar Agora
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Processando -->
<div id="modalProcessando" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom" style="background: linear-gradient(135deg, #4158D0, #C850C0);">
                <h5>
                    <i class='bx bx-loader-alt bx-spin'></i>
                    Processando
                </h5>
            </div>
            <div class="modal-body-custom">
                <div class="processing-spinner">
                    <div class="spinner-ring"></div>
                    <p class="spinner-text">Aguarde enquanto processamos sua solicitaÃ§Ã£o...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sucesso -->
<div id="modalSucessoOperacao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5>
                    <i class='bx bx-check-circle'></i>
                    OperaÃ§Ã£o Realizada!
                </h5>
                <button type="button" class="modal-close" onclick="fecharModalSucessoOperacao()">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-success-icon">
                    <i class='bx bx-check-circle'></i>
                </div>
                <h3 style="color:white; text-align:center; margin-bottom:10px;" id="sucesso-titulo">Sucesso!</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="sucesso-mensagem">OperaÃ§Ã£o realizada com sucesso!</p>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal btn-modal-ok" onclick="fecharModalSucessoOperacao()">
                    <i class='bx bx-check'></i> OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Erro -->
<div id="modalErro" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5>
                    <i class='bx bx-error-circle'></i>
                    Erro!
                </h5>
                <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <div style="text-align:center; margin-bottom: 20px;">
                    <i class='bx bx-error-circle' style="font-size:70px; color:#dc2626;"></i>
                </div>
                <h3 style="color:white; margin-bottom:10px; text-align:center;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="erro-mensagem">Erro ao processar solicitaÃ§Ã£o!</p>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal" style="background:linear-gradient(135deg,#dc2626,#b91c1c);" onclick="fecharModal('modalErro')">
                    <i class='bx bx-check'></i> Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
    // VariÃ¡veis globais
    let _reativacaoData = {};
    
    // Busca automÃ¡tica
    let searchTimeout;
    
    function filtrarBusca() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            let busca = document.getElementById('searchInput').value;
            let status = document.getElementById('statusFilter').value;
            let url = 'suspensoes_limite.php?busca=' + encodeURIComponent(busca) + '&filtro=' + status;
            window.location.href = url;
        }, 500);
    }
    
    function filtrarStatus() {
        let busca = document.getElementById('searchInput').value;
        let status = document.getElementById('statusFilter').value;
        let url = 'suspensoes_limite.php?busca=' + encodeURIComponent(busca) + '&filtro=' + status;
        window.location.href = url;
    }
    
    // FunÃ§Ãµes de modal
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }
    
    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }
    
    function fecharModalSucessoOperacao() {
        fecharModal('modalSucessoOperacao');
        location.reload();
    }
    
    function mostrarProcessando() {
        abrirModal('modalProcessando');
    }
    
    function esconderProcessando() {
        fecharModal('modalProcessando');
    }
    
    function mostrarSucesso(titulo, mensagem) {
        document.getElementById('sucesso-titulo').textContent = titulo;
        document.getElementById('sucesso-mensagem').textContent = mensagem;
        abrirModal('modalSucessoOperacao');
    }
    
    function mostrarErro(mensagem) {
        document.getElementById('erro-mensagem').textContent = mensagem;
        abrirModal('modalErro');
    }
    
    // ==================== REATIVAR ====================
    function reativarSuspenso(userId, buttonElement) {
        // Busca os dados no card
        const card = buttonElement.closest('.user-card');
        const login = card.getAttribute('data-login-nome');
        const senha = card.getAttribute('data-senha');
        const expira = card.getAttribute('data-expira');
        
        _reativacaoData = { id: userId, login, senha, expira };
        
        document.getElementById('reativar-login').textContent = login;
        document.getElementById('reativar-senha').textContent = senha;
        document.getElementById('reativar-expira').textContent = expira;
        
        // Remove evento anterior e adiciona novo
        const btnConfirmar = document.getElementById('btnConfirmarReativacao');
        const novoBtn = btnConfirmar.cloneNode(true);
        btnConfirmar.parentNode.replaceChild(novoBtn, btnConfirmar);
        
        novoBtn.onclick = function() {
            fecharModal('modalConfirmarReativacao');
            executarReativacaoSuspenso(userId);
        };
        
        abrirModal('modalConfirmarReativacao');
    }
    
    function executarReativacaoSuspenso(userId) {
        mostrarProcessando();
        
        $.ajax({
            url: 'reativar.php?id=' + userId,
            type: 'GET',
            success: function(data) {
                esconderProcessando();
                data = data.replace(/(\r\n|\n|\r)/gm, "");
                
                if (data == 'reativado com sucesso') {
                    // Atualiza o histÃ³rico de suspensÃµes
                    $.ajax({
                        url: 'atualizar_historico_reativacao.php',
                        type: 'POST',
                        data: { login: _reativacaoData.login },
                        success: function() {
                            mostrarSucesso('âœ… UsuÃ¡rio Reativado!', `UsuÃ¡rio ${_reativacaoData.login} foi reativado com sucesso!`);
                        },
                        error: function() {
                            mostrarSucesso('âœ… UsuÃ¡rio Reativado!', `UsuÃ¡rio ${_reativacaoData.login} foi reativado com sucesso!`);
                        }
                    });
                } else if (data == 'usuario nao encontrado') {
                    mostrarErro('UsuÃ¡rio nÃ£o encontrado no sistema!');
                } else if (data == 'erro no servidor') {
                    mostrarErro('Erro nos servidores! Verifique se estÃ£o online.');
                } else {
                    mostrarErro('Erro ao reativar usuÃ¡rio!');
                }
            },
            error: function(xhr, status, error) {
                esconderProcessando();
                mostrarErro('Erro ao conectar com o servidor: ' + error);
            }
        });
    }
    
    // Fechar modais ao clicar fora
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modalId = e.target.id;
            if (modalId === 'modalSucessoOperacao') {
                fecharModalSucessoOperacao();
            } else {
                e.target.classList.remove('show');
            }
        }
    });
    
    // ESC fecha modais
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('modalSucessoOperacao').classList.contains('show')) {
                fecharModalSucessoOperacao();
            } else {
                document.querySelectorAll('.modal-overlay.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        }
    });
</script>
</body>
</html>



