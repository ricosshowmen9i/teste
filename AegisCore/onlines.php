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

// Buscar total de usuários online
$sql_total = "SELECT COUNT(*) as total FROM ssh_accounts WHERE status = 'Online'";
$result_total = $conn->query($sql_total);
$total_registros = $result_total->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $limite_por_pagina);

// Buscar usuários online com paginação
$sql = "SELECT accounts.login AS acc_login, ssh_accounts.login AS ssh_login, ssh_accounts.id,
        ssh_accounts.limite, ssh_accounts.uuid, ssh_accounts.senha, ssh_accounts.expira,
        categorias.nome AS categoria_nome,
        servidores.nome AS servidor_nome
        FROM accounts 
        INNER JOIN ssh_accounts ON accounts.id = ssh_accounts.byid 
        LEFT JOIN categorias ON ssh_accounts.categoriaid = categorias.id
        LEFT JOIN servidores ON ssh_accounts.categoriaid = servidores.subid
        WHERE ssh_accounts.status = 'Online'
        ORDER BY ssh_accounts.login ASC
        LIMIT $limite_por_pagina OFFSET $offset";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Usuários Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        /* ========== VARIÁVEIS DO TEMA ATIVO ========== */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--fundo, #0f172a);
            color: var(--texto, #ffffff);
            min-height: 100vh;
        }

        .app-content {
            margin-left: 240px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 1400px;
            margin: 0 auto !important;
            padding: 20px !important;
        }

        /* Header */
        .page-header {
            margin-bottom: 20px;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, var(--primaria, #10b981));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-title i {
            -webkit-text-fill-color: var(--primaria, #10b981);
            font-size: 26px;
        }

        .page-subtitle {
            color: rgba(255,255,255,0.5);
            font-size: 12px;
            margin-top: 4px;
        }

        /* Filtros */
        .filters-card {
            background: var(--fundo_claro, #1e293b);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .filters-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--texto, #ffffff);
        }

        .filters-title i {
            color: var(--primaria, #10b981);
            font-size: 16px;
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .filter-item {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-input {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px;
            font-size: 12px;
            color: #ffffff !important;
            transition: all 0.2s;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primaria, #10b981);
            background: rgba(255,255,255,0.12);
        }

        .filter-input::placeholder {
            color: rgba(255,255,255,0.4);
        }

        /* Grid de usuários */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }

        /* Card */
        .user-card {
            background: var(--fundo_claro, #1e293b);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .user-card:hover {
            transform: translateY(-2px);
            border-color: var(--primaria, #10b981);
        }

        /* Header do card */
        .user-header {
            background: linear-gradient(135deg, 
                var(--primaria, #10b981), 
                var(--secundaria, #C850C0));
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
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
            flex-shrink: 0;
        }

        .user-text {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 14px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
            word-break: break-all;
        }

        .user-owner {
            font-size: 10px;
            color: rgba(255,255,255,0.7);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
        }

        .v2ray-badge {
            background: rgba(255,255,255,0.2);
            padding: 2px 6px;
            border-radius: 20px;
            font-size: 8px;
            font-weight: 600;
        }

        .online-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            margin-left: 8px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* Corpo do card */
        .user-body {
            padding: 12px;
        }

        /* Grid de informações - 2 colunas */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 8px;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            transition: all 0.2s;
        }

        .info-row:hover {
            background: rgba(255,255,255,0.06);
            border-color: var(--primaria, #10b981);
        }

        .info-icon {
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .info-content {
            flex: 1;
            min-width: 0;
        }

        .info-label {
            font-size: 9px;
            color: rgba(255,255,255,0.4);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 11px;
            font-weight: 600;
            word-break: break-all;
            color: var(--texto, #ffffff);
        }

        /* Cores dos ícones */
        .icon-dono { color: #818cf8; }
        .icon-tipo { color: #34d399; }
        .icon-limite { color: #fbbf24; }
        .icon-servidor { color: #60a5fa; }

        /* Botões de ação */
        .user-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .action-btn {
            flex: 1;
            padding: 6px 10px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 11px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: white;
            transition: all 0.2s;
        }

        .btn-suspender {
            background: linear-gradient(135deg, #f59e0b, #f97316);
        }

        .btn-excluir {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .action-btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
        }

        /* Controles de paginação */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 24px;
            padding: 12px 0;
        }

        .limit-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--fundo_claro, #1e293b);
            padding: 5px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .limit-selector label {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .limit-selector select {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            padding: 5px 10px;
            color: #ffffff !important;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
        }

        .limit-selector select:focus {
            outline: none;
            border-color: var(--primaria, #10b981);
        }

        .limit-selector select option {
            background: #1e293b;
            color: #ffffff !important;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            background: var(--fundo_claro, #1e293b);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: var(--texto, #fff);
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: var(--primaria, #10b981);
            border-color: var(--primaria, #10b981);
        }

        .pagination .active {
            background: var(--primaria, #10b981);
            border-color: var(--primaria, #10b981);
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-info {
            text-align: center;
            margin-top: 12px;
            color: rgba(255,255,255,0.4);
            font-size: 11px;
        }

        /* Estado vazio */
        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 40px;
            background: var(--fundo_claro, #1e293b);
            border-radius: 16px;
        }

        .empty-state i {
            font-size: 48px;
            color: rgba(255,255,255,0.2);
            margin-bottom: 12px;
        }

        .empty-state h3 {
            font-size: 16px;
            margin-bottom: 6px;
        }

        .empty-state p {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
        }

        /* ========== MODAIS ========== */
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
            z-index: 10000;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.3s ease;
            max-width: 450px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95) translateY(-20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-content-custom {
            background: var(--fundo_claro, #1e293b);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 600;
            color: white;
        }

        .modal-header-custom.success { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header-custom.error { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header-custom.warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header-custom.processing { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 22px;
            cursor: pointer;
            opacity: 0.7;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 20px;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 12px 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .modal-icon-large {
            text-align: center;
            margin-bottom: 16px;
        }

        .modal-icon-large i {
            font-size: 54px;
        }

        .modal-info-card {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 12px;
        }

        .modal-info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .modal-info-row:last-child { border-bottom: none; }

        .modal-info-label {
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .modal-info-value {
            font-size: 12px;
            font-weight: 700;
            color: white;
        }

        .modal-info-value.credential {
            background: rgba(0,0,0,0.3);
            padding: 2px 8px;
            border-radius: 6px;
            font-family: monospace;
        }

        .btn-modal {
            padding: 8px 16px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: white;
            transition: all 0.2s;
        }

        .btn-modal:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
        }

        .btn-modal-cancel { background: linear-gradient(135deg, #64748b, #475569); }
        .btn-modal-ok { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-modal-warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .btn-modal-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }

        /* Spinner */
        .processing-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
        }

        .spinner-ring {
            width: 48px;
            height: 48px;
            border: 3px solid rgba(255,255,255,0.1);
            border-top-color: var(--primaria, #10b981);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Toast */
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 10px 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 10001;
            animation: slideInRight 0.3s ease;
            font-size: 12px;
            font-weight: 600;
        }

        .toast-notification.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        @keyframes slideInRight {
            from { transform: translateX(110%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Responsivo Mobile */
        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { padding: 12px !important; }
            .users-grid { grid-template-columns: 1fr; gap: 12px; }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .user-actions {
                flex-direction: column;
                gap: 6px;
            }
            
            .action-btn {
                width: 100%;
            }
            
            .pagination-wrapper {
                flex-direction: row;
                justify-content: center;
                gap: 10px;
            }
            
            .limit-selector label span {
                display: none;
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
include('headeradmin2.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// ========== INCLUIR SISTEMA DE TEMAS ==========
include_once '../AegisCore/temas.php';
$temaAtual = initTemas($conn);
$listaTemas = getListaTemas($conn);

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

// Buscar total de usuários online
$sql_total = "SELECT COUNT(*) as total FROM ssh_accounts WHERE status = 'Online'";
$result_total = $conn->query($sql_total);
$total_registros = $result_total->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $limite_por_pagina);

// Buscar usuários online com paginação
$sql = "SELECT accounts.login AS acc_login, ssh_accounts.login AS ssh_login, ssh_accounts.id,
        ssh_accounts.limite, ssh_accounts.uuid, ssh_accounts.senha, ssh_accounts.expira,
        categorias.nome AS categoria_nome,
        servidores.nome AS servidor_nome
        FROM accounts 
        INNER JOIN ssh_accounts ON accounts.id = ssh_accounts.byid 
        LEFT JOIN categorias ON ssh_accounts.categoriaid = categorias.id
        LEFT JOIN servidores ON ssh_accounts.categoriaid = servidores.subid
        WHERE ssh_accounts.status = 'Online'
        ORDER BY ssh_accounts.login ASC
        LIMIT $limite_por_pagina OFFSET $offset";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Usuários Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        /* ========== VARIÁVEIS DO TEMA ATIVO ========== */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--fundo, #0f172a);
            color: var(--texto, #ffffff);
            min-height: 100vh;
        }

        .app-content {
            margin-left: 240px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 1400px;
            margin: 0 auto !important;
            padding: 20px !important;
        }

        /* Header */
        .page-header {
            margin-bottom: 20px;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, var(--primaria, #10b981));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-title i {
            -webkit-text-fill-color: var(--primaria, #10b981);
            font-size: 26px;
        }

        .page-subtitle {
            color: rgba(255,255,255,0.5);
            font-size: 12px;
            margin-top: 4px;
        }

        /* Filtros */
        .filters-card {
            background: var(--fundo_claro, #1e293b);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .filters-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--texto, #ffffff);
        }

        .filters-title i {
            color: var(--primaria, #10b981);
            font-size: 16px;
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .filter-item {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-input {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px;
            font-size: 12px;
            color: #ffffff !important;
            transition: all 0.2s;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primaria, #10b981);
            background: rgba(255,255,255,0.12);
        }

        .filter-input::placeholder {
            color: rgba(255,255,255,0.4);
        }

        /* Grid de usuários */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }

        /* Card */
        .user-card {
            background: var(--fundo_claro, #1e293b);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .user-card:hover {
            transform: translateY(-2px);
            border-color: var(--primaria, #10b981);
        }

        /* Header do card */
        .user-header {
            background: linear-gradient(135deg, 
                var(--primaria, #10b981), 
                var(--secundaria, #C850C0));
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
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
            flex-shrink: 0;
        }

        .user-text {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 14px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
            word-break: break-all;
        }

        .user-owner {
            font-size: 10px;
            color: rgba(255,255,255,0.7);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
        }

        .v2ray-badge {
            background: rgba(255,255,255,0.2);
            padding: 2px 6px;
            border-radius: 20px;
            font-size: 8px;
            font-weight: 600;
        }

        .online-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            margin-left: 8px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* Corpo do card */
        .user-body {
            padding: 12px;
        }

        /* Grid de informações - 2 colunas */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 8px;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            transition: all 0.2s;
        }

        .info-row:hover {
            background: rgba(255,255,255,0.06);
            border-color: var(--primaria, #10b981);
        }

        .info-icon {
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .info-content {
            flex: 1;
            min-width: 0;
        }

        .info-label {
            font-size: 9px;
            color: rgba(255,255,255,0.4);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 11px;
            font-weight: 600;
            word-break: break-all;
            color: var(--texto, #ffffff);
        }

        /* Cores dos ícones */
        .icon-dono { color: #818cf8; }
        .icon-tipo { color: #34d399; }
        .icon-limite { color: #fbbf24; }
        .icon-servidor { color: #60a5fa; }

        /* Botões de ação */
        .user-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .action-btn {
            flex: 1;
            padding: 6px 10px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 11px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: white;
            transition: all 0.2s;
        }

        .btn-suspender {
            background: linear-gradient(135deg, #f59e0b, #f97316);
        }

        .btn-excluir {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .action-btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
        }

        /* Controles de paginação */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 24px;
            padding: 12px 0;
        }

        .limit-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--fundo_claro, #1e293b);
            padding: 5px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .limit-selector label {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .limit-selector select {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            padding: 5px 10px;
            color: #ffffff !important;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
        }

        .limit-selector select:focus {
            outline: none;
            border-color: var(--primaria, #10b981);
        }

        .limit-selector select option {
            background: #1e293b;
            color: #ffffff !important;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            background: var(--fundo_claro, #1e293b);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: var(--texto, #fff);
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: var(--primaria, #10b981);
            border-color: var(--primaria, #10b981);
        }

        .pagination .active {
            background: var(--primaria, #10b981);
            border-color: var(--primaria, #10b981);
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-info {
            text-align: center;
            margin-top: 12px;
            color: rgba(255,255,255,0.4);
            font-size: 11px;
        }

        /* Estado vazio */
        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 40px;
            background: var(--fundo_claro, #1e293b);
            border-radius: 16px;
        }

        .empty-state i {
            font-size: 48px;
            color: rgba(255,255,255,0.2);
            margin-bottom: 12px;
        }

        .empty-state h3 {
            font-size: 16px;
            margin-bottom: 6px;
        }

        .empty-state p {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
        }

        /* ========== MODAIS ========== */
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
            z-index: 10000;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.3s ease;
            max-width: 450px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95) translateY(-20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-content-custom {
            background: var(--fundo_claro, #1e293b);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 600;
            color: white;
        }

        .modal-header-custom.success { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header-custom.error { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header-custom.warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header-custom.processing { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 22px;
            cursor: pointer;
            opacity: 0.7;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 20px;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 12px 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .modal-icon-large {
            text-align: center;
            margin-bottom: 16px;
        }

        .modal-icon-large i {
            font-size: 54px;
        }

        .modal-info-card {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 12px;
        }

        .modal-info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .modal-info-row:last-child { border-bottom: none; }

        .modal-info-label {
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .modal-info-value {
            font-size: 12px;
            font-weight: 700;
            color: white;
        }

        .modal-info-value.credential {
            background: rgba(0,0,0,0.3);
            padding: 2px 8px;
            border-radius: 6px;
            font-family: monospace;
        }

        .btn-modal {
            padding: 8px 16px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: white;
            transition: all 0.2s;
        }

        .btn-modal:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
        }

        .btn-modal-cancel { background: linear-gradient(135deg, #64748b, #475569); }
        .btn-modal-ok { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-modal-warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .btn-modal-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }

        /* Spinner */
        .processing-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
        }

        .spinner-ring {
            width: 48px;
            height: 48px;
            border: 3px solid rgba(255,255,255,0.1);
            border-top-color: var(--primaria, #10b981);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Toast */
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 10px 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 10001;
            animation: slideInRight 0.3s ease;
            font-size: 12px;
            font-weight: 600;
        }

        .toast-notification.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        @keyframes slideInRight {
            from { transform: translateX(110%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Responsivo Mobile */
        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { padding: 12px !important; }
            .users-grid { grid-template-columns: 1fr; gap: 12px; }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .user-actions {
                flex-direction: column;
                gap: 6px;
            }
            
            .action-btn {
                width: 100%;
            }
            
            .pagination-wrapper {
                flex-direction: row;
                justify-content: center;
                gap: 10px;
            }
            
            .limit-selector label span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">
                        <i class='bx bx-wifi'></i>
                        Usuários Online
                    </h1>
                    <div class="page-subtitle">
                        Total: <?php echo $total_registros; ?> usuário(s) conectado(s)
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-card">
                <div class="filters-title">
                    <i class='bx bx-filter-alt'></i>
                    Filtros de Busca
                </div>
                <div class="filter-group">
                    <div class="filter-item">
                        <div class="filter-label">BUSCAR POR USUÁRIO</div>
                        <input type="text" class="filter-input" id="searchInput"
                               placeholder="Digite o nome do usuário..."
                               onkeyup="filtrarUsuarios()">
                    </div>
                </div>
            </div>

            <!-- Grid de usuários -->
            <div class="users-grid" id="usersGrid">
                <?php
                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $login   = $row['ssh_login'];
                        $dono    = $row['acc_login'];
                        $senha   = $row['senha'];
                        $limite  = $row['limite'];
                        $expira  = $row['expira'];
                        $tipo    = !empty($row['uuid']) ? 'V2Ray' : 'SSH';
                        $servidor = $row['servidor_nome'] ?? $row['categoria_nome'] ?? 'N/A';
                        
                        $expira_formatada = date('d/m/Y', strtotime($expira));
                ?>
                <div class="user-card" data-usuario="<?php echo strtolower($login); ?>"
                     data-login="<?php echo htmlspecialchars($login); ?>"
                     data-senha="<?php echo htmlspecialchars($senha); ?>"
                     data-dono="<?php echo htmlspecialchars($dono); ?>"
                     data-tipo="<?php echo $tipo; ?>"
                     data-servidor="<?php echo htmlspecialchars($servidor); ?>"
                     data-expira="<?php echo $expira_formatada; ?>">
                    
                    <div class="user-header">
                        <div class="user-info">
                            <div class="user-avatar">
                                <i class='bx bx-user'></i>
                            </div>
                            <div class="user-text">
                                <div class="user-name">
                                    <?php echo htmlspecialchars($login); ?>
                                    <?php if ($tipo == 'V2Ray'): ?>
                                        <span class="v2ray-badge">V2RAY</span>
                                    <?php endif; ?>
                                    <span class="online-dot"></span>
                                </div>
                                <div class="user-owner">
                                    <i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($dono); ?>
                                </div>
                            </div>
                        </div>
                        <button class="btn-copy-card" onclick="copiarInfoCard(this, event)">
                            <i class='bx bx-copy'></i>
                            <span>Copiar</span>
                        </button>
                    </div>

                    <div class="user-body">
                        <!-- Grid 2x2 -->
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-wifi icon-tipo'></i></div>
                                <div class="info-content">
                                    <div class="info-label">TIPO</div>
                                    <div class="info-value"><?php echo $tipo; ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-group icon-limite'></i></div>
                                <div class="info-content">
                                    <div class="info-label">LIMITE</div>
                                    <div class="info-value"><?php echo $limite; ?> conexões</div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-server icon-servidor'></i></div>
                                <div class="info-content">
                                    <div class="info-label">SERVIDOR</div>
                                    <div class="info-value"><?php echo htmlspecialchars($servidor); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-calendar icon-calendar'></i></div>
                                <div class="info-content">
                                    <div class="info-label">EXPIRA</div>
                                    <div class="info-value"><?php echo $expira_formatada; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Botões de ação -->
                        <div class="user-actions">
                            <button class="action-btn btn-suspender" onclick="suspenderUsuario(<?php echo $row['id']; ?>, event)">
                                <i class='bx bx-pause'></i> Suspender
                            </button>
                            <button class="action-btn btn-excluir" onclick="excluirUsuario(<?php echo $row['id']; ?>, event)">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo '<div class="empty-state">';
                    echo '<i class="bx bx-wifi-off"></i>';
                    echo '<h3>Nenhum usuário online</h3>';
                    echo '<p>Não há usuários conectados no momento</p>';
                    echo '</div>';
                }
                ?>
            </div>

            <!-- Controles de Paginação -->
            <div class="pagination-wrapper">
                <div class="limit-selector">
                    <label><i class='bx bx-list-ul'></i> <span>Mostrar:</span></label>
                    <select id="limitSelect" onchange="mudarLimite()">
                        <option value="10" <?php echo $limite_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $limite_por_pagina == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo $limite_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limite_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>

                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php if ($pagina_atual > 1): ?>
                        <a href="?pagina=<?php echo $pagina_atual - 1; ?>&limite=<?php echo $limite_por_pagina; ?>">
                            <i class='bx bx-chevron-left'></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class='bx bx-chevron-left'></i></span>
                    <?php endif; ?>

                    <?php
                    $max_paginas_mostrar = 5;
                    $inicio = max(1, $pagina_atual - floor($max_paginas_mostrar / 2));
                    $fim = min($total_paginas, $inicio + $max_paginas_mostrar - 1);
                    
                    if ($inicio > 1) {
                        echo '<a href="?pagina=1&limite=' . $limite_por_pagina . '">1</a>';
                        if ($inicio > 2) echo '<span class="disabled">...</span>';
                    }
                    
                    for ($i = $inicio; $i <= $fim; $i++) {
                        if ($i == $pagina_atual) {
                            echo '<span class="active">' . $i . '</span>';
                        } else {
                            echo '<a href="?pagina=' . $i . '&limite=' . $limite_por_pagina . '">' . $i . '</a>';
                        }
                    }
                    
                    if ($fim < $total_paginas) {
                        if ($fim < $total_paginas - 1) echo '<span class="disabled">...</span>';
                        echo '<a href="?pagina=' . $total_paginas . '&limite=' . $limite_por_pagina . '">' . $total_paginas . '</a>';
                    }
                    ?>

                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="?pagina=<?php echo $pagina_atual + 1; ?>&limite=<?php echo $limite_por_pagina; ?>">
                            <i class='bx bx-chevron-right'></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class='bx bx-chevron-right'></i></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="pagination-info">
                Mostrando <?php echo $result->num_rows; ?> de <?php echo $total_registros; ?> usuários online | Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
            </div>
        </div>
    </div>

    <!-- ========== MODAIS ========== -->
    
    <!-- Modal Processando -->
    <div id="modalProcessando" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom processing">
                    <h5><i class='bx bx-loader-alt bx-spin'></i> Processando</h5>
                </div>
                <div class="modal-body-custom">
                    <div class="processing-spinner">
                        <div class="spinner-ring"></div>
                        <p class="spinner-text">Aguarde...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmar Suspensão -->
    <div id="modalConfirmarSuspensao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom warning">
                    <h5><i class='bx bx-pause-circle'></i> Confirmar Suspensão</h5>
                    <button class="modal-close" onclick="fecharModal('modalConfirmarSuspensao')">&times;</button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-icon-large warning">
                        <i class='bx bx-pause-circle'></i>
                    </div>
                    <div class="modal-info-card">
                        <div class="modal-info-row">
                            <span class="modal-info-label"><i class='bx bx-user'></i> Usuário</span>
                            <span class="modal-info-value credential" id="suspender-login">—</span>
                        </div>
                    </div>
                    <p style="text-align:center; font-size:11px; color:rgba(255,255,255,0.6);">Após suspenso, o usuário será desconectado.</p>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarSuspensao')">Cancelar</button>
                    <button class="btn-modal btn-modal-warning" id="btnConfirmarSuspensao">Suspender</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmar Exclusão -->
    <div id="modalConfirmarExclusao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5><i class='bx bx-trash'></i> Confirmar Exclusão</h5>
                    <button class="modal-close" onclick="fecharModal('modalConfirmarExclusao')">&times;</button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-icon-large error">
                        <i class='bx bx-trash'></i>
                    </div>
                    <div class="modal-info-card">
                        <div class="modal-info-row">
                            <span class="modal-info-label"><i class='bx bx-user'></i> Usuário</span>
                            <span class="modal-info-value credential" id="excluir-login">—</span>
                        </div>
                    </div>
                    <p style="text-align:center; font-size:11px; color:#f87171;">Esta ação não pode ser desfeita!</p>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarExclusao')">Cancelar</button>
                    <button class="btn-modal btn-modal-danger" id="btnConfirmarExclusao">Excluir</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5><i class='bx bx-check-circle'></i> Sucesso!</h5>
                    <button class="modal-close" onclick="fecharModalSucesso()">&times;</button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-icon-large success">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <p style="text-align:center;" id="sucesso-mensagem">Operação realizada!</p>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn-modal btn-modal-ok" onclick="fecharModalSucesso()">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                    <button class="modal-close" onclick="fecharModal('modalErro')">&times;</button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-icon-large error">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <p style="text-align:center;" id="erro-mensagem">Erro ao processar!</p>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        let _operacaoData = {};

        // Filtro de usuários
        function filtrarUsuarios() {
            let search = document.getElementById('searchInput').value.toLowerCase();
            
            document.querySelectorAll('.user-card').forEach(card => {
                let usuario = card.getAttribute('data-usuario');
                let searchMatch = usuario.includes(search);
                card.style.display = searchMatch ? 'block' : 'none';
            });
        }

        // Mudar limite por página
        function mudarLimite() {
            let limite = document.getElementById('limitSelect').value;
            let url = new URL(window.location.href);
            url.searchParams.set('limite', limite);
            url.searchParams.set('pagina', 1);
            window.location.href = url.toString();
        }

        // Copiar informações
        function copiarInfoCard(btn, event) {
            event.stopPropagation();
            const card = btn.closest('.user-card');
            if (!card) return;
            
            const login = card.getAttribute('data-login');
            const senha = card.getAttribute('data-senha');
            const dono = card.getAttribute('data-dono');
            const tipo = card.getAttribute('data-tipo');
            const servidor = card.getAttribute('data-servidor');
            const expira = card.getAttribute('data-expira');
            
            let texto = `👤 USUÁRIO ONLINE\n`;
            texto += `━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `Login: ${login}\n`;
            texto += `Senha: ${senha}\n`;
            texto += `Dono: ${dono}\n`;
            texto += `Tipo: ${tipo}\n`;
            texto += `Servidor: ${servidor}\n`;
            texto += `Expira em: ${expira}\n`;
            texto += `━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `Data: ${new Date().toLocaleString('pt-BR')}`;
            
            navigator.clipboard.writeText(texto).then(() => {
                btn.classList.add('copied');
                btn.innerHTML = '<i class="bx bx-check"></i> <span>Copiado!</span>';
                mostrarToast('Informações copiadas!');
                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<i class="bx bx-copy"></i> <span>Copiar</span>';
                }, 2000);
            }).catch(() => mostrarToast('Erro ao copiar!', true));
        }

        // Suspender usuário
        function suspenderUsuario(id, event) {
            event.stopPropagation();
            const card = event.target.closest('.user-card');
            const usuario = card?.getAttribute('data-login') || '';
            
            _operacaoData = { id, usuario };
            
            document.getElementById('suspender-login').textContent = usuario;
            
            document.getElementById('btnConfirmarSuspensao').onclick = () => {
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
                        mostrarSucesso(`Usuário ${_operacaoData.usuario} suspenso!`);
                    } else if (data == 'erro no servidor') {
                        mostrarErro('Erro no servidor!');
                    } else {
                        mostrarErro('Erro ao suspender!');
                    }
                },
                error: function() { esconderProcessando(); mostrarErro('Erro de conexão!'); }
            });
        }

        // Excluir usuário
        function excluirUsuario(id, event) {
            event.stopPropagation();
            const card = event.target.closest('.user-card');
            const usuario = card?.getAttribute('data-login') || '';
            
            _operacaoData = { id, usuario };
            
            document.getElementById('excluir-login').textContent = usuario;
            
            document.getElementById('btnConfirmarExclusao').onclick = () => {
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
                        mostrarSucesso(`Usuário ${_operacaoData.usuario} excluído!`);
                    } else {
                        mostrarErro('Erro ao excluir!');
                    }
                },
                error: function() { esconderProcessando(); mostrarErro('Erro de conexão!'); }
            });
        }

        // Toast
        function mostrarToast(msg, erro = false) {
            const toast = document.createElement('div');
            toast.className = 'toast-notification' + (erro ? ' error' : '');
            toast.innerHTML = `<i class="bx ${erro ? 'bx-error-circle' : 'bx-check-circle'}"></i> ${msg}`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // Modais
        function abrirModal(id) { document.getElementById(id).classList.add('show'); }
        function fecharModal(id) { document.getElementById(id).classList.remove('show'); }
        function mostrarProcessando() { abrirModal('modalProcessando'); }
        function esconderProcessando() { fecharModal('modalProcessando'); }

        function mostrarErro(mensagem) {
            document.getElementById('erro-mensagem').textContent = mensagem;
            abrirModal('modalErro');
        }

        function mostrarSucesso(mensagem) {
            document.getElementById('sucesso-mensagem').textContent = mensagem;
            abrirModal('modalSucesso');
        }

        function fecharModalSucesso() {
            fecharModal('modalSucesso');
            location.reload();
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('show');
            }
        });

        // ESC fecha modais
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show'));
            }
        });
    </script>
</body>
</html>
h2_tema ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">
                        <i class='bx bx-wifi'></i>
                        Usuários Online
                    </h1>
                    <div class="page-subtitle">
                        Total: <?php echo $total_registros; ?> usuário(s) conectado(s)
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-card">
                <div class="filters-title">
                    <i class='bx bx-filter-alt'></i>
                    Filtros de Busca
                </div>
                <div class="filter-group">
                    <div class="filter-item">
                        <div class="filter-label">BUSCAR POR USUÁRIO</div>
                        <input type="text" class="filter-input" id="searchInput"
                               placeholder="Digite o nome do usuário..."
                               onkeyup="filtrarUsuarios()">
                    </div>
                </div>
            </div>

            <!-- Grid de usuários -->
            <div class="users-grid" id="usersGrid">
                <?php
                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $login   = $row['ssh_login'];
                        $dono    = $row['acc_login'];
                        $senha   = $row['senha'];
                        $limite  = $row['limite'];
                        $expira  = $row['expira'];
                        $tipo    = !empty($row['uuid']) ? 'V2Ray' : 'SSH';
                        $servidor = $row['servidor_nome'] ?? $row['categoria_nome'] ?? 'N/A';
                        
                        $expira_formatada = date('d/m/Y', strtotime($expira));
                ?>
                <div class="user-card" data-usuario="<?php echo strtolower($login); ?>"
                     data-login="<?php echo htmlspecialchars($login); ?>"
                     data-senha="<?php echo htmlspecialchars($senha); ?>"
                     data-dono="<?php echo htmlspecialchars($dono); ?>"
                     data-tipo="<?php echo $tipo; ?>"
                     data-servidor="<?php echo htmlspecialchars($servidor); ?>"
                     data-expira="<?php echo $expira_formatada; ?>">
                    
                    <div class="user-header">
                        <div class="user-info">
                            <div class="user-avatar">
                                <i class='bx bx-user'></i>
                            </div>
                            <div class="user-text">
                                <div class="user-name">
                                    <?php echo htmlspecialchars($login); ?>
                                    <?php if ($tipo == 'V2Ray'): ?>
                                        <span class="v2ray-badge">V2RAY</span>
                                    <?php endif; ?>
                                    <span class="online-dot"></span>
                                </div>
                                <div class="user-owner">
                                    <i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($dono); ?>
                                </div>
                            </div>
                        </div>
                        <button class="btn-copy-card" onclick="copiarInfoCard(this, event)">
                            <i class='bx bx-copy'></i>
                            <span>Copiar</span>
                        </button>
                    </div>

                    <div class="user-body">
                        <!-- Grid 2x2 -->
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-wifi icon-tipo'></i></div>
                                <div class="info-content">
                                    <div class="info-label">TIPO</div>
                                    <div class="info-value"><?php echo $tipo; ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-group icon-limite'></i></div>
                                <div class="info-content">
                                    <div class="info-label">LIMITE</div>
                                    <div class="info-value"><?php echo $limite; ?> conexões</div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-server icon-servidor'></i></div>
                                <div class="info-content">
                                    <div class="info-label">SERVIDOR</div>
                                    <div class="info-value"><?php echo htmlspecialchars($servidor); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-calendar icon-calendar'></i></div>
                                <div class="info-content">
                                    <div class="info-label">EXPIRA</div>
                                    <div class="info-value"><?php echo $expira_formatada; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Botões de ação -->
                        <div class="user-actions">
                            <button class="action-btn btn-suspender" onclick="suspenderUsuario(<?php echo $row['id']; ?>, event)">
                                <i class='bx bx-pause'></i> Suspender
                            </button>
                            <button class="action-btn btn-excluir" onclick="excluirUsuario(<?php echo $row['id']; ?>, event)">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo '<div class="empty-state">';
                    echo '<i class="bx bx-wifi-off"></i>';
                    echo '<h3>Nenhum usuário online</h3>';
                    echo '<p>Não há usuários conectados no momento</p>';
                    echo '</div>';
                }
                ?>
            </div>

            <!-- Controles de Paginação -->
            <div class="pagination-wrapper">
                <div class="limit-selector">
                    <label><i class='bx bx-list-ul'></i> <span>Mostrar:</span></label>
                    <select id="limitSelect" onchange="mudarLimite()">
                        <option value="10" <?php echo $limite_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $limite_por_pagina == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo $limite_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limite_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>

                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php if ($pagina_atual > 1): ?>
                        <a href="?pagina=<?php echo $pagina_atual - 1; ?>&limite=<?php echo $limite_por_pagina; ?>">
                            <i class='bx bx-chevron-left'></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class='bx bx-chevron-left'></i></span>
                    <?php endif; ?>

                    <?php
                    $max_paginas_mostrar = 5;
                    $inicio = max(1, $pagina_atual - floor($max_paginas_mostrar / 2));
                    $fim = min($total_paginas, $inicio + $max_paginas_mostrar - 1);
                    
                    if ($inicio > 1) {
                        echo '<a href="?pagina=1&limite=' . $limite_por_pagina . '">1</a>';
                        if ($inicio > 2) echo '<span class="disabled">...</span>';
                    }
                    
                    for ($i = $inicio; $i <= $fim; $i++) {
                        if ($i == $pagina_atual) {
                            echo '<span class="active">' . $i . '</span>';
                        } else {
                            echo '<a href="?pagina=' . $i . '&limite=' . $limite_por_pagina . '">' . $i . '</a>';
                        }
                    }
                    
                    if ($fim < $total_paginas) {
                        if ($fim < $total_paginas - 1) echo '<span class="disabled">...</span>';
                        echo '<a href="?pagina=' . $total_paginas . '&limite=' . $limite_por_pagina . '">' . $total_paginas . '</a>';
                    }
                    ?>

                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="?pagina=<?php echo $pagina_atual + 1; ?>&limite=<?php echo $limite_por_pagina; ?>">
                            <i class='bx bx-chevron-right'></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class='bx bx-chevron-right'></i></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="pagination-info">
                Mostrando <?php echo $result->num_rows; ?> de <?php echo $total_registros; ?> usuários online | Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
            </div>
        </div>
    </div>

    <!-- ========== MODAIS ========== -->
    
    <!-- Modal Processando -->
    <div id="modalProcessando" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom processing">
                    <h5><i class='bx bx-loader-alt bx-spin'></i> Processando</h5>
                </div>
                <div class="modal-body-custom">
                    <div class="processing-spinner">
                        <div class="spinner-ring"></div>
                        <p class="spinner-text">Aguarde...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmar Suspensão -->
    <div id="modalConfirmarSuspensao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom warning">
                    <h5><i class='bx bx-pause-circle'></i> Confirmar Suspensão</h5>
                    <button class="modal-close" onclick="fecharModal('modalConfirmarSuspensao')">&times;</button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-icon-large warning">
                        <i class='bx bx-pause-circle'></i>
                    </div>
                    <div class="modal-info-card">
                        <div class="modal-info-row">
                            <span class="modal-info-label"><i class='bx bx-user'></i> Usuário</span>
                            <span class="modal-info-value credential" id="suspender-login">—</span>
                        </div>
                    </div>
                    <p style="text-align:center; font-size:11px; color:rgba(255,255,255,0.6);">Após suspenso, o usuário será desconectado.</p>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarSuspensao')">Cancelar</button>
                    <button class="btn-modal btn-modal-warning" id="btnConfirmarSuspensao">Suspender</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmar Exclusão -->
    <div id="modalConfirmarExclusao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5><i class='bx bx-trash'></i> Confirmar Exclusão</h5>
                    <button class="modal-close" onclick="fecharModal('modalConfirmarExclusao')">&times;</button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-icon-large error">
                        <i class='bx bx-trash'></i>
                    </div>
                    <div class="modal-info-card">
                        <div class="modal-info-row">
                            <span class="modal-info-label"><i class='bx bx-user'></i> Usuário</span>
                            <span class="modal-info-value credential" id="excluir-login">—</span>
                        </div>
                    </div>
                    <p style="text-align:center; font-size:11px; color:#f87171;">Esta ação não pode ser desfeita!</p>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarExclusao')">Cancelar</button>
                    <button class="btn-modal btn-modal-danger" id="btnConfirmarExclusao">Excluir</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5><i class='bx bx-check-circle'></i> Sucesso!</h5>
                    <button class="modal-close" onclick="fecharModalSucesso()">&times;</button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-icon-large success">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <p style="text-align:center;" id="sucesso-mensagem">Operação realizada!</p>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn-modal btn-modal-ok" onclick="fecharModalSucesso()">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                    <button class="modal-close" onclick="fecharModal('modalErro')">&times;</button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-icon-large error">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <p style="text-align:center;" id="erro-mensagem">Erro ao processar!</p>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        let _operacaoData = {};

        // Filtro de usuários
        function filtrarUsuarios() {
            let search = document.getElementById('searchInput').value.toLowerCase();
            
            document.querySelectorAll('.user-card').forEach(card => {
                let usuario = card.getAttribute('data-usuario');
                let searchMatch = usuario.includes(search);
                card.style.display = searchMatch ? 'block' : 'none';
            });
        }

        // Mudar limite por página
        function mudarLimite() {
            let limite = document.getElementById('limitSelect').value;
            let url = new URL(window.location.href);
            url.searchParams.set('limite', limite);
            url.searchParams.set('pagina', 1);
            window.location.href = url.toString();
        }

        // Copiar informações
        function copiarInfoCard(btn, event) {
            event.stopPropagation();
            const card = btn.closest('.user-card');
            if (!card) return;
            
            const login = card.getAttribute('data-login');
            const senha = card.getAttribute('data-senha');
            const dono = card.getAttribute('data-dono');
            const tipo = card.getAttribute('data-tipo');
            const servidor = card.getAttribute('data-servidor');
            const expira = card.getAttribute('data-expira');
            
            let texto = `👤 USUÁRIO ONLINE\n`;
            texto += `━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `Login: ${login}\n`;
            texto += `Senha: ${senha}\n`;
            texto += `Dono: ${dono}\n`;
            texto += `Tipo: ${tipo}\n`;
            texto += `Servidor: ${servidor}\n`;
            texto += `Expira em: ${expira}\n`;
            texto += `━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `Data: ${new Date().toLocaleString('pt-BR')}`;
            
            navigator.clipboard.writeText(texto).then(() => {
                btn.classList.add('copied');
                btn.innerHTML = '<i class="bx bx-check"></i> <span>Copiado!</span>';
                mostrarToast('Informações copiadas!');
                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<i class="bx bx-copy"></i> <span>Copiar</span>';
                }, 2000);
            }).catch(() => mostrarToast('Erro ao copiar!', true));
        }

        // Suspender usuário
        function suspenderUsuario(id, event) {
            event.stopPropagation();
            const card = event.target.closest('.user-card');
            const usuario = card?.getAttribute('data-login') || '';
            
            _operacaoData = { id, usuario };
            
            document.getElementById('suspender-login').textContent = usuario;
            
            document.getElementById('btnConfirmarSuspensao').onclick = () => {
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
                        mostrarSucesso(`Usuário ${_operacaoData.usuario} suspenso!`);
                    } else if (data == 'erro no servidor') {
                        mostrarErro('Erro no servidor!');
                    } else {
                        mostrarErro('Erro ao suspender!');
                    }
                },
                error: function() { esconderProcessando(); mostrarErro('Erro de conexão!'); }
            });
        }

        // Excluir usuário
        function excluirUsuario(id, event) {
            event.stopPropagation();
            const card = event.target.closest('.user-card');
            const usuario = card?.getAttribute('data-login') || '';
            
            _operacaoData = { id, usuario };
            
            document.getElementById('excluir-login').textContent = usuario;
            
            document.getElementById('btnConfirmarExclusao').onclick = () => {
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
                        mostrarSucesso(`Usuário ${_operacaoData.usuario} excluído!`);
                    } else {
                        mostrarErro('Erro ao excluir!');
                    }
                },
                error: function() { esconderProcessando(); mostrarErro('Erro de conexão!'); }
            });
        }

        // Toast
        function mostrarToast(msg, erro = false) {
            const toast = document.createElement('div');
            toast.className = 'toast-notification' + (erro ? ' error' : '');
            toast.innerHTML = `<i class="bx ${erro ? 'bx-error-circle' : 'bx-check-circle'}"></i> ${msg}`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // Modais
        function abrirModal(id) { document.getElementById(id).classList.add('show'); }
        function fecharModal(id) { document.getElementById(id).classList.remove('show'); }
        function mostrarProcessando() { abrirModal('modalProcessando'); }
        function esconderProcessando() { fecharModal('modalProcessando'); }

        function mostrarErro(mensagem) {
            document.getElementById('erro-mensagem').textContent = mensagem;
            abrirModal('modalErro');
        }

        function mostrarSucesso(mensagem) {
            document.getElementById('sucesso-mensagem').textContent = mensagem;
            abrirModal('modalSucesso');
        }

        function fecharModalSucesso() {
            fecharModal('modalSucesso');
            location.reload();
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('show');
            }
        });

        // ESC fecha modais
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show'));
            }
        });
    </script>
</body>
</html>

