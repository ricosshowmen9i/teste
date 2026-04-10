<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio926942($input)
    {
        ?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Usuários Expirados</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <?php
    error_reporting(0);
    session_start();
    include('../AegisCore/conexao.php');
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

    $sql = "SELECT * FROM configs";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $nomepainel = $row["nomepainel"];
            $logo       = $row["logo"];
            $icon       = $row["icon"];
        }
    }

    $sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
    $sql5 = $conn->query($sql5);
    $row  = $sql5->fetch_assoc();
    $validade = $row['expira'];
    $tipo     = $row['tipo'];
    $_SESSION['tipodeconta'] = $row['tipo'];

    date_default_timezone_set('America/Sao_Paulo');
    $hoje = date('Y-m-d H:i:s');

    if ($_SESSION['tipodeconta'] != 'Credito') {
        if ($validade < $hoje) {
            echo "<script>alert('Sua conta está vencida')</script>";
            echo "<script>window.location.href = '../home.php'</script>";
            unset($_POST['criaruser'], $_POST['usuariofin'], $_POST['senhafin'], $_POST['validadefin']);
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

    include('header2.php');

    date_default_timezone_set('America/Sao_Paulo');
    $data   = date('Y-m-d H:i:s');

    // Busca server-side via GET
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

    if (!empty($search)) {
        $slq = "SELECT * FROM ssh_accounts WHERE byid = '".$_SESSION['iduser']."' AND expira < '$data' AND login LIKE '%$search%' ORDER BY expira ASC";
    } else {
        $slq = "SELECT * FROM ssh_accounts WHERE byid = '".$_SESSION['iduser']."' AND expira < '$data' ORDER BY expira ASC";
    }
    $result = mysqli_query($conn, $slq);

    // Total geral (sem filtro de busca) para o badge
    $res_total  = mysqli_query($conn, "SELECT COUNT(*) as total FROM ssh_accounts WHERE byid = '".$_SESSION['iduser']."' AND expira < '$data'");
    $total_exp  = mysqli_fetch_assoc($res_total)['total'];
    ?>

    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Usuários Expirados</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <?php
    error_reporting(0);
    session_start();
    include('../AegisCore/conexao.php');
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

    $sql = "SELECT * FROM configs";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $nomepainel = $row["nomepainel"];
            $logo       = $row["logo"];
            $icon       = $row["icon"];
        }
    }

    $sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
    $sql5 = $conn->query($sql5);
    $row  = $sql5->fetch_assoc();
    $validade = $row['expira'];
    $tipo     = $row['tipo'];
    $_SESSION['tipodeconta'] = $row['tipo'];

    date_default_timezone_set('America/Sao_Paulo');
    $hoje = date('Y-m-d H:i:s');

    if ($_SESSION['tipodeconta'] != 'Credito') {
        if ($validade < $hoje) {
            echo "<script>alert('Sua conta está vencida')</script>";
            echo "<script>window.location.href = '../home.php'</script>";
            unset($_POST['criaruser'], $_POST['usuariofin'], $_POST['senhafin'], $_POST['validadefin']);
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

    include('header2.php');

    date_default_timezone_set('America/Sao_Paulo');
    $data   = date('Y-m-d H:i:s');

    // Busca server-side via GET
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

    if (!empty($search)) {
        $slq = "SELECT * FROM ssh_accounts WHERE byid = '".$_SESSION['iduser']."' AND expira < '$data' AND login LIKE '%$search%' ORDER BY expira ASC";
    } else {
        $slq = "SELECT * FROM ssh_accounts WHERE byid = '".$_SESSION['iduser']."' AND expira < '$data' ORDER BY expira ASC";
    }
    $result = mysqli_query($conn, $slq);

    // Total geral (sem filtro de busca) para o badge
    $res_total  = mysqli_query($conn, "SELECT COUNT(*) as total FROM ssh_accounts WHERE byid = '".$_SESSION['iduser']."' AND expira < '$data'");
    $total_exp  = mysqli_fetch_assoc($res_total)['total'];
    ?>

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

        /* STATUS ROW - LADO A LADO */
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

        .status-expirado {
            background: rgba(220, 38, 38, 0.2);
            color: #dc2626;
            border-color: rgba(220, 38, 38, 0.3);
        }

        .status-suspended {
            background: rgba(220, 38, 38, 0.2);
            color: #dc2626;
            border-color: rgba(220, 38, 38, 0.3);
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

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-server { color: #60a5fa; }
        .icon-note { color: #a78bfa; }

        .expiry-danger { color: #f87171; }

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

        .btn-excluir {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        .btn-excluir:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(220,38,38,0.4);
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

        .btn-excluir-todos {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border: none;
            border-radius: 30px;
            padding: 8px 20px;
            color: white;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            box-shadow: 0 4px 12px rgba(220,38,38,0.3);
        }

        .btn-excluir-todos:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(220,38,38,0.4);
        }

        .btn-excluir-todos i {
            font-size: 16px;
        }

        .pagination-info {
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
            font-size: 13px;
        }

        /* =============================================
           MODAIS
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

        .modal-danger-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-danger-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 15px rgba(220, 38, 38, 0.5));
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

        .btn-modal-success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .btn-modal-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(16,185,129,0.5);
        }

        /* Spinner */
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
            border-top-color: #dc2626;
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
            to   { transform: translateX(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { margin: 0 auto !important; padding: 10px !important; }
            .users-grid { grid-template-columns: 1fr; gap: 12px; }
            .user-actions { display: grid; grid-template-columns: repeat(1, 1fr); gap: 6px; }
            .action-btn { width: 100%; }
            
            /* GRID 2 COLUNAS NO MOBILE */
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
            .btn-excluir-todos { width: 100%; justify-content: center; }
            .btn-copy-card { padding: 5px 10px; font-size: 11px; }
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
                <i class='bx bx-calendar-x'></i>
                <span>Usuários Expirados</span>
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
                               value="<?php echo htmlspecialchars($search); ?>"
                               onkeyup="filtrarLive(this.value)">
                    </div>
                    <div class="filter-item">
                        <div class="filter-label">FILTRAR POR STATUS</div>
                        <select class="filter-select" id="statusFilter" onchange="filtrarStatus()">
                            <option value="todos">Todos</option>
                            <option value="expirado">Expirado</option>
                            <option value="suspenso">Suspenso</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Botão excluir todos -->
            <button class="btn-excluir-todos" onclick="confirmarExcluirTodos()">
                <i class='bx bx-trash'></i> Excluir Todos os Expirados
            </button>

            <!-- Grid de usuários -->
            <div class="users-grid" id="usersGrid">
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $id        = $row['id'];
                        $login     = $row['login'];
                        $senha     = $row['senha'];
                        $limite    = $row['limite'];
                        $validade  = $row['expira'];
                        $categoria = $row['categoriaid'];
                        $suspenso  = $row['mainid'];
                        $notas     = $row['lastview'];

                        $expira_formatada = date('d/m/Y', strtotime($validade));

                        $status_classe = 'status-expirado';
                        $status_texto  = 'Expirado';
                        $status_filter = 'expirado';
                        $status_badge = '<span class="status-badge status-expirado"><i class="bx bx-calendar-x"></i> Expirado</span>';

                        if ($suspenso == 'Suspenso') {
                            $status_classe = 'status-suspended';
                            $status_texto  = 'Suspenso';
                            $status_filter = 'suspenso';
                            $status_badge = '<span class="status-badge status-suspended"><i class="bx bx-lock"></i> Suspenso</span>';
                        }
                        
                        $validade_badge = '<span class="status-badge status-expirado"><i class="bx bx-calendar-x"></i> ' . $expira_formatada . '</span>';
                ?>
                <div class="user-card"
                     data-status="<?php echo $status_filter; ?>"
                     data-login="<?php echo strtolower($login); ?>"
                     data-id="<?php echo $id; ?>"
                     data-usuario="<?php echo htmlspecialchars($login); ?>"
                     data-senha="<?php echo htmlspecialchars($senha); ?>"
                     data-expira="<?php echo $expira_formatada; ?>">

                    <div class="user-header">
                        <div class="user-info">
                            <div class="user-avatar">
                                <i class='bx bx-user'></i>
                            </div>
                            <div class="user-text">
                                <div class="user-name">
                                    <?php echo htmlspecialchars($login); ?>
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
                                    <div class="status-label">EXPIROU EM</div>
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
                                    <div class="info-value"><?php echo $limite; ?></div>
                                </div>
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
                            <button class="action-btn btn-excluir" onclick="confirmarExcluir(<?php echo $id; ?>, '<?php echo htmlspecialchars($login); ?>')">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo '<div class="empty-state">';
                    echo '<i class="bx bx-check-circle"></i>';
                    echo '<h3>Nenhum usuário expirado</h3>';
                    echo '<p>' . (!empty($search) ? 'Nenhum resultado para "' . htmlspecialchars($search) . '"' : 'Todos os usuários estão com validade em dia') . '</p>';
                    echo '</div>';
                }
                ?>
            </div>

            <div class="pagination-info">
                Exibindo <?php echo $result->num_rows; ?> de <?php echo $total_exp; ?> usuário(s) expirado(s)
                <?php if (!empty($search)): ?>
                — busca: <strong style="color:#818cf8;"><?php echo htmlspecialchars($search); ?></strong>
                <a href="?" style="color:#f87171; font-size:11px; margin-left:6px; text-decoration:none;"><i class='bx bx-x'></i> limpar</a>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- =============================================
         MODAL CONFIRMAR EXCLUSÃO
         ============================================= -->
    <div id="modalConfirmarExcluir" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-trash'></i>
                        Confirmar Exclusão
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarExcluir')">
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
                                <i class='bx bx-calendar' style="color:#fbbf24;"></i> Expirou em
                            </div>
                            <div class="modal-info-value" id="excluir-expira">—</div>
                        </div>
                    </div>
                    <p style="text-align:center; color: rgba(255,255,255,0.5); font-size: 12px; margin-top: 4px;">
                        ⚠️ Esta ação não pode ser desfeita! O usuário será permanentemente removido.
                    </p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarExcluir')">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn-modal btn-modal-danger" id="btnConfirmarExcluir">
                        <i class='bx bx-trash'></i> Excluir Permanentemente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL CONFIRMAR EXCLUSÃO DE TODOS
         ============================================= -->
    <div id="modalConfirmarTodos" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-trash'></i>
                        Excluir Todos os Expirados
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarTodos')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-danger-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color:white; text-align:center; margin-bottom:15px;">Tem certeza?</h3>
                    <p style="color:rgba(255,255,255,0.8); text-align:center;">
                        Todos os <strong style="color:#f87171;"><?php echo $total_exp; ?> usuários expirados</strong> serão removidos permanentemente.<br>
                        Esta ação não pode ser desfeita!
                    </p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarTodos')">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="button" class="btn-modal btn-modal-danger" id="btnExcluirTodos">
                        <i class='bx bx-trash'></i> Excluir Todos
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL PROCESSANDO
         ============================================= -->
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
                        <p class="spinner-text">Aguarde enquanto processamos sua solicitação...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================
         MODAL SUCESSO
         ============================================= -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Operação Realizada!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <p style="color:rgba(255,255,255,0.9); text-align:center; font-size:14px;" id="sucesso-mensagem">
                        Usuário excluído com sucesso!
                    </p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-success" onclick="fecharModalSucesso()">
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
                    <div class="modal-danger-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color:white; margin-bottom:10px; text-align:center;">Ops! Algo deu errado</h3>
                    <p style="color:rgba(255,255,255,0.8); text-align:center;" id="erro-mensagem">
                        Erro ao excluir usuário!
                    </p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>

    <script>
        // Função para obter card pelo ID
        function getCard(id) {
            return document.querySelector(`.user-card[data-id="${id}"]`);
        }

        // Função de copiar informações do card
        function copiarInfoCard(id, event) {
            event.stopPropagation();
            const card = getCard(id);
            if (!card) return;
            
            const usuario = card.getAttribute('data-usuario');
            const senha = card.getAttribute('data-senha');
            const expira = card.getAttribute('data-expira');
            
            let texto = `📋 INFORMAÇÕES DO USUÁRIO EXPIROU\n━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `👤 Login: ${usuario}\n`;
            texto += `🔑 Senha: ${senha}\n`;
            texto += `📅 Expirou em: ${expira}\n`;
            texto += `⚠️ Conta expirada\n`;
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

        function fecharModalSucesso() {
            fecharModal('modalSucesso');
            location.reload();
        }

        // Filtro de busca em tempo real
        function filtrarLive(valor) {
            let search = valor.toLowerCase();
            let cards = document.querySelectorAll('.user-card');
            let count = 0;
            
            cards.forEach(card => {
                let login = card.getAttribute('data-login');
                let matches = login.includes(search);
                card.style.display = matches ? 'block' : 'none';
                if (matches) count++;
            });
            
            let infoDiv = document.querySelector('.pagination-info');
            if (infoDiv && infoDiv.innerHTML.includes('Exibindo')) {
                let total = cards.length;
                infoDiv.innerHTML = infoDiv.innerHTML.replace(/Exibindo \d+ de/, 'Exibindo ' + count + ' de');
            }
        }

        // Filtro por status
        function filtrarStatus() {
            let status = document.getElementById('statusFilter').value;
            let cards = document.querySelectorAll('.user-card');
            let count = 0;
            
            cards.forEach(card => {
                if (status === 'todos') {
                    card.style.display = 'block';
                    count++;
                } else {
                    let match = card.getAttribute('data-status') === status;
                    card.style.display = match ? 'block' : 'none';
                    if (match) count++;
                }
            });
            
            let infoDiv = document.querySelector('.pagination-info');
            if (infoDiv && infoDiv.innerHTML.includes('Exibindo')) {
                let total = cards.length;
                infoDiv.innerHTML = infoDiv.innerHTML.replace(/Exibindo \d+ de/, 'Exibindo ' + count + ' de');
            }
        }

        // Excluir individual
        function confirmarExcluir(id, login) {
            const card = getCard(id);
            const senha = card?.getAttribute('data-senha') || '';
            const expira = card?.getAttribute('data-expira') || '';
            
            document.getElementById('excluir-login').textContent = login;
            document.getElementById('excluir-senha').textContent = senha;
            document.getElementById('excluir-expira').textContent = expira;
            
            document.getElementById('btnConfirmarExcluir').onclick = function() {
                fecharModal('modalConfirmarExcluir');
                abrirModal('modalProcessando');
                
                $.ajax({
                    url: 'excluiruser.php?id=' + id,
                    type: 'GET',
                    success: function(data) {
                        fecharModal('modalProcessando');
                        data = data.replace(/(\r\n|\n|\r)/gm, "");
                        if (data === 'excluido') {
                            document.getElementById('sucesso-mensagem').textContent = `✅ Usuário ${login} excluído com sucesso!`;
                            abrirModal('modalSucesso');
                        } else {
                            document.getElementById('erro-mensagem').textContent = '❌ Erro ao excluir o usuário!';
                            abrirModal('modalErro');
                        }
                    },
                    error: function() {
                        fecharModal('modalProcessando');
                        document.getElementById('erro-mensagem').textContent = '❌ Erro ao conectar com o servidor!';
                        abrirModal('modalErro');
                    }
                });
            };
            
            abrirModal('modalConfirmarExcluir');
        }

        // Excluir todos
        function confirmarExcluirTodos() {
            abrirModal('modalConfirmarTodos');
            document.getElementById('btnExcluirTodos').onclick = function() {
                fecharModal('modalConfirmarTodos');
                abrirModal('modalProcessando');
                window.location.href = 'deleteexpirados.php';
            };
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                const modalId = e.target.id;
                if (modalId === 'modalSucesso') {
                    fecharModalSucesso();
                } else if (modalId !== 'modalProcessando') {
                    e.target.classList.remove('show');
                }
            }
        });

        // ESC fecha modais
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    fecharModalSucesso();
                } else {
                    document.querySelectorAll('.modal-overlay.show').forEach(modal => {
                        if (modal.id !== 'modalProcessando') {
                            modal.classList.remove('show');
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>
<?php
    }
    aleatorio926942($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
