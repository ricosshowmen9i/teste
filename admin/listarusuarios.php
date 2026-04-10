<?php
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
include('headeradmin2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// ========== INCLUIR SISTEMA DE TEMAS ==========
include_once '../AegisCore/temas.php';
$temaAtual = initTemas($conn);
$listaTemas = getListaTemas($conn);

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

if (!file_exists('suspenderrev.php')) {
    exit ("<script>alert('Token Invalido!');</script>");
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

// ========== PAGINAÇÃO ==========
$limite_por_pagina = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
$limite_por_pagina = in_array($limite_por_pagina, [10, 20, 50, 100]) ? $limite_por_pagina : 10;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $limite_por_pagina;

// Buscar total de registros
$sql_total = "SELECT COUNT(*) as total FROM ssh_accounts WHERE byid = '{$_SESSION['iduser']}'";
$result_total = $conn->query($sql_total);
$total_registros = $result_total->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $limite_por_pagina);

$_GET['search'] = anti_sql($_GET['search'] ?? '');
if (!empty($_GET['search'])){
    $search = $_GET['search'];
    $sql = "SELECT * FROM ssh_accounts WHERE login LIKE '%$search%' AND byid = '{$_SESSION['iduser']}' ORDER BY expira ASC LIMIT $limite_por_pagina OFFSET $offset";
    $sql_total_busca = "SELECT COUNT(*) as total FROM ssh_accounts WHERE login LIKE '%$search%' AND byid = '{$_SESSION['iduser']}'";
    $result_total_busca = $conn->query($sql_total_busca);
    $total_registros = $result_total_busca->fetch_assoc()['total'];
    $total_paginas = ceil($total_registros / $limite_por_pagina);
    $result = $conn->query($sql);
} else {
    $sql = "SELECT * FROM ssh_accounts WHERE byid = '{$_SESSION['iduser']}' ORDER BY expira ASC LIMIT $limite_por_pagina OFFSET $offset";
    $result = $conn->query($sql);
}

// Stats rápidas
$total_online = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid='{$_SESSION['iduser']}' AND status='Online'"); if($r){$total_online=$r->fetch_assoc()['t'];}
$total_vencidos = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid='{$_SESSION['iduser']}' AND expira < NOW()"); if($r){$total_vencidos=$r->fetch_assoc()['t'];}
$total_suspensos = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid='{$_SESSION['iduser']}' AND mainid='Suspenso'"); if($r){$total_suspensos=$r->fetch_assoc()['t'];}

$sql44 = "SELECT * FROM configs";
$result44 = $conn->query($sql44);
while ($row44 = $result44->fetch_assoc()) {
    $deviceativo = $row44['deviceativo'];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Lista de Usuários</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
 
    <link rel="stylesheet" href="../AegisCore/temas_visual.css?v=<?php echo time(); ?>">
<style>
  body::before {
    content: 'Tema: <?php echo $temaAtual['classe']; ?>';
    position: fixed;
    top: 10px;
    right: 10px;
    background: red;
    color: white;
    padding: 10px;
    z-index: 99999;
    font-weight: bold;
  }
  
        /* ========== VARIÁVEIS DO TEMA ATIVO ========== */
       
        body {
            font-family: 'Inter', sans-serif;
            background: var(--fundo, #0f172a);
            color: var(--texto, #ffffff);
            min-height: 100vh;
        }

        .app-content { margin-left: 0px !important; padding: 0 !important; }

        .content-wrapper {
            max-width: 1700px;
            margin: 0 auto !important;
            padding: 20px !important;
        }

        /* ========== STATS CARD ========== */
        .stats-card {
            background: linear-gradient(135deg, var(--fundo_claro, #1e293b), var(--fundo, #0f172a));
            border-radius: 20px;
            padding: 20px 24px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            overflow: hidden;
            transition: all .3s ease;
        }
        .stats-card:hover { transform: translateY(-2px); border-color: var(--primaria, #10b981); }
        .stats-card-icon {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, var(--primaria, #10b981), var(--secundaria, #C850C0));
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; color: white; flex-shrink: 0;
        }
        .stats-card-content { flex: 1; }
        .stats-card-title {
            font-size: 13px; font-weight: 600;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase; margin-bottom: 5px;
        }
        .stats-card-value {
            font-size: 36px; font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primaria, #10b981));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; line-height: 1;
        }
        .stats-card-subtitle { font-size: 12px; color: rgba(255,255,255,0.4); margin-top: 4px; }
        .stats-card-decoration {
            position: absolute; right: 20px; top: 50%;
            transform: translateY(-50%);
            font-size: 80px; opacity: 0.05;
        }

        /* Mini Stats */
        .mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
        .mini-stat{flex:1;min-width:90px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;}
        .mini-stat:hover{border-color:var(--primaria,#10b981);transform:translateY(-2px);}
        .mini-stat-val{font-size:18px;font-weight:800;}
        .mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

        /* Modern Card */
        .modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;margin-bottom:16px;transition:all .2s;}
        .modern-card:hover{border-color:var(--primaria,#10b981);}
        .card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
        .card-header-custom.blue{background:linear-gradient(135deg,#3b82f6,#2563eb);}
        .header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
        .header-title{font-size:14px;font-weight:700;color:white;}
        .header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
        .card-body-custom{padding:16px;}

        /* Filtros */
        .filter-group { display: flex; flex-wrap: wrap; gap: 12px; }
        .filter-item { flex: 1; min-width: 140px; }
        .filter-label {
            font-size: 9px; font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .filter-input, .filter-select {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; font-size: 12px;
            color: #ffffff !important; transition: all 0.2s;
            font-family: inherit; outline: none;
        }
        .filter-input:focus, .filter-select:focus {
            border-color: var(--primaria, #10b981);
            background: rgba(255,255,255,0.09);
        }
        .filter-input::placeholder { color: rgba(255,255,255,0.3); }
        .filter-select {
            cursor: pointer; appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center;
        }
        .filter-select option { background: #1e293b; color: #ffffff !important; }

        /* Grid de usuários */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 14px;
        }

        /* Card */
        .user-card {
            background: var(--fundo_claro, #1e293b);
            border-radius: 14px; overflow: hidden;
            transition: all 0.2s; border: 1px solid rgba(255,255,255,0.08);
        }
        .user-card:hover { transform: translateY(-2px); border-color: var(--primaria, #10b981); }

        /* Header do card */
        .user-header {
            background: linear-gradient(135deg, var(--primaria, #10b981), var(--secundaria, #C850C0));
            padding: 12px; display: flex; align-items: center; justify-content: space-between;
        }
        .user-info { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
        .user-avatar {
            width: 36px; height: 36px; background: rgba(255,255,255,0.2);
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .user-text { flex: 1; min-width: 0; }
        .user-name {
            font-size: 14px; font-weight: 700; color: white;
            display: flex; align-items: center; gap: 5px; flex-wrap: wrap; word-break: break-all;
        }
        .v2ray-badge {
            background: rgba(255,255,255,0.2); padding: 2px 6px;
            border-radius: 20px; font-size: 8px; font-weight: 600;
        }
        .user-senha {
            font-size: 10px; color: rgba(255,255,255,0.7); margin-top: 2px;
            display: flex; align-items: center; gap: 4px;
        }
        .btn-copy-card {
            background: rgba(255,255,255,0.15); border: none; border-radius: 8px;
            padding: 6px 10px; font-size: 11px; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 4px; color: white; flex-shrink: 0; transition: all 0.2s;
        }
        .btn-copy-card:hover { background: rgba(255,255,255,0.25); }
        .btn-copy-card.copied { background: var(--sucesso, #10b981); }

        /* Corpo do card */
        .user-body { padding: 12px; }

        .status-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; }
        .status-item-card { display: flex; align-items: center; gap: 6px; padding: 6px 8px; background: rgba(255,255,255,0.03); border-radius: 8px; }
        .status-icon {
            width: 26px; height: 26px; background: rgba(255,255,255,0.05);
            border-radius: 7px; display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0;
        }
        .status-content { flex: 1; }
        .status-label { font-size: 8px; color: rgba(255,255,255,0.4); font-weight: 600; margin-bottom: 1px; }
        .status-value { font-size: 11px; font-weight: 600; }

        .status-badge { display: inline-flex; align-items: center; gap: 3px; padding: 2px 7px; border-radius: 16px; font-size: 9px; font-weight: 600; }
        .status-online { background: rgba(16,185,129,0.2); color: #10b981; }
        .status-offline { background: rgba(100,116,139,0.2); color: #94a3b8; }
        .status-suspended { background: rgba(220,38,38,0.2); color: #f87171; }
        .status-limit { background: rgba(245,158,11,0.2); color: #fbbf24; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-bottom: 8px; }
        .info-row { display: flex; align-items: center; gap: 5px; padding: 5px 7px; background: rgba(255,255,255,0.03); border-radius: 7px; }
        .info-icon {
            width: 22px; height: 22px; background: rgba(255,255,255,0.05);
            border-radius: 6px; display: flex; align-items: center; justify-content: center;
            font-size: 11px; flex-shrink: 0;
        }
        .info-content { flex: 1; min-width: 0; }
        .info-label { font-size: 8px; color: rgba(255,255,255,0.4); font-weight: 600; }
        .info-value { font-size: 10px; font-weight: 600; word-break: break-all; color: var(--texto, #ffffff); }
        .info-value.warning { color: #fbbf24; }
        .info-value.danger { color: #f87171; }

        /* Botões de ação */
        .user-actions { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
        .action-btn {
            flex: 1; min-width: 60px; padding: 6px 8px; border: none; border-radius: 8px;
            font-weight: 600; font-size: 10px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            gap: 4px; color: white; transition: all 0.2s; font-family: inherit;
        }
        .action-btn:hover { transform: translateY(-1px); filter: brightness(1.05); }
        .action-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
        .btn-edit { background: linear-gradient(135deg, #4158D0, #6366f1); }
        .btn-renew { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-warn { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .btn-reactivate { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .btn-device { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .btn-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }

        /* Paginação */
        .pagination-wrapper {
            display: flex; justify-content: center; align-items: center;
            gap: 12px; flex-wrap: wrap; margin-top: 20px; padding: 10px 0;
        }
        .limit-selector {
            display: flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.04);
            padding: 5px 12px; border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .limit-selector label { font-size: 11px; color: rgba(255,255,255,0.6); display: flex; align-items: center; gap: 4px; }
        .limit-selector select {
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px; padding: 4px 8px; color: #ffffff;
            font-size: 11px; cursor: pointer;
        }
        .limit-selector select option { background: #1e293b; color: #fff; }

        .pagination { display: flex; align-items: center; gap: 5px; }
        .pagination a, .pagination span {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 32px; height: 32px; padding: 0 8px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px; color: #fff;
            text-decoration: none; font-size: 11px; font-weight: 500; transition: all 0.2s;
        }
        .pagination a:hover { background: var(--primaria, #10b981); border-color: var(--primaria, #10b981); }
        .pagination .active { background: var(--primaria, #10b981); border-color: var(--primaria, #10b981); }
        .pagination .disabled { opacity: 0.4; cursor: not-allowed; }
        .pagination-info { text-align: center; margin-top: 10px; color: rgba(255,255,255,0.3); font-size: 10px; }

        /* Estado vazio */
        .empty-state {
            grid-column: 1/-1; text-align: center; padding: 40px;
            background: var(--fundo_claro, #1e293b); border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .empty-state i { font-size: 48px; color: rgba(255,255,255,0.15); margin-bottom: 10px; }
        .empty-state h3 { font-size: 15px; margin-bottom: 6px; }
        .empty-state p { font-size: 11px; color: rgba(255,255,255,0.3); }

        /* Modais */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 10000; backdrop-filter: blur(8px); padding: 16px;
        }
        .modal-overlay.show { display: flex; }
        .modal-container { animation: modalIn 0.3s ease; max-width: 450px; width: 92%; }
        @keyframes modalIn { from { opacity:0; transform:scale(.95); } to { opacity:1; transform:scale(1); } }
        .modal-content-custom {
            background: var(--fundo_claro, #1e293b);
            border-radius: 20px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
        }
        .modal-header-custom {
            padding: 14px 18px; display: flex;
            align-items: center; justify-content: space-between;
        }
        .modal-header-custom h5 {
            margin: 0; display: flex; align-items: center; gap: 8px;
            font-size: 14px; font-weight: 700; color: white;
        }
        .modal-header-custom.success { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header-custom.error { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header-custom.warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header-custom.processing { background: linear-gradient(135deg, var(--primaria, #10b981), var(--secundaria, #C850C0)); }
        .modal-header-custom.info { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .modal-close {
            background: rgba(255,255,255,0.15); border: none; color: #fff;
            font-size: 18px; cursor: pointer; width: 28px; height: 28px;
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .modal-close:hover { background: rgba(255,255,255,0.25); transform: rotate(90deg); }
        .modal-body-custom { padding: 18px; }
        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.07);
            padding: 12px 18px; display: flex; justify-content: center; gap: 8px; flex-wrap: wrap;
        }

        .modal-ic {
            width: 70px; height: 70px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px; font-size: 34px;
            animation: icPop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.15s both;
        }
        @keyframes icPop { 0%{transform:scale(0);opacity:0} 100%{transform:scale(1);opacity:1} }
        .modal-ic.success { background:rgba(16,185,129,.15); color:#34d399; border:2px solid rgba(16,185,129,.3); }
        .modal-ic.error { background:rgba(239,68,68,.15); color:#f87171; border:2px solid rgba(239,68,68,.3); }
        .modal-ic.warning { background:rgba(245,158,11,.15); color:#fbbf24; border:2px solid rgba(245,158,11,.3); }

        .btn-modal {
            padding: 8px 16px; border: none; border-radius: 10px;
            font-weight: 600; font-size: 12px; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
            color: white; transition: all 0.2s; font-family: inherit;
        }
        .btn-modal:hover { transform: translateY(-1px); filter: brightness(1.08); }
        .btn-modal-cancel { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); }
        .btn-modal-cancel:hover { background: rgba(255,255,255,.15); }
        .btn-modal-ok { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-modal-warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .btn-modal-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .btn-modal-info { background: linear-gradient(135deg, #06b6d4, #0891b2); }

        /* Dias select */
        .dias-select-modal { display: grid; grid-template-columns: repeat(4,1fr); gap: 6px; margin-top: 8px; }
        .dia-opt {
            background: rgba(255,255,255,.06); border: 1.5px solid rgba(255,255,255,.08);
            border-radius: 8px; padding: 7px 4px; text-align: center; cursor: pointer;
            transition: all .2s; font-size: 11px; font-weight: 600; color: rgba(255,255,255,.6);
        }
        .dia-opt:hover { background: rgba(255,255,255,.1); border-color: var(--primaria, #10b981); }
        .dia-opt.active { background: linear-gradient(135deg, var(--primaria,#10b981), var(--secundaria,#C850C0)); color: white; border-color: transparent; }

        .spinner-wrap { display: flex; flex-direction: column; align-items: center; gap: 14px; padding: 20px 0; }
        .spinner-ring { width: 44px; height: 44px; border: 3px solid rgba(255,255,255,.08); border-top-color: var(--primaria,#10b981); border-right-color: var(--secundaria,#C850C0); border-radius: 50%; animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Toast */
        .toast-notification {
            position: fixed; bottom: 20px; right: 20px; color: #fff;
            padding: 10px 16px; border-radius: 10px; display: flex; align-items: center; gap: 8px;
            z-index: 10001; animation: toastIn 0.3s ease; font-weight: 600; font-size: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        .toast-notification.ok { background: linear-gradient(135deg, #10b981, #059669); }
        .toast-notification.err { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        @keyframes toastIn { from { transform: translateX(110%); opacity:0; } to { transform: translateX(0); opacity:1; } }

        /* Device items */
        .device-item { display:flex; align-items:center; gap:10px; padding:8px 10px; background:rgba(255,255,255,.04); border-radius:10px; margin-bottom:6px; border:1px solid rgba(255,255,255,.06); }
        .device-icon { width:32px; height:32px; border-radius:8px; background:rgba(6,182,212,.15); color:#22d3ee; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
        .device-info { flex:1; }
        .device-id { font-size:10px; font-family:monospace; color:rgba(255,255,255,.7); word-break:break-all; }
        .device-date { font-size:9px; color:rgba(255,255,255,.3); margin-top:2px; }

        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { padding: 10px !important; }
            .users-grid { grid-template-columns: 1fr; }
            .stats-card { padding: 14px; gap: 14px; }
            .stats-card-icon { width: 48px; height: 48px; font-size: 24px; }
            .stats-card-value { font-size: 28px; }
            .filter-group { flex-direction: column; }
            .user-actions { display: grid; grid-template-columns: repeat(3, 1fr); }
            .mini-stats { flex-wrap: wrap; }
            .mini-stat { min-width: 80px; }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">

            <!-- Stats Card -->
            <div class="stats-card">
                <div class="stats-card-icon"><i class='bx bx-user-circle'></i></div>
                <div class="stats-card-content">
                    <div class="stats-card-title">Lista de Usuários</div>
                    <div class="stats-card-value"><?php echo $total_registros; ?></div>
                    <div class="stats-card-subtitle">usuários cadastrados no sistema</div>
                </div>
                <div class="stats-card-decoration"><i class='bx bx-user-circle'></i></div>
            </div>

            <!-- Mini Stats -->
            <div class="mini-stats">
                <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_registros; ?></div><div class="mini-stat-lbl">Total</div></div>
                <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_online; ?></div><div class="mini-stat-lbl">Onlines</div></div>
                <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_vencidos; ?></div><div class="mini-stat-lbl">Vencidos</div></div>
                <div class="mini-stat"><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_suspensos; ?></div><div class="mini-stat-lbl">Suspensos</div></div>
            </div>

            <!-- Filtros -->
            <div class="modern-card">
                <div class="card-header-custom blue">
                    <div class="header-icon"><i class='bx bx-filter-alt'></i></div>
                    <div><div class="header-title">Filtros de Busca</div><div class="header-subtitle">Encontre rapidamente</div></div>
                </div>
                <div class="card-body-custom">
                    <div class="filter-group">
                        <div class="filter-item">
                            <div class="filter-label">Buscar por Login</div>
                            <input type="text" class="filter-input" id="searchInput" placeholder="Digite o nome..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" onkeyup="filtrarUsuarios()">
                        </div>
                        <div class="filter-item">
                            <div class="filter-label">Filtrar por Status</div>
                            <select class="filter-select" id="statusFilter" onchange="filtrarUsuarios()">
                                <option value="todos">📋 Todos</option>
                                <option value="online">🟢 Online</option>
                                <option value="offline">⚫ Offline</option>
                                <option value="suspenso">🔒 Suspenso</option>
                                <option value="expirado">⏰ Expirado</option>
                            </select>
                        </div>
                        <div class="filter-item" style="max-width:120px;">
                            <div class="filter-label">Por Página</div>
                            <select class="filter-select" id="limitSelect" onchange="mudarLimite()">
                                <option value="10" <?php echo $limite_por_pagina==10?'selected':''; ?>>10</option>
                                <option value="20" <?php echo $limite_por_pagina==20?'selected':''; ?>>20</option>
                                <option value="50" <?php echo $limite_por_pagina==50?'selected':''; ?>>50</option>
                                <option value="100" <?php echo $limite_por_pagina==100?'selected':''; ?>>100</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid de Usuários -->
            <div class="users-grid" id="usersGrid">
                <?php
                if ($result && $result->num_rows > 0):
                    while ($user = $result->fetch_assoc()):
                        $status = $user['status'] ?? 'Offline';
                        $is_suspenso = ($user['mainid'] == 'Suspenso');
                        $is_limite = ($user['mainid'] == 'Limite Ultrapassado');

                        if ($is_suspenso) { $status_class='status-suspended'; $status_icon='bx-lock'; $status_label='Suspenso'; }
                        elseif ($is_limite) { $status_class='status-limit'; $status_icon='bx-error'; $status_label='Limite'; }
                        elseif ($status === 'Online') { $status_class='status-online'; $status_icon='bx-wifi'; $status_label='Online'; }
                        else { $status_class='status-offline'; $status_icon='bx-power-off'; $status_label='Offline'; }

                        $expira = $user['expira']; $expira_fmt = date('d/m/Y', strtotime($expira));
                        $diff = strtotime($expira) - time(); $dias_rest = floor($diff / 86400);
                        $val_class = $dias_rest < 0 ? 'danger' : ($dias_rest <= 5 ? 'warning' : '');
                        $val_label = $dias_rest < 0 ? 'Expirado' : ($dias_rest === 0 ? 'Hoje' : $dias_rest.'d');

                        $r_cat = $conn->query("SELECT nome FROM categorias WHERE subid='{$user['categoriaid']}'");
                        $cat_nome = ($r_cat && $r_cat->num_rows > 0) ? $r_cat->fetch_assoc()['nome'] : $user['categoriaid'];
                        $tem_v2ray = !empty($user['uuid']);

                        $data_status = $is_suspenso ? 'suspenso' : ($is_limite ? 'suspenso' : strtolower($status_label));
                ?>
                <div class="user-card" data-login="<?php echo strtolower(htmlspecialchars($user['login'])); ?>" data-status="<?php echo $data_status; ?>" data-expirado="<?php echo $dias_rest<0?'expirado':''; ?>">
                    <div class="user-header">
                        <div class="user-info">
                            <div class="user-avatar"><i class='bx bx-user'></i></div>
                            <div class="user-text">
                                <div class="user-name">
                                    <?php echo htmlspecialchars($user['login']); ?>
                                    <?php if ($tem_v2ray): ?><span class="v2ray-badge">V2Ray</span><?php endif; ?>
                                </div>
                                <div class="user-senha">
                                    <i class='bx bx-lock-alt'></i> <?php echo htmlspecialchars($user['senha']); ?>
                                </div>
                            </div>
                        </div>
                        <button class="btn-copy-card" onclick="copiarUsuario(this,'<?php echo htmlspecialchars($user['login']); ?>','<?php echo htmlspecialchars($user['senha']); ?>','<?php echo $expira_fmt; ?>','<?php echo $user['limite']; ?>')">
                            <i class='bx bx-copy'></i> Copiar
                        </button>
                    </div>
                    <div class="user-body">
                        <div class="status-row">
                            <div class="status-item-card">
                                <div class="status-icon"><i class='bx <?php echo $status_icon; ?>'></i></div>
                                <div class="status-content"><div class="status-label">STATUS</div><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></div>
                            </div>
                            <div class="status-item-card">
                                <div class="status-icon"><i class='bx bx-calendar' style="color:#fbbf24;"></i></div>
                                <div class="status-content"><div class="status-label">VALIDADE</div><div class="status-value <?php echo $val_class; ?>"><?php echo $val_label; ?></div></div>
                            </div>
                        </div>
                        <div class="info-grid">
                            <div class="info-row"><div class="info-icon"><i class='bx bx-user' style="color:#818cf8;"></i></div><div class="info-content"><div class="info-label">LOGIN</div><div class="info-value"><?php echo htmlspecialchars($user['login']); ?></div></div></div>
                            <div class="info-row"><div class="info-icon"><i class='bx bx-lock-alt' style="color:#e879f9;"></i></div><div class="info-content"><div class="info-label">SENHA</div><div class="info-value"><?php echo htmlspecialchars($user['senha']); ?></div></div></div>
                            <div class="info-row"><div class="info-icon"><i class='bx bx-group' style="color:#34d399;"></i></div><div class="info-content"><div class="info-label">LIMITE</div><div class="info-value"><?php echo $user['limite']; ?> conexões</div></div></div>
                            <div class="info-row"><div class="info-icon"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i></div><div class="info-content"><div class="info-label">EXPIRA</div><div class="info-value <?php echo $val_class; ?>"><?php echo $expira_fmt; ?></div></div></div>
                            <div class="info-row"><div class="info-icon"><i class='bx bx-category' style="color:#60a5fa;"></i></div><div class="info-content"><div class="info-label">CATEGORIA</div><div class="info-value"><?php echo htmlspecialchars($cat_nome); ?></div></div></div>
                            <?php if (!empty($user['lastview'])): ?>
                            <div class="info-row"><div class="info-icon"><i class='bx bx-note' style="color:#a78bfa;"></i></div><div class="info-content"><div class="info-label">NOTAS</div><div class="info-value"><?php echo htmlspecialchars($user['lastview']); ?></div></div></div>
                            <?php endif; ?>
                            <?php if (!empty($user['whatsapp'])): ?>
                            <div class="info-row"><div class="info-icon"><i class='bx bxl-whatsapp' style="color:#25D366;"></i></div><div class="info-content"><div class="info-label">WHATSAPP</div><div class="info-value"><?php echo htmlspecialchars($user['whatsapp']); ?></div></div></div>
                            <?php endif; ?>
                        </div>
                        <div class="user-actions">
                            <button class="action-btn btn-edit" onclick="window.location.href='editarlogin.php?id=<?php echo $user['id']; ?>'"><i class='bx bx-edit'></i> Editar</button>
                            <button class="action-btn btn-renew" onclick="renovar(<?php echo $user['id']; ?>,'<?php echo htmlspecialchars($user['login']); ?>')"><i class='bx bx-refresh'></i> Renovar</button>
                            <?php if ($is_suspenso): ?>
                            <button class="action-btn btn-reactivate" onclick="reativar(<?php echo $user['id']; ?>,'<?php echo htmlspecialchars($user['login']); ?>')"><i class='bx bx-check-circle'></i> Ativar</button>
                            <?php else: ?>
                            <button class="action-btn btn-warn" onclick="suspender(<?php echo $user['id']; ?>,'<?php echo htmlspecialchars($user['login']); ?>')"><i class='bx bx-lock'></i> Suspender</button>
                            <?php endif; ?>
                            <?php if ($deviceativo == 'ativo' || $deviceativo == '1'): ?>
                            <button class="action-btn btn-device" onclick="limparDevice(<?php echo $user['id']; ?>,'<?php echo htmlspecialchars($user['login']); ?>')"><i class='bx bx-devices'></i> Device</button>
                            <?php endif; ?>
                            <button class="action-btn btn-danger" onclick="excluir(<?php echo $user['id']; ?>,'<?php echo htmlspecialchars($user['login']); ?>')"><i class='bx bx-trash'></i> Deletar</button>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                <div class="empty-state"><i class='bx bx-user-x'></i><h3>Nenhum usuário encontrado</h3><p>Crie novos usuários para que apareçam aqui.</p></div>
                <?php endif; ?>
            </div>

            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination">
                    <?php if ($pagina_atual > 1): ?>
                        <a href="?pagina=<?php echo $pagina_atual-1; ?>&limite=<?php echo $limite_por_pagina; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>"><i class='bx bx-chevron-left'></i></a>
                    <?php else: ?><span class="disabled"><i class='bx bx-chevron-left'></i></span><?php endif; ?>
                    <?php
                    $max_p=5; $ini=max(1,$pagina_atual-floor($max_p/2)); $fim=min($total_paginas,$ini+$max_p-1);
                    if($ini>1){echo '<a href="?pagina=1&limite='.$limite_por_pagina.'&search='.urlencode($_GET['search']??'').'">1</a>';if($ini>2)echo '<span class="disabled">…</span>';}
                    for($i=$ini;$i<=$fim;$i++){echo($i==$pagina_atual)?'<span class="active">'.$i.'</span>':'<a href="?pagina='.$i.'&limite='.$limite_por_pagina.'&search='.urlencode($_GET['search']??'').'">'.$i.'</a>';}
                    if($fim<$total_paginas){if($fim<$total_paginas-1)echo '<span class="disabled">…</span>';echo '<a href="?pagina='.$total_paginas.'&limite='.$limite_por_pagina.'&search='.urlencode($_GET['search']??'').'">'.$total_paginas.'</a>';}
                    ?>
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="?pagina=<?php echo $pagina_atual+1; ?>&limite=<?php echo $limite_por_pagina; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>"><i class='bx bx-chevron-right'></i></a>
                    <?php else: ?><span class="disabled"><i class='bx bx-chevron-right'></i></span><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="pagination-info">Mostrando <?php echo min($offset+1,$total_registros); ?>–<?php echo min($offset+$limite_por_pagina,$total_registros); ?> de <?php echo $total_registros; ?> usuários</div>

        </div>
    </div>

    <!-- ========== MODAIS ========== -->

    <!-- Modal Renovar -->
    <div id="modalRenovar" class="modal-overlay">
    <div class="modal-container"><div class="modal-content-custom">
        <div class="modal-header-custom success"><h5><i class='bx bx-refresh'></i> Renovar Usuário</h5><button class="modal-close" onclick="fecharModal('modalRenovar')"><i class='bx bx-x'></i></button></div>
        <div class="modal-body-custom">
            <div class="modal-ic success"><i class='bx bx-calendar-plus'></i></div>
            <p style="text-align:center;font-size:13px;margin-bottom:14px;">Renovando: <strong id="renovarNome" style="color:#34d399;"></strong></p>
            <div style="margin-bottom:8px;"><div class="filter-label">Dias para renovar</div>
            <input type="number" id="renovarDias" class="filter-input" value="30" min="1" max="365" style="text-align:center;font-size:16px;font-weight:700;"></div>
            <div class="dias-select-modal">
                <div class="dia-opt" onclick="setDias(7)">7d</div>
                <div class="dia-opt" onclick="setDias(15)">15d</div>
                <div class="dia-opt active" onclick="setDias(30)">30d</div>
                <div class="dia-opt" onclick="setDias(60)">60d</div>
                <div class="dia-opt" onclick="setDias(90)">90d</div>
                <div class="dia-opt" onclick="setDias(180)">180d</div>
                <div class="dia-opt" onclick="setDias(365)">1 ano</div>
            </div>
        </div>
        <div class="modal-footer-custom">
            <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalRenovar')"><i class='bx bx-x'></i> Cancelar</button>
            <button class="btn-modal btn-modal-ok" onclick="confirmarRenovar()"><i class='bx bx-check'></i> Renovar</button>
        </div>
    </div></div>
    </div>

    <!-- Modal Suspender -->
    <div id="modalSuspender" class="modal-overlay">
    <div class="modal-container"><div class="modal-content-custom">
        <div class="modal-header-custom warning"><h5><i class='bx bx-lock'></i> Suspender Usuário</h5><button class="modal-close" onclick="fecharModal('modalSuspender')"><i class='bx bx-x'></i></button></div>
        <div class="modal-body-custom">
            <div class="modal-ic warning"><i class='bx bx-lock'></i></div>
            <p style="text-align:center;font-size:13px;">Deseja suspender <strong id="suspenderNome" style="color:#fbbf24;"></strong>?</p>
        </div>
        <div class="modal-footer-custom">
            <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalSuspender')"><i class='bx bx-x'></i> Cancelar</button>
            <button class="btn-modal btn-modal-warning" onclick="confirmarSuspender()"><i class='bx bx-check'></i> Suspender</button>
        </div>
    </div></div>
    </div>

    <!-- Modal Reativar -->
    <div id="modalReativar" class="modal-overlay">
    <div class="modal-container"><div class="modal-content-custom">
        <div class="modal-header-custom info"><h5><i class='bx bx-check-circle'></i> Reativar Usuário</h5><button class="modal-close" onclick="fecharModal('modalReativar')"><i class='bx bx-x'></i></button></div>
        <div class="modal-body-custom">
            <div class="modal-ic success"><i class='bx bx-check-circle'></i></div>
            <p style="text-align:center;font-size:13px;">Deseja reativar <strong id="reativarNome" style="color:#34d399;"></strong>?</p>
        </div>
        <div class="modal-footer-custom">
            <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalReativar')"><i class='bx bx-x'></i> Cancelar</button>
            <button class="btn-modal btn-modal-ok" onclick="confirmarReativar()"><i class='bx bx-check'></i> Reativar</button>
        </div>
    </div></div>
    </div>

    <!-- Modal Excluir -->
    <div id="modalExcluir" class="modal-overlay">
    <div class="modal-container"><div class="modal-content-custom">
        <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Excluir Usuário</h5><button class="modal-close" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i></button></div>
        <div class="modal-body-custom">
            <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
            <p style="text-align:center;font-size:13px;">Tem certeza que deseja excluir <strong id="excluirNome" style="color:#f87171;"></strong>?</p>
            <p style="text-align:center;font-size:10px;color:rgba(255,255,255,.35);margin-top:4px;">Esta ação não pode ser desfeita.</p>
        </div>
        <div class="modal-footer-custom">
            <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i> Cancelar</button>
            <button class="btn-modal btn-modal-danger" onclick="confirmarExcluir()"><i class='bx bx-trash'></i> Excluir</button>
        </div>
    </div></div>
    </div>

    <!-- Modal Device -->
    <div id="modalDevice" class="modal-overlay">
    <div class="modal-container"><div class="modal-content-custom">
        <div class="modal-header-custom warning"><h5><i class='bx bx-devices'></i> Limpar Device ID</h5><button class="modal-close" onclick="fecharModal('modalDevice')"><i class='bx bx-x'></i></button></div>
        <div class="modal-body-custom">
            <div class="modal-ic warning"><i class='bx bx-devices'></i></div>
            <p style="text-align:center;font-size:13px;">Deseja limpar o Device ID de <strong id="deviceNome" style="color:#fbbf24;"></strong>?</p>
        </div>
        <div class="modal-footer-custom">
            <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalDevice')"><i class='bx bx-x'></i> Cancelar</button>
            <button class="btn-modal btn-modal-warning" onclick="confirmarDevice()"><i class='bx bx-check'></i> Limpar</button>
        </div>
    </div></div>
    </div>

    <!-- Modal Processando -->
    <div id="modalProcessando" class="modal-overlay">
    <div class="modal-container"><div class="modal-content-custom">
        <div class="modal-header-custom processing"><h5><i class='bx bx-loader-alt bx-spin'></i> Processando...</h5></div>
        <div class="modal-body-custom">
            <div class="spinner-wrap"><div class="spinner-ring"></div><p id="processandoTexto" style="font-size:13px;color:rgba(255,255,255,.6);">Aguarde...</p></div>
        </div>
    </div></div>
    </div>

    <script>
    // ===== Estado =====
    var _userId = null;
    var _userName = null;

    // ===== Modais =====
    function abrirModal(id) { document.getElementById(id).classList.add('show'); }
    function fecharModal(id) { document.getElementById(id).classList.remove('show'); }
    document.querySelectorAll('.modal-overlay').forEach(function(o) {
        o.addEventListener('click', function(e) { if (e.target === o) o.classList.remove('show'); });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.show').forEach(function(m) { m.classList.remove('show'); });
    });

    // ===== Toast =====
    function mostrarToast(msg, tipo) {
        var t = document.createElement('div');
        t.className = 'toast-notification ' + (tipo === 'err' ? 'err' : 'ok');
        t.innerHTML = '<i class="bx ' + (tipo === 'err' ? 'bx-error-circle' : 'bx-check-circle') + '"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function() { t.remove(); }, 3500);
    }

    // ===== Copiar =====
    function copiarUsuario(btn, login, senha, validade, limite) {
        var texto = '✅ DADOS DO USUÁRIO\n━━━━━━━━━━━━━━━━━━━━━━\n👤 Login: ' + login + '\n🔑 Senha: ' + senha + '\n📅 Validade: ' + validade + '\n🔗 Limite: ' + limite + ' conexões\n━━━━━━━━━━━━━━━━━━━━━━';
        navigator.clipboard.writeText(texto).then(function() {
            btn.classList.add('copied'); btn.innerHTML = '<i class="bx bx-check"></i> Copiado!';
            setTimeout(function() { btn.classList.remove('copied'); btn.innerHTML = '<i class="bx bx-copy"></i> Copiar'; }, 2000);
        }).catch(function() { mostrarToast('Erro ao copiar!', 'err'); });
    }

    // ===== Filtrar =====
    function filtrarUsuarios() {
        var busca = document.getElementById('searchInput').value.toLowerCase();
        var status = document.getElementById('statusFilter').value;
        document.querySelectorAll('.user-card').forEach(function(c) {
            var login = c.getAttribute('data-login') || '';
            var st = c.getAttribute('data-status') || '';
            var exp = c.getAttribute('data-expirado') || '';
            var mb = login.includes(busca);
            var ms = true;
            if (status === 'online') ms = st === 'online';
            else if (status === 'offline') ms = st === 'offline';
            else if (status === 'suspenso') ms = st === 'suspenso';
            else if (status === 'expirado') ms = exp === 'expirado';
            c.style.display = (mb && ms) ? '' : 'none';
        });
    }

    // ===== Mudar limite =====
    function mudarLimite() {
        var l = document.getElementById('limitSelect').value;
        var url = new URL(window.location.href);
        url.searchParams.set('limite', l); url.searchParams.set('pagina', '1');
        window.location.href = url.toString();
    }

    // ===== Dias select =====
    function setDias(d) {
        document.getElementById('renovarDias').value = d;
        document.querySelectorAll('.dia-opt').forEach(function(o) {
            o.classList.remove('active');
            var v = o.textContent.trim();
            var val = v === '1 ano' ? 365 : parseInt(v);
            if (val === d) o.classList.add('active');
        });
    }

    // ══════════════════════════════════════════════════════════
    // RENOVAR — chama renovardias.php?id=X via GET (igual ao exemplo)
    // ══════════════════════════════════════════════════════════
    function renovar(id, nome) {
        _userId = id; _userName = nome;
        document.getElementById('renovarNome').textContent = nome;
        document.getElementById('renovarDias').value = 30;
        setDias(30);
        abrirModal('modalRenovar');
    }

    function confirmarRenovar() {
        var dias = parseInt(document.getElementById('renovarDias').value);
        if (!dias || dias < 1) { mostrarToast('Informe dias válidos!', 'err'); return; }
        fecharModal('modalRenovar');
        document.getElementById('processandoTexto').textContent = 'Renovando ' + _userName + '...';
        abrirModal('modalProcessando');

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'renovardias.php?id=' + _userId + '&dias=' + dias, true);
        xhr.onload = function() {
            fecharModal('modalProcessando');
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.sucesso === true || data.sucesso === 'true') {
                    mostrarToast(data.mensagem || 'Renovado com sucesso!', 'ok');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    mostrarToast(data.mensagem || 'Erro ao renovar!', 'err');
                }
            } catch(e) {
                // Caso não retorne JSON, verifica texto
                var resp = xhr.responseText.trim().toLowerCase();
                if (resp.indexOf('sucesso') !== -1 || resp.indexOf('renovado') !== -1) {
                    mostrarToast('Renovado com sucesso!', 'ok');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    mostrarToast('Erro ao renovar!', 'err');
                }
            }
        };
        xhr.onerror = function() { fecharModal('modalProcessando'); mostrarToast('Erro de conexão!', 'err'); };
        xhr.send();
    }

    // ══════════════════════════════════════════════════════════
    // SUSPENDER — chama suspender.php?id=X via GET (igual ao exemplo)
    // ══════════════════════════════════════════════════════════
    function suspender(id, nome) {
        _userId = id; _userName = nome;
        document.getElementById('suspenderNome').textContent = nome;
        abrirModal('modalSuspender');
    }

    function confirmarSuspender() {
        fecharModal('modalSuspender');
        document.getElementById('processandoTexto').textContent = 'Suspendendo ' + _userName + '...';
        abrirModal('modalProcessando');

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'suspender.php?id=' + _userId, true);
        xhr.onload = function() {
            fecharModal('modalProcessando');
            var resp = xhr.responseText.trim().toLowerCase();
            if (resp === 'suspenso com sucesso' || resp.indexOf('suspenso') !== -1 || resp.indexOf('sucesso') !== -1) {
                mostrarToast('Usuário suspenso com sucesso!', 'ok');
                setTimeout(function() { location.reload(); }, 1500);
            } else if (resp === 'erro no servidor' || resp.indexOf('erro') !== -1) {
                mostrarToast('Erro no servidor! Verifique se está online.', 'err');
            } else {
                mostrarToast('Erro ao suspender!', 'err');
            }
        };
        xhr.onerror = function() { fecharModal('modalProcessando'); mostrarToast('Erro de conexão!', 'err'); };
        xhr.send();
    }

    // ══════════════════════════���═══════════════════════════════
    // REATIVAR — chama reativar.php?id=X via GET (igual ao exemplo)
    // ══════════════════════════════════════════════════════════
    function reativar(id, nome) {
        _userId = id; _userName = nome;
        document.getElementById('reativarNome').textContent = nome;
        abrirModal('modalReativar');
    }

    function confirmarReativar() {
        fecharModal('modalReativar');
        document.getElementById('processandoTexto').textContent = 'Reativando ' + _userName + '...';
        abrirModal('modalProcessando');

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'reativar.php?id=' + _userId, true);
        xhr.onload = function() {
            fecharModal('modalProcessando');
            var resp = xhr.responseText.trim().toLowerCase();
            if (resp === 'reativado com sucesso' || resp.indexOf('reativado') !== -1 || resp.indexOf('sucesso') !== -1) {
                mostrarToast('Usuário reativado com sucesso!', 'ok');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                mostrarToast('Erro ao reativar!', 'err');
            }
        };
        xhr.onerror = function() { fecharModal('modalProcessando'); mostrarToast('Erro de conexão!', 'err'); };
        xhr.send();
    }

    // ══════════════════════════════════════════════════════════
    // EXCLUIR — chama excluiruser.php?id=X via GET (igual ao exemplo)
    // ══════════════════════════════════════════════════════════
    function excluir(id, nome) {
        _userId = id; _userName = nome;
        document.getElementById('excluirNome').textContent = nome;
        abrirModal('modalExcluir');
    }

    function confirmarExcluir() {
        fecharModal('modalExcluir');
        document.getElementById('processandoTexto').textContent = 'Excluindo ' + _userName + '...';
        abrirModal('modalProcessando');

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'excluiruser.php?id=' + _userId, true);
        xhr.onload = function() {
            fecharModal('modalProcessando');
            var resp = xhr.responseText.trim().toLowerCase();
            if (resp === 'excluido' || resp.indexOf('excluido') !== -1 || resp.indexOf('sucesso') !== -1) {
                mostrarToast('Usuário excluído com sucesso!', 'ok');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                mostrarToast('Erro ao excluir!', 'err');
            }
        };
        xhr.onerror = function() { fecharModal('modalProcessando'); mostrarToast('Erro de conexão!', 'err'); };
        xhr.send();
    }

    // ══════════════════════════════════════════════════════════
    // LIMPAR DEVICE ID — chama deviceid.php?id=X via GET (igual ao exemplo)
    // ══════════════════════════════════════════════════════════
    function limparDevice(id, nome) {
        _userId = id; _userName = nome;
        document.getElementById('deviceNome').textContent = nome;
        abrirModal('modalDevice');
    }

    function confirmarDevice() {
        fecharModal('modalDevice');
        document.getElementById('processandoTexto').textContent = 'Limpando Device ID...';
        abrirModal('modalProcessando');

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'deviceid.php?id=' + _userId, true);
        xhr.onload = function() {
            fecharModal('modalProcessando');
            var resp = xhr.responseText.replace(/[\r\n]/g, '').trim().toLowerCase();
            if (resp === 'deletado com sucesso' || resp.indexOf('deletado') !== -1 || resp.indexOf('sucesso') !== -1) {
                mostrarToast('Device ID limpo com sucesso!', 'ok');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                mostrarToast('Erro ao limpar Device ID!', 'err');
            }
        };
        xhr.onerror = function() { fecharModal('modalProcessando'); mostrarToast('Erro de conexão!', 'err'); };
        xhr.send();
    }
    </script>
</body>
</html>

