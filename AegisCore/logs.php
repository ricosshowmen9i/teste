<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio924926($input)
    {
        ?>
    
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Sistema</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    
    <?php 
    error_reporting(0);
    session_start();
    include 'header2.php';
    include('conexao.php');
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }

    // Buscar logs
    $sql2 = "SELECT * FROM logs WHERE revenda = '$_SESSION[login]' OR byid = '$_SESSION[iduser]' ORDER BY id DESC";
    $result = mysqli_query($conn, $sql2);
    
    // Se nÃ£o houver registros, vamos criar alguns de exemplo para teste
    if (mysqli_num_rows($result) == 0) {
        // Inserir alguns logs de exemplo

        
        foreach ($exemplos as $exemplo) {
            $texto = $exemplo[0];
            $tempo = $exemplo[1];
            $data = date('Y-m-d H:i:s', strtotime("-$tempo"));
            
            $insert = "INSERT INTO logs (revenda, byid, texto, validade) VALUES (
                '{$_SESSION['login']}', 
                '{$_SESSION['iduser']}', 
                '$texto', 
                '$data'
            )";
            mysqli_query($conn, $insert);
        }
        
        // Buscar novamente apÃ³s inserir os exemplos
        $result = mysqli_query($conn, $sql2);
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
            echo "<script>alert('Token InvÃ¡lido!');</script>";
            echo "<script>location.href='../index.php';</script>";
            $telegram->sendMessage([
                'chat_id' => '2017803306',
                'text' => "O domÃ­nio " . $_SERVER['HTTP_HOST'] . " tentou acessar o painel com token - " . $_SESSION['token'] . " invÃ¡lido!"
            ]);
            $_SESSION['token_invalido_'] = true;
            exit;
        }
    }
    ?>

    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Sistema</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    
    <?php 
    error_reporting(0);
    session_start();
    include 'header2.php';
    include('conexao.php');
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }

    // Buscar logs
    $sql2 = "SELECT * FROM logs WHERE revenda = '$_SESSION[login]' OR byid = '$_SESSION[iduser]' ORDER BY id DESC";
    $result = mysqli_query($conn, $sql2);
    
    // Se nÃ£o houver registros, vamos criar alguns de exemplo para teste
    if (mysqli_num_rows($result) == 0) {
        // Inserir alguns logs de exemplo

        
        foreach ($exemplos as $exemplo) {
            $texto = $exemplo[0];
            $tempo = $exemplo[1];
            $data = date('Y-m-d H:i:s', strtotime("-$tempo"));
            
            $insert = "INSERT INTO logs (revenda, byid, texto, validade) VALUES (
                '{$_SESSION['login']}', 
                '{$_SESSION['iduser']}', 
                '$texto', 
                '$data'
            )";
            mysqli_query($conn, $insert);
        }
        
        // Buscar novamente apÃ³s inserir os exemplos
        $result = mysqli_query($conn, $sql2);
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
            echo "<script>alert('Token InvÃ¡lido!');</script>";
            echo "<script>location.href='../index.php';</script>";
            $telegram->sendMessage([
                'chat_id' => '2017803306',
                'text' => "O domÃ­nio " . $_SERVER['HTTP_HOST'] . " tentou acessar o painel com token - " . $_SESSION['token'] . " invÃ¡lido!"
            ]);
            $_SESSION['token_invalido_'] = true;
            exit;
        }
    }
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
            
            /* Cores Ãºnicas para Ã­cones */
            --icon-user: #4361ee;
            --icon-history: #f59e0b;
            --icon-calendar: #fbbf24;
            --icon-detail: #a78bfa;
        }

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
            margin-left: 245px !important;
            padding: 10 !important;
        }
        
      

        /* Info badge */
        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 10px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }


 .content-wrapper {
            max-width: 1635px;
            margin: 0 auto 0 0px !important;
            padding: 0px !important;
        }


        .info-badge i {
            font-size: 20px !important;
            color: var(--primary) !important;
        }

        /* Status Info */
        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 12px !important;
            padding: 10px 15px !important;
            margin-bottom: 15px !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
            color: white !important;
            max-width: 100% !important;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        /* AÃ§Ãµes de logs */
        .logs-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 8px 16px;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-action i {
            font-size: 16px;
        }

        .btn-excluir-todos {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        .btn-excluir-todos:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(220,38,38,0.4);
        }

        .btn-refresh {
            background: linear-gradient(135deg, #4158D0, #6366f1);
        }
        .btn-refresh:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(65,88,208,0.4);
        }

        /* Filtros Card */
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

        /* Grid de Logs */
        .logs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
            margin-top: 20px;
            width: 100%;
        }

        /* Card de Log */
        .log-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 16px !important;
            overflow: hidden !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            animation: fadeIn 0.4s ease !important;
            position: relative !important;
        }

        .log-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 80% 20%, rgba(65,88,208,0.1) 0%, transparent 60%);
            pointer-events: none;
        }

        .log-card:hover {
            transform: translateY(-3px) !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card-header-custom {
            background: linear-gradient(135deg, #C850C0, #4158D0) !important;
            color: white;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .header-title {
            font-size: 16px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .header-subtitle {
            font-size: 11px;
            color: rgba(255,255,255,0.7);
            margin-top: 2px;
        }

        .card-body-custom {
            padding: 16px;
            position: relative;
            z-index: 1;
        }

        .info-row {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            margin-bottom: 8px;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
        }

        .info-row:hover {
            border-color: var(--primary);
            background: rgba(255,255,255,0.05);
        }

        .info-icon {
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
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 13px;
            font-weight: 600;
            color: white;
            word-break: break-word;
            line-height: 1.4;
        }

        .icon-user     { color: #818cf8; }
        .icon-history  { color: #f59e0b; }
        .icon-calendar { color: #fbbf24; }
        .icon-detail   { color: #a78bfa; }

        .time-badge {
            display: inline-block;
            padding: 2px 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            font-size: 10px;
            color: rgba(255,255,255,0.7);
        }

        /* BotÃ£o excluir individual */
        .btn-delete-log {
            background: rgba(220, 38, 38, 0.2);
            color: #dc2626;
            border: 1px solid rgba(220, 38, 38, 0.3);
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 10px;
            width: 100%;
        }

        .btn-delete-log:hover {
            background: rgba(220, 38, 38, 0.3);
            transform: translateY(-2px);
        }

        .btn-delete-log i {
            font-size: 16px;
        }

        /* Empty state */
        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 50px 20px;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.08);
            color: white;
        }

        .empty-state i {
            font-size: 60px;
            color: rgba(255,255,255,0.2);
            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: white;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: rgba(255,255,255,0.3);
            font-size: 14px;
        }

        /* Alert */
        .alert-warning {
            background: rgba(245, 158, 11, 0.15) !important;
            border: 1px solid rgba(245, 158, 11, 0.3) !important;
            color: #fbbf24 !important;
            border-radius: 12px !important;
            padding: 12px 15px !important;
            margin-bottom: 15px !important;
            font-size: 13px !important;
        }

        /* PaginaÃ§Ã£o info */
        .pagination-info {
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,0.5);
            font-size: 13px;
            font-weight: 600;
        }

    
           /* ===== AJUSTES APENAS PARA MOBILE ===== */
@media (max-width: 768px) {
    .app-content {
        margin-left: 0 !important;
    }
    

    .info-badge {
        margin-top: 20px !important;
        margin-bottom: 10px !important;
        padding: 6px 12px !important;
        font-size: 12px !important;
    }

    /* FILTROS CARD - MAIS COMPACTO NO MOBILE */
    .filters-card {
        padding: 10px !important;
        margin-bottom: 12px !important;
    }

    .filters-title {
        font-size: 13px !important;
        margin-bottom: 8px !important;
    }

    .filters-title i {
        font-size: 14px !important;
    }

    .filter-group {
        gap: 8px !important;
    }

    .filter-item {
        min-width: 100% !important;
    }

    .filter-label {
        font-size: 10px !important;
        margin-bottom: 3px !important;
    }

    .filter-input, .filter-select {
        padding: 6px 10px !important;
        font-size: 12px !important;
    }

    .users-grid {
        grid-template-columns: 1fr;
        gap: 10px;
        margin-top: 10px;
    }

    /* Manter grid-2 no mobile */
    .grid-2 {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 5px !important;
        margin-bottom: 5px !important;
    }

    .grid-2 .info-row {
        margin-bottom: 0 !important;
        padding: 6px 8px !important;
    }

    /* BotÃµes lado a lado */
    .user-actions {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 5px !important;
        margin-top: 10px !important;
    }

    .action-btn {
        width: 100% !important;
        min-width: auto !important;
        padding: 6px 3px !important;
        font-size: 10px !important;
        margin: 0 !important;
    }

    .action-btn i {
        font-size: 11px !important;
    }

    .user-header {
        padding: 8px 10px !important;
    }

    .user-avatar {
        width: 32px !important;
        height: 32px !important;
        font-size: 16px !important;
    }

    .user-name {
        font-size: 13px !important;
    }

    .status-badge {
        padding: 2px 6px !important;
        font-size: 9px !important;
    }

    .btn-edit[style*="flex:none"] {
        padding: 3px 8px !important;
        font-size: 10px !important;
    }

    .info-row {
        padding: 6px 8px !important;
        margin-bottom: 5px !important;
    }

    .info-icon {
        width: 24px !important;
        height: 24px !important;
        font-size: 12px !important;
        margin-right: 6px !important;
    }

    .info-label {
        font-size: 8px !important;
    }

    .info-value {
        font-size: 11px !important;
    }

    .pagination-info {
        margin-top: 15px !important;
        font-size: 12px !important;
    }
    
      .content-wrapper {
                margin: 35 auto !important;
                padding: 5px !important;
            }
}
    </style>
</head>
<body class="vertical-layout vertical-menu-modern dark-layout 2-columns navbar-sticky footer-static"
      data-open="click" data-menu="vertical-menu-modern" data-col="2-columns" data-layout="dark-layout">

    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">

            <!-- Info badge -->
            <div class="info-badge">
                <i class='bx bx-history'></i>
                <span>Logs de Atividades</span>
            </div>

            <!-- Status Info -->
            <div class="status-info">
                <div class="status-item">
                    <i class='bx bx-info-circle'></i>
                    <span>Total de registros: <?php echo mysqli_num_rows($result); ?></span>
                </div>
                <div class="status-item">
                    <i class='bx bx-time icon-time'></i>
                    <span><?php echo date('d/m/Y H:i'); ?></span>
                </div>
            </div>

            <!-- AÃ§Ãµes de Logs -->
            <div class="logs-actions">
                <button type="button" class="btn-action btn-excluir-todos" onclick="excluirTodosLogs()">
                    <i class='bx bx-trash'></i> Excluir Todos os Logs
                </button>
                <button class="btn-action btn-refresh" onclick="window.location.reload()">
                    <i class='bx bx-refresh'></i> Atualizar
                </button>
            </div>

            <!-- Filtros Card -->
            <div class="filters-card">
                <div class="filters-title">
                    <i class='bx bx-filter-alt'></i>
                    Filtros
                </div>
                <div class="filter-group">
                    <div class="filter-item">
                        <div class="filter-label">BUSCAR POR REVENDEDOR</div>
                        <input type="text" class="filter-input" id="searchInput"
                               placeholder="Digite para buscar...">
                    </div>
                    <div class="filter-item">
                        <div class="filter-label">FILTRAR POR DATA</div>
                        <select class="filter-select" id="dateFilter">
                            <option value="todos">Todas</option>
                            <option value="hoje">Hoje</option>
                            <option value="ontem">Ontem</option>
                            <option value="semana">Ãšltimos 7 dias</option>
                            <option value="mes">Ãšltimos 30 dias</option>
                        </select>
                    </div>
                </div>
            </div>

            <script>
            if (window.innerWidth < 678) {
                document.write('<div class="alert-warning"><strong>AtenÃ§Ã£o!</strong> Deslize para o lado para ver mais detalhes!</div>');
                window.setTimeout(function() {
                    $(".alert-warning").fadeTo(500, 0).slideUp(500, function(){
                        $(this).remove(); 
                    });
                }, 3000);
            }
            </script>

            <!-- Grid de Logs -->
            <div class="logs-grid" id="logsGrid">
                <?php
                if (mysqli_num_rows($result) > 0) {
                    while ($user_data = mysqli_fetch_assoc($result)) {
                        $log_id = $user_data['id'];
                        $data_log = $user_data['validade'];
                        $data_formatada = date('d/m/Y H:i:s', strtotime($data_log));
                        
                        // Calcular tempo relativo
                        $timestamp = strtotime($data_log);
                        $agora = time();
                        $diferenca = $agora - $timestamp;
                        
                        if ($diferenca < 60) {
                            $tempo_relativo = 'agora mesmo';
                        } elseif ($diferenca < 3600) {
                            $minutos = floor($diferenca / 60);
                            $tempo_relativo = $minutos . ' min atrÃ¡s';
                        } elseif ($diferenca < 86400) {
                            $horas = floor($diferenca / 3600);
                            $tempo_relativo = $horas . ' h atrÃ¡s';
                        } elseif ($diferenca < 2592000) {
                            $dias = floor($diferenca / 86400);
                            $tempo_relativo = $dias . ' d atrÃ¡s';
                        } else {
                            $tempo_relativo = date('d/m/Y', $timestamp);
                        }
                ?>
                <div class="log-card"
                     data-revendedor="<?php echo strtolower($user_data['revenda']); ?>"
                     data-data="<?php echo $data_log; ?>">
                    
                    <div class="card-header-custom">
                        <div class="header-icon">
                            <i class='bx bx-history'></i>
                        </div>
                        <div>
                            <div class="header-title">
                                <?php echo htmlspecialchars($user_data['revenda']); ?>
                                <span class="time-badge"><?php echo $tempo_relativo; ?></span>
                            </div>
                            <div class="header-subtitle">Registro de atividade</div>
                        </div>
                    </div>

                    <div class="card-body-custom">
                        <!-- Revendedor -->
                        <div class="info-row">
                            <div class="info-icon"><i class='bx bx-user icon-user'></i></div>
                            <div class="info-content">
                                <div class="info-label">REVENDEDOR</div>
                                <div class="info-value"><?php echo htmlspecialchars($user_data['revenda']); ?></div>
                            </div>
                        </div>

                        <!-- Detalhes -->
                        <div class="info-row">
                            <div class="info-icon"><i class='bx bx-detail icon-detail'></i></div>
                            <div class="info-content">
                                <div class="info-label">DETALHES</div>
                                <div class="info-value"><?php echo htmlspecialchars($user_data['texto']); ?></div>
                            </div>
                        </div>

                        <!-- Data e Hora -->
                        <div class="info-row">
                            <div class="info-icon"><i class='bx bx-calendar icon-calendar'></i></div>
                            <div class="info-content">
                                <div class="info-label">DATA E HORA</div>
                                <div class="info-value"><?php echo $data_formatada; ?></div>
                            </div>
                        </div>

                        <!-- BotÃ£o Excluir Individual - VIA AJAX -->
                        <button type="button" class="btn-delete-log" onclick="excluirLog(<?php echo $log_id; ?>, this)">
                            <i class='bx bx-trash'></i> Excluir este log
                        </button>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo '<div class="empty-state">';
                    echo '<i class="bx bx-history"></i>';
                    echo '<h3>Nenhum log encontrado</h3>';
                    echo '<p>Os registros de atividades aparecerÃ£o aqui</p>';
                    echo '</div>';
                }
                ?>
            </div>

            <div class="pagination-info">
                Exibindo <?php echo mysqli_num_rows($result); ?> registro(s)
            </div>

        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <script>
    // FunÃ§Ã£o para excluir log via AJAX
    function excluirLog(logId, botao) {
        swal({
            title: "Tem certeza?",
            text: "Este log serÃ¡ excluÃ­do permanentemente!",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                    url: 'excluir_log.php',
                    type: 'POST',
                    data: { log_id: logId },
                    success: function(response) {
                        if (response.trim() === 'ok') {
                            $(botao).closest('.log-card').fadeOut(300, function() {
                                $(this).remove();
                                atualizarContador();
                            });
                            swal("Sucesso!", "Log excluÃ­do com sucesso!", "success");
                        } else {
                            swal("Erro!", "Erro ao excluir log: " + response, "error");
                        }
                    },
                    error: function() {
                        swal("Erro!", "Erro na requisiÃ§Ã£o AJAX", "error");
                    }
                });
            }
        });
    }

    // FunÃ§Ã£o para excluir todos os logs via AJAX
    function excluirTodosLogs() {
        swal({
            title: "Tem certeza?",
            text: "Todos os logs serÃ£o excluÃ­dos permanentemente!",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                    url: 'excluir_todos_logs.php',
                    type: 'POST',
                    data: { excluir_todos: true },
                    success: function(response) {
                        if (response.trim() === 'ok') {
                            $('.logs-grid').fadeOut(300, function() {
                                $(this).html('<div class="empty-state">' +
                                    '<i class="bx bx-history"></i>' +
                                    '<h3>Nenhum log encontrado</h3>' +
                                    '<p>Os registros de atividades aparecerÃ£o aqui</p>' +
                                    '</div>').fadeIn(300);
                                atualizarContador();
                            });
                            swal("Sucesso!", "Todos os logs foram excluÃ­dos!", "success");
                        } else {
                            swal("Erro!", "Erro ao excluir logs: " + response, "error");
                        }
                    },
                    error: function() {
                        swal("Erro!", "Erro na requisiÃ§Ã£o AJAX", "error");
                    }
                });
            }
        });
    }

    // FunÃ§Ã£o para atualizar o contador
    function atualizarContador() {
        let total = document.querySelectorAll('.log-card').length;
        document.querySelector('.status-item span').textContent = 'Total de registros: ' + total;
        document.querySelector('.pagination-info').textContent = 'Exibindo ' + total + ' registro(s)';
    }

    // FunÃ§Ã£o para comparar datas
    function isDateInRange(logDate, filter) {
        const date = new Date(logDate);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        const lastWeek = new Date(today);
        lastWeek.setDate(lastWeek.getDate() - 7);
        
        const lastMonth = new Date(today);
        lastMonth.setMonth(lastMonth.getMonth() - 1);
        
        const logDateObj = new Date(date);
        logDateObj.setHours(0, 0, 0, 0);
        
        switch(filter) {
            case 'hoje':
                return logDateObj.getTime() === today.getTime();
            case 'ontem':
                return logDateObj.getTime() === yesterday.getTime();
            case 'semana':
                return logDateObj >= lastWeek;
            case 'mes':
                return logDateObj >= lastMonth;
            default:
                return true;
        }
    }

    // Filtro de busca
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let search = this.value.toLowerCase();
        let dateFilter = document.getElementById('dateFilter').value;
        
        document.querySelectorAll('.log-card').forEach(card => {
            let revendedor = card.getAttribute('data-revendedor');
            let logDate = card.getAttribute('data-data');
            
            let matchesSearch = revendedor.includes(search);
            let matchesDate = isDateInRange(logDate, dateFilter);
            
            card.style.display = (matchesSearch && matchesDate) ? 'block' : 'none';
        });
    });

    // Filtro por data
    document.getElementById('dateFilter').addEventListener('change', function() {
        let search = document.getElementById('searchInput').value.toLowerCase();
        let dateFilter = this.value;
        
        document.querySelectorAll('.log-card').forEach(card => {
            let revendedor = card.getAttribute('data-revendedor');
            let logDate = card.getAttribute('data-data');
            
            let matchesSearch = revendedor.includes(search);
            let matchesDate = isDateInRange(logDate, dateFilter);
            
            card.style.display = (matchesSearch && matchesDate) ? 'block' : 'none';
        });
    });
    </script>

</body>
</html>
<?php
    }
    aleatorio924926($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
