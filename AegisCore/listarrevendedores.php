<?php
error_reporting(0);
session_start();
date_default_timezone_set('America/Sao_Paulo');

include('../AegisCore/conexao.php');
include('header2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Busca server-side
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

if (!empty($search)) {
    $sql = "SELECT * FROM accounts WHERE byid = '$_SESSION[iduser]' AND login LIKE '%$search%'";
} else {
    $sql = "SELECT * FROM accounts WHERE byid = '$_SESSION[iduser]'";
}
$result = $conn->query($sql);

// Total geral sem filtro
$res_total = $conn->query("SELECT COUNT(*) as total FROM accounts WHERE byid = '$_SESSION[iduser]'");
$total_rev = mysqli_fetch_assoc($res_total)['total'];

$sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$sql5 = $conn->query($sql5);
$row  = $sql5->fetch_assoc();
$validade = $row['expira'];
$tipo     = $row['tipo'];
$_SESSION['tipodeconta'] = $row['tipo'];

$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta está vencida')</script>";
        echo "<script>window.location.href = '../home.php'</script>";
        exit();
    }
}

if (!file_exists('../admin/suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once '../admin/suspenderrev.php';
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

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Revendedores</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Rubik',sans-serif; min-height:100vh; background:linear-gradient(135deg,#0f172a,#1e1b4b); }

        .app-content { margin-left:240px !important; padding:0 !important; }
        .content-wrapper { max-width:1650px; margin:0 auto 0 5px !important; padding:0 !important; }

        .info-badge {
            display:inline-flex !important; align-items:center !important; gap:8px !important;
            background:white !important; color:var(--dark) !important;
            padding:8px 16px !important; border-radius:30px !important; font-size:13px !important;
            margin-top:5px !important; margin-bottom:15px !important;
            border-left:4px solid var(--primary) !important;
            box-shadow:0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size:22px; color:var(--primary); }

        .status-info {
            background:linear-gradient(135deg,#1e293b,#0f172a); border-radius:14px;
            padding:12px 18px; margin-bottom:15px; border:1px solid rgba(255,255,255,0.1);
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; color:white;
        }
        .status-item { display:flex !important; align-items:center !important; gap:6px !important; }
        .status-item i { font-size:20px !important; color:var(--tertiary) !important; }
        .status-item span { font-size:12px !important; font-weight:500 !important; }

        .filters-card {
            background:linear-gradient(135deg,#1e293b,#0f172a) !important; border-radius:14px !important;
            padding:14px !important; margin-bottom:16px !important;
            box-shadow:0 4px 15px rgba(0,0,0,0.3) !important;
            border:1px solid rgba(255,255,255,0.08) !important;
        }
        .filters-title { font-size:14px; font-weight:700; color:white; margin-bottom:10px; display:flex; align-items:center; gap:6px; }
        .filters-title i { color:var(--tertiary); font-size:16px; }
        .filter-group { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
        .filter-item { flex:1 1 200px; min-width:160px; }
        .filter-label { font-size:11px; font-weight:600; color:rgba(255,255,255,0.5); margin-bottom:4px; text-transform:uppercase; letter-spacing:.5px; }
        .filter-input, .filter-select {
            width:100%; padding:7px 12px; background:rgba(255,255,255,0.06);
            border:1.5px solid rgba(255,255,255,0.08); border-radius:10px;
            font-size:13px; transition:all .3s; color:white;
        }
        .filter-input:focus, .filter-select:focus { outline:none; border-color:rgba(65,88,208,0.6); background:rgba(255,255,255,0.09); }
        .filter-input::placeholder { color:rgba(255,255,255,0.3); }
        .filter-select { cursor:pointer; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23C850C0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; background-size:14px; }
        .filter-select option { background:#1e293b; color:white; }

        .revendedores-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; margin-top:14px; width:100%; }

        .revendedor-card {
            background:linear-gradient(135deg,#1e293b,#0f172a) !important; border-radius:16px !important;
            overflow:hidden !important; box-shadow:0 4px 15px rgba(0,0,0,0.3) !important;
            transition:all .3s !important; border:1px solid rgba(255,255,255,0.08) !important;
            animation:fadeIn .4s ease !important;
        }
        .revendedor-card:hover { transform:translateY(-3px) !important; box-shadow:0 10px 25px rgba(0,0,0,0.5) !important; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        .card-header-custom { 
            background:linear-gradient(135deg,#C850C0,#4158D0) !important; 
            color:white; padding:14px 16px; 
            display:flex; align-items:center; 
            justify-content:space-between;
            gap:10px;
        }
        .header-info { display:flex; align-items:center; gap:10px; flex:1; min-width:0; }
        .header-icon { width:40px; height:40px; background:rgba(255,255,255,0.2); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; color:white; flex-shrink:0; }
        .header-text { flex:1; min-width:0; }
        .header-title { font-size:16px; font-weight:700; color:white; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .header-subtitle { font-size:11px; color:rgba(255,255,255,0.7); margin-top:2px; }
        
        .btn-copy-card {
            background:rgba(255,255,255,0.15);
            border:none;
            border-radius:10px;
            padding:8px 12px;
            color:white;
            font-size:13px;
            font-weight:600;
            cursor:pointer;
            transition:all .2s;
            display:flex;
            align-items:center;
            gap:6px;
            flex-shrink:0;
        }
        .btn-copy-card:hover { background:rgba(255,255,255,0.25); transform:scale(1.02); }
        .btn-copy-card.copied { background:linear-gradient(135deg,#10b981,#059669); animation:copiedPulse 0.5s ease; }
        @keyframes copiedPulse { 0% { transform:scale(1); } 50% { transform:scale(1.05); } 100% { transform:scale(1); } }

        .card-body-custom { padding:16px; }

        .status-row { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:12px; }
        .info-row-status {
            display:flex; align-items:center; padding:8px 12px;
            background:rgba(255,255,255,0.03); border-radius:10px;
            border:1px solid rgba(255,255,255,0.05);
        }
        .info-row-status:hover { border-color:var(--primary); background:rgba(255,255,255,0.05); }
        .info-icon-status { width:32px; height:32px; background:rgba(255,255,255,0.03); border-radius:8px; display:flex; align-items:center; justify-content:center; margin-right:10px; font-size:16px; border:1px solid rgba(255,255,255,0.05); flex-shrink:0; }
        .info-content-status { flex:1; min-width:0; }
        .info-label-status { font-size:9px; color:rgba(255,255,255,0.4); font-weight:700; text-transform:uppercase; letter-spacing:.4px; margin-bottom:2px; }
        .info-value-status { font-size:13px; font-weight:600; color:white; }

        .info-row {
            display:flex; align-items:center; padding:8px 12px;
            background:rgba(255,255,255,0.03); border-radius:10px;
            margin-bottom:8px; border:1px solid rgba(255,255,255,0.05);
        }
        .info-row:hover { border-color:var(--primary); background:rgba(255,255,255,0.05); }
        .info-icon { width:32px; height:32px; background:rgba(255,255,255,0.03); border-radius:8px; display:flex; align-items:center; justify-content:center; margin-right:10px; font-size:16px; border:1px solid rgba(255,255,255,0.05); flex-shrink:0; }
        .info-content { flex:1; min-width:0; }
        .info-label { font-size:9px; color:rgba(255,255,255,0.4); font-weight:700; text-transform:uppercase; letter-spacing:.4px; margin-bottom:2px; }
        .info-value { font-size:13px; font-weight:600; color:white; }
        .icon-user { color:#818cf8; }
        .icon-lock { color:#e879f9; }
        .icon-group { color:#34d399; }
        .icon-calendar { color:#fbbf24; }

        .badge-custom {
            display:inline-flex; align-items:center; gap:4px;
            padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600;
        }
        .badge-success { background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.3); }
        .badge-danger { background:rgba(220,38,38,0.15); color:#dc2626; border:1px solid rgba(220,38,38,0.3); }
        .badge-warning { background:rgba(245,158,11,0.15); color:#fbbf24; border:1px solid rgba(245,158,11,0.3); }
        .badge-credit { background:linear-gradient(135deg,#f59e0b,#f97316); color:white; border:none; }

        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px; }
        .grid-2 .info-row { margin-bottom:0; }

        .action-buttons { display:flex; gap:6px; margin-top:12px; flex-wrap:wrap; }
        .action-btn {
            flex:1; min-width:70px; padding:6px 10px; border:none;
            border-radius:20px; font-weight:600; font-size:11px; cursor:pointer;
            transition:all .2s; display:inline-flex; align-items:center;
            justify-content:center; gap:4px; color:white;
            box-shadow:0 3px 8px rgba(0,0,0,.2);
        }
        .action-btn i { font-size:13px; }
        .btn-edit    { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-edit:hover    { transform:translateY(-2px); box-shadow:0 5px 12px rgba(65,88,208,.4); }
        .btn-danger  { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-danger:hover  { transform:translateY(-2px); box-shadow:0 5px 12px rgba(220,38,38,.4); }
        .btn-view    { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-view:hover    { transform:translateY(-2px); box-shadow:0 5px 12px rgba(100,116,139,.4); }
        .btn-warning { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-warning:hover { transform:translateY(-2px); box-shadow:0 5px 12px rgba(245,158,11,.4); }
        .btn-success { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-success:hover { transform:translateY(-2px); box-shadow:0 5px 12px rgba(16,185,129,.4); }
        .btn-renew   { background:linear-gradient(135deg,#8b5cf6,#7c3aed); }
        .btn-renew:hover   { transform:translateY(-2px); box-shadow:0 5px 12px rgba(139,92,246,.4); }

        .empty-state {
            grid-column:1/-1; text-align:center; padding:50px 20px;
            background:linear-gradient(135deg,#1e293b,#0f172a);
            border-radius:16px; border:1px solid rgba(255,255,255,0.08);
            color:white;
        }
        .empty-state i { font-size:60px; color:rgba(255,255,255,0.2); margin-bottom:15px; display:block; }
        .empty-state h3 { color:white; font-size:18px; margin-bottom:8px; }
        .empty-state p  { color:rgba(255,255,255,0.3); font-size:14px; }
        .pagination-info { text-align:center; margin-top:20px; color:rgba(255,255,255,0.5); font-weight:600; font-size:13px; }

        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669);
            color:white; padding:12px 20px; border-radius:12px;
            display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease;
            box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn { from{transform:translateX(110%);opacity:0;} to{transform:translateX(0);opacity:1;} }

        /* =============================================
           MODAIS - ESTILOS COMPLETOS
           ============================================= */
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
            transition: all 0.3s ease;
        }

        .modal-overlay.show {
            display: flex !important;
        }

        .modal-container {
            animation: modalSlideIn 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
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

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom.warning {
            background: linear-gradient(135deg, #f59e0b, #f97316);
        }

        .modal-header-custom.processing {
            background: linear-gradient(135deg, #4158D0, #C850C0);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .modal-success-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 15px rgba(16,185,129,0.5));
        }

        .modal-warning-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-warning-icon i {
            font-size: 70px;
            color: #f59e0b;
            filter: drop-shadow(0 0 15px rgba(245,158,11,0.5));
        }

        .modal-danger-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-danger-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 15px rgba(220,38,38,0.5));
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

        .modal-info-row:last-child {
            border-bottom: none;
        }

        .modal-info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-info-label i {
            font-size: 18px;
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
            letter-spacing: 0.5px;
        }

        .modal-info-value.highlight-green {
            color: #10b981;
        }

        .modal-info-value.highlight-orange {
            color: #f59e0b;
        }

        .modal-divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin: 16px 0;
        }

        .modal-success-title {
            text-align: center;
            color: #10b981;
            font-weight: 700;
            font-size: 14px;
            margin-top: 12px;
        }

        /* Botões do modal */
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
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
            color: white;
        }

        .btn-modal-copy {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .btn-modal-copy:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(59,130,246,0.5);
        }

        .btn-modal-ok {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .btn-modal-ok:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(16,185,129,0.5);
        }

        .btn-modal-cancel {
            background: linear-gradient(135deg, #64748b, #475569);
        }

        .btn-modal-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(100,116,139,0.5);
        }

        .btn-modal-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .btn-modal-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(220,38,38,0.5);
        }

        .btn-modal-warning {
            background: linear-gradient(135deg, #f59e0b, #f97316);
        }

        .btn-modal-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(245,158,11,0.5);
        }

        /* Spinner processando */
        .processing-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            padding: 20px 0;
        }

        .spinner-ring {
            width: 64px;
            height: 64px;
            border: 4px solid rgba(255,255,255,0.2);
            border-top: 4px solid #10b981;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .spinner-text {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            font-weight: 500;
            text-align: center;
        }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:10px !important; }
            .revendedores-grid { grid-template-columns:1fr; gap:12px; }
            .action-buttons { display:grid; grid-template-columns:repeat(2,1fr); gap:6px; }
            .action-btn { width:100%; }
            .grid-2 { grid-template-columns:1fr 1fr; gap:8px; }
            .status-row { grid-template-columns:1fr 1fr; gap:8px; }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer-custom { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
error_reporting(0);
session_start();
date_default_timezone_set('America/Sao_Paulo');

include('../AegisCore/conexao.php');
include('header2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Busca server-side
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

if (!empty($search)) {
    $sql = "SELECT * FROM accounts WHERE byid = '$_SESSION[iduser]' AND login LIKE '%$search%'";
} else {
    $sql = "SELECT * FROM accounts WHERE byid = '$_SESSION[iduser]'";
}
$result = $conn->query($sql);

// Total geral sem filtro
$res_total = $conn->query("SELECT COUNT(*) as total FROM accounts WHERE byid = '$_SESSION[iduser]'");
$total_rev = mysqli_fetch_assoc($res_total)['total'];

$sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$sql5 = $conn->query($sql5);
$row  = $sql5->fetch_assoc();
$validade = $row['expira'];
$tipo     = $row['tipo'];
$_SESSION['tipodeconta'] = $row['tipo'];

$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta está vencida')</script>";
        echo "<script>window.location.href = '../home.php'</script>";
        exit();
    }
}

if (!file_exists('../admin/suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once '../admin/suspenderrev.php';
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

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Revendedores</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Rubik',sans-serif; min-height:100vh; background:linear-gradient(135deg,#0f172a,#1e1b4b); }

        .app-content { margin-left:240px !important; padding:0 !important; }
        .content-wrapper { max-width:1650px; margin:0 auto 0 5px !important; padding:0 !important; }

        .info-badge {
            display:inline-flex !important; align-items:center !important; gap:8px !important;
            background:white !important; color:var(--dark) !important;
            padding:8px 16px !important; border-radius:30px !important; font-size:13px !important;
            margin-top:5px !important; margin-bottom:15px !important;
            border-left:4px solid var(--primary) !important;
            box-shadow:0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size:22px; color:var(--primary); }

        .status-info {
            background:linear-gradient(135deg,#1e293b,#0f172a); border-radius:14px;
            padding:12px 18px; margin-bottom:15px; border:1px solid rgba(255,255,255,0.1);
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; color:white;
        }
        .status-item { display:flex !important; align-items:center !important; gap:6px !important; }
        .status-item i { font-size:20px !important; color:var(--tertiary) !important; }
        .status-item span { font-size:12px !important; font-weight:500 !important; }

        .filters-card {
            background:linear-gradient(135deg,#1e293b,#0f172a) !important; border-radius:14px !important;
            padding:14px !important; margin-bottom:16px !important;
            box-shadow:0 4px 15px rgba(0,0,0,0.3) !important;
            border:1px solid rgba(255,255,255,0.08) !important;
        }
        .filters-title { font-size:14px; font-weight:700; color:white; margin-bottom:10px; display:flex; align-items:center; gap:6px; }
        .filters-title i { color:var(--tertiary); font-size:16px; }
        .filter-group { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
        .filter-item { flex:1 1 200px; min-width:160px; }
        .filter-label { font-size:11px; font-weight:600; color:rgba(255,255,255,0.5); margin-bottom:4px; text-transform:uppercase; letter-spacing:.5px; }
        .filter-input, .filter-select {
            width:100%; padding:7px 12px; background:rgba(255,255,255,0.06);
            border:1.5px solid rgba(255,255,255,0.08); border-radius:10px;
            font-size:13px; transition:all .3s; color:white;
        }
        .filter-input:focus, .filter-select:focus { outline:none; border-color:rgba(65,88,208,0.6); background:rgba(255,255,255,0.09); }
        .filter-input::placeholder { color:rgba(255,255,255,0.3); }
        .filter-select { cursor:pointer; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23C850C0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; background-size:14px; }
        .filter-select option { background:#1e293b; color:white; }

        .revendedores-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; margin-top:14px; width:100%; }

        .revendedor-card {
            background:linear-gradient(135deg,#1e293b,#0f172a) !important; border-radius:16px !important;
            overflow:hidden !important; box-shadow:0 4px 15px rgba(0,0,0,0.3) !important;
            transition:all .3s !important; border:1px solid rgba(255,255,255,0.08) !important;
            animation:fadeIn .4s ease !important;
        }
        .revendedor-card:hover { transform:translateY(-3px) !important; box-shadow:0 10px 25px rgba(0,0,0,0.5) !important; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        .card-header-custom { 
            background:linear-gradient(135deg,#C850C0,#4158D0) !important; 
            color:white; padding:14px 16px; 
            display:flex; align-items:center; 
            justify-content:space-between;
            gap:10px;
        }
        .header-info { display:flex; align-items:center; gap:10px; flex:1; min-width:0; }
        .header-icon { width:40px; height:40px; background:rgba(255,255,255,0.2); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; color:white; flex-shrink:0; }
        .header-text { flex:1; min-width:0; }
        .header-title { font-size:16px; font-weight:700; color:white; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .header-subtitle { font-size:11px; color:rgba(255,255,255,0.7); margin-top:2px; }
        
        .btn-copy-card {
            background:rgba(255,255,255,0.15);
            border:none;
            border-radius:10px;
            padding:8px 12px;
            color:white;
            font-size:13px;
            font-weight:600;
            cursor:pointer;
            transition:all .2s;
            display:flex;
            align-items:center;
            gap:6px;
            flex-shrink:0;
        }
        .btn-copy-card:hover { background:rgba(255,255,255,0.25); transform:scale(1.02); }
        .btn-copy-card.copied { background:linear-gradient(135deg,#10b981,#059669); animation:copiedPulse 0.5s ease; }
        @keyframes copiedPulse { 0% { transform:scale(1); } 50% { transform:scale(1.05); } 100% { transform:scale(1); } }

        .card-body-custom { padding:16px; }

        .status-row { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:12px; }
        .info-row-status {
            display:flex; align-items:center; padding:8px 12px;
            background:rgba(255,255,255,0.03); border-radius:10px;
            border:1px solid rgba(255,255,255,0.05);
        }
        .info-row-status:hover { border-color:var(--primary); background:rgba(255,255,255,0.05); }
        .info-icon-status { width:32px; height:32px; background:rgba(255,255,255,0.03); border-radius:8px; display:flex; align-items:center; justify-content:center; margin-right:10px; font-size:16px; border:1px solid rgba(255,255,255,0.05); flex-shrink:0; }
        .info-content-status { flex:1; min-width:0; }
        .info-label-status { font-size:9px; color:rgba(255,255,255,0.4); font-weight:700; text-transform:uppercase; letter-spacing:.4px; margin-bottom:2px; }
        .info-value-status { font-size:13px; font-weight:600; color:white; }

        .info-row {
            display:flex; align-items:center; padding:8px 12px;
            background:rgba(255,255,255,0.03); border-radius:10px;
            margin-bottom:8px; border:1px solid rgba(255,255,255,0.05);
        }
        .info-row:hover { border-color:var(--primary); background:rgba(255,255,255,0.05); }
        .info-icon { width:32px; height:32px; background:rgba(255,255,255,0.03); border-radius:8px; display:flex; align-items:center; justify-content:center; margin-right:10px; font-size:16px; border:1px solid rgba(255,255,255,0.05); flex-shrink:0; }
        .info-content { flex:1; min-width:0; }
        .info-label { font-size:9px; color:rgba(255,255,255,0.4); font-weight:700; text-transform:uppercase; letter-spacing:.4px; margin-bottom:2px; }
        .info-value { font-size:13px; font-weight:600; color:white; }
        .icon-user { color:#818cf8; }
        .icon-lock { color:#e879f9; }
        .icon-group { color:#34d399; }
        .icon-calendar { color:#fbbf24; }

        .badge-custom {
            display:inline-flex; align-items:center; gap:4px;
            padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600;
        }
        .badge-success { background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.3); }
        .badge-danger { background:rgba(220,38,38,0.15); color:#dc2626; border:1px solid rgba(220,38,38,0.3); }
        .badge-warning { background:rgba(245,158,11,0.15); color:#fbbf24; border:1px solid rgba(245,158,11,0.3); }
        .badge-credit { background:linear-gradient(135deg,#f59e0b,#f97316); color:white; border:none; }

        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px; }
        .grid-2 .info-row { margin-bottom:0; }

        .action-buttons { display:flex; gap:6px; margin-top:12px; flex-wrap:wrap; }
        .action-btn {
            flex:1; min-width:70px; padding:6px 10px; border:none;
            border-radius:20px; font-weight:600; font-size:11px; cursor:pointer;
            transition:all .2s; display:inline-flex; align-items:center;
            justify-content:center; gap:4px; color:white;
            box-shadow:0 3px 8px rgba(0,0,0,.2);
        }
        .action-btn i { font-size:13px; }
        .btn-edit    { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-edit:hover    { transform:translateY(-2px); box-shadow:0 5px 12px rgba(65,88,208,.4); }
        .btn-danger  { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-danger:hover  { transform:translateY(-2px); box-shadow:0 5px 12px rgba(220,38,38,.4); }
        .btn-view    { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-view:hover    { transform:translateY(-2px); box-shadow:0 5px 12px rgba(100,116,139,.4); }
        .btn-warning { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-warning:hover { transform:translateY(-2px); box-shadow:0 5px 12px rgba(245,158,11,.4); }
        .btn-success { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-success:hover { transform:translateY(-2px); box-shadow:0 5px 12px rgba(16,185,129,.4); }
        .btn-renew   { background:linear-gradient(135deg,#8b5cf6,#7c3aed); }
        .btn-renew:hover   { transform:translateY(-2px); box-shadow:0 5px 12px rgba(139,92,246,.4); }

        .empty-state {
            grid-column:1/-1; text-align:center; padding:50px 20px;
            background:linear-gradient(135deg,#1e293b,#0f172a);
            border-radius:16px; border:1px solid rgba(255,255,255,0.08);
            color:white;
        }
        .empty-state i { font-size:60px; color:rgba(255,255,255,0.2); margin-bottom:15px; display:block; }
        .empty-state h3 { color:white; font-size:18px; margin-bottom:8px; }
        .empty-state p  { color:rgba(255,255,255,0.3); font-size:14px; }
        .pagination-info { text-align:center; margin-top:20px; color:rgba(255,255,255,0.5); font-weight:600; font-size:13px; }

        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669);
            color:white; padding:12px 20px; border-radius:12px;
            display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease;
            box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn { from{transform:translateX(110%);opacity:0;} to{transform:translateX(0);opacity:1;} }

        /* =============================================
           MODAIS - ESTILOS COMPLETOS
           ============================================= */
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
            transition: all 0.3s ease;
        }

        .modal-overlay.show {
            display: flex !important;
        }

        .modal-container {
            animation: modalSlideIn 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
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

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom.warning {
            background: linear-gradient(135deg, #f59e0b, #f97316);
        }

        .modal-header-custom.processing {
            background: linear-gradient(135deg, #4158D0, #C850C0);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .modal-success-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 15px rgba(16,185,129,0.5));
        }

        .modal-warning-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-warning-icon i {
            font-size: 70px;
            color: #f59e0b;
            filter: drop-shadow(0 0 15px rgba(245,158,11,0.5));
        }

        .modal-danger-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-danger-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 15px rgba(220,38,38,0.5));
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

        .modal-info-row:last-child {
            border-bottom: none;
        }

        .modal-info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-info-label i {
            font-size: 18px;
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
            letter-spacing: 0.5px;
        }

        .modal-info-value.highlight-green {
            color: #10b981;
        }

        .modal-info-value.highlight-orange {
            color: #f59e0b;
        }

        .modal-divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin: 16px 0;
        }

        .modal-success-title {
            text-align: center;
            color: #10b981;
            font-weight: 700;
            font-size: 14px;
            margin-top: 12px;
        }

        /* Botões do modal */
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
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
            color: white;
        }

        .btn-modal-copy {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .btn-modal-copy:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(59,130,246,0.5);
        }

        .btn-modal-ok {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .btn-modal-ok:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(16,185,129,0.5);
        }

        .btn-modal-cancel {
            background: linear-gradient(135deg, #64748b, #475569);
        }

        .btn-modal-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(100,116,139,0.5);
        }

        .btn-modal-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .btn-modal-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(220,38,38,0.5);
        }

        .btn-modal-warning {
            background: linear-gradient(135deg, #f59e0b, #f97316);
        }

        .btn-modal-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(245,158,11,0.5);
        }

        /* Spinner processando */
        .processing-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            padding: 20px 0;
        }

        .spinner-ring {
            width: 64px;
            height: 64px;
            border: 4px solid rgba(255,255,255,0.2);
            border-top: 4px solid #10b981;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .spinner-text {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            font-weight: 500;
            text-align: center;
        }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:10px !important; }
            .revendedores-grid { grid-template-columns:1fr; gap:12px; }
            .action-buttons { display:grid; grid-template-columns:repeat(2,1fr); gap:6px; }
            .action-btn { width:100%; }
            .grid-2 { grid-template-columns:1fr 1fr; gap:8px; }
            .status-row { grid-template-columns:1fr 1fr; gap:8px; }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer-custom { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body>
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <div class="info-badge"><i class='bx bx-group'></i><span>Gerenciar Revendedores</span></div>

    <div class="status-info">
        <div class="status-item"><i class='bx bx-info-circle'></i><span>Total revendedores: <?php echo $total_rev; ?></span></div>
        <div class="status-item"><i class='bx bx-time'></i><span><?php echo date('d/m/Y H:i'); ?></span></div>
    </div>

    <div class="filters-card">
        <div class="filters-title"><i class='bx bx-filter-alt'></i> Filtros</div>
        <div class="filter-group">
            <div class="filter-item">
                <div class="filter-label">BUSCAR POR LOGIN</div>
                <div class="search-field">
                    <input type="text" class="filter-input" id="searchInput"
                           placeholder="Digite para buscar..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           onkeyup="filtrarLive(this.value)">
                </div>
            </div>
            <div class="filter-item">
                <div class="filter-label">FILTRAR POR STATUS</div>
                <select class="filter-select" id="statusFilter" onchange="filtrarStatus(this.value)">
                    <option value="todos">Todos</option>
                    <option value="ativo">Ativo</option>
                    <option value="suspenso">Suspenso</option>
                </select>
            </div>
        </div>
    </div>

    <div class="revendedores-grid" id="revendedoresGrid">
    <?php
    if ($result->num_rows > 0) {
        while ($user_data = mysqli_fetch_assoc($result)) {
            $sql2       = "SELECT * FROM atribuidos WHERE userid = '".$user_data['id']."'";
            $result2    = $conn->query($sql2);
            $user_data2 = mysqli_fetch_assoc($result2);
            $expira_raw = $user_data2['expira'];
            $expira_fmt = date('d/m/Y', strtotime($expira_raw));
            $diff       = strtotime($expira_raw) - time();
            $dias       = floor($diff / 86400);

            if ($user_data2['tipo'] == 'Credito') {
                $expira_badge = '<span class="badge-custom badge-credit"><i class="bx bx-credit-card"></i> Crédito</span>';
            } elseif ($dias < 0) {
                $expira_badge = '<span class="badge-custom badge-danger"><i class="bx bx-calendar-x"></i> Expirado</span>';
            } elseif ($dias <= 5) {
                $expira_badge = '<span class="badge-custom badge-warning"><i class="bx bx-calendar-exclamation"></i> '.$dias.' dias</span>';
            } else {
                $expira_badge = '<span class="badge-custom badge-success"><i class="bx bx-calendar-check"></i> '.$dias.' dias</span>';
            }

            $status_val  = $user_data2['suspenso'] == '0' ? 'ativo' : 'suspenso';
            $status_badge = $user_data2['suspenso'] == '0'
                ? '<span class="badge-custom badge-success"><i class="bx bx-check-circle"></i> Ativo</span>'
                : '<span class="badge-custom badge-danger"><i class="bx bx-lock"></i> Suspenso</span>';
    ?>
        <div class="revendedor-card"
             data-login="<?php echo strtolower($user_data['login']); ?>"
             data-status="<?php echo $status_val; ?>"
             data-id="<?php echo $user_data['id']; ?>"
             data-suspenso="<?php echo $user_data2['suspenso']; ?>"
             data-nome="<?php echo htmlspecialchars($user_data['login']); ?>"
             data-senha="<?php echo htmlspecialchars($user_data['senha']); ?>"
             data-tipo="<?php echo htmlspecialchars($user_data2['tipo']); ?>"
             data-limite="<?php echo htmlspecialchars($user_data2['limite']); ?>"
             data-expira="<?php echo $expira_fmt; ?>"
             data-dias="<?php echo max(0, $dias); ?>">

            <div class="card-header-custom">
                <div class="header-info">
                    <div class="header-icon"><i class='bx bx-user'></i></div>
                    <div class="header-text">
                        <div class="header-title"><?php echo htmlspecialchars($user_data['login']); ?></div>
                        <div class="header-subtitle">ID: <?php echo $user_data['id']; ?></div>
                    </div>
                </div>
                <button class="btn-copy-card" onclick="copiarInfoCard(<?php echo $user_data['id']; ?>, event)">
                    <i class='bx bx-copy'></i>
                    <span class="copy-text">Copiar</span>
                </button>
            </div>

            <div class="card-body-custom">
                <div class="status-row">
                    <div class="info-row-status">
                        <div class="info-icon-status"><i class='bx bx-info-circle icon-group'></i></div>
                        <div class="info-content-status">
                            <div class="info-label-status">STATUS</div>
                            <div class="info-value-status"><?php echo $status_badge; ?></div>
                        </div>
                    </div>
                    <div class="info-row-status">
                        <div class="info-icon-status"><i class='bx bx-calendar icon-calendar'></i></div>
                        <div class="info-content-status">
                            <div class="info-label-status">VALIDADE</div>
                            <div class="info-value-status"><?php echo $expira_badge; ?></div>
                        </div>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="info-row"><div class="info-icon"><i class='bx bx-user icon-user'></i></div><div class="info-content"><div class="info-label">LOGIN</div><div class="info-value"><?php echo htmlspecialchars($user_data['login']); ?></div></div></div>
                    <div class="info-row"><div class="info-icon"><i class='bx bx-lock-alt icon-lock'></i></div><div class="info-content"><div class="info-label">SENHA</div><div class="info-value"><?php echo htmlspecialchars($user_data['senha']); ?></div></div></div>
                </div>

                <div class="grid-2">
                    <div class="info-row"><div class="info-icon"><i class='bx bx-category icon-user'></i></div><div class="info-content"><div class="info-label">MODO</div><div class="info-value"><?php echo $user_data2['tipo']; ?></div></div></div>
                    <div class="info-row"><div class="info-icon"><i class='bx bx-group icon-group'></i></div><div class="info-content"><div class="info-label">LIMITE</div><div class="info-value"><?php echo $user_data2['limite']; ?></div></div></div>
                </div>

                <?php if ($user_data2['tipo'] != 'Credito'): ?>
                <div class="info-row"><div class="info-icon"><i class='bx bx-calendar-check icon-calendar'></i></div><div class="info-content"><div class="info-label">EXPIRA EM</div><div class="info-value"><?php echo $expira_fmt; ?></div></div></div>
                <?php endif; ?>

                <div class="action-buttons">
                    <button class="action-btn btn-edit" onclick="editarRevendedor(<?php echo $user_data['id']; ?>)"><i class='bx bx-edit'></i> Editar</button>
                    <button class="action-btn btn-view" onclick="visualizarRevendedor(<?php echo $user_data['id']; ?>)"><i class='bx bx-show'></i> Ver</button>
                    <?php if ($user_data2['suspenso'] == '0' && $user_data2['tipo'] != 'Credito'): ?>
                    <button class="action-btn btn-warning" onclick="suspenderRevendedor(<?php echo $user_data['id']; ?>)"><i class='bx bx-pause'></i> Suspender</button>
                    <?php elseif ($user_data2['suspenso'] == '1'): ?>
                    <button class="action-btn btn-success" onclick="reativarRevendedor(<?php echo $user_data['id']; ?>)"><i class='bx bx-refresh'></i> Reativar</button>
                    <?php endif; ?>
                    <?php if ($user_data2['tipo'] != 'Credito'): ?>
                    <button class="action-btn btn-renew" onclick="renovarRevendedor(<?php echo $user_data['id']; ?>)"><i class='bx bx-calendar-plus'></i> Renovar</button>
                    <?php endif; ?>
                    <button class="action-btn btn-danger" onclick="excluirRevendedor(<?php echo $user_data['id']; ?>)"><i class='bx bx-trash'></i> Excluir</button>
                </div>
            </div>
        </div>
    <?php
        }
    } else {
        echo '<div class="empty-state"><i class="bx bx-group"></i><h3>Nenhum revendedor encontrado</h3><p>' . (!empty($search) ? 'Nenhum resultado para "'.htmlspecialchars($search).'"' : 'Você ainda não possui revendedores cadastrados') . '</p></div>';
    }
    ?>
    </div>

    <div class="pagination-info">
        Exibindo <?php echo $result->num_rows; ?> de <?php echo $total_rev; ?> revendedor(es)
        <?php if (!empty($search)): ?>
        — busca: <strong style="color:#818cf8;"><?php echo htmlspecialchars($search); ?></strong>
        <a href="?" style="color:#f87171;font-size:11px;margin-left:6px;"><i class='bx bx-x'></i> limpar</a>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- =============================================
     MODAL PROCESSANDO
     ============================================= -->
<div id="modalProcessando" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom processing">
                <h5><i class='bx bx-loader-alt bx-spin'></i> Processando</h5>
            </div>
            <div class="modal-body-custom">
                <div class="processing-spinner">
                    <div class="spinner-ring"></div>
                    <p class="spinner-text">Aguarde enquanto processamos sua solicitação...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAÇÃO SUSPENSÃO -->
<div id="modalConfirmarSuspensao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom warning">
                <h5><i class='bx bx-pause-circle'></i> Confirmar Suspensão</h5>
                <button class="modal-close" onclick="fecharModal('modalConfirmarSuspensao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-warning-icon"><i class='bx bx-pause-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user'></i> Revendedor</div><div class="modal-info-value credential" id="suspender-login">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha</div><div class="modal-info-value credential" id="suspender-senha">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar'></i> Validade</div><div class="modal-info-value" id="suspender-expira">—</div></div>
                </div>
                <p style="text-align:center; color:rgba(255,255,255,0.5); font-size:12px;">Após suspenso, o revendedor não poderá mais acessar até ser reativado.</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarSuspensao')"><i class='bx bx-x'></i> Cancelar</button>
                <button class="btn-modal btn-modal-warning" id="btnConfirmarSuspensao"><i class='bx bx-pause-circle'></i> Suspender Agora</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAÇÃO REATIVAÇÃO -->
<div id="modalConfirmarReativacao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5><i class='bx bx-refresh'></i> Confirmar Reativação</h5>
                <button class="modal-close" onclick="fecharModal('modalConfirmarReativacao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-success-icon"><i class='bx bx-refresh'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user'></i> Revendedor</div><div class="modal-info-value credential" id="reativar-login">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha</div><div class="modal-info-value credential" id="reativar-senha">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar'></i> Validade</div><div class="modal-info-value" id="reativar-expira">—</div></div>
                </div>
                <p style="text-align:center; color:rgba(255,255,255,0.5); font-size:12px;">O revendedor será reativado e poderá acessar normalmente.</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarReativacao')"><i class='bx bx-x'></i> Cancelar</button>
                <button class="btn-modal btn-modal-ok" id="btnConfirmarReativacao"><i class='bx bx-refresh'></i> Reativar Agora</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAÇÃO EXCLUSÃO -->
<div id="modalConfirmarExclusao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5><i class='bx bx-trash'></i> Confirmar Exclusão</h5>
                <button class="modal-close" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-danger-icon"><i class='bx bx-trash'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user'></i> Revendedor</div><div class="modal-info-value credential" id="excluir-login">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha</div><div class="modal-info-value credential" id="excluir-senha">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar'></i> Validade</div><div class="modal-info-value" id="excluir-expira">—</div></div>
                </div>
                <p style="text-align:center; color:rgba(220,38,38,0.8); font-size:12px;">⚠️ Esta ação não pode ser desfeita! O revendedor será permanentemente removido.</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i> Cancelar</button>
                <button class="btn-modal btn-modal-danger" id="btnConfirmarExclusao"><i class='bx bx-trash'></i> Excluir Permanentemente</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAÇÃO RENOVAÇÃO -->
<div id="modalConfirmarRenovacao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom warning">
                <h5><i class='bx bx-calendar-plus'></i> Confirmar Renovação</h5>
                <button class="modal-close" onclick="fecharModal('modalConfirmarRenovacao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-warning-icon"><i class='bx bx-calendar-plus'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user'></i> Revendedor</div><div class="modal-info-value credential" id="renovar-login">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar'></i> Validade Atual</div><div class="modal-info-value" id="renovar-expira">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-plus-circle'></i> Dias a Adicionar</div><div class="modal-info-value highlight-green">+30 dias</div></div>
                </div>
                <p style="text-align:center; color:rgba(255,255,255,0.5); font-size:12px;">A validade será extendida a partir da data de expiração atual.</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarRenovacao')"><i class='bx bx-x'></i> Cancelar</button>
                <button class="btn-modal btn-modal-warning" id="btnConfirmarRenovacao"><i class='bx bx-calendar-plus'></i> Renovar Agora</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUCESSO OPERAÇÃO -->
<div id="modalSucessoOperacao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5><i class='bx bx-check-circle'></i> Operação Realizada!</h5>
                <button class="modal-close" onclick="fecharModalSucessoOperacao()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-success-icon"><i class='bx bx-check-circle'></i></div>
                <h3 style="color:white; text-align:center; margin-bottom:10px;" id="sucesso-titulo">Sucesso!</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="sucesso-mensagem">Operação realizada com sucesso!</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-ok" onclick="fecharModalSucessoOperacao()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUCESSO RENOVAÇÃO -->
<div id="modalSucessoRenovacao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5><i class='bx bx-check-circle'></i> Revendedor Renovado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModalSucessoRenovacao()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-success-icon"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user'></i> Login</div><div class="modal-info-value credential" id="renovacao-login">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha</div><div class="modal-info-value credential" id="renovacao-senha">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar-x'></i> Validade Anterior</div><div class="modal-info-value" id="renovacao-validade-ant">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar-check'></i> Nova Validade</div><div class="modal-info-value highlight-green" id="renovacao-validade-nova">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-layer'></i> Limite</div><div class="modal-info-value" id="renovacao-limite">—</div></div>
                </div>
                <hr class="modal-divider">
                <p class="modal-success-title">✨ Renovação realizada com sucesso! ✨</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-copy" onclick="copiarRenovacao()"><i class='bx bx-copy'></i> Copiar Informações</button>
                <button class="btn-modal btn-modal-ok" onclick="fecharModalSucessoRenovacao()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ERRO -->
<div id="modalErro" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div style="text-align:center; margin-bottom:20px;"><i class='bx bx-error-circle' style="font-size:70px; color:#dc2626;"></i></div>
                <h3 style="color:white; text-align:center; margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="erro-mensagem">Erro ao processar solicitação!</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal" style="background:linear-gradient(135deg,#dc2626,#b91c1c);" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// ==================== FUNÇÕES DE UTILIDADE ====================
function getCard(id) {
    return document.querySelector(`.revendedor-card[data-id="${id}"]`);
}

function mostrarToast(msg, erro = false) {
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    if (erro) toast.style.background = 'linear-gradient(135deg, #dc2626, #b91c1c)';
    toast.innerHTML = `<i class="bx ${erro ? 'bx-error-circle' : 'bx-check-circle'}" style="font-size:20px;"></i> ${msg}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function abrirModal(id) {
    const modal = document.getElementById(id);
    modal.style.display = 'flex';
    modal.classList.add('show');
}

function fecharModal(id) {
    const modal = document.getElementById(id);
    modal.style.display = 'none';
    modal.classList.remove('show');
}

function fecharTodosModais() {
    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.style.display = 'none';
        m.classList.remove('show');
    });
}

function mostrarProcessando() {
    const modal = document.getElementById('modalProcessando');
    modal.style.display = 'flex';
    modal.classList.add('show');
}

function esconderProcessando() {
    const modal = document.getElementById('modalProcessando');
    modal.style.display = 'none';
    modal.classList.remove('show');
}

function mostrarErro(mensagem) {
    document.getElementById('erro-mensagem').textContent = mensagem;
    abrirModal('modalErro');
}

function mostrarSucesso(titulo, mensagem) {
    document.getElementById('sucesso-titulo').textContent = titulo;
    document.getElementById('sucesso-mensagem').textContent = mensagem;
    abrirModal('modalSucessoOperacao');
}

function fecharModalSucessoOperacao() {
    fecharModal('modalSucessoOperacao');
    location.reload();
}

function fecharModalSucessoRenovacao() {
    fecharModal('modalSucessoRenovacao');
    location.reload();
}

function copiarInfoCard(id, event) {
    event.stopPropagation();
    const card = getCard(id);
    if (!card) return;
    
    const nome = card.getAttribute('data-nome');
    const senha = card.getAttribute('data-senha');
    const tipo = card.getAttribute('data-tipo');
    const limite = card.getAttribute('data-limite');
    const expira = card.getAttribute('data-expira');
    
    let texto = "📋 INFORMAÇÕES DO REVENDEDOR\n━━━━━━━━━━━━━━━━━━━━━\n";
    texto += "👤 Login: " + nome + "\n";
    texto += "🔑 Senha: " + senha + "\n";
    texto += "📦 Modo: " + tipo + "\n";
    texto += "🔗 Limite: " + limite + " conexões\n";
    if (tipo != 'Credito') {
        texto += "📅 Expira em: " + expira + "\n";
    }
    texto += "━━━━━━━━━━━━━━━━━━━━━\n";
    texto += "📆 Data: " + new Date().toLocaleString('pt-BR');
    
    navigator.clipboard.writeText(texto).then(function() {
        const btn = event.currentTarget;
        const originalText = btn.innerHTML;
        btn.classList.add('copied');
        btn.innerHTML = '<i class="bx bx-check"></i> <span class="copy-text">Copiado!</span>';
        mostrarToast('✅ Informações copiadas com sucesso!');
        setTimeout(function() {
            btn.classList.remove('copied');
            btn.innerHTML = originalText;
        }, 2000);
    });
}

// ==================== SUSPENDER (COM DELAY) ====================
function suspenderRevendedor(id) {
    const card = getCard(id);
    const login = card?.getAttribute('data-nome') || '';
    const senha = card?.getAttribute('data-senha') || '';
    const expira = card?.getAttribute('data-expira') || '';

    document.getElementById('suspender-login').textContent = login;
    document.getElementById('suspender-senha').textContent = senha;
    document.getElementById('suspender-expira').textContent = expira;

    const btnConfirmar = document.getElementById('btnConfirmarSuspensao');
    const novoBtn = btnConfirmar.cloneNode(true);
    btnConfirmar.parentNode.replaceChild(novoBtn, btnConfirmar);
    
    novoBtn.onclick = function() {
        fecharModal('modalConfirmarSuspensao');
        mostrarProcessando();
        
        const startTime = Date.now();
        
        $.ajax({
            url: 'suspenderrevenda.php?id=' + id,
            type: 'GET',
            success: function(data) {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        data = data.replace(/(\r\n|\n|\r)/gm, "");
                        if (data.includes('sucesso') || data.includes('Sucesso')) {
                            mostrarSucesso('⚠️ Revendedor Suspenso!', `Revendedor ${login} foi suspenso com sucesso!`);
                        } else {
                            mostrarErro('Erro ao suspender revendedor!');
                        }
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    if (data.includes('sucesso') || data.includes('Sucesso')) {
                        mostrarSucesso('⚠️ Revendedor Suspenso!', `Revendedor ${login} foi suspenso com sucesso!`);
                    } else {
                        mostrarErro('Erro ao suspender revendedor!');
                    }
                }
            },
            error: function() {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        mostrarErro('Erro ao conectar com o servidor!');
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            }
        });
    };
    abrirModal('modalConfirmarSuspensao');
}

// ==================== REATIVAR (COM DELAY) ====================
function reativarRevendedor(id) {
    const card = getCard(id);
    const login = card?.getAttribute('data-nome') || '';
    const senha = card?.getAttribute('data-senha') || '';
    const expira = card?.getAttribute('data-expira') || '';

    document.getElementById('reativar-login').textContent = login;
    document.getElementById('reativar-senha').textContent = senha;
    document.getElementById('reativar-expira').textContent = expira;

    const btnConfirmar = document.getElementById('btnConfirmarReativacao');
    const novoBtn = btnConfirmar.cloneNode(true);
    btnConfirmar.parentNode.replaceChild(novoBtn, btnConfirmar);
    
    novoBtn.onclick = function() {
        fecharModal('modalConfirmarReativacao');
        mostrarProcessando();
        
        const startTime = Date.now();
        
        $.ajax({
            url: 'reativarrevenda.php?id=' + id,
            type: 'GET',
            success: function(data) {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        data = data.replace(/(\r\n|\n|\r)/gm, "");
                        if (data.includes('sucesso') || data.includes('Sucesso')) {
                            mostrarSucesso('✅ Revendedor Reativado!', `Revendedor ${login} foi reativado com sucesso!`);
                        } else {
                            mostrarErro('Erro ao reativar revendedor!');
                        }
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    if (data.includes('sucesso') || data.includes('Sucesso')) {
                        mostrarSucesso('✅ Revendedor Reativado!', `Revendedor ${login} foi reativado com sucesso!`);
                    } else {
                        mostrarErro('Erro ao reativar revendedor!');
                    }
                }
            },
            error: function() {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        mostrarErro('Erro ao conectar com o servidor!');
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            }
        });
    };
    abrirModal('modalConfirmarReativacao');
}

// ==================== EXCLUIR (COM DELAY) ====================
function excluirRevendedor(id) {
    const card = getCard(id);
    const login = card?.getAttribute('data-nome') || '';
    const senha = card?.getAttribute('data-senha') || '';
    const expira = card?.getAttribute('data-expira') || '';

    document.getElementById('excluir-login').textContent = login;
    document.getElementById('excluir-senha').textContent = senha;
    document.getElementById('excluir-expira').textContent = expira;

    const btnConfirmar = document.getElementById('btnConfirmarExclusao');
    const novoBtn = btnConfirmar.cloneNode(true);
    btnConfirmar.parentNode.replaceChild(novoBtn, btnConfirmar);
    
    novoBtn.onclick = function() {
        fecharModal('modalConfirmarExclusao');
        mostrarProcessando();
        
        const startTime = Date.now();
        
        $.ajax({
            url: 'excluirrevenda.php?id=' + id,
            type: 'GET',
            success: function(data) {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        data = data.replace(/(\r\n|\n|\r)/gm, "");
                        if (data.includes('excluido') || data.includes('sucesso')) {
                            mostrarSucesso('🗑️ Revendedor Excluído!', `Revendedor ${login} foi excluído permanentemente!`);
                        } else {
                            mostrarErro('Erro ao excluir revendedor!');
                        }
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    if (data.includes('excluido') || data.includes('sucesso')) {
                        mostrarSucesso('🗑️ Revendedor Excluído!', `Revendedor ${login} foi excluído permanentemente!`);
                    } else {
                        mostrarErro('Erro ao excluir revendedor!');
                    }
                }
            },
            error: function() {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        mostrarErro('Erro ao conectar com o servidor!');
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            }
        });
    };
    abrirModal('modalConfirmarExclusao');
}

// ==================== RENOVAR (COM DELAY) ====================
function renovarRevendedor(id) {
    const card = getCard(id);
    const login = card?.getAttribute('data-nome') || '';
    const expira = card?.getAttribute('data-expira') || '';
    const senha = card?.getAttribute('data-senha') || '';
    const limite = card?.getAttribute('data-limite') || '';

    document.getElementById('renovar-login').textContent = login;
    document.getElementById('renovar-expira').textContent = expira;

    const btnConfirmar = document.getElementById('btnConfirmarRenovacao');
    const novoBtn = btnConfirmar.cloneNode(true);
    btnConfirmar.parentNode.replaceChild(novoBtn, btnConfirmar);
    
    novoBtn.onclick = function() {
        fecharModal('modalConfirmarRenovacao');
        mostrarProcessando();
        
        const startTime = Date.now();
        
        $.ajax({
            url: 'renovarrevenda.php?id=' + id,
            type: 'GET',
            success: function(data) {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        data = data.replace(/(\r\n|\n|\r)/gm, "");
                        if (data.includes('sucesso') || data.includes('Sucesso')) {
                            let partes = expira.split('/');
                            let dataAtual = new Date(partes[2], partes[1]-1, partes[0]);
                            dataAtual.setDate(dataAtual.getDate() + 30);
                            let novaData = String(dataAtual.getDate()).padStart(2,'0') + '/' + String(dataAtual.getMonth()+1).padStart(2,'0') + '/' + dataAtual.getFullYear();
                            
                            document.getElementById('renovacao-login').textContent = login;
                            document.getElementById('renovacao-senha').textContent = senha;
                            document.getElementById('renovacao-validade-ant').textContent = expira;
                            document.getElementById('renovacao-validade-nova').textContent = novaData;
                            document.getElementById('renovacao-limite').textContent = limite + ' conexões';
                            abrirModal('modalSucessoRenovacao');
                        } else {
                            mostrarErro('Erro ao renovar revendedor!');
                        }
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    if (data.includes('sucesso') || data.includes('Sucesso')) {
                        let partes = expira.split('/');
                        let dataAtual = new Date(partes[2], partes[1]-1, partes[0]);
                        dataAtual.setDate(dataAtual.getDate() + 30);
                        let novaData = String(dataAtual.getDate()).padStart(2,'0') + '/' + String(dataAtual.getMonth()+1).padStart(2,'0') + '/' + dataAtual.getFullYear();
                        
                        document.getElementById('renovacao-login').textContent = login;
                        document.getElementById('renovacao-senha').textContent = senha;
                        document.getElementById('renovacao-validade-ant').textContent = expira;
                        document.getElementById('renovacao-validade-nova').textContent = novaData;
                        document.getElementById('renovacao-limite').textContent = limite + ' conexões';
                        abrirModal('modalSucessoRenovacao');
                    } else {
                        mostrarErro('Erro ao renovar revendedor!');
                    }
                }
            },
            error: function() {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        mostrarErro('Erro ao conectar com o servidor!');
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            }
        });
    };
    abrirModal('modalConfirmarRenovacao');
}

function copiarRenovacao() {
    const login = document.getElementById('renovacao-login').textContent;
    const senha = document.getElementById('renovacao-senha').textContent;
    const validadeAnt = document.getElementById('renovacao-validade-ant').textContent;
    const validadeNova = document.getElementById('renovacao-validade-nova').textContent;
    const limite = document.getElementById('renovacao-limite').textContent;
    
    let texto = "✅ REVENDEDOR RENOVADO COM SUCESSO!\n";
    texto += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    texto += "👤 Login: " + login + "\n";
    texto += "🔑 Senha: " + senha + "\n";
    texto += "📅 Validade Anterior: " + validadeAnt + "\n";
    texto += "✅ Nova Validade: " + validadeNova + "\n";
    texto += "🔗 Limite: " + limite + "\n";
    texto += "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    texto += "📆 Data: " + new Date().toLocaleString('pt-BR');
    
    navigator.clipboard.writeText(texto).then(function() {
        mostrarToast('Informações copiadas com sucesso!');
    });
}

// ==================== EDIÇÃO E VISUALIZAÇÃO ====================
function editarRevendedor(id) {
    window.location.href = 'editarrev.php?id=' + id;
}

function visualizarRevendedor(id) {
    window.location.href = 'detalhesrev.php?id=' + id;
}

// ==================== FILTROS ====================
function filtrarLive(valor) {
    let s = valor.toLowerCase();
    document.querySelectorAll('.revendedor-card').forEach(c => {
        c.style.display = c.getAttribute('data-login').includes(s) ? 'block' : 'none';
    });
}

function filtrarStatus(status) {
    document.querySelectorAll('.revendedor-card').forEach(c => {
        c.style.display = (status === 'todos' || c.dataset.status === status) ? 'block' : 'none';
    });
}

// Fechar modais ao clicar fora
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
        e.target.classList.remove('show');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharTodosModais();
    }
});
</script>
</body>
</html>
h2_tema ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <div class="info-badge"><i class='bx bx-group'></i><span>Gerenciar Revendedores</span></div>

    <div class="status-info">
        <div class="status-item"><i class='bx bx-info-circle'></i><span>Total revendedores: <?php echo $total_rev; ?></span></div>
        <div class="status-item"><i class='bx bx-time'></i><span><?php echo date('d/m/Y H:i'); ?></span></div>
    </div>

    <div class="filters-card">
        <div class="filters-title"><i class='bx bx-filter-alt'></i> Filtros</div>
        <div class="filter-group">
            <div class="filter-item">
                <div class="filter-label">BUSCAR POR LOGIN</div>
                <div class="search-field">
                    <input type="text" class="filter-input" id="searchInput"
                           placeholder="Digite para buscar..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           onkeyup="filtrarLive(this.value)">
                </div>
            </div>
            <div class="filter-item">
                <div class="filter-label">FILTRAR POR STATUS</div>
                <select class="filter-select" id="statusFilter" onchange="filtrarStatus(this.value)">
                    <option value="todos">Todos</option>
                    <option value="ativo">Ativo</option>
                    <option value="suspenso">Suspenso</option>
                </select>
            </div>
        </div>
    </div>

    <div class="revendedores-grid" id="revendedoresGrid">
    <?php
    if ($result->num_rows > 0) {
        while ($user_data = mysqli_fetch_assoc($result)) {
            $sql2       = "SELECT * FROM atribuidos WHERE userid = '".$user_data['id']."'";
            $result2    = $conn->query($sql2);
            $user_data2 = mysqli_fetch_assoc($result2);
            $expira_raw = $user_data2['expira'];
            $expira_fmt = date('d/m/Y', strtotime($expira_raw));
            $diff       = strtotime($expira_raw) - time();
            $dias       = floor($diff / 86400);

            if ($user_data2['tipo'] == 'Credito') {
                $expira_badge = '<span class="badge-custom badge-credit"><i class="bx bx-credit-card"></i> Crédito</span>';
            } elseif ($dias < 0) {
                $expira_badge = '<span class="badge-custom badge-danger"><i class="bx bx-calendar-x"></i> Expirado</span>';
            } elseif ($dias <= 5) {
                $expira_badge = '<span class="badge-custom badge-warning"><i class="bx bx-calendar-exclamation"></i> '.$dias.' dias</span>';
            } else {
                $expira_badge = '<span class="badge-custom badge-success"><i class="bx bx-calendar-check"></i> '.$dias.' dias</span>';
            }

            $status_val  = $user_data2['suspenso'] == '0' ? 'ativo' : 'suspenso';
            $status_badge = $user_data2['suspenso'] == '0'
                ? '<span class="badge-custom badge-success"><i class="bx bx-check-circle"></i> Ativo</span>'
                : '<span class="badge-custom badge-danger"><i class="bx bx-lock"></i> Suspenso</span>';
    ?>
        <div class="revendedor-card"
             data-login="<?php echo strtolower($user_data['login']); ?>"
             data-status="<?php echo $status_val; ?>"
             data-id="<?php echo $user_data['id']; ?>"
             data-suspenso="<?php echo $user_data2['suspenso']; ?>"
             data-nome="<?php echo htmlspecialchars($user_data['login']); ?>"
             data-senha="<?php echo htmlspecialchars($user_data['senha']); ?>"
             data-tipo="<?php echo htmlspecialchars($user_data2['tipo']); ?>"
             data-limite="<?php echo htmlspecialchars($user_data2['limite']); ?>"
             data-expira="<?php echo $expira_fmt; ?>"
             data-dias="<?php echo max(0, $dias); ?>">

            <div class="card-header-custom">
                <div class="header-info">
                    <div class="header-icon"><i class='bx bx-user'></i></div>
                    <div class="header-text">
                        <div class="header-title"><?php echo htmlspecialchars($user_data['login']); ?></div>
                        <div class="header-subtitle">ID: <?php echo $user_data['id']; ?></div>
                    </div>
                </div>
                <button class="btn-copy-card" onclick="copiarInfoCard(<?php echo $user_data['id']; ?>, event)">
                    <i class='bx bx-copy'></i>
                    <span class="copy-text">Copiar</span>
                </button>
            </div>

            <div class="card-body-custom">
                <div class="status-row">
                    <div class="info-row-status">
                        <div class="info-icon-status"><i class='bx bx-info-circle icon-group'></i></div>
                        <div class="info-content-status">
                            <div class="info-label-status">STATUS</div>
                            <div class="info-value-status"><?php echo $status_badge; ?></div>
                        </div>
                    </div>
                    <div class="info-row-status">
                        <div class="info-icon-status"><i class='bx bx-calendar icon-calendar'></i></div>
                        <div class="info-content-status">
                            <div class="info-label-status">VALIDADE</div>
                            <div class="info-value-status"><?php echo $expira_badge; ?></div>
                        </div>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="info-row"><div class="info-icon"><i class='bx bx-user icon-user'></i></div><div class="info-content"><div class="info-label">LOGIN</div><div class="info-value"><?php echo htmlspecialchars($user_data['login']); ?></div></div></div>
                    <div class="info-row"><div class="info-icon"><i class='bx bx-lock-alt icon-lock'></i></div><div class="info-content"><div class="info-label">SENHA</div><div class="info-value"><?php echo htmlspecialchars($user_data['senha']); ?></div></div></div>
                </div>

                <div class="grid-2">
                    <div class="info-row"><div class="info-icon"><i class='bx bx-category icon-user'></i></div><div class="info-content"><div class="info-label">MODO</div><div class="info-value"><?php echo $user_data2['tipo']; ?></div></div></div>
                    <div class="info-row"><div class="info-icon"><i class='bx bx-group icon-group'></i></div><div class="info-content"><div class="info-label">LIMITE</div><div class="info-value"><?php echo $user_data2['limite']; ?></div></div></div>
                </div>

                <?php if ($user_data2['tipo'] != 'Credito'): ?>
                <div class="info-row"><div class="info-icon"><i class='bx bx-calendar-check icon-calendar'></i></div><div class="info-content"><div class="info-label">EXPIRA EM</div><div class="info-value"><?php echo $expira_fmt; ?></div></div></div>
                <?php endif; ?>

                <div class="action-buttons">
                    <button class="action-btn btn-edit" onclick="editarRevendedor(<?php echo $user_data['id']; ?>)"><i class='bx bx-edit'></i> Editar</button>
                    <button class="action-btn btn-view" onclick="visualizarRevendedor(<?php echo $user_data['id']; ?>)"><i class='bx bx-show'></i> Ver</button>
                    <?php if ($user_data2['suspenso'] == '0' && $user_data2['tipo'] != 'Credito'): ?>
                    <button class="action-btn btn-warning" onclick="suspenderRevendedor(<?php echo $user_data['id']; ?>)"><i class='bx bx-pause'></i> Suspender</button>
                    <?php elseif ($user_data2['suspenso'] == '1'): ?>
                    <button class="action-btn btn-success" onclick="reativarRevendedor(<?php echo $user_data['id']; ?>)"><i class='bx bx-refresh'></i> Reativar</button>
                    <?php endif; ?>
                    <?php if ($user_data2['tipo'] != 'Credito'): ?>
                    <button class="action-btn btn-renew" onclick="renovarRevendedor(<?php echo $user_data['id']; ?>)"><i class='bx bx-calendar-plus'></i> Renovar</button>
                    <?php endif; ?>
                    <button class="action-btn btn-danger" onclick="excluirRevendedor(<?php echo $user_data['id']; ?>)"><i class='bx bx-trash'></i> Excluir</button>
                </div>
            </div>
        </div>
    <?php
        }
    } else {
        echo '<div class="empty-state"><i class="bx bx-group"></i><h3>Nenhum revendedor encontrado</h3><p>' . (!empty($search) ? 'Nenhum resultado para "'.htmlspecialchars($search).'"' : 'Você ainda não possui revendedores cadastrados') . '</p></div>';
    }
    ?>
    </div>

    <div class="pagination-info">
        Exibindo <?php echo $result->num_rows; ?> de <?php echo $total_rev; ?> revendedor(es)
        <?php if (!empty($search)): ?>
        — busca: <strong style="color:#818cf8;"><?php echo htmlspecialchars($search); ?></strong>
        <a href="?" style="color:#f87171;font-size:11px;margin-left:6px;"><i class='bx bx-x'></i> limpar</a>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- =============================================
     MODAL PROCESSANDO
     ============================================= -->
<div id="modalProcessando" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom processing">
                <h5><i class='bx bx-loader-alt bx-spin'></i> Processando</h5>
            </div>
            <div class="modal-body-custom">
                <div class="processing-spinner">
                    <div class="spinner-ring"></div>
                    <p class="spinner-text">Aguarde enquanto processamos sua solicitação...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAÇÃO SUSPENSÃO -->
<div id="modalConfirmarSuspensao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom warning">
                <h5><i class='bx bx-pause-circle'></i> Confirmar Suspensão</h5>
                <button class="modal-close" onclick="fecharModal('modalConfirmarSuspensao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-warning-icon"><i class='bx bx-pause-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user'></i> Revendedor</div><div class="modal-info-value credential" id="suspender-login">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha</div><div class="modal-info-value credential" id="suspender-senha">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar'></i> Validade</div><div class="modal-info-value" id="suspender-expira">—</div></div>
                </div>
                <p style="text-align:center; color:rgba(255,255,255,0.5); font-size:12px;">Após suspenso, o revendedor não poderá mais acessar até ser reativado.</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarSuspensao')"><i class='bx bx-x'></i> Cancelar</button>
                <button class="btn-modal btn-modal-warning" id="btnConfirmarSuspensao"><i class='bx bx-pause-circle'></i> Suspender Agora</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAÇÃO REATIVAÇÃO -->
<div id="modalConfirmarReativacao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5><i class='bx bx-refresh'></i> Confirmar Reativação</h5>
                <button class="modal-close" onclick="fecharModal('modalConfirmarReativacao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-success-icon"><i class='bx bx-refresh'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user'></i> Revendedor</div><div class="modal-info-value credential" id="reativar-login">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha</div><div class="modal-info-value credential" id="reativar-senha">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar'></i> Validade</div><div class="modal-info-value" id="reativar-expira">—</div></div>
                </div>
                <p style="text-align:center; color:rgba(255,255,255,0.5); font-size:12px;">O revendedor será reativado e poderá acessar normalmente.</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarReativacao')"><i class='bx bx-x'></i> Cancelar</button>
                <button class="btn-modal btn-modal-ok" id="btnConfirmarReativacao"><i class='bx bx-refresh'></i> Reativar Agora</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAÇÃO EXCLUSÃO -->
<div id="modalConfirmarExclusao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5><i class='bx bx-trash'></i> Confirmar Exclusão</h5>
                <button class="modal-close" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-danger-icon"><i class='bx bx-trash'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user'></i> Revendedor</div><div class="modal-info-value credential" id="excluir-login">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha</div><div class="modal-info-value credential" id="excluir-senha">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar'></i> Validade</div><div class="modal-info-value" id="excluir-expira">—</div></div>
                </div>
                <p style="text-align:center; color:rgba(220,38,38,0.8); font-size:12px;">⚠️ Esta ação não pode ser desfeita! O revendedor será permanentemente removido.</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i> Cancelar</button>
                <button class="btn-modal btn-modal-danger" id="btnConfirmarExclusao"><i class='bx bx-trash'></i> Excluir Permanentemente</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAÇÃO RENOVAÇÃO -->
<div id="modalConfirmarRenovacao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom warning">
                <h5><i class='bx bx-calendar-plus'></i> Confirmar Renovação</h5>
                <button class="modal-close" onclick="fecharModal('modalConfirmarRenovacao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-warning-icon"><i class='bx bx-calendar-plus'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user'></i> Revendedor</div><div class="modal-info-value credential" id="renovar-login">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar'></i> Validade Atual</div><div class="modal-info-value" id="renovar-expira">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-plus-circle'></i> Dias a Adicionar</div><div class="modal-info-value highlight-green">+30 dias</div></div>
                </div>
                <p style="text-align:center; color:rgba(255,255,255,0.5); font-size:12px;">A validade será extendida a partir da data de expiração atual.</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarRenovacao')"><i class='bx bx-x'></i> Cancelar</button>
                <button class="btn-modal btn-modal-warning" id="btnConfirmarRenovacao"><i class='bx bx-calendar-plus'></i> Renovar Agora</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUCESSO OPERAÇÃO -->
<div id="modalSucessoOperacao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5><i class='bx bx-check-circle'></i> Operação Realizada!</h5>
                <button class="modal-close" onclick="fecharModalSucessoOperacao()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-success-icon"><i class='bx bx-check-circle'></i></div>
                <h3 style="color:white; text-align:center; margin-bottom:10px;" id="sucesso-titulo">Sucesso!</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="sucesso-mensagem">Operação realizada com sucesso!</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-ok" onclick="fecharModalSucessoOperacao()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUCESSO RENOVAÇÃO -->
<div id="modalSucessoRenovacao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5><i class='bx bx-check-circle'></i> Revendedor Renovado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModalSucessoRenovacao()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-success-icon"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user'></i> Login</div><div class="modal-info-value credential" id="renovacao-login">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha</div><div class="modal-info-value credential" id="renovacao-senha">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar-x'></i> Validade Anterior</div><div class="modal-info-value" id="renovacao-validade-ant">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar-check'></i> Nova Validade</div><div class="modal-info-value highlight-green" id="renovacao-validade-nova">—</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-layer'></i> Limite</div><div class="modal-info-value" id="renovacao-limite">—</div></div>
                </div>
                <hr class="modal-divider">
                <p class="modal-success-title">✨ Renovação realizada com sucesso! ✨</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-copy" onclick="copiarRenovacao()"><i class='bx bx-copy'></i> Copiar Informações</button>
                <button class="btn-modal btn-modal-ok" onclick="fecharModalSucessoRenovacao()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ERRO -->
<div id="modalErro" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div style="text-align:center; margin-bottom:20px;"><i class='bx bx-error-circle' style="font-size:70px; color:#dc2626;"></i></div>
                <h3 style="color:white; text-align:center; margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="erro-mensagem">Erro ao processar solicitação!</p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal" style="background:linear-gradient(135deg,#dc2626,#b91c1c);" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// ==================== FUNÇÕES DE UTILIDADE ====================
function getCard(id) {
    return document.querySelector(`.revendedor-card[data-id="${id}"]`);
}

function mostrarToast(msg, erro = false) {
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    if (erro) toast.style.background = 'linear-gradient(135deg, #dc2626, #b91c1c)';
    toast.innerHTML = `<i class="bx ${erro ? 'bx-error-circle' : 'bx-check-circle'}" style="font-size:20px;"></i> ${msg}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function abrirModal(id) {
    const modal = document.getElementById(id);
    modal.style.display = 'flex';
    modal.classList.add('show');
}

function fecharModal(id) {
    const modal = document.getElementById(id);
    modal.style.display = 'none';
    modal.classList.remove('show');
}

function fecharTodosModais() {
    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.style.display = 'none';
        m.classList.remove('show');
    });
}

function mostrarProcessando() {
    const modal = document.getElementById('modalProcessando');
    modal.style.display = 'flex';
    modal.classList.add('show');
}

function esconderProcessando() {
    const modal = document.getElementById('modalProcessando');
    modal.style.display = 'none';
    modal.classList.remove('show');
}

function mostrarErro(mensagem) {
    document.getElementById('erro-mensagem').textContent = mensagem;
    abrirModal('modalErro');
}

function mostrarSucesso(titulo, mensagem) {
    document.getElementById('sucesso-titulo').textContent = titulo;
    document.getElementById('sucesso-mensagem').textContent = mensagem;
    abrirModal('modalSucessoOperacao');
}

function fecharModalSucessoOperacao() {
    fecharModal('modalSucessoOperacao');
    location.reload();
}

function fecharModalSucessoRenovacao() {
    fecharModal('modalSucessoRenovacao');
    location.reload();
}

function copiarInfoCard(id, event) {
    event.stopPropagation();
    const card = getCard(id);
    if (!card) return;
    
    const nome = card.getAttribute('data-nome');
    const senha = card.getAttribute('data-senha');
    const tipo = card.getAttribute('data-tipo');
    const limite = card.getAttribute('data-limite');
    const expira = card.getAttribute('data-expira');
    
    let texto = "📋 INFORMAÇÕES DO REVENDEDOR\n━━━━━━━━━━━━━━━━━━━━━\n";
    texto += "👤 Login: " + nome + "\n";
    texto += "🔑 Senha: " + senha + "\n";
    texto += "📦 Modo: " + tipo + "\n";
    texto += "🔗 Limite: " + limite + " conexões\n";
    if (tipo != 'Credito') {
        texto += "📅 Expira em: " + expira + "\n";
    }
    texto += "━━━━━━━━━━━━━━━━━━━━━\n";
    texto += "📆 Data: " + new Date().toLocaleString('pt-BR');
    
    navigator.clipboard.writeText(texto).then(function() {
        const btn = event.currentTarget;
        const originalText = btn.innerHTML;
        btn.classList.add('copied');
        btn.innerHTML = '<i class="bx bx-check"></i> <span class="copy-text">Copiado!</span>';
        mostrarToast('✅ Informações copiadas com sucesso!');
        setTimeout(function() {
            btn.classList.remove('copied');
            btn.innerHTML = originalText;
        }, 2000);
    });
}

// ==================== SUSPENDER (COM DELAY) ====================
function suspenderRevendedor(id) {
    const card = getCard(id);
    const login = card?.getAttribute('data-nome') || '';
    const senha = card?.getAttribute('data-senha') || '';
    const expira = card?.getAttribute('data-expira') || '';

    document.getElementById('suspender-login').textContent = login;
    document.getElementById('suspender-senha').textContent = senha;
    document.getElementById('suspender-expira').textContent = expira;

    const btnConfirmar = document.getElementById('btnConfirmarSuspensao');
    const novoBtn = btnConfirmar.cloneNode(true);
    btnConfirmar.parentNode.replaceChild(novoBtn, btnConfirmar);
    
    novoBtn.onclick = function() {
        fecharModal('modalConfirmarSuspensao');
        mostrarProcessando();
        
        const startTime = Date.now();
        
        $.ajax({
            url: 'suspenderrevenda.php?id=' + id,
            type: 'GET',
            success: function(data) {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        data = data.replace(/(\r\n|\n|\r)/gm, "");
                        if (data.includes('sucesso') || data.includes('Sucesso')) {
                            mostrarSucesso('⚠️ Revendedor Suspenso!', `Revendedor ${login} foi suspenso com sucesso!`);
                        } else {
                            mostrarErro('Erro ao suspender revendedor!');
                        }
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    if (data.includes('sucesso') || data.includes('Sucesso')) {
                        mostrarSucesso('⚠️ Revendedor Suspenso!', `Revendedor ${login} foi suspenso com sucesso!`);
                    } else {
                        mostrarErro('Erro ao suspender revendedor!');
                    }
                }
            },
            error: function() {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        mostrarErro('Erro ao conectar com o servidor!');
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            }
        });
    };
    abrirModal('modalConfirmarSuspensao');
}

// ==================== REATIVAR (COM DELAY) ====================
function reativarRevendedor(id) {
    const card = getCard(id);
    const login = card?.getAttribute('data-nome') || '';
    const senha = card?.getAttribute('data-senha') || '';
    const expira = card?.getAttribute('data-expira') || '';

    document.getElementById('reativar-login').textContent = login;
    document.getElementById('reativar-senha').textContent = senha;
    document.getElementById('reativar-expira').textContent = expira;

    const btnConfirmar = document.getElementById('btnConfirmarReativacao');
    const novoBtn = btnConfirmar.cloneNode(true);
    btnConfirmar.parentNode.replaceChild(novoBtn, btnConfirmar);
    
    novoBtn.onclick = function() {
        fecharModal('modalConfirmarReativacao');
        mostrarProcessando();
        
        const startTime = Date.now();
        
        $.ajax({
            url: 'reativarrevenda.php?id=' + id,
            type: 'GET',
            success: function(data) {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        data = data.replace(/(\r\n|\n|\r)/gm, "");
                        if (data.includes('sucesso') || data.includes('Sucesso')) {
                            mostrarSucesso('✅ Revendedor Reativado!', `Revendedor ${login} foi reativado com sucesso!`);
                        } else {
                            mostrarErro('Erro ao reativar revendedor!');
                        }
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    if (data.includes('sucesso') || data.includes('Sucesso')) {
                        mostrarSucesso('✅ Revendedor Reativado!', `Revendedor ${login} foi reativado com sucesso!`);
                    } else {
                        mostrarErro('Erro ao reativar revendedor!');
                    }
                }
            },
            error: function() {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        mostrarErro('Erro ao conectar com o servidor!');
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            }
        });
    };
    abrirModal('modalConfirmarReativacao');
}

// ==================== EXCLUIR (COM DELAY) ====================
function excluirRevendedor(id) {
    const card = getCard(id);
    const login = card?.getAttribute('data-nome') || '';
    const senha = card?.getAttribute('data-senha') || '';
    const expira = card?.getAttribute('data-expira') || '';

    document.getElementById('excluir-login').textContent = login;
    document.getElementById('excluir-senha').textContent = senha;
    document.getElementById('excluir-expira').textContent = expira;

    const btnConfirmar = document.getElementById('btnConfirmarExclusao');
    const novoBtn = btnConfirmar.cloneNode(true);
    btnConfirmar.parentNode.replaceChild(novoBtn, btnConfirmar);
    
    novoBtn.onclick = function() {
        fecharModal('modalConfirmarExclusao');
        mostrarProcessando();
        
        const startTime = Date.now();
        
        $.ajax({
            url: 'excluirrevenda.php?id=' + id,
            type: 'GET',
            success: function(data) {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        data = data.replace(/(\r\n|\n|\r)/gm, "");
                        if (data.includes('excluido') || data.includes('sucesso')) {
                            mostrarSucesso('🗑️ Revendedor Excluído!', `Revendedor ${login} foi excluído permanentemente!`);
                        } else {
                            mostrarErro('Erro ao excluir revendedor!');
                        }
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    if (data.includes('excluido') || data.includes('sucesso')) {
                        mostrarSucesso('🗑️ Revendedor Excluído!', `Revendedor ${login} foi excluído permanentemente!`);
                    } else {
                        mostrarErro('Erro ao excluir revendedor!');
                    }
                }
            },
            error: function() {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        mostrarErro('Erro ao conectar com o servidor!');
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            }
        });
    };
    abrirModal('modalConfirmarExclusao');
}

// ==================== RENOVAR (COM DELAY) ====================
function renovarRevendedor(id) {
    const card = getCard(id);
    const login = card?.getAttribute('data-nome') || '';
    const expira = card?.getAttribute('data-expira') || '';
    const senha = card?.getAttribute('data-senha') || '';
    const limite = card?.getAttribute('data-limite') || '';

    document.getElementById('renovar-login').textContent = login;
    document.getElementById('renovar-expira').textContent = expira;

    const btnConfirmar = document.getElementById('btnConfirmarRenovacao');
    const novoBtn = btnConfirmar.cloneNode(true);
    btnConfirmar.parentNode.replaceChild(novoBtn, btnConfirmar);
    
    novoBtn.onclick = function() {
        fecharModal('modalConfirmarRenovacao');
        mostrarProcessando();
        
        const startTime = Date.now();
        
        $.ajax({
            url: 'renovarrevenda.php?id=' + id,
            type: 'GET',
            success: function(data) {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        data = data.replace(/(\r\n|\n|\r)/gm, "");
                        if (data.includes('sucesso') || data.includes('Sucesso')) {
                            let partes = expira.split('/');
                            let dataAtual = new Date(partes[2], partes[1]-1, partes[0]);
                            dataAtual.setDate(dataAtual.getDate() + 30);
                            let novaData = String(dataAtual.getDate()).padStart(2,'0') + '/' + String(dataAtual.getMonth()+1).padStart(2,'0') + '/' + dataAtual.getFullYear();
                            
                            document.getElementById('renovacao-login').textContent = login;
                            document.getElementById('renovacao-senha').textContent = senha;
                            document.getElementById('renovacao-validade-ant').textContent = expira;
                            document.getElementById('renovacao-validade-nova').textContent = novaData;
                            document.getElementById('renovacao-limite').textContent = limite + ' conexões';
                            abrirModal('modalSucessoRenovacao');
                        } else {
                            mostrarErro('Erro ao renovar revendedor!');
                        }
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    if (data.includes('sucesso') || data.includes('Sucesso')) {
                        let partes = expira.split('/');
                        let dataAtual = new Date(partes[2], partes[1]-1, partes[0]);
                        dataAtual.setDate(dataAtual.getDate() + 30);
                        let novaData = String(dataAtual.getDate()).padStart(2,'0') + '/' + String(dataAtual.getMonth()+1).padStart(2,'0') + '/' + dataAtual.getFullYear();
                        
                        document.getElementById('renovacao-login').textContent = login;
                        document.getElementById('renovacao-senha').textContent = senha;
                        document.getElementById('renovacao-validade-ant').textContent = expira;
                        document.getElementById('renovacao-validade-nova').textContent = novaData;
                        document.getElementById('renovacao-limite').textContent = limite + ' conexões';
                        abrirModal('modalSucessoRenovacao');
                    } else {
                        mostrarErro('Erro ao renovar revendedor!');
                    }
                }
            },
            error: function() {
                const elapsed = Date.now() - startTime;
                const minDelay = 800;
                
                if (elapsed < minDelay) {
                    setTimeout(function() {
                        esconderProcessando();
                        mostrarErro('Erro ao conectar com o servidor!');
                    }, minDelay - elapsed);
                } else {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            }
        });
    };
    abrirModal('modalConfirmarRenovacao');
}

function copiarRenovacao() {
    const login = document.getElementById('renovacao-login').textContent;
    const senha = document.getElementById('renovacao-senha').textContent;
    const validadeAnt = document.getElementById('renovacao-validade-ant').textContent;
    const validadeNova = document.getElementById('renovacao-validade-nova').textContent;
    const limite = document.getElementById('renovacao-limite').textContent;
    
    let texto = "✅ REVENDEDOR RENOVADO COM SUCESSO!\n";
    texto += "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    texto += "👤 Login: " + login + "\n";
    texto += "🔑 Senha: " + senha + "\n";
    texto += "📅 Validade Anterior: " + validadeAnt + "\n";
    texto += "✅ Nova Validade: " + validadeNova + "\n";
    texto += "🔗 Limite: " + limite + "\n";
    texto += "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    texto += "📆 Data: " + new Date().toLocaleString('pt-BR');
    
    navigator.clipboard.writeText(texto).then(function() {
        mostrarToast('Informações copiadas com sucesso!');
    });
}

// ==================== EDIÇÃO E VISUALIZAÇÃO ====================
function editarRevendedor(id) {
    window.location.href = 'editarrev.php?id=' + id;
}

function visualizarRevendedor(id) {
    window.location.href = 'detalhesrev.php?id=' + id;
}

// ==================== FILTROS ====================
function filtrarLive(valor) {
    let s = valor.toLowerCase();
    document.querySelectorAll('.revendedor-card').forEach(c => {
        c.style.display = c.getAttribute('data-login').includes(s) ? 'block' : 'none';
    });
}

function filtrarStatus(status) {
    document.querySelectorAll('.revendedor-card').forEach(c => {
        c.style.display = (status === 'todos' || c.dataset.status === status) ? 'block' : 'none';
    });
}

// Fechar modais ao clicar fora
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
        e.target.classList.remove('show');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharTodosModais();
    }
});
</script>
</body>
</html>


