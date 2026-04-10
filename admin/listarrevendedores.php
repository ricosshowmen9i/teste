<?php
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);



// ========== HANDLER AJAX: DELETAR REVENDEDOR ==========
if (isset($_POST['deletarrevenda']) && isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $del_id = (int) $_POST['deletarrevenda'];

    $sql_check = "SELECT * FROM accounts WHERE id = '$del_id' AND byid = '{$_SESSION['iduser']}'";
    $result_check = $conn->query($sql_check);

    if ($result_check && $result_check->num_rows > 0) {
        $del_data = $result_check->fetch_assoc();
        $del_login = $del_data['login'];

        $conn->query("DELETE FROM atribuidos WHERE userid = '$del_id'");
        $conn->query("DELETE FROM accounts WHERE id = '$del_id' AND byid = '{$_SESSION['iduser']}'");

        echo json_encode([
            'success' => true,
            'titulo' => 'Deletado!',
            'texto' => 'O revendedor "' . $del_login . '" foi removido com sucesso.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'titulo' => 'Erro ao Deletar',
            'texto' => 'Revendedor não encontrado ou sem permissão.'
        ]);
    }

    $conn->close();
    exit;
}

// ========== HANDLER AJAX: RENOVAR REVENDEDOR ==========
if (isset($_POST['renovarrevenda']) && isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $ren_id = (int) $_POST['renovarrevenda'];
    $ren_dias = (int) $_POST['dias'];

    if ($ren_dias < 1) {
        echo json_encode(['success' => false, 'titulo' => 'Erro', 'texto' => 'Informe um número de dias válido.']);
        $conn->close();
        exit;
    }

    $sql_check = "SELECT * FROM accounts WHERE id = '$ren_id' AND byid = '{$_SESSION['iduser']}'";
    $result_check = $conn->query($sql_check);

    if ($result_check && $result_check->num_rows > 0) {
        $ren_data = $result_check->fetch_assoc();

        $sql_atrib = "SELECT * FROM atribuidos WHERE userid = '$ren_id'";
        $result_atrib = $conn->query($sql_atrib);
        $atrib_data = $result_atrib ? $result_atrib->fetch_assoc() : null;

        if ($atrib_data) {
            $expira_atual = $atrib_data['expira'];
            $base_time = (strtotime($expira_atual) > time()) ? strtotime($expira_atual) : time();
            $nova_data = date('Y-m-d H:i:s', $base_time + ($ren_dias * 86400));

            $conn->query("UPDATE atribuidos SET expira = '$nova_data' WHERE userid = '$ren_id'");

            echo json_encode([
                'success' => true,
                'titulo' => 'Renovado!',
                'texto' => 'O revendedor "' . $ren_data['login'] . '" foi renovado por ' . $ren_dias . ' dias. Nova validade: ' . date('d/m/Y', strtotime($nova_data))
            ]);
        } else {
            echo json_encode(['success' => false, 'titulo' => 'Erro', 'texto' => 'Dados de atribuição não encontrados.']);
        }
    } else {
        echo json_encode(['success' => false, 'titulo' => 'Erro', 'texto' => 'Revendedor não encontrado.']);
    }

    $conn->close();
    exit;
}

// ========== HANDLER AJAX: SUSPENDER / REATIVAR ==========
if (isset($_POST['suspenderrevenda']) && isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $sus_id = (int) $_POST['suspenderrevenda'];
    $acao = $_POST['acao']; // 'suspender' ou 'reativar'

    $sql_check = "SELECT * FROM accounts WHERE id = '$sus_id' AND byid = '{$_SESSION['iduser']}'";
    $result_check = $conn->query($sql_check);

    if ($result_check && $result_check->num_rows > 0) {
        $sus_data = $result_check->fetch_assoc();

        if ($acao === 'suspender') {
            $conn->query("UPDATE atribuidos SET suspenso = 1 WHERE userid = '$sus_id'");
            echo json_encode([
                'success' => true,
                'titulo' => 'Suspenso!',
                'texto' => 'O revendedor "' . $sus_data['login'] . '" foi suspenso com sucesso.'
            ]);
        } else {
            $conn->query("UPDATE atribuidos SET suspenso = 0 WHERE userid = '$sus_id'");
            echo json_encode([
                'success' => true,
                'titulo' => 'Reativado!',
                'texto' => 'O revendedor "' . $sus_data['login'] . '" foi reativado com sucesso.'
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'titulo' => 'Erro', 'texto' => 'Revendedor não encontrado.']);
    }

    $conn->close();
    exit;
}

// ========== AGORA INCLUI O HEADER (após todos os handlers AJAX) ==========
include('headeradmin2.php');

// ========== SISTEMA DE TEMAS ==========
include_once '../AegisCore/temas.php';
$temaAtual = initTemas($conn);
$listaTemas = getListaTemas($conn);

if (!file_exists('suspenderrev.php')) {
    exit("<script>document.addEventListener('DOMContentLoaded',function(){mostrarModalResultado('error','Token Inválido','Arquivo de segurança não encontrado.');});</script>");
} else {
    include_once 'suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        $_SESSION['token_invalido_'] = true;
        echo "<script>location.href='../index.php';</script>";
        exit;
    }
}

// ========== PAGINAÇÃO ==========
$limite_por_pagina = isset($_GET['limite']) ? (int)$_GET['limite'] : 12;
$limite_por_pagina = in_array($limite_por_pagina, [12, 24, 48, 96]) ? $limite_por_pagina : 12;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $limite_por_pagina;

$search = '';
if (!empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
}

$sql_total = "SELECT COUNT(*) as total FROM accounts WHERE byid = '{$_SESSION['iduser']}'";
if (!empty($search)) {
    $sql_total = "SELECT COUNT(*) as total FROM accounts WHERE byid = '{$_SESSION['iduser']}' AND login LIKE '%$search%'";
}
$result_total = $conn->query($sql_total);
$total_registros = $result_total->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $limite_por_pagina);

