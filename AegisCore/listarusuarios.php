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
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Verificação de validade da conta do revendedor
$sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$sql5 = $conn->query($sql5);
$row5 = $sql5->fetch_assoc();
$validade = $row5['expira'];
$tipo = $row5['tipo'];
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

// Anti SQL na busca
$_GET['search'] = anti_sql($_GET['search'] ?? '');
if (!empty($_GET['search'])) {
    $search = $_GET['search'];
    $sql = "SELECT * FROM ssh_accounts WHERE login LIKE '%$search%' AND byid = '$_SESSION[iduser]' ORDER BY expira ASC";
    $result = $conn->query($sql);
} else {
    $sql = "SELECT * FROM ssh_accounts WHERE byid = '$_SESSION[iduser]' ORDER BY expira ASC";
    $result = $conn->query($sql);
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Usuários</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
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
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
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
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .filters-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 14px !important;
            padding: 14px !important;
            margin-bottom: 16px !important;
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
            background: radial-gradient(circle at 20% 30%, rgba(200,80,192,0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .filters-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 1;
        }

        .filters-title i {
            color: var(--tertiary);
            font-size: 16px;
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
            position: relative;
            z-index: 1;
        }

        .filter-item {
            flex: 1 1 200px;
            min-width: 160px;
        }

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
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            font-size: 13px;
            transition: all 0.3s;
            color: white;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .filter-input::placeholder {
            color: rgba(255,255,255,0.3);
        }

        .filter-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23C850C0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
        }

        .filter-select option {
            background: #1e293b;
            color: white;
        }

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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
            background: radial-gradient(circle at 80% 20%, rgba(65,88,208,0.1) 0%, transparent 60%);
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
            background: linear-gradient(135deg, #C850C0, #4158D0) !important;
            color: white;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
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
            margin: 0 0 4px 0;
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

        /* STATUS BADGE LADO A LADO */
        .status-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }

        .status-item-card {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
        }

        .status-item-card:hover {
            border-color: var(--primary);
            background: rgba(255,255,255,0.05);
        }

        .status-icon {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 16px;
            border: 1px solid rgba(255,255,255,0.05);
            flex-shrink: 0;
        }

        .status-content {
            flex: 1;
            min-width: 0;
        }

        .status-label {
            font-size: 9px;
            color: rgba(255,255,255,0.4);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }

        .status-value {
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .status-online    { background: rgba(16, 185, 129, 0.2); color: #10b981; border-color: rgba(16, 185, 129, 0.3); }
        .status-offline   { background: rgba(100, 116, 139, 0.2); color: #94a3b8; border-color: rgba(100, 116, 139, 0.3); }
        .status-suspended { background: rgba(220, 38, 38, 0.2); color: #dc2626; border-color: rgba(220, 38, 38, 0.3); }
        .status-limit     { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3); }

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
            border-color: var(--primary);
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

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-server   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }

        .expiry-warning { color: #fbbf24; }
        .expiry-danger  { color: #f87171; }

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

        .action-btn i {
            font-size: 12px;
        }

        .btn-edit {
            background: linear-gradient(135deg, #4158D0, #6366f1);
        }
        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(65,88,208,0.4);
        }

        .btn-renew {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .btn-renew:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(16,185,129,0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #f97316);
        }
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(245,158,11,0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(220,38,38,0.4);
        }

        .pagination-info {
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
            font-size: 13px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-bottom: 6px;
        }

        .grid-2 .info-row {
            margin-bottom: 0;
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

        .empty-state i {
            font-size: 48px;
            color: rgba(255,255,255,0.2);
            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: white;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .empty-state p {
            color: rgba(255,255,255,0.3);
            font-size: 13px;
        }

        .btn-copy-card {
            background: rgba(255,255,255,0.15);
            border: none;
            border-radius: 10px;
            padding: 6px 12px;
            color: white;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
        }

        .btn-copy-card:hover {
            background: rgba(255,255,255,0.25);
            transform: scale(1.02);
        }

        .btn-copy-card.copied {
            background: linear-gradient(135deg, #10b981, #059669);
            animation: copiedPulse 0.5s ease;
        }

        @keyframes copiedPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* =============================================
           MODAIS PERSONALIZADOS
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
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.9) translateY(-30px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
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

        .modal-close:hover { opacity: 1; }

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
            filter: drop-shadow(0 0 15px rgba(16, 185, 129, 0.5));
        }

        .modal-warning-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-warning-icon i {
            font-size: 70px;
            color: #f59e0b;
            filter: drop-shadow(0 0 15px rgba(245, 158, 11, 0.5));
        }

        .modal-danger-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-danger-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 15px rgba(220, 38, 38, 0.5));
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

        .modal-info-label i { font-size: 18px; }

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

        .modal-server-list {
            background: rgba(0,0,0,0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .modal-server-badge {
            display: inline-block;
            background: rgba(16,185,129,0.2);
            border: 1px solid rgba(16,185,129,0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .modal-server-badge.fail {
            background: rgba(220,38,38,0.2);
            border-color: rgba(220,38,38,0.3);
            color: #dc2626;
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

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .spinner-text {
            color: rgba(255,255,255,0.7);
            font-size: 14px;
            font-weight: 500;
        }

        /* Toast */
        .toast-notification {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
            font-weight: 600;
            font-size: 13px;
        }

        @keyframes slideIn {
            from { transform: translateX(110%); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }

        /* MOBILE: INFORMAÇÕES EM GRID 2 COLUNAS */
        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { margin: 0 auto !important; padding: 10px !important; }
            .users-grid { grid-template-columns: 1fr; gap: 12px; }
            .user-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .action-btn { width: 100%; }
            
            /* GRID 2 COLUNAS NO MOBILE PARA INFORMAÇÕES */
            .grid-2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 6px;
                margin-bottom: 6px;
            }
            .grid-2 .info-row {
                margin-bottom: 0;
            }
            
            /* STATUS ROW TAMBÉM EM 2 COLUNAS */
            .status-row {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            
            .modal-container { width: 95%; }
            .modal-info-row { flex-direction: column; align-items: flex-start; gap: 6px; }
            .modal-footer-custom { flex-direction: column; }
            .btn-modal { width: 100%; justify-content: center; }
            .btn-copy-card { padding: 5px 8px; font-size: 11px; }
            .user-name { font-size: 13px; }
            .user-avatar { width: 32px; height: 32px; font-size: 16px; }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
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
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Verificação de validade da conta do revendedor
$sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$sql5 = $conn->query($sql5);
$row5 = $sql5->fetch_assoc();
$validade = $row5['expira'];
$tipo = $row5['tipo'];
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

// Anti SQL na busca
$_GET['search'] = anti_sql($_GET['search'] ?? '');
if (!empty($_GET['search'])) {
    $search = $_GET['search'];
    $sql = "SELECT * FROM ssh_accounts WHERE login LIKE '%$search%' AND byid = '$_SESSION[iduser]' ORDER BY expira ASC";
    $result = $conn->query($sql);
} else {
    $sql = "SELECT * FROM ssh_accounts WHERE byid = '$_SESSION[iduser]' ORDER BY expira ASC";
    $result = $conn->query($sql);
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Usuários</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
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
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
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
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .filters-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 14px !important;
            padding: 14px !important;
            margin-bottom: 16px !important;
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
            background: radial-gradient(circle at 20% 30%, rgba(200,80,192,0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .filters-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 1;
        }

        .filters-title i {
            color: var(--tertiary);
            font-size: 16px;
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
            position: relative;
            z-index: 1;
        }

        .filter-item {
            flex: 1 1 200px;
            min-width: 160px;
        }

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
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            font-size: 13px;
            transition: all 0.3s;
            color: white;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .filter-input::placeholder {
            color: rgba(255,255,255,0.3);
        }

        .filter-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23C850C0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
        }

        .filter-select option {
            background: #1e293b;
            color: white;
        }

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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
            background: radial-gradient(circle at 80% 20%, rgba(65,88,208,0.1) 0%, transparent 60%);
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
            background: linear-gradient(135deg, #C850C0, #4158D0) !important;
            color: white;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
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
            margin: 0 0 4px 0;
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

        /* STATUS BADGE LADO A LADO */
        .status-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }

        .status-item-card {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
        }

        .status-item-card:hover {
            border-color: var(--primary);
            background: rgba(255,255,255,0.05);
        }

        .status-icon {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 16px;
            border: 1px solid rgba(255,255,255,0.05);
            flex-shrink: 0;
        }

        .status-content {
            flex: 1;
            min-width: 0;
        }

        .status-label {
            font-size: 9px;
            color: rgba(255,255,255,0.4);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }

        .status-value {
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .status-online    { background: rgba(16, 185, 129, 0.2); color: #10b981; border-color: rgba(16, 185, 129, 0.3); }
        .status-offline   { background: rgba(100, 116, 139, 0.2); color: #94a3b8; border-color: rgba(100, 116, 139, 0.3); }
        .status-suspended { background: rgba(220, 38, 38, 0.2); color: #dc2626; border-color: rgba(220, 38, 38, 0.3); }
        .status-limit     { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3); }

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
            border-color: var(--primary);
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

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-server   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }

        .expiry-warning { color: #fbbf24; }
        .expiry-danger  { color: #f87171; }

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

        .action-btn i {
            font-size: 12px;
        }

        .btn-edit {
            background: linear-gradient(135deg, #4158D0, #6366f1);
        }
        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(65,88,208,0.4);
        }

        .btn-renew {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .btn-renew:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(16,185,129,0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #f97316);
        }
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(245,158,11,0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(220,38,38,0.4);
        }

        .pagination-info {
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
            font-size: 13px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-bottom: 6px;
        }

        .grid-2 .info-row {
            margin-bottom: 0;
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

        .empty-state i {
            font-size: 48px;
            color: rgba(255,255,255,0.2);
            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: white;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .empty-state p {
            color: rgba(255,255,255,0.3);
            font-size: 13px;
        }

        .btn-copy-card {
            background: rgba(255,255,255,0.15);
            border: none;
            border-radius: 10px;
            padding: 6px 12px;
            color: white;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
        }

        .btn-copy-card:hover {
            background: rgba(255,255,255,0.25);
            transform: scale(1.02);
        }

        .btn-copy-card.copied {
            background: linear-gradient(135deg, #10b981, #059669);
            animation: copiedPulse 0.5s ease;
        }

        @keyframes copiedPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* =============================================
           MODAIS PERSONALIZADOS
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
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.9) translateY(-30px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
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

        .modal-close:hover { opacity: 1; }

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
            filter: drop-shadow(0 0 15px rgba(16, 185, 129, 0.5));
        }

        .modal-warning-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-warning-icon i {
            font-size: 70px;
            color: #f59e0b;
            filter: drop-shadow(0 0 15px rgba(245, 158, 11, 0.5));
        }

        .modal-danger-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-danger-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 15px rgba(220, 38, 38, 0.5));
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

        .modal-info-label i { font-size: 18px; }

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

        .modal-server-list {
            background: rgba(0,0,0,0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .modal-server-badge {
            display: inline-block;
            background: rgba(16,185,129,0.2);
            border: 1px solid rgba(16,185,129,0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .modal-server-badge.fail {
            background: rgba(220,38,38,0.2);
            border-color: rgba(220,38,38,0.3);
            color: #dc2626;
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

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .spinner-text {
            color: rgba(255,255,255,0.7);
            font-size: 14px;
            font-weight: 500;
        }

        /* Toast */
        .toast-notification {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
            font-weight: 600;
            font-size: 13px;
        }

        @keyframes slideIn {
            from { transform: translateX(110%); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }

        /* MOBILE: INFORMAÇÕES EM GRID 2 COLUNAS */
        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { margin: 0 auto !important; padding: 10px !important; }
            .users-grid { grid-template-columns: 1fr; gap: 12px; }
            .user-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .action-btn { width: 100%; }
            
            /* GRID 2 COLUNAS NO MOBILE PARA INFORMAÇÕES */
            .grid-2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 6px;
                margin-bottom: 6px;
            }
            .grid-2 .info-row {
                margin-bottom: 0;
            }
            
            /* STATUS ROW TAMBÉM EM 2 COLUNAS */
            .status-row {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            
            .modal-container { width: 95%; }
            .modal-info-row { flex-direction: column; align-items: flex-start; gap: 6px; }
            .modal-footer-custom { flex-direction: column; }
            .btn-modal { width: 100%; justify-content: center; }
            .btn-copy-card { padding: 5px 8px; font-size: 11px; }
            .user-name { font-size: 13px; }
            .user-avatar { width: 32px; height: 32px; font-size: 16px; }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">

            <div class="info-badge">
                <i class='bx bx-user'></i>
                <span>Gerenciar usuários SSH</span>
            </div>

            <!-- Filtros Card -->
            <div class="filters-card">
                <div class="filters-title">
                    <i class='bx bx-filter-alt'></i>
                    Filtros
                </div>
                <div class="filter-group">
                    <div class="filter-item">
                        <div class="filter-label">BUSCAR POR LOGIN</div>
                        <input type="text" class="filter-input" id="searchInput"
                               placeholder="Digite para buscar..."
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                               onkeyup="filtrarUsuarios()">
                    </div>
                    <div class="filter-item">
                        <div class="filter-label">FILTRAR POR STATUS</div>
                        <select class="filter-select" id="statusFilter" onchange="filtrarUsuarios()">
                            <option value="todos">Todos</option>
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                            <option value="suspenso">Suspenso</option>
                            <option value="expirado">Expirado</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Grid de usuários -->
            <div class="users-grid" id="usersGrid">
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $id       = $row['id'];
                        $login    = $row['login'];
                        $senha    = $row['senha'];
                        $limite   = $row['limite'];
                        $status   = $row['status'];
                        $categoria = $row['categoriaid'];
                        $suspenso = $row['mainid'];
                        $notas    = $row['lastview'];
                        $uuid     = $row['uuid'];
                        $expira   = $row['expira'];
                        $expira_formatada = date('d/m/Y', strtotime($expira));

                        $sql_online  = "SELECT quantidade FROM onlines WHERE usuario = '$login'";
                        $res_online  = $conn->query($sql_online);
                        $row_online  = $res_online->fetch_assoc();
                        $usando      = $row_online['quantidade'] ?? 0;

                        $data_validade  = strtotime($expira);
                        $data_atual     = time();
                        $diferenca      = $data_validade - $data_atual;
                        $dias_restantes = floor($diferenca / (60 * 60 * 24));
                        $horas_restantes = floor(($diferenca % (60 * 60 * 24)) / (60 * 60));

                        $status_classe = 'status-offline';
                        $status_texto  = 'Offline';
                        if ($suspenso == 'Suspenso') {
                            $status_classe = 'status-suspended';
                            $status_texto  = 'Suspenso';
                        } elseif ($suspenso == 'Limite Ultrapassado') {
                            $status_classe = 'status-limit';
                            $status_texto  = 'Limite Excedido';
                        } elseif ($status == 'Online') {
                            $status_classe = 'status-online';
                            $status_texto  = 'Online';
                        }

                        $expiry_class = '';
                        $expiry_texto = "{$dias_restantes}d {$horas_restantes}h";
                        if ($dias_restantes < 0) {
                            $expiry_class = 'expiry-danger';
                            $expiry_texto = 'Expirado';
                        } elseif ($dias_restantes <= 5) {
                            $expiry_class = 'expiry-warning';
                        }
                        
                        // Badge de status para exibir
                        $status_badge = '<span class="status-badge ' . $status_classe . '"><i class="bx bx-' . ($status_texto == 'Online' ? 'wifi' : ($status_texto == 'Suspenso' ? 'lock' : 'power-off')) . '"></i> ' . $status_texto . '</span>';
                        
                        // Badge de validade
                        if ($dias_restantes < 0) {
                            $validade_badge = '<span class="status-badge status-suspended"><i class="bx bx-calendar-x"></i> Expirado</span>';
                        } elseif ($dias_restantes <= 5) {
                            $validade_badge = '<span class="status-badge status-limit"><i class="bx bx-calendar-exclamation"></i> ' . $dias_restantes . ' dias</span>';
                        } else {
                            $validade_badge = '<span class="status-badge status-online"><i class="bx bx-calendar-check"></i> ' . $dias_restantes . ' dias</span>';
                        }
                ?>
                <div class="user-card"
                     data-status="<?php echo strtolower($status_texto); ?>"
                     data-login="<?php echo strtolower($login); ?>"
                     data-id="<?php echo $id; ?>"
                     data-usuario="<?php echo htmlspecialchars($login); ?>"
                     data-senha="<?php echo htmlspecialchars($senha); ?>"
                     data-limite="<?php echo $limite; ?>"
                     data-expira="<?php echo $expira_formatada; ?>">

                    <div class="user-header">
                        <div class="user-info">
                            <div class="user-avatar">
                                <i class='bx bx-user'></i>
                            </div>
                            <div class="user-text">
                                <div class="user-name">
                                    <?php echo htmlspecialchars($login); ?>
                                    <?php if (!empty($uuid)): ?>
                                        <span class="v2ray-badge">V2RAY</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <button class="btn-copy-card" onclick="copiarInfoCard(<?php echo $id; ?>, event)">
                            <i class='bx bx-copy'></i>
                            <span class="copy-text">Copiar</span>
                        </button>
                    </div>

                    <div class="user-body">
                        <!-- STATUS E VALIDADE LADO A LADO -->
                        <div class="status-row">
                            <div class="status-item-card">
                                <div class="status-icon"><i class='bx bx-info-circle'></i></div>
                                <div class="status-content">
                                    <div class="status-label">STATUS</div>
                                    <div class="status-value"><?php echo $status_badge; ?></div>
                                </div>
                            </div>
                            <div class="status-item-card">
                                <div class="status-icon"><i class='bx bx-calendar'></i></div>
                                <div class="status-content">
                                    <div class="status-label">VALIDADE</div>
                                    <div class="status-value"><?php echo $validade_badge; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- LOGIN E SENHA EM GRID 2 COLUNAS -->
                        <div class="grid-2">
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-user icon-user'></i></div>
                                <div class="info-content">
                                    <div class="info-label">LOGIN</div>
                                    <div class="info-value"><?php echo htmlspecialchars($login); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-lock-alt icon-lock'></i></div>
                                <div class="info-content">
                                    <div class="info-label">SENHA</div>
                                    <div class="info-value"><?php echo htmlspecialchars($senha); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- CATEGORIA E LIMITE EM GRID 2 COLUNAS -->
                        <div class="grid-2">
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-category icon-server'></i></div>
                                <div class="info-content">
                                    <div class="info-label">CATEGORIA</div>
                                    <div class="info-value"><?php echo htmlspecialchars($categoria); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-group icon-group'></i></div>
                                <div class="info-content">
                                    <div class="info-label">LIMITE</div>
                                    <div class="info-value">
                                        <?php if ($usando > 0): ?>
                                            <span class="<?php echo $usando >= $limite ? 'expiry-danger' : ''; ?>">
                                                <?php echo $usando; ?>/<?php echo $limite; ?>
                                            </span>
                                        <?php else: ?>
                                            <?php echo $limite; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- DATA DE EXPIRAÇÃO -->
                        <div class="info-row">
                            <div class="info-icon"><i class='bx bx-calendar-check icon-calendar'></i></div>
                            <div class="info-content">
                                <div class="info-label">EXPIRA EM</div>
                                <div class="info-value <?php echo $expiry_class; ?>"><?php echo $expira_formatada; ?></div>
                            </div>
                        </div>

                        <?php if (!empty($notas)): ?>
                        <div class="info-row">
                            <div class="info-icon"><i class='bx bx-note icon-note'></i></div>
                            <div class="info-content">
                                <div class="info-label">NOTAS</div>
                                <div class="info-value"><?php echo htmlspecialchars($notas); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="user-actions">
                            <button class="action-btn btn-edit" onclick="editarUsuario(<?php echo $id; ?>)">
                                <i class='bx bx-edit'></i> Editar
                            </button>
                            <button class="action-btn btn-renew" onclick="renovardias(<?php echo $id; ?>)">
                                <i class='bx bx-calendar-plus'></i> Renovar
                            </button>
                            <?php if ($suspenso == 'Suspenso'): ?>
                                <button class="action-btn btn-warning" onclick="reativar(<?php echo $id; ?>)">
                                    <i class='bx bx-refresh'></i> Reativar
                                </button>
                            <?php else: ?>
                                <button class="action-btn btn-warning" onclick="suspender(<?php echo $id; ?>)">
                                    <i class='bx bx-pause'></i> Suspender
                                </button>
                            <?php endif; ?>
                            <button class="action-btn btn-danger" onclick="excluir(<?php echo $id; ?>)">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo '<div class="empty-state">';
                    echo '<i class="bx bx-user-x"></i>';
                    echo '<h3>Nenhum usuário encontrado</h3>';
                    echo '<p>Crie um novo usuário para começar</p>';
                    echo '</div>';
                }
                ?>
            </div>

            <div class="pagination-info">
                Exibindo <?php echo $result->num_rows; ?> usuário(s)
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
                    <h5>
                        <i class='bx bx-loader-alt bx-spin'></i>
                        Processando
                    </h5>
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

    <!-- =============================================
         MODAL CONFIRMAÇÃO DE RENOVAÇÃO
         ============================================= -->
    <div id="modalConfirmarRenovacao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom warning">
                    <h5>
                        <i class='bx bx-calendar-plus'></i>
                        Confirmar Renovação
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarRenovacao')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-warning-icon">
                        <i class='bx bx-calendar-plus'></i>
                    </div>
                    <div class="modal-info-card">
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-user' style="color:#818cf8;"></i> Usuário
                            </div>
                            <div class="modal-info-value credential" id="confirmar-login">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade Atual
                            </div>
                            <div class="modal-info-value" id="confirmar-expira">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-plus-circle' style="color:#10b981;"></i> Dias a Adicionar
                            </div>
                            <div class="modal-info-value highlight-green">+30 dias</div>
                        </div>
                    </div>
                    <p style="text-align:center; color: rgba(255,255,255,0.5); font-size: 12px; margin-top: 4px;">
                        A validade será extendida a partir da data de expiração atual.
                    </p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarRenovacao')">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn-modal btn-modal-warning" id="btnConfirmarRenovacao">
                        <i class='bx bx-calendar-plus'></i> Renovar Agora
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL CONFIRMAÇÃO DE SUSPENSÃO
         ============================================= -->
    <div id="modalConfirmarSuspensao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom warning">
                    <h5>
                        <i class='bx bx-pause-circle'></i>
                        Confirmar Suspensão
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarSuspensao')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-warning-icon">
                        <i class='bx bx-pause-circle'></i>
                    </div>
                    <div class="modal-info-card">
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-user' style="color:#818cf8;"></i> Usuário
                            </div>
                            <div class="modal-info-value credential" id="suspender-login">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha
                            </div>
                            <div class="modal-info-value credential" id="suspender-senha">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade
                            </div>
                            <div class="modal-info-value" id="suspender-expira">—</div>
                        </div>
                    </div>
                    <p style="text-align:center; color: rgba(255,255,255,0.5); font-size: 12px; margin-top: 4px;">
                        Após suspenso, o usuário não poderá mais acessar até ser reativado.
                    </p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarSuspensao')">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn-modal btn-modal-warning" id="btnConfirmarSuspensao">
                        <i class='bx bx-pause-circle'></i> Suspender Agora
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL CONFIRMAÇÃO DE REATIVAÇÃO
         ============================================= -->
    <div id="modalConfirmarReativacao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-refresh'></i>
                        Confirmar Reativação
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
                                <i class='bx bx-user' style="color:#818cf8;"></i> Usuário
                            </div>
                            <div class="modal-info-value credential" id="reativar-login">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha
                            </div>
                            <div class="modal-info-value credential" id="reativar-senha">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade
                            </div>
                            <div class="modal-info-value" id="reativar-expira">—</div>
                        </div>
                    </div>
                    <p style="text-align:center; color: rgba(255,255,255,0.5); font-size: 12px; margin-top: 4px;">
                        O usuário será reativado e poderá acessar normalmente.
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

    <!-- =============================================
         MODAL CONFIRMAÇÃO DE EXCLUSÃO
         ============================================= -->
    <div id="modalConfirmarExclusao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-trash'></i>
                        Confirmar Exclusão
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarExclusao')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-danger-icon">
                        <i class='bx bx-trash'></i>
                    </div>
                    <div class="modal-info-card">
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-user' style="color:#818cf8;"></i> Usuário
                            </div>
                            <div class="modal-info-value credential" id="excluir-login">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha
                            </div>
                            <div class="modal-info-value credential" id="excluir-senha">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade
                            </div>
                            <div class="modal-info-value" id="excluir-expira">—</div>
                        </div>
                    </div>
                    <p style="text-align:center; color: rgba(220,38,38,0.8); font-size: 12px; margin-top: 4px;">
                        ⚠️ Esta ação não pode ser desfeita! O usuário será permanentemente removido.
                    </p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarExclusao')">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn-modal btn-modal-danger" id="btnConfirmarExclusao">
                        <i class='bx bx-trash'></i> Excluir Permanentemente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL SUCESSO DA RENOVAÇÃO
         ============================================= -->
    <div id="modalSucessoRenovacao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Usuário Renovado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>

                    <div class="modal-info-card" id="renovacao-info-card">
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-user' style="color:#818cf8;"></i> Login
                            </div>
                            <div class="modal-info-value credential" id="renovacao-login">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha
                            </div>
                            <div class="modal-info-value credential" id="renovacao-senha">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-calendar-x' style="color:#f87171;"></i> Validade Anterior
                            </div>
                            <div class="modal-info-value" id="renovacao-validade-anterior">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-calendar-check' style="color:#10b981;"></i> Nova Validade
                            </div>
                            <div class="modal-info-value highlight-green" id="renovacao-nova-validade">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-layer' style="color:#34d399;"></i> Limite
                            </div>
                            <div class="modal-info-value" id="renovacao-limite">—</div>
                        </div>
                        <div class="modal-info-row" id="renovacao-row-uuid" style="display:none;">
                            <div class="modal-info-label">
                                <i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray
                            </div>
                            <div class="modal-info-value" id="renovacao-uuid" style="font-size:11px; word-break:break-all;">—</div>
                        </div>
                    </div>

                    <div class="modal-server-list" id="renovacao-servidores-ok" style="display:none;">
                        <div style="font-size:12px; margin-bottom:8px; color:rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div id="renovacao-servidores-ok-lista"></div>
                    </div>

                    <div class="modal-server-list" id="renovacao-servidores-fail" style="display:none; margin-top:8px; border-color:rgba(220,38,38,0.3);">
                        <div style="font-size:12px; margin-bottom:8px; color:rgba(220,38,38,0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div id="renovacao-servidores-fail-lista"></div>
                    </div>

                    <hr class="modal-divider">
                    <p class="modal-success-title">✨ Renovação realizada com sucesso! ✨</p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-copy" onclick="copiarInformacoesRenovacao()">
                        <i class='bx bx-copy'></i> Copiar Informações
                    </button>
                    <button type="button" class="btn-modal btn-modal-ok" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL SUCESSO OPERAÇÃO
         ============================================= -->
    <div id="modalSucessoOperacao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Operação Realizada!
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
                    <p style="color:rgba(255,255,255,0.8); text-align:center;" id="sucesso-mensagem">Operação realizada com sucesso!</p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-ok" onclick="fecharModalSucessoOperacao()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL ERRO
         ============================================= -->
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
                        <i class='bx bx-error-circle' style="font-size:70px; color:#dc2626; filter: drop-shadow(0 0 10px rgba(220,38,38,0.5));"></i>
                    </div>
                    <h3 style="color:white; margin-bottom:10px; text-align:center;">Ops! Algo deu errado</h3>
                    <p style="color:rgba(255,255,255,0.8); text-align:center;" id="erro-mensagem">Erro ao processar solicitação!</p>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>

    <script>
        // Estado global
        let _renovacaoData = {};
        let _operacaoData = {};

        // Funções de busca e filtro
        function filtrarUsuarios() {
            let search = document.getElementById('searchInput').value.toLowerCase();
            let status = document.getElementById('statusFilter').value;
            
            document.querySelectorAll('.user-card').forEach(card => {
                let login = card.getAttribute('data-login');
                let cardStatus = card.getAttribute('data-status');
                let statusMatch = (status === 'todos' || cardStatus === status);
                let searchMatch = login.includes(search);
                
                card.style.display = (searchMatch && statusMatch) ? 'block' : 'none';
            });
        }

        document.getElementById('searchInput').addEventListener('keyup', filtrarUsuarios);
        document.getElementById('statusFilter').addEventListener('change', filtrarUsuarios);

        // Funções de utilidade
        function getCard(id) {
            return document.querySelector(`.user-card[data-id="${id}"]`);
        }

        function copiarInfoCard(id, event) {
            event.stopPropagation();
            const card = getCard(id);
            if (!card) return;
            
            const usuario = card.getAttribute('data-usuario');
            const senha = card.getAttribute('data-senha');
            const expira = card.getAttribute('data-expira');
            const limite = card.getAttribute('data-limite');
            
            let texto = `📋 INFORMAÇÕES DO USUÁRIO\n━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `👤 Login: ${usuario}\n`;
            texto += `🔑 Senha: ${senha}\n`;
            texto += `🔗 Limite: ${limite} conexões\n`;
            texto += `📅 Expira em: ${expira}\n`;
            texto += `━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `📆 Data: ${new Date().toLocaleString('pt-BR')}`;
            
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
            }).catch(function() {
                mostrarToast('❌ Erro ao copiar informações!', true);
            });
        }

        function editarUsuario(id) {
            window.location.href = 'editarlogin.php?id=' + id;
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
            document.getElementById(id).classList.add('show');
        }

        function fecharModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        function fecharTodosModais() {
            document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('show'));
        }

        function mostrarProcessando() {
            abrirModal('modalProcessando');
        }

        function esconderProcessando() {
            fecharModal('modalProcessando');
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

        // ==================== RENOVAÇÃO ====================
        function renovardias(id) {
            const card = getCard(id);
            const usuario = card?.getAttribute('data-usuario') || '';
            const expira = card?.getAttribute('data-expira') || '';
            const senha = card?.getAttribute('data-senha') || '';
            const limite = card?.getAttribute('data-limite') || '';

            _renovacaoData = { id, usuario, expira, senha, limite };

            document.getElementById('confirmar-login').textContent = usuario;
            document.getElementById('confirmar-expira').textContent = expira;

            document.getElementById('btnConfirmarRenovacao').onclick = function() {
                fecharModal('modalConfirmarRenovacao');
                processarRenovacao(id);
            };

            abrirModal('modalConfirmarRenovacao');
        }

        function processarRenovacao(id) {
            mostrarProcessando();

            $.ajax({
                url: 'renovardias.php?id=' + id,
                type: 'GET',
                dataType: 'json',
                timeout: 60000,
                success: function(response) {
                    esconderProcessando();

                    if (response.status === 'success') {
                        preencherModalSucessoRenovacao(response);
                        abrirModal('modalSucessoRenovacao');
                    } else {
                        mostrarErro(response.message || 'Erro ao renovar usuário!');
                    }
                },
                error: function(xhr) {
                    esconderProcessando();
                    let errorMsg = 'Erro ao conectar com o servidor!';
                    try {
                        const resp = JSON.parse(xhr.responseText);
                        if (resp.message) errorMsg = resp.message;
                    } catch(e) {
                        if (xhr.responseText) errorMsg = xhr.responseText;
                    }
                    mostrarErro(errorMsg);
                }
            });
        }

        function preencherModalSucessoRenovacao(data) {
            document.getElementById('renovacao-login').textContent = data.login || '—';
            document.getElementById('renovacao-senha').textContent = data.senha || '—';
            document.getElementById('renovacao-nova-validade').textContent = data.new_expiry || '—';
            document.getElementById('renovacao-validade-anterior').textContent = data.validade_anterior || '—';
            document.getElementById('renovacao-limite').textContent = (data.limite ? data.limite + ' conexões' : '—');

            const rowUUID = document.getElementById('renovacao-row-uuid');
            if (data.uuid) {
                rowUUID.style.display = 'flex';
                document.getElementById('renovacao-uuid').textContent = data.uuid;
            } else {
                rowUUID.style.display = 'none';
            }

            const divOk = document.getElementById('renovacao-servidores-ok');
            const listaOk = document.getElementById('renovacao-servidores-ok-lista');
            if (data.servers && data.servers.length > 0) {
                divOk.style.display = 'block';
                listaOk.innerHTML = data.servers.map(s =>
                    `<span class="modal-server-badge"><i class='bx bx-check-circle' style="font-size:10px;"></i> ${s}</span>`
                ).join('');
            } else {
                divOk.style.display = 'none';
            }

            const divFail = document.getElementById('renovacao-servidores-fail');
            const listaFail = document.getElementById('renovacao-servidores-fail-lista');
            if (data.failed && data.failed.length > 0) {
                divFail.style.display = 'block';
                listaFail.innerHTML = data.failed.map(s =>
                    `<span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> ${s}</span>`
                ).join('');
            } else {
                divFail.style.display = 'none';
            }
        }

        function copiarInformacoesRenovacao() {
            const login = document.getElementById('renovacao-login').textContent;
            const senha = document.getElementById('renovacao-senha').textContent;
            const novaValidade = document.getElementById('renovacao-nova-validade').textContent;
            const validadeAnt = document.getElementById('renovacao-validade-anterior').textContent;
            const limite = document.getElementById('renovacao-limite').textContent;
            const uuid = document.getElementById('renovacao-uuid')?.textContent || '';

            let texto = `✅ USUÁRIO RENOVADO COM SUCESSO!\n`;
            texto += `━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
            texto += `👤 Login: ${login}\n`;
            texto += `🔑 Senha: ${senha}\n`;
            texto += `📅 Validade Anterior: ${validadeAnt}\n`;
            texto += `✅ Nova Validade: ${novaValidade}\n`;
            texto += `🔗 Limite: ${limite}\n`;
            if (uuid && uuid !== '—') texto += `🛡️ UUID: ${uuid}\n`;
            texto += `\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `📆 Data: ${new Date().toLocaleString('pt-BR')}\n`;

            navigator.clipboard.writeText(texto).then(function() {
                mostrarToast('Informações copiadas com sucesso!');
            });
        }

        function fecharModalSucesso() {
            fecharModal('modalSucessoRenovacao');
            location.reload();
        }

        // ==================== SUSPENDER ====================
        function suspender(id) {
            const card = getCard(id);
            const usuario = card?.getAttribute('data-usuario') || '';
            const senha = card?.getAttribute('data-senha') || '';
            const expira = card?.getAttribute('data-expira') || '';

            _operacaoData = { id, usuario, senha, expira };

            document.getElementById('suspender-login').textContent = usuario;
            document.getElementById('suspender-senha').textContent = senha;
            document.getElementById('suspender-expira').textContent = expira;

            document.getElementById('btnConfirmarSuspensao').onclick = function() {
                fecharModal('modalConfirmarSuspensao');
                executarSuspensao(id);
            };

            abrirModal('modalConfirmarSuspensao');
        }

        function executarSuspensao(id) {
            mostrarProcessando();

            $.ajax({
                url: 'suspender.php?id=' + id,
                type: 'GET',
                success: function(data) {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    
                    if (data == 'suspenso com sucesso') {
                        mostrarSucesso('⚠️ Usuário Suspenso!', `Usuário ${_operacaoData.usuario} foi suspenso com sucesso!`);
                    } else if (data == 'erro no servidor') {
                        mostrarErro('Erro no servidor! Verifique se está online.');
                    } else {
                        mostrarErro('Erro ao suspender usuário!');
                    }
                },
                error: function() {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            });
        }

        // ==================== REATIVAR ====================
        function reativar(id) {
            const card = getCard(id);
            const usuario = card?.getAttribute('data-usuario') || '';
            const senha = card?.getAttribute('data-senha') || '';
            const expira = card?.getAttribute('data-expira') || '';

            _operacaoData = { id, usuario, senha, expira };

            document.getElementById('reativar-login').textContent = usuario;
            document.getElementById('reativar-senha').textContent = senha;
            document.getElementById('reativar-expira').textContent = expira;

            document.getElementById('btnConfirmarReativacao').onclick = function() {
                fecharModal('modalConfirmarReativacao');
                executarReativacao(id);
            };

            abrirModal('modalConfirmarReativacao');
        }

        function executarReativacao(id) {
            mostrarProcessando();

            $.ajax({
                url: 'reativar.php?id=' + id,
                type: 'GET',
                success: function(data) {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    
                    if (data == 'reativado com sucesso') {
                        mostrarSucesso('✅ Usuário Reativado!', `Usuário ${_operacaoData.usuario} foi reativado com sucesso!`);
                    } else {
                        mostrarErro('Erro ao reativar usuário!');
                    }
                },
                error: function() {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            });
        }

        // ==================== EXCLUIR ====================
        function excluir(id) {
            const card = getCard(id);
            const usuario = card?.getAttribute('data-usuario') || '';
            const senha = card?.getAttribute('data-senha') || '';
            const expira = card?.getAttribute('data-expira') || '';

            _operacaoData = { id, usuario, senha, expira };

            document.getElementById('excluir-login').textContent = usuario;
            document.getElementById('excluir-senha').textContent = senha;
            document.getElementById('excluir-expira').textContent = expira;

            document.getElementById('btnConfirmarExclusao').onclick = function() {
                fecharModal('modalConfirmarExclusao');
                executarExclusao(id);
            };

            abrirModal('modalConfirmarExclusao');
        }

        function executarExclusao(id) {
            mostrarProcessando();

            $.ajax({
                url: 'excluiruser.php?id=' + id,
                type: 'GET',
                success: function(data) {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    
                    if (data == 'excluido') {
                        mostrarSucesso('🗑️ Usuário Excluído!', `Usuário ${_operacaoData.usuario} foi excluído permanentemente!`);
                    } else {
                        mostrarErro('Erro ao excluir usuário!');
                    }
                },
                error: function() {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            });
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                const modalId = e.target.id;
                if (modalId === 'modalSucessoRenovacao') {
                    fecharModalSucesso();
                } else if (modalId === 'modalSucessoOperacao') {
                    fecharModalSucessoOperacao();
                } else {
                    e.target.classList.remove('show');
                }
            }
        });

        // ESC fecha modais
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('modalSucessoRenovacao').classList.contains('show')) {
                    fecharModalSucesso();
                } else if (document.getElementById('modalSucessoOperacao').classList.contains('show')) {
                    fecharModalSucessoOperacao();
                } else {
                    fecharTodosModais();
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
                <i class='bx bx-user'></i>
                <span>Gerenciar usuários SSH</span>
            </div>

            <!-- Filtros Card -->
            <div class="filters-card">
                <div class="filters-title">
                    <i class='bx bx-filter-alt'></i>
                    Filtros
                </div>
                <div class="filter-group">
                    <div class="filter-item">
                        <div class="filter-label">BUSCAR POR LOGIN</div>
                        <input type="text" class="filter-input" id="searchInput"
                               placeholder="Digite para buscar..."
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                               onkeyup="filtrarUsuarios()">
                    </div>
                    <div class="filter-item">
                        <div class="filter-label">FILTRAR POR STATUS</div>
                        <select class="filter-select" id="statusFilter" onchange="filtrarUsuarios()">
                            <option value="todos">Todos</option>
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                            <option value="suspenso">Suspenso</option>
                            <option value="expirado">Expirado</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Grid de usuários -->
            <div class="users-grid" id="usersGrid">
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $id       = $row['id'];
                        $login    = $row['login'];
                        $senha    = $row['senha'];
                        $limite   = $row['limite'];
                        $status   = $row['status'];
                        $categoria = $row['categoriaid'];
                        $suspenso = $row['mainid'];
                        $notas    = $row['lastview'];
                        $uuid     = $row['uuid'];
                        $expira   = $row['expira'];
                        $expira_formatada = date('d/m/Y', strtotime($expira));

                        $sql_online  = "SELECT quantidade FROM onlines WHERE usuario = '$login'";
                        $res_online  = $conn->query($sql_online);
                        $row_online  = $res_online->fetch_assoc();
                        $usando      = $row_online['quantidade'] ?? 0;

                        $data_validade  = strtotime($expira);
                        $data_atual     = time();
                        $diferenca      = $data_validade - $data_atual;
                        $dias_restantes = floor($diferenca / (60 * 60 * 24));
                        $horas_restantes = floor(($diferenca % (60 * 60 * 24)) / (60 * 60));

                        $status_classe = 'status-offline';
                        $status_texto  = 'Offline';
                        if ($suspenso == 'Suspenso') {
                            $status_classe = 'status-suspended';
                            $status_texto  = 'Suspenso';
                        } elseif ($suspenso == 'Limite Ultrapassado') {
                            $status_classe = 'status-limit';
                            $status_texto  = 'Limite Excedido';
                        } elseif ($status == 'Online') {
                            $status_classe = 'status-online';
                            $status_texto  = 'Online';
                        }

                        $expiry_class = '';
                        $expiry_texto = "{$dias_restantes}d {$horas_restantes}h";
                        if ($dias_restantes < 0) {
                            $expiry_class = 'expiry-danger';
                            $expiry_texto = 'Expirado';
                        } elseif ($dias_restantes <= 5) {
                            $expiry_class = 'expiry-warning';
                        }
                        
                        // Badge de status para exibir
                        $status_badge = '<span class="status-badge ' . $status_classe . '"><i class="bx bx-' . ($status_texto == 'Online' ? 'wifi' : ($status_texto == 'Suspenso' ? 'lock' : 'power-off')) . '"></i> ' . $status_texto . '</span>';
                        
                        // Badge de validade
                        if ($dias_restantes < 0) {
                            $validade_badge = '<span class="status-badge status-suspended"><i class="bx bx-calendar-x"></i> Expirado</span>';
                        } elseif ($dias_restantes <= 5) {
                            $validade_badge = '<span class="status-badge status-limit"><i class="bx bx-calendar-exclamation"></i> ' . $dias_restantes . ' dias</span>';
                        } else {
                            $validade_badge = '<span class="status-badge status-online"><i class="bx bx-calendar-check"></i> ' . $dias_restantes . ' dias</span>';
                        }
                ?>
                <div class="user-card"
                     data-status="<?php echo strtolower($status_texto); ?>"
                     data-login="<?php echo strtolower($login); ?>"
                     data-id="<?php echo $id; ?>"
                     data-usuario="<?php echo htmlspecialchars($login); ?>"
                     data-senha="<?php echo htmlspecialchars($senha); ?>"
                     data-limite="<?php echo $limite; ?>"
                     data-expira="<?php echo $expira_formatada; ?>">

                    <div class="user-header">
                        <div class="user-info">
                            <div class="user-avatar">
                                <i class='bx bx-user'></i>
                            </div>
                            <div class="user-text">
                                <div class="user-name">
                                    <?php echo htmlspecialchars($login); ?>
                                    <?php if (!empty($uuid)): ?>
                                        <span class="v2ray-badge">V2RAY</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <button class="btn-copy-card" onclick="copiarInfoCard(<?php echo $id; ?>, event)">
                            <i class='bx bx-copy'></i>
                            <span class="copy-text">Copiar</span>
                        </button>
                    </div>

                    <div class="user-body">
                        <!-- STATUS E VALIDADE LADO A LADO -->
                        <div class="status-row">
                            <div class="status-item-card">
                                <div class="status-icon"><i class='bx bx-info-circle'></i></div>
                                <div class="status-content">
                                    <div class="status-label">STATUS</div>
                                    <div class="status-value"><?php echo $status_badge; ?></div>
                                </div>
                            </div>
                            <div class="status-item-card">
                                <div class="status-icon"><i class='bx bx-calendar'></i></div>
                                <div class="status-content">
                                    <div class="status-label">VALIDADE</div>
                                    <div class="status-value"><?php echo $validade_badge; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- LOGIN E SENHA EM GRID 2 COLUNAS -->
                        <div class="grid-2">
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-user icon-user'></i></div>
                                <div class="info-content">
                                    <div class="info-label">LOGIN</div>
                                    <div class="info-value"><?php echo htmlspecialchars($login); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-lock-alt icon-lock'></i></div>
                                <div class="info-content">
                                    <div class="info-label">SENHA</div>
                                    <div class="info-value"><?php echo htmlspecialchars($senha); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- CATEGORIA E LIMITE EM GRID 2 COLUNAS -->
                        <div class="grid-2">
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-category icon-server'></i></div>
                                <div class="info-content">
                                    <div class="info-label">CATEGORIA</div>
                                    <div class="info-value"><?php echo htmlspecialchars($categoria); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-group icon-group'></i></div>
                                <div class="info-content">
                                    <div class="info-label">LIMITE</div>
                                    <div class="info-value">
                                        <?php if ($usando > 0): ?>
                                            <span class="<?php echo $usando >= $limite ? 'expiry-danger' : ''; ?>">
                                                <?php echo $usando; ?>/<?php echo $limite; ?>
                                            </span>
                                        <?php else: ?>
                                            <?php echo $limite; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- DATA DE EXPIRAÇÃO -->
                        <div class="info-row">
                            <div class="info-icon"><i class='bx bx-calendar-check icon-calendar'></i></div>
                            <div class="info-content">
                                <div class="info-label">EXPIRA EM</div>
                                <div class="info-value <?php echo $expiry_class; ?>"><?php echo $expira_formatada; ?></div>
                            </div>
                        </div>

                        <?php if (!empty($notas)): ?>
                        <div class="info-row">
                            <div class="info-icon"><i class='bx bx-note icon-note'></i></div>
                            <div class="info-content">
                                <div class="info-label">NOTAS</div>
                                <div class="info-value"><?php echo htmlspecialchars($notas); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="user-actions">
                            <button class="action-btn btn-edit" onclick="editarUsuario(<?php echo $id; ?>)">
                                <i class='bx bx-edit'></i> Editar
                            </button>
                            <button class="action-btn btn-renew" onclick="renovardias(<?php echo $id; ?>)">
                                <i class='bx bx-calendar-plus'></i> Renovar
                            </button>
                            <?php if ($suspenso == 'Suspenso'): ?>
                                <button class="action-btn btn-warning" onclick="reativar(<?php echo $id; ?>)">
                                    <i class='bx bx-refresh'></i> Reativar
                                </button>
                            <?php else: ?>
                                <button class="action-btn btn-warning" onclick="suspender(<?php echo $id; ?>)">
                                    <i class='bx bx-pause'></i> Suspender
                                </button>
                            <?php endif; ?>
                            <button class="action-btn btn-danger" onclick="excluir(<?php echo $id; ?>)">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo '<div class="empty-state">';
                    echo '<i class="bx bx-user-x"></i>';
                    echo '<h3>Nenhum usuário encontrado</h3>';
                    echo '<p>Crie um novo usuário para começar</p>';
                    echo '</div>';
                }
                ?>
            </div>

            <div class="pagination-info">
                Exibindo <?php echo $result->num_rows; ?> usuário(s)
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
                    <h5>
                        <i class='bx bx-loader-alt bx-spin'></i>
                        Processando
                    </h5>
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

    <!-- =============================================
         MODAL CONFIRMAÇÃO DE RENOVAÇÃO
         ============================================= -->
    <div id="modalConfirmarRenovacao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom warning">
                    <h5>
                        <i class='bx bx-calendar-plus'></i>
                        Confirmar Renovação
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarRenovacao')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-warning-icon">
                        <i class='bx bx-calendar-plus'></i>
                    </div>
                    <div class="modal-info-card">
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-user' style="color:#818cf8;"></i> Usuário
                            </div>
                            <div class="modal-info-value credential" id="confirmar-login">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade Atual
                            </div>
                            <div class="modal-info-value" id="confirmar-expira">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-plus-circle' style="color:#10b981;"></i> Dias a Adicionar
                            </div>
                            <div class="modal-info-value highlight-green">+30 dias</div>
                        </div>
                    </div>
                    <p style="text-align:center; color: rgba(255,255,255,0.5); font-size: 12px; margin-top: 4px;">
                        A validade será extendida a partir da data de expiração atual.
                    </p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarRenovacao')">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn-modal btn-modal-warning" id="btnConfirmarRenovacao">
                        <i class='bx bx-calendar-plus'></i> Renovar Agora
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL CONFIRMAÇÃO DE SUSPENSÃO
         ============================================= -->
    <div id="modalConfirmarSuspensao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom warning">
                    <h5>
                        <i class='bx bx-pause-circle'></i>
                        Confirmar Suspensão
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarSuspensao')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-warning-icon">
                        <i class='bx bx-pause-circle'></i>
                    </div>
                    <div class="modal-info-card">
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-user' style="color:#818cf8;"></i> Usuário
                            </div>
                            <div class="modal-info-value credential" id="suspender-login">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha
                            </div>
                            <div class="modal-info-value credential" id="suspender-senha">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade
                            </div>
                            <div class="modal-info-value" id="suspender-expira">—</div>
                        </div>
                    </div>
                    <p style="text-align:center; color: rgba(255,255,255,0.5); font-size: 12px; margin-top: 4px;">
                        Após suspenso, o usuário não poderá mais acessar até ser reativado.
                    </p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarSuspensao')">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn-modal btn-modal-warning" id="btnConfirmarSuspensao">
                        <i class='bx bx-pause-circle'></i> Suspender Agora
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL CONFIRMAÇÃO DE REATIVAÇÃO
         ============================================= -->
    <div id="modalConfirmarReativacao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-refresh'></i>
                        Confirmar Reativação
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
                                <i class='bx bx-user' style="color:#818cf8;"></i> Usuário
                            </div>
                            <div class="modal-info-value credential" id="reativar-login">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha
                            </div>
                            <div class="modal-info-value credential" id="reativar-senha">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade
                            </div>
                            <div class="modal-info-value" id="reativar-expira">—</div>
                        </div>
                    </div>
                    <p style="text-align:center; color: rgba(255,255,255,0.5); font-size: 12px; margin-top: 4px;">
                        O usuário será reativado e poderá acessar normalmente.
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

    <!-- =============================================
         MODAL CONFIRMAÇÃO DE EXCLUSÃO
         ============================================= -->
    <div id="modalConfirmarExclusao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-trash'></i>
                        Confirmar Exclusão
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarExclusao')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-danger-icon">
                        <i class='bx bx-trash'></i>
                    </div>
                    <div class="modal-info-card">
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-user' style="color:#818cf8;"></i> Usuário
                            </div>
                            <div class="modal-info-value credential" id="excluir-login">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha
                            </div>
                            <div class="modal-info-value credential" id="excluir-senha">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade
                            </div>
                            <div class="modal-info-value" id="excluir-expira">—</div>
                        </div>
                    </div>
                    <p style="text-align:center; color: rgba(220,38,38,0.8); font-size: 12px; margin-top: 4px;">
                        ⚠️ Esta ação não pode ser desfeita! O usuário será permanentemente removido.
                    </p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarExclusao')">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn-modal btn-modal-danger" id="btnConfirmarExclusao">
                        <i class='bx bx-trash'></i> Excluir Permanentemente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL SUCESSO DA RENOVAÇÃO
         ============================================= -->
    <div id="modalSucessoRenovacao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Usuário Renovado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>

                    <div class="modal-info-card" id="renovacao-info-card">
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-user' style="color:#818cf8;"></i> Login
                            </div>
                            <div class="modal-info-value credential" id="renovacao-login">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha
                            </div>
                            <div class="modal-info-value credential" id="renovacao-senha">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-calendar-x' style="color:#f87171;"></i> Validade Anterior
                            </div>
                            <div class="modal-info-value" id="renovacao-validade-anterior">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-calendar-check' style="color:#10b981;"></i> Nova Validade
                            </div>
                            <div class="modal-info-value highlight-green" id="renovacao-nova-validade">—</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label">
                                <i class='bx bx-layer' style="color:#34d399;"></i> Limite
                            </div>
                            <div class="modal-info-value" id="renovacao-limite">—</div>
                        </div>
                        <div class="modal-info-row" id="renovacao-row-uuid" style="display:none;">
                            <div class="modal-info-label">
                                <i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray
                            </div>
                            <div class="modal-info-value" id="renovacao-uuid" style="font-size:11px; word-break:break-all;">—</div>
                        </div>
                    </div>

                    <div class="modal-server-list" id="renovacao-servidores-ok" style="display:none;">
                        <div style="font-size:12px; margin-bottom:8px; color:rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div id="renovacao-servidores-ok-lista"></div>
                    </div>

                    <div class="modal-server-list" id="renovacao-servidores-fail" style="display:none; margin-top:8px; border-color:rgba(220,38,38,0.3);">
                        <div style="font-size:12px; margin-bottom:8px; color:rgba(220,38,38,0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div id="renovacao-servidores-fail-lista"></div>
                    </div>

                    <hr class="modal-divider">
                    <p class="modal-success-title">✨ Renovação realizada com sucesso! ✨</p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-copy" onclick="copiarInformacoesRenovacao()">
                        <i class='bx bx-copy'></i> Copiar Informações
                    </button>
                    <button type="button" class="btn-modal btn-modal-ok" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL SUCESSO OPERAÇÃO
         ============================================= -->
    <div id="modalSucessoOperacao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Operação Realizada!
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
                    <p style="color:rgba(255,255,255,0.8); text-align:center;" id="sucesso-mensagem">Operação realizada com sucesso!</p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-ok" onclick="fecharModalSucessoOperacao()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL ERRO
         ============================================= -->
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
                        <i class='bx bx-error-circle' style="font-size:70px; color:#dc2626; filter: drop-shadow(0 0 10px rgba(220,38,38,0.5));"></i>
                    </div>
                    <h3 style="color:white; margin-bottom:10px; text-align:center;">Ops! Algo deu errado</h3>
                    <p style="color:rgba(255,255,255,0.8); text-align:center;" id="erro-mensagem">Erro ao processar solicitação!</p>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>

    <script>
        // Estado global
        let _renovacaoData = {};
        let _operacaoData = {};

        // Funções de busca e filtro
        function filtrarUsuarios() {
            let search = document.getElementById('searchInput').value.toLowerCase();
            let status = document.getElementById('statusFilter').value;
            
            document.querySelectorAll('.user-card').forEach(card => {
                let login = card.getAttribute('data-login');
                let cardStatus = card.getAttribute('data-status');
                let statusMatch = (status === 'todos' || cardStatus === status);
                let searchMatch = login.includes(search);
                
                card.style.display = (searchMatch && statusMatch) ? 'block' : 'none';
            });
        }

        document.getElementById('searchInput').addEventListener('keyup', filtrarUsuarios);
        document.getElementById('statusFilter').addEventListener('change', filtrarUsuarios);

        // Funções de utilidade
        function getCard(id) {
            return document.querySelector(`.user-card[data-id="${id}"]`);
        }

        function copiarInfoCard(id, event) {
            event.stopPropagation();
            const card = getCard(id);
            if (!card) return;
            
            const usuario = card.getAttribute('data-usuario');
            const senha = card.getAttribute('data-senha');
            const expira = card.getAttribute('data-expira');
            const limite = card.getAttribute('data-limite');
            
            let texto = `📋 INFORMAÇÕES DO USUÁRIO\n━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `👤 Login: ${usuario}\n`;
            texto += `🔑 Senha: ${senha}\n`;
            texto += `🔗 Limite: ${limite} conexões\n`;
            texto += `📅 Expira em: ${expira}\n`;
            texto += `━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `📆 Data: ${new Date().toLocaleString('pt-BR')}`;
            
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
            }).catch(function() {
                mostrarToast('❌ Erro ao copiar informações!', true);
            });
        }

        function editarUsuario(id) {
            window.location.href = 'editarlogin.php?id=' + id;
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
            document.getElementById(id).classList.add('show');
        }

        function fecharModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        function fecharTodosModais() {
            document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('show'));
        }

        function mostrarProcessando() {
            abrirModal('modalProcessando');
        }

        function esconderProcessando() {
            fecharModal('modalProcessando');
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

        // ==================== RENOVAÇÃO ====================
        function renovardias(id) {
            const card = getCard(id);
            const usuario = card?.getAttribute('data-usuario') || '';
            const expira = card?.getAttribute('data-expira') || '';
            const senha = card?.getAttribute('data-senha') || '';
            const limite = card?.getAttribute('data-limite') || '';

            _renovacaoData = { id, usuario, expira, senha, limite };

            document.getElementById('confirmar-login').textContent = usuario;
            document.getElementById('confirmar-expira').textContent = expira;

            document.getElementById('btnConfirmarRenovacao').onclick = function() {
                fecharModal('modalConfirmarRenovacao');
                processarRenovacao(id);
            };

            abrirModal('modalConfirmarRenovacao');
        }

        function processarRenovacao(id) {
            mostrarProcessando();

            $.ajax({
                url: 'renovardias.php?id=' + id,
                type: 'GET',
                dataType: 'json',
                timeout: 60000,
                success: function(response) {
                    esconderProcessando();

                    if (response.status === 'success') {
                        preencherModalSucessoRenovacao(response);
                        abrirModal('modalSucessoRenovacao');
                    } else {
                        mostrarErro(response.message || 'Erro ao renovar usuário!');
                    }
                },
                error: function(xhr) {
                    esconderProcessando();
                    let errorMsg = 'Erro ao conectar com o servidor!';
                    try {
                        const resp = JSON.parse(xhr.responseText);
                        if (resp.message) errorMsg = resp.message;
                    } catch(e) {
                        if (xhr.responseText) errorMsg = xhr.responseText;
                    }
                    mostrarErro(errorMsg);
                }
            });
        }

        function preencherModalSucessoRenovacao(data) {
            document.getElementById('renovacao-login').textContent = data.login || '—';
            document.getElementById('renovacao-senha').textContent = data.senha || '—';
            document.getElementById('renovacao-nova-validade').textContent = data.new_expiry || '—';
            document.getElementById('renovacao-validade-anterior').textContent = data.validade_anterior || '—';
            document.getElementById('renovacao-limite').textContent = (data.limite ? data.limite + ' conexões' : '—');

            const rowUUID = document.getElementById('renovacao-row-uuid');
            if (data.uuid) {
                rowUUID.style.display = 'flex';
                document.getElementById('renovacao-uuid').textContent = data.uuid;
            } else {
                rowUUID.style.display = 'none';
            }

            const divOk = document.getElementById('renovacao-servidores-ok');
            const listaOk = document.getElementById('renovacao-servidores-ok-lista');
            if (data.servers && data.servers.length > 0) {
                divOk.style.display = 'block';
                listaOk.innerHTML = data.servers.map(s =>
                    `<span class="modal-server-badge"><i class='bx bx-check-circle' style="font-size:10px;"></i> ${s}</span>`
                ).join('');
            } else {
                divOk.style.display = 'none';
            }

            const divFail = document.getElementById('renovacao-servidores-fail');
            const listaFail = document.getElementById('renovacao-servidores-fail-lista');
            if (data.failed && data.failed.length > 0) {
                divFail.style.display = 'block';
                listaFail.innerHTML = data.failed.map(s =>
                    `<span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> ${s}</span>`
                ).join('');
            } else {
                divFail.style.display = 'none';
            }
        }

        function copiarInformacoesRenovacao() {
            const login = document.getElementById('renovacao-login').textContent;
            const senha = document.getElementById('renovacao-senha').textContent;
            const novaValidade = document.getElementById('renovacao-nova-validade').textContent;
            const validadeAnt = document.getElementById('renovacao-validade-anterior').textContent;
            const limite = document.getElementById('renovacao-limite').textContent;
            const uuid = document.getElementById('renovacao-uuid')?.textContent || '';

            let texto = `✅ USUÁRIO RENOVADO COM SUCESSO!\n`;
            texto += `━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
            texto += `👤 Login: ${login}\n`;
            texto += `🔑 Senha: ${senha}\n`;
            texto += `📅 Validade Anterior: ${validadeAnt}\n`;
            texto += `✅ Nova Validade: ${novaValidade}\n`;
            texto += `🔗 Limite: ${limite}\n`;
            if (uuid && uuid !== '—') texto += `🛡️ UUID: ${uuid}\n`;
            texto += `\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `📆 Data: ${new Date().toLocaleString('pt-BR')}\n`;

            navigator.clipboard.writeText(texto).then(function() {
                mostrarToast('Informações copiadas com sucesso!');
            });
        }

        function fecharModalSucesso() {
            fecharModal('modalSucessoRenovacao');
            location.reload();
        }

        // ==================== SUSPENDER ====================
        function suspender(id) {
            const card = getCard(id);
            const usuario = card?.getAttribute('data-usuario') || '';
            const senha = card?.getAttribute('data-senha') || '';
            const expira = card?.getAttribute('data-expira') || '';

            _operacaoData = { id, usuario, senha, expira };

            document.getElementById('suspender-login').textContent = usuario;
            document.getElementById('suspender-senha').textContent = senha;
            document.getElementById('suspender-expira').textContent = expira;

            document.getElementById('btnConfirmarSuspensao').onclick = function() {
                fecharModal('modalConfirmarSuspensao');
                executarSuspensao(id);
            };

            abrirModal('modalConfirmarSuspensao');
        }

        function executarSuspensao(id) {
            mostrarProcessando();

            $.ajax({
                url: 'suspender.php?id=' + id,
                type: 'GET',
                success: function(data) {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    
                    if (data == 'suspenso com sucesso') {
                        mostrarSucesso('⚠️ Usuário Suspenso!', `Usuário ${_operacaoData.usuario} foi suspenso com sucesso!`);
                    } else if (data == 'erro no servidor') {
                        mostrarErro('Erro no servidor! Verifique se está online.');
                    } else {
                        mostrarErro('Erro ao suspender usuário!');
                    }
                },
                error: function() {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            });
        }

        // ==================== REATIVAR ====================
        function reativar(id) {
            const card = getCard(id);
            const usuario = card?.getAttribute('data-usuario') || '';
            const senha = card?.getAttribute('data-senha') || '';
            const expira = card?.getAttribute('data-expira') || '';

            _operacaoData = { id, usuario, senha, expira };

            document.getElementById('reativar-login').textContent = usuario;
            document.getElementById('reativar-senha').textContent = senha;
            document.getElementById('reativar-expira').textContent = expira;

            document.getElementById('btnConfirmarReativacao').onclick = function() {
                fecharModal('modalConfirmarReativacao');
                executarReativacao(id);
            };

            abrirModal('modalConfirmarReativacao');
        }

        function executarReativacao(id) {
            mostrarProcessando();

            $.ajax({
                url: 'reativar.php?id=' + id,
                type: 'GET',
                success: function(data) {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    
                    if (data == 'reativado com sucesso') {
                        mostrarSucesso('✅ Usuário Reativado!', `Usuário ${_operacaoData.usuario} foi reativado com sucesso!`);
                    } else {
                        mostrarErro('Erro ao reativar usuário!');
                    }
                },
                error: function() {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            });
        }

        // ==================== EXCLUIR ====================
        function excluir(id) {
            const card = getCard(id);
            const usuario = card?.getAttribute('data-usuario') || '';
            const senha = card?.getAttribute('data-senha') || '';
            const expira = card?.getAttribute('data-expira') || '';

            _operacaoData = { id, usuario, senha, expira };

            document.getElementById('excluir-login').textContent = usuario;
            document.getElementById('excluir-senha').textContent = senha;
            document.getElementById('excluir-expira').textContent = expira;

            document.getElementById('btnConfirmarExclusao').onclick = function() {
                fecharModal('modalConfirmarExclusao');
                executarExclusao(id);
            };

            abrirModal('modalConfirmarExclusao');
        }

        function executarExclusao(id) {
            mostrarProcessando();

            $.ajax({
                url: 'excluiruser.php?id=' + id,
                type: 'GET',
                success: function(data) {
                    esconderProcessando();
                    data = data.replace(/(\r\n|\n|\r)/gm, "");
                    
                    if (data == 'excluido') {
                        mostrarSucesso('🗑️ Usuário Excluído!', `Usuário ${_operacaoData.usuario} foi excluído permanentemente!`);
                    } else {
                        mostrarErro('Erro ao excluir usuário!');
                    }
                },
                error: function() {
                    esconderProcessando();
                    mostrarErro('Erro ao conectar com o servidor!');
                }
            });
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                const modalId = e.target.id;
                if (modalId === 'modalSucessoRenovacao') {
                    fecharModalSucesso();
                } else if (modalId === 'modalSucessoOperacao') {
                    fecharModalSucessoOperacao();
                } else {
                    e.target.classList.remove('show');
                }
            }
        });

        // ESC fecha modais
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('modalSucessoRenovacao').classList.contains('show')) {
                    fecharModalSucesso();
                } else if (document.getElementById('modalSucessoOperacao').classList.contains('show')) {
                    fecharModalSucessoOperacao();
                } else {
                    fecharTodosModais();
                }
            }
        });
    </script>
</body>
</html>