if (!empty($search)) {
    $sql = "SELECT * FROM accounts WHERE byid = '{$_SESSION['iduser']}' AND login LIKE '%$search%' ORDER BY id DESC LIMIT $limite_por_pagina OFFSET $offset";
} else {
    $sql = "SELECT * FROM accounts WHERE byid = '{$_SESSION['iduser']}' ORDER BY id DESC LIMIT $limite_por_pagina OFFSET $offset";
}
$result = $conn->query($sql);

date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Lista de Revendedores</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        <?php echo getCSSVariables($temaAtual); ?>

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--fundo, #0f172a);
            color: var(--texto, #ffffff);
            min-height: 100vh;
        }

        .app-content { margin-left: 0px !important; padding: 0 !important; }
        .content-wrapper { max-width: 1680px; margin: 0 auto !important; padding: 20px !important; }

        /* ========== STATS CARD ========== */
        .stats-card {
            background: linear-gradient(135deg, var(--fundo_claro, #1e293b), var(--fundo, #0f172a));
            border-radius: 20px; padding: 20px 24px; margin-bottom: 24px;
            border: 1px solid rgba(255,255,255,0.08);
            display: flex; align-items: center; gap: 20px;
            position: relative; overflow: hidden; transition: all .3s ease;
        }
        .stats-card:hover { transform: translateY(-2px); border-color: var(--primaria, #10b981); }
        .stats-card-icon {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, var(--primaria, #10b981), var(--secundaria, #C850C0));
            border-radius: 18px; display: flex; align-items: center; justify-content: center;
            font-size: 32px; color: white; flex-shrink: 0;
        }
        .stats-card-content { flex: 1; }
        .stats-card-title { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.5); text-transform: uppercase; margin-bottom: 5px; }
        .stats-card-value {
            font-size: 36px; font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primaria, #10b981));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; line-height: 1;
        }
        .stats-card-subtitle { font-size: 12px; color: rgba(255,255,255,0.4); margin-top: 4px; }
        .stats-card-decoration { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); font-size: 80px; opacity: 0.05; }

        /* ========== FILTROS ========== */
        .filters-card {
            background: var(--fundo_claro, #1e293b); border-radius: 16px; padding: 16px; margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .filters-title { font-size: 13px; font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; color: var(--texto, #ffffff); }
        .filters-title i { color: var(--primaria, #10b981); font-size: 16px; }
        .filter-group { display: flex; flex-wrap: wrap; gap: 12px; }
        .filter-item { flex: 1; min-width: 160px; }
        .filter-label { font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.6); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-input, .filter-select {
            width: 100%; padding: 8px 12px; background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15); border-radius: 10px;
            font-size: 12px; color: #ffffff !important; transition: all 0.2s;
        }
        .filter-input:focus, .filter-select:focus { outline: none; border-color: var(--primaria, #10b981); background: rgba(255,255,255,0.12); }
        .filter-input::placeholder { color: rgba(255,255,255,0.4); }
        .filter-select {
            cursor: pointer; appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; background-size: 14px;
        }
        .filter-select option { background: #1e293b; color: #ffffff !important; padding: 8px; }

        /* ========== GRID ========== */
        .revendedores-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }

        /* ========== CARD ========== */
        .revendedor-card {
            background: var(--fundo_claro, #1e293b); border-radius: 16px; overflow: hidden;
            transition: all 0.2s; border: 1px solid rgba(255,255,255,0.08);
        }
        .revendedor-card:hover { transform: translateY(-2px); border-color: var(--primaria, #10b981); }
        .revendedor-header {
            background: linear-gradient(135deg, var(--primaria, #10b981), var(--secundaria, #C850C0));
            padding: 12px; display: flex; align-items: center; justify-content: space-between;
        }
        .revendedor-info { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
        .revendedor-avatar {
            width: 36px; height: 36px; background: rgba(255,255,255,0.2);
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .revendedor-text { flex: 1; min-width: 0; }
        .revendedor-nome { font-size: 14px; font-weight: 700; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .revendedor-sub { font-size: 10px; color: rgba(255,255,255,0.7); margin-top: 2px; }
        .revendedor-body { padding: 12px; }

        .status-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; }
        .status-item-card { display: flex; align-items: center; gap: 6px; padding: 6px 8px; background: rgba(255,255,255,0.03); border-radius: 10px; }
        .status-icon { width: 28px; height: 28px; background: rgba(255,255,255,0.05); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .status-content { flex: 1; }
        .status-label { font-size: 9px; color: rgba(255,255,255,0.4); font-weight: 600; margin-bottom: 2px; }
        .status-value { font-size: 11px; font-weight: 600; }

        .status-badge { display: inline-flex; align-items: center; gap: 3px; padding: 3px 8px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .status-ativo { background: rgba(16,185,129,0.2); color: #10b981; }
        .status-suspenso { background: rgba(220,38,38,0.2); color: #f87171; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 8px; }
        .info-row { display: flex; align-items: center; gap: 6px; padding: 6px 8px; background: rgba(255,255,255,0.03); border-radius: 8px; }
        .info-row-full { grid-column: 1 / -1; }
        .info-icon { width: 24px; height: 24px; background: rgba(255,255,255,0.05); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }
        .info-content { flex: 1; min-width: 0; }
        .info-label { font-size: 8px; color: rgba(255,255,255,0.4); font-weight: 600; margin-bottom: 1px; }
        .info-value { font-size: 10px; font-weight: 600; word-break: break-all; color: var(--texto, #ffffff); }
        .info-value.warning { color: #fbbf24; }
        .info-value.danger { color: #f87171; }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-credit { color: #60a5fa; }
        .icon-category { color: #f472b6; }

        /* ========== BOTÕES DE AÇÃO ========== */
        .revendedor-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .action-btn {
            flex: 1; min-width: 55px; padding: 6px 8px; border: none; border-radius: 8px;
            font-weight: 600; font-size: 10px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            gap: 4px; color: white; transition: all 0.2s;
        }
        .btn-edit { background: linear-gradient(135deg, #4158D0, #6366f1); }
        .btn-renew { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .btn-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .btn-info { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .btn-view { background: linear-gradient(135deg, #64748b, #475569); }
        .btn-enter { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
        .action-btn:hover { transform: translateY(-1px); filter: brightness(1.05); }
        .action-btn i { font-size: 11px; }
        .action-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; filter: none !important; }

        /* ========== PAGINAÇÃO ========== */
        .pagination-wrapper { display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap; margin-top: 24px; padding: 12px 0; }
        .limit-selector {
            display: flex; align-items: center; gap: 8px;
            background: var(--fundo_claro, #1e293b); padding: 5px 12px; border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .limit-selector label { font-size: 12px; color: rgba(255,255,255,0.7); display: flex; align-items: center; gap: 5px; }
        .limit-selector select {
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px; padding: 5px 10px; color: #ffffff !important;
            font-size: 12px; font-weight: 500; cursor: pointer;
        }
        .limit-selector select:focus { outline: none; border-color: var(--primaria, #10b981); }
        .limit-selector select option { background: #1e293b; color: #ffffff !important; }

        .pagination { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .pagination a, .pagination span {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 34px; height: 34px; padding: 0 10px;
            background: var(--fundo_claro, #1e293b);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px; color: var(--texto, #fff);
            text-decoration: none; font-size: 12px; font-weight: 500; transition: all 0.2s;
        }
        .pagination a:hover { background: var(--primaria, #10b981); border-color: var(--primaria, #10b981); }
        .pagination .active { background: var(--primaria, #10b981); border-color: var(--primaria, #10b981); }
        .pagination .disabled { opacity: 0.5; cursor: not-allowed; }
        .pagination-info { text-align: center; margin-top: 12px; color: rgba(255,255,255,0.4); font-size: 11px; }

        /* ========== ESTADO VAZIO ========== */
        .empty-state { grid-column: 1/-1; text-align: center; padding: 40px; background: var(--fundo_claro, #1e293b); border-radius: 16px; }
        .empty-state i { font-size: 48px; color: rgba(255,255,255,0.2); margin-bottom: 12px; display: block; }
        .empty-state h3 { font-size: 16px; margin-bottom: 6px; }
        .empty-state p { font-size: 12px; color: rgba(255,255,255,0.4); }

        /* ========== MODAIS - VISUAL ATUALIZADO ========== */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 10000; backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .modal-overlay.show { display: flex; }
        .modal-container {
            animation: modalBounceIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            max-width: 440px; width: 92%;
        }
        @keyframes modalBounceIn {
            0% { opacity: 0; transform: scale(0.7) translateY(-30px); }
            60% { opacity: 1; transform: scale(1.02) translateY(2px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }
        @keyframes modalShake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }
        .modal-content-custom {
            background: linear-gradient(145deg, var(--fundo_claro, #1e293b), rgba(15,23,42,0.98));
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 25px 60px rgba(0,0,0,0.5), 0 0 40px rgba(16,185,129,0.08);
        }
        .modal-header-custom {
            padding: 18px 24px; display: flex;
            align-items: center; justify-content: space-between;
            position: relative; overflow: hidden;
        }
        .modal-header-custom::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: inherit; filter: brightness(1.1); z-index: 0;
        }
        .modal-header-custom > * { position: relative; z-index: 1; }
        .modal-header-custom h5 {
            margin: 0; display: flex; align-items: center; gap: 10px;
            font-size: 16px; font-weight: 700; color: white;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .modal-header-custom.success { background: linear-gradient(135deg, #10b981, #059669, #047857); }
        .modal-header-custom.error { background: linear-gradient(135deg, #ef4444, #dc2626, #b91c1c); }
        .modal-header-custom.warning { background: linear-gradient(135deg, #f59e0b, #f97316, #ea580c); }
        .modal-header-custom.processing { background: linear-gradient(135deg, #4158D0, #C850C0, #FFCC70); background-size: 200% 200%; animation: gradientMove 2s ease infinite; }
        .modal-header-custom.purple { background: linear-gradient(135deg, #7c3aed, #6d28d9, #5b21b6); }
        .modal-header-custom.info { background: linear-gradient(135deg, #06b6d4, #0891b2, #0e7490); }
        @keyframes gradientMove { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }

        .modal-close {
            background: rgba(255,255,255,0.15); border: none; color: white;
            font-size: 20px; cursor: pointer; opacity: 0.9;
            width: 32px; height: 32px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .modal-close:hover { opacity: 1; background: rgba(255,255,255,0.25); transform: rotate(90deg); }
        .modal-body-custom { padding: 24px; }
        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.08);
            padding: 14px 24px; display: flex; justify-content: center; gap: 12px;
            background: rgba(0,0,0,0.15);
        }

        /* ========== ÍCONE GRANDE DO MODAL ========== */
        .modal-icon-large { text-align: center; margin-bottom: 20px; }
        .modal-icon-large i { font-size: 54px; }

        /* Ícone animado circular */
        .modal-icon-circle {
            width: 88px; height: 88px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; font-size: 42px;
            animation: iconPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
        }
        @keyframes iconPop {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .modal-icon-circle.success-circle {
            background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(5,150,105,0.15));
            color: #10b981; border: 2px solid rgba(16,185,129,0.3);
            box-shadow: 0 0 30px rgba(16,185,129,0.15);
        }
        .modal-icon-circle.error-circle {
            background: linear-gradient(135deg, rgba(239,68,68,0.2), rgba(220,38,38,0.15));
            color: #f87171; border: 2px solid rgba(239,68,68,0.3);
            box-shadow: 0 0 30px rgba(239,68,68,0.15);
        }
        .modal-icon-circle.warning-circle {
            background: linear-gradient(135deg, rgba(245,158,11,0.2), rgba(249,115,22,0.15));
            color: #fbbf24; border: 2px solid rgba(245,158,11,0.3);
            box-shadow: 0 0 30px rgba(245,158,11,0.15);
        }
        .modal-icon-circle.purple-circle {
            background: linear-gradient(135deg, rgba(124,58,237,0.2), rgba(91,33,182,0.15));
            color: #a78bfa; border: 2px solid rgba(124,58,237,0.3);
            box-shadow: 0 0 30px rgba(124,58,237,0.15);
        }
        .modal-icon-circle.info-circle {
            background: linear-gradient(135deg, rgba(6,182,212,0.2), rgba(8,145,178,0.15));
            color: #22d3ee; border: 2px solid rgba(6,182,212,0.3);
            box-shadow: 0 0 30px rgba(6,182,212,0.15);
        }

        .modal-titulo-texto {
            font-size: 18px; font-weight: 700; text-align: center;
            margin-bottom: 8px; color: #ffffff;
        }
        .modal-subtitulo-texto {
            font-size: 13px; text-align: center; color: rgba(255,255,255,0.6);
            line-height: 1.6;
        }
        .modal-subtitulo-texto strong { color: #ffffff; font-weight: 600; }
        .modal-subtitulo-texto .destaque-nome {
            display: inline-block; padding: 2px 10px; border-radius: 8px;
            background: rgba(255,255,255,0.08); color: #ffffff;
            font-weight: 700; font-size: 14px; margin: 4px 0;
        }
        .modal-nota {
            font-size: 11px; text-align: center; color: rgba(255,255,255,0.35);
            margin-top: 10px; font-style: italic;
        }

        /* ========== BOTÕES DO MODAL ========== */
        .btn-modal {
            padding: 10px 20px; border: none; border-radius: 12px;
            font-weight: 700; font-size: 13px; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            color: white; transition: all 0.25s;
            text-transform: none; letter-spacing: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .btn-modal:hover { transform: translateY(-2px); filter: brightness(1.1); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
        .btn-modal:active { transform: translateY(0); }
        .btn-modal-cancel { background: linear-gradient(135deg, #475569, #334155); }
        .btn-modal-ok { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-modal-warning { background: linear-gradient(135deg, #f59e0b, #ea580c); }
        .btn-modal-danger { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .btn-modal-purple { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
        .btn-modal-info { background: linear-gradient(135deg, #06b6d4, #0891b2); }

        /* ========== SPINNER PROCESSANDO ========== */
        .processing-spinner { display: flex; flex-direction: column; align-items: center; gap: 18px; padding: 20px 0; }
        .spinner-ring {
            width: 52px; height: 52px;
            border: 3px solid rgba(255,255,255,0.08);
            border-top-color: var(--primaria, #10b981);
            border-right-color: var(--secundaria, #C850C0);
            border-radius: 50%; animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .processing-text { font-size: 14px; color: rgba(255,255,255,0.7); font-weight: 500; }

        /* ========== INPUT MODAL ========== */
        .modal-input-group { display: flex; flex-direction: column; gap: 6px; margin-top: 16px; }
        .modal-input-label { font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; }
        .modal-input {
            width: 100%; padding: 10px 14px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px; font-size: 14px; font-weight: 600;
            color: #ffffff; text-align: center; transition: all 0.2s;
        }
        .modal-input:focus { outline: none; border-color: var(--primaria, #10b981); background: rgba(255,255,255,0.1); box-shadow: 0 0 0 3px rgba(16,185,129,0.15); }

        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { padding: 12px !important; }
            .revendedores-grid { grid-template-columns: 1fr; gap: 12px; }
            .revendedor-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; }
            .action-btn { min-width: auto; padding: 5px 4px; font-size: 9px; }
            .action-btn i { font-size: 12px; }
            .stats-card { padding: 16px; }
            .stats-card-icon { width: 48px; height: 48px; font-size: 26px; }
            .stats-card-value { font-size: 28px; }
            .modal-container { width: 95%; }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">

            <!-- STATS -->
            <div class="stats-card">
                <div class="stats-card-icon"><i class='bx bx-group'></i></div>
                <div class="stats-card-content">
                    <div class="stats-card-title">Lista de Revendedores</div>
                    <div class="stats-card-value"><?php echo $total_registros; ?></div>
                    <div class="stats-card-subtitle">revendedores cadastrados no sistema</div>
                </div>
                <div class="stats-card-decoration"><i class='bx bx-group'></i></div>
            </div>

            <!-- FILTROS -->
            <div class="filters-card">
                <div class="filters-title"><i class='bx bx-filter-alt'></i> Filtros de Busca</div>
                <div class="filter-group">
                    <div class="filter-item">
                        <div class="filter-label">BUSCAR POR LOGIN</div>
                        <input type="text" class="filter-input" id="searchInput"
                               placeholder="Digite o nome do revendedor..."
                               value="<?php echo htmlspecialchars($search); ?>"
                               onkeyup="filtrarRevendedores()">
                    </div>
                    <div class="filter-item">
                        <div class="filter-label">FILTRAR POR STATUS</div>
                        <select class="filter-select" id="statusFilter" onchange="filtrarRevendedores()">
                            <option value="todos">📋 Todos</option>
                            <option value="ativo">🟢 Ativo</option>
                            <option value="suspenso">🔒 Suspenso</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- PAGINAÇÃO TOPO -->
            <div class="pagination-wrapper">
                <div class="limit-selector">
                    <label><i class='bx bx-list-ul'></i> <span>Mostrar:</span></label>
                    <select id="limitSelect" onchange="mudarLimite()">
                        <option value="12" <?php echo $limite_por_pagina == 12 ? 'selected' : ''; ?>>12</option>
                        <option value="24" <?php echo $limite_por_pagina == 24 ? 'selected' : ''; ?>>24</option>
                        <option value="48" <?php echo $limite_por_pagina == 48 ? 'selected' : ''; ?>>48</option>
                        <option value="96" <?php echo $limite_por_pagina == 96 ? 'selected' : ''; ?>>96</option>
                    </select>
                </div>
                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php if ($pagina_atual > 1): ?>
                        <a href="?pagina=<?php echo $pagina_atual - 1; ?>&limite=<?php echo $limite_por_pagina; ?>&search=<?php echo urlencode($search); ?>"><i class='bx bx-chevron-left'></i></a>
                    <?php else: ?>
                        <span class="disabled"><i class='bx bx-chevron-left'></i></span>
                    <?php endif; ?>
                    <?php
                    $max_paginas_mostrar = 5;
                    $inicio = max(1, $pagina_atual - floor($max_paginas_mostrar / 2));
                    $fim = min($total_paginas, $inicio + $max_paginas_mostrar - 1);
                    if ($inicio > 1) {
                        echo '<a href="?pagina=1&limite='.$limite_por_pagina.'&search='.urlencode($search).'">1</a>';
                        if ($inicio > 2) echo '<span class="disabled">...</span>';
                    }
                    for ($i = $inicio; $i <= $fim; $i++) {
                        if ($i == $pagina_atual) echo '<span class="active">'.$i.'</span>';
                        else echo '<a href="?pagina='.$i.'&limite='.$limite_por_pagina.'&search='.urlencode($search).'">'.$i.'</a>';
                    }
                    if ($fim < $total_paginas) {
                        if ($fim < $total_paginas - 1) echo '<span class="disabled">...</span>';
                        echo '<a href="?pagina='.$total_paginas.'&limite='.$limite_por_pagina.'&search='.urlencode($search).'">'.$total_paginas.'</a>';
                    }
                    ?>
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="?pagina=<?php echo $pagina_atual + 1; ?>&limite=<?php echo $limite_por_pagina; ?>&search=<?php echo urlencode($search); ?>"><i class='bx bx-chevron-right'></i></a>
                    <?php else: ?>
                        <span class="disabled"><i class='bx bx-chevron-right'></i></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- GRID DE REVENDEDORES -->
            <div class="revendedores-grid" id="revendedoresGrid">
                <?php
                if ($result && $result->num_rows > 0):
                    while ($user_data = mysqli_fetch_assoc($result)):
                        $sql_atrib = "SELECT * FROM atribuidos WHERE userid = '".$user_data['id']."'";
                        $result_atrib = $conn->query($sql_atrib);
                        $user_data2 = mysqli_fetch_assoc($result_atrib);

                        $expira_raw = $user_data2['expira'] ?? '';
                        $expira_formatada = ($expira_raw != '') ? date('d/m/Y', strtotime($expira_raw)) : 'Nunca';
                        $expiry_class = '';
                        $expiry_texto = '';

                        if ($user_data2['tipo'] == 'Validade' && $expira_raw != '') {
                            $diferenca = strtotime($expira_raw) - time();
                            $dias_restantes = floor($diferenca / 86400);
                            $horas_restantes = floor(($diferenca % 86400) / 3600);
                            $expiry_texto = "{$dias_restantes}d {$horas_restantes}h";
                            if ($dias_restantes < 0) { $expiry_class = 'danger'; $expiry_texto = 'Expirado'; }
                            elseif ($dias_restantes <= 5) { $expiry_class = 'warning'; }
                        } elseif ($user_data2['tipo'] == 'Credito') {
                            $expiry_texto = 'Crédito';
                        } else {
                            $expiry_texto = $expira_formatada;
                        }

                        $status_classe = ($user_data2['suspenso'] ?? 0) == 0 ? 'status-ativo' : 'status-suspenso';
                        $status_texto = ($user_data2['suspenso'] ?? 0) == 0 ? 'Ativo' : 'Suspenso';

                        $sql_cat = "SELECT nome FROM categorias WHERE subid = '".$user_data2['categoriaid']."'";
                        $result_cat = $conn->query($sql_cat);
                        $cat_row = $result_cat ? $result_cat->fetch_assoc() : null;
                        $categoria_nome = $cat_row['nome'] ?? 'N/A';
                        $data_status_attr = strtolower($status_texto);
                ?>
                <div class="revendedor-card"
                     data-login="<?php echo strtolower(htmlspecialchars($user_data['login'])); ?>"
                     data-status="<?php echo $data_status_attr; ?>"
                     id="card-<?php echo $user_data['id']; ?>">

                    <div class="revendedor-header">
                        <div class="revendedor-info">
                            <div class="revendedor-avatar"><i class='bx bx-group'></i></div>
                            <div class="revendedor-text">
                                <div class="revendedor-nome"><?php echo htmlspecialchars($user_data['login']); ?></div>
                                <div class="revendedor-sub"><?php echo htmlspecialchars($user_data['senha']); ?></div>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $status_classe; ?>" style="flex-shrink:0;">
                            <i class='bx bx-<?php echo $status_texto == 'Ativo' ? 'check-circle' : 'lock'; ?>'></i>
                            <?php echo $status_texto; ?>
                        </span>
                    </div>

                    <div class="revendedor-body">
                        <div class="status-row">
                            <div class="status-item-card">
                                <div class="status-icon">
                                    <i class='bx bx-<?php echo $status_texto == 'Ativo' ? 'check-circle' : 'lock'; ?>'
                                       style="color:<?php echo $status_texto == 'Ativo' ? '#10b981' : '#f87171'; ?>;"></i>
                                </div>
                                <div class="status-content">
                                    <div class="status-label">STATUS</div>
                                    <span class="status-badge <?php echo $status_classe; ?>"><?php echo $status_texto; ?></span>
                                </div>
                            </div>
                            <div class="status-item-card">
                                <div class="status-icon"><i class='bx bx-calendar icon-calendar'></i></div>
                                <div class="status-content">
                                    <div class="status-label">VALIDADE</div>
                                    <div class="status-value <?php echo $expiry_class; ?>"><?php echo $expiry_texto ?: $expira_formatada; ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-user icon-user'></i></div>
                                <div class="info-content"><div class="info-label">LOGIN</div><div class="info-value"><?php echo htmlspecialchars($user_data['login']); ?></div></div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-lock-alt icon-lock'></i></div>
                                <div class="info-content"><div class="info-label">SENHA</div><div class="info-value"><?php echo htmlspecialchars($user_data['senha']); ?></div></div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-credit-card icon-credit'></i></div>
                                <div class="info-content"><div class="info-label">MODO</div><div class="info-value"><?php echo htmlspecialchars($user_data2['tipo'] ?? 'N/A'); ?></div></div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-category icon-category'></i></div>
                                <div class="info-content"><div class="info-label">CATEGORIA</div><div class="info-value"><?php echo htmlspecialchars($categoria_nome); ?></div></div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-group icon-group'></i></div>
                                <div class="info-content"><div class="info-label">LIMITE</div><div class="info-value"><?php echo $user_data2['limite'] ?? '0'; ?> conexões</div></div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-calendar-check icon-calendar'></i></div>
                                <div class="info-content"><div class="info-label">EXPIRA EM</div><div class="info-value <?php echo $expiry_class; ?>"><?php echo $user_data2['tipo'] == 'Credito' ? 'Crédito' : $expira_formatada; ?></div></div>
                            </div>
                            <?php if (!empty($user_data['whatsapp'])): ?>
                            <div class="info-row info-row-full">
                                <div class="info-icon"><i class='bx bxl-whatsapp' style="color:#25D366;"></i></div>
                                <div class="info-content"><div class="info-label">WHATSAPP</div><div class="info-value"><?php echo htmlspecialchars($user_data['whatsapp']); ?></div></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="revendedor-actions">
                            <button class="action-btn btn-edit" onclick="window.location.href='editarrevenda.php?id=<?php echo $user_data['id']; ?>'">
                                <i class='bx bx-edit'></i> Editar
                            </button>
                            <?php if ($user_data2['tipo'] != 'Credito'): ?>
                            <button class="action-btn btn-renew" onclick="renovar(<?php echo $user_data['id']; ?>, '<?php echo htmlspecialchars(addslashes($user_data['login'])); ?>')">
                                <i class='bx bx-calendar-plus'></i> Renovar
                            </button>
                            <?php endif; ?>
                            <?php if (($user_data2['suspenso'] ?? 0) == 0): ?>
                            <button class="action-btn btn-warning" onclick="suspender(<?php echo $user_data['id']; ?>, '<?php echo htmlspecialchars(addslashes($user_data['login'])); ?>', 'ativo')">
                                <i class='bx bx-pause'></i> Suspender
                            </button>
                            <?php else: ?>
                            <button class="action-btn btn-info" onclick="suspender(<?php echo $user_data['id']; ?>, '<?php echo htmlspecialchars(addslashes($user_data['login'])); ?>', 'suspenso')">
                                <i class='bx bx-refresh'></i> Reativar
                            </button>
                            <?php endif; ?>
                            <button class="action-btn btn-view" onclick="window.location.href='visualizarrevelanda.php?id=<?php echo $user_data['id']; ?>'">
                                <i class='bx bx-show'></i> Ver
                            </button>
                           
                            <button class="action-btn btn-danger" onclick="deletar(<?php echo $user_data['id']; ?>, '<?php echo htmlspecialchars(addslashes($user_data['login'])); ?>')">
                                <i class='bx bx-trash'></i> Deletar
                            </button>
                        </div>
                    </div>
                </div>
                <?php
                    endwhile;
                else:
                ?>
                <div class="empty-state">
                    <i class='bx bx-group'></i>
                    <h3>Nenhum revendedor encontrado</h3>
                    <p>Crie um novo revendedor para começar.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- PAGINAÇÃO INFERIOR -->
            <div class="pagination-wrapper">
                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php if ($pagina_atual > 1): ?>
                        <a href="?pagina=<?php echo $pagina_atual - 1; ?>&limite=<?php echo $limite_por_pagina; ?>&search=<?php echo urlencode($search); ?>"><i class='bx bx-chevron-left'></i></a>
                    <?php else: ?>
                        <span class="disabled"><i class='bx bx-chevron-left'></i></span>
                    <?php endif; ?>
                    <?php
                    for ($i = $inicio; $i <= $fim; $i++) {
                        if ($i == $pagina_atual) echo '<span class="active">'.$i.'</span>';
                        else echo '<a href="?pagina='.$i.'&limite='.$limite_por_pagina.'&search='.urlencode($search).'">'.$i.'</a>';
                    }
                    ?>
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="?pagina=<?php echo $pagina_atual + 1; ?>&limite=<?php echo $limite_por_pagina; ?>&search=<?php echo urlencode($search); ?>"><i class='bx bx-chevron-right'></i></a>
                    <?php else: ?>
                        <span class="disabled"><i class='bx bx-chevron-right'></i></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="pagination-info">
                Mostrando <?php echo min($offset + 1, $total_registros); ?>–<?php echo min($offset + $limite_por_pagina, $total_registros); ?> de <?php echo $total_registros; ?> revendedor(es)
            </div>

        </div>
    </div>

    <!-- ===================== MODAL: RENOVAR ===================== -->
    <div id="modalRenovar" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5><i class='bx bx-calendar-plus'></i> Renovar Revendedor</h5>
                    <button class="modal-close" onclick="fecharModal('modalRenovar')"><i class='bx bx-x'></i></button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-icon-circle success-circle">
                        <i class='bx bx-calendar-plus'></i>
                    </div>
                    <div class="modal-titulo-texto">Renovar Validade</div>
                    <div class="modal-subtitulo-texto">
                        Revendedor: <span class="destaque-nome" id="renovarNome"></span>
                    </div>
                    <div class="modal-input-group">
                        <label class="modal-input-label">Quantos dias deseja renovar?</label>
                        <input type="number" id="renovarDias" class="modal-input" value="30" min="1">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalRenovar')"><i class='bx bx-x'></i> Cancelar</button>
                    <button class="btn-modal btn-modal-ok" id="btnConfirmarRenovar" onclick="confirmarRenovar()"><i class='bx bx-check'></i> Renovar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== MODAL: SUSPENDER / REATIVAR ===================== -->
    <div id="modalSuspender" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom warning" id="suspenderHeader">
                    <h5><i class='bx bx-lock' id="suspenderHeaderIcon"></i> <span id="suspenderTitulo">Suspender Revendedor</span></h5>
                    <button class="modal-close" onclick="fecharModal('modalSuspender')"><i class='bx bx-x'></i></button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-icon-circle warning-circle" id="suspenderIconCircle">
                        <i class='bx bx-lock' id="suspenderIcon"></i>
                    </div>
                    <div class="modal-titulo-texto" id="suspenderTituloBody">Suspender Revendedor</div>
                    <div class="modal-subtitulo-texto">
                        <span id="suspenderTexto">Deseja suspender o revendedor</span><br>
                        <span class="destaque-nome" id="suspenderNome"></span>
                    </div>
                    <div class="modal-nota" id="suspenderNota">O revendedor não poderá acessar o sistema enquanto estiver suspenso.</div>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalSuspender')"><i class='bx bx-x'></i> Cancelar</button>
                    <button class="btn-modal btn-modal-warning" id="btnConfirmarSuspender" onclick="confirmarSuspender()"><i class='bx bx-check'></i> Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== MODAL: DELETAR ===================== -->
    <div id="modalDeletar" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5><i class='bx bx-trash'></i> Deletar Revendedor</h5>
                    <button class="modal-close" onclick="fecharModal('modalDeletar')"><i class='bx bx-x'></i></button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-icon-circle error-circle">
                        <i class='bx bx-trash'></i>
                    </div>
                    <div class="modal-titulo-texto">Tem certeza?</div>
                    <div class="modal-subtitulo-texto">
                        Você está prestes a deletar o revendedor<br>
                        <span class="destaque-nome" id="deletarNome"></span>
                    </div>
                    <div class="modal-nota">⚠️ Esta ação é irreversível. Todos os dados serão removidos permanentemente.</div>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalDeletar')"><i class='bx bx-x'></i> Cancelar</button>
                    <button class="btn-modal btn-modal-danger" id="btnConfirmarDeletar" onclick="confirmarDeletar()"><i class='bx bx-trash'></i> Deletar</button>
                </div>
            </div>
        </div>
    </div>

   

    <!-- ===================== MODAL: PROCESSANDO ===================== -->
    <div id="modalProcessando" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom processing">
                    <h5><i class='bx bx-loader-alt bx-spin'></i> Processando</h5>
                </div>
                <div class="modal-body-custom">
                    <div class="processing-spinner">
                        <div class="spinner-ring"></div>
                        <p class="processing-text" id="processandoTexto">Aguarde...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== MODAL: RESULTADO (SUCESSO / ERRO) ===================== -->
    <div id="modalResultado" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom" id="modalResultadoContent">
                <div class="modal-header-custom success" id="modalResultadoHeader">
                    <h5 id="modalResultadoHeaderTitle"><i class='bx bx-check-circle'></i> Sucesso</h5>
                    <button class="modal-close" onclick="fecharModalResultado()"><i class='bx bx-x'></i></button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-icon-circle success-circle" id="modalResultadoIconCircle">
                        <i class='bx bx-check-circle' id="modalResultadoIcon"></i>
                    </div>
                    <div class="modal-titulo-texto" id="modalResultadoTitulo">Sucesso!</div>
                    <div class="modal-subtitulo-texto" id="modalResultadoTexto">Operação realizada com sucesso.</div>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn-modal btn-modal-ok" id="modalResultadoBtn" onclick="fecharModalResultado()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // ===== Estado Global =====
    var _revId = null, _revName = null, _revStatus = null, _redirectUrl = null;

    // ===== Modal Helpers =====
    function abrirModal(id) { document.getElementById(id).classList.add('show'); }
    function fecharModal(id) { document.getElementById(id).classList.remove('show'); }
    function fecharTodosModais() {
        document.querySelectorAll('.modal-overlay.show').forEach(function(m) { m.classList.remove('show'); });
    }

    // Fechar clicando fora (exceto processando e resultado com redirect)
    document.querySelectorAll('.modal-overlay').forEach(function(o) {
        o.addEventListener('click', function(e) {
            if (e.target !== o) return;
            if (o.id === 'modalProcessando') return;
            if (o.id === 'modalResultado' && _redirectUrl) return;
            o.classList.remove('show');
        });
    });

    // Fechar com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m) {
                if (m.id === 'modalProcessando') return;
                if (m.id === 'modalResultado' && _redirectUrl) return;
                m.classList.remove('show');
            });
        }
    });

    // ===== AJAX Helper =====
    function ajaxPost(dados, callbackSucesso) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'listarrevendedores.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                fecharModal('modalProcessando');
                if (xhr.status === 200) {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.success) {
                            mostrarModalResultado('success', resp.titulo || 'Sucesso!', resp.texto || 'Operação concluída.', resp.redirect || null);
                            if (callbackSucesso) callbackSucesso(resp);
                        } else {
                            mostrarModalResultado('error', resp.titulo || 'Erro!', resp.texto || 'Ocorreu um erro.');
                        }
                    } catch(e) {
                        mostrarModalResultado('error', 'Erro de Comunicação', 'Não foi possível processar a resposta do servidor.');
                    }
                } else {
                    mostrarModalResultado('error', 'Erro de Conexão', 'Não foi possível conectar ao servidor. Tente novamente.');
                }
            }
        };
        xhr.send(dados);
    }

    // ===== Modal de Resultado (Sucesso / Erro) =====
    function mostrarModalResultado(tipo, titulo, texto, redirect) {
        fecharTodosModais();
        _redirectUrl = redirect || null;

        var header = document.getElementById('modalResultadoHeader');
        var headerTitle = document.getElementById('modalResultadoHeaderTitle');
        var iconCircle = document.getElementById('modalResultadoIconCircle');
        var icon = document.getElementById('modalResultadoIcon');
        var tituloEl = document.getElementById('modalResultadoTitulo');
        var textoEl = document.getElementById('modalResultadoTexto');
        var btn = document.getElementById('modalResultadoBtn');

        if (tipo === 'success') {
            header.className = 'modal-header-custom success';
            headerTitle.innerHTML = '<i class="bx bx-check-circle"></i> Sucesso';
            iconCircle.className = 'modal-icon-circle success-circle';
            icon.className = 'bx bx-check-circle';
            btn.className = 'btn-modal btn-modal-ok';
        } else {
            header.className = 'modal-header-custom error';
            headerTitle.innerHTML = '<i class="bx bx-error-circle"></i> Erro';
            iconCircle.className = 'modal-icon-circle error-circle';
            icon.className = 'bx bx-error-circle';
            btn.className = 'btn-modal btn-modal-danger';
        }

        tituloEl.textContent = titulo;
        textoEl.textContent = texto;

        // Re-trigger animação
        iconCircle.style.animation = 'none';
        iconCircle.offsetHeight;
        iconCircle.style.animation = '';

        abrirModal('modalResultado');

        // Auto-redirect se tiver
        if (_redirectUrl) {
            setTimeout(function() { window.location.href = _redirectUrl; }, 2500);
        }
    }

    function fecharModalResultado() {
        fecharModal('modalResultado');
        if (_redirectUrl) {
            window.location.href = _redirectUrl;
            _redirectUrl = null;
        } else {
            // Recarrega a página para atualizar os dados
            window.location.reload();
        }
    }

    // ===== Filtrar =====
    function filtrarRevendedores() {
        var busca = document.getElementById('searchInput').value.toLowerCase();
        var status = document.getElementById('statusFilter').value;
        document.querySelectorAll('.revendedor-card').forEach(function(card) {
            var login = card.getAttribute('data-login') || '';
            var st = card.getAttribute('data-status') || '';
            var matchBusca = login.includes(busca);
            var matchStatus = true;
            if (status === 'ativo') matchStatus = st === 'ativo';
            else if (status === 'suspenso') matchStatus = st === 'suspenso';
            card.style.display = (matchBusca && matchStatus) ? 'block' : 'none';
        });
    }

    // ===== Mudar limite =====
    function mudarLimite() {
        var l = document.getElementById('limitSelect').value;
        var url = new URL(window.location.href);
        url.searchParams.set('limite', l);
        url.searchParams.set('pagina', '1');
        window.location.href = url.toString();
    }

    // ==================== RENOVAR ====================
    function renovar(id, nome) {
        _revId = id; _revName = nome;
        document.getElementById('renovarNome').textContent = nome;
        document.getElementById('renovarDias').value = 30;
        abrirModal('modalRenovar');
    }

    function confirmarRenovar() {
        var dias = parseInt(document.getElementById('renovarDias').value);
        if (!dias || dias < 1) {
            fecharModal('modalRenovar');
            mostrarModalResultado('error', 'Valor Inválido', 'Informe um número de dias válido (mínimo 1).');
            return;
        }
        fecharModal('modalRenovar');
        document.getElementById('processandoTexto').textContent = 'Renovando revendedor...';
        abrirModal('modalProcessando');

        ajaxPost('renovarrevenda=' + _revId + '&dias=' + dias + '&ajax=1');
    }

    // ==================== SUSPENDER / REATIVAR ====================
    function suspender(id, nome, status) {
        _revId = id; _revName = nome; _revStatus = status;
        var isSuspenso = status === 'suspenso';

        document.getElementById('suspenderNome').textContent = nome;
        document.getElementById('suspenderTitulo').textContent = isSuspenso ? 'Reativar Revendedor' : 'Suspender Revendedor';
        document.getElementById('suspenderTituloBody').textContent = isSuspenso ? 'Reativar Revendedor' : 'Suspender Revendedor';
        document.getElementById('suspenderTexto').textContent = isSuspenso ? 'Deseja reativar o revendedor' : 'Deseja suspender o revendedor';
        document.getElementById('suspenderNota').textContent = isSuspenso
            ? 'O revendedor voltará a ter acesso ao sistema.'
            : 'O revendedor não poderá acessar o sistema enquanto estiver suspenso.';

        var headerEl = document.getElementById('suspenderHeader');
        var iconCircle = document.getElementById('suspenderIconCircle');
        var iconEl = document.getElementById('suspenderIcon');
        var headerIcon = document.getElementById('suspenderHeaderIcon');
        var btnEl = document.getElementById('btnConfirmarSuspender');

        if (isSuspenso) {
            headerEl.className = 'modal-header-custom info';
            iconCircle.className = 'modal-icon-circle info-circle';
            iconEl.className = 'bx bx-check-circle';
            headerIcon.className = 'bx bx-check-circle';
            btnEl.className = 'btn-modal btn-modal-info';
            btnEl.innerHTML = '<i class="bx bx-check"></i> Reativar';
        } else {
            headerEl.className = 'modal-header-custom warning';
            iconCircle.className = 'modal-icon-circle warning-circle';
            iconEl.className = 'bx bx-lock';
            headerIcon.className = 'bx bx-lock';
            btnEl.className = 'btn-modal btn-modal-warning';
            btnEl.innerHTML = '<i class="bx bx-check"></i> Suspender';
        }

        abrirModal('modalSuspender');
    }

    function confirmarSuspender() {
        fecharModal('modalSuspender');
        var isSuspenso = _revStatus === 'suspenso';
        document.getElementById('processandoTexto').textContent = isSuspenso ? 'Reativando revendedor...' : 'Suspendendo revendedor...';
        abrirModal('modalProcessando');

        var acao = isSuspenso ? 'reativar' : 'suspender';
        ajaxPost('suspenderrevenda=' + _revId + '&acao=' + acao + '&ajax=1');
    }

    // ==================== DELETAR ====================
    function deletar(id, nome) {
        _revId = id; _revName = nome;
        document.getElementById('deletarNome').textContent = nome;
        abrirModal('modalDeletar');
    }

    function confirmarDeletar() {
        fecharModal('modalDeletar');
        document.getElementById('processandoTexto').textContent = 'Deletando revendedor...';
        abrirModal('modalProcessando');

        ajaxPost('deletarrevenda=' + _revId + '&ajax=1', function(resp) {
            // Remove o card visualmente após deletar com sucesso
            var card = document.getElementById('card-' + _revId);
            if (card) card.style.display = 'none';
        });
    }

    </script>
</body>
</html>
<?php if (isset($conn)) $conn->close(); ?>

