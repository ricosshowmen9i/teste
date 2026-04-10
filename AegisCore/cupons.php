<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio339313($input)
    {
        ?>
    
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Cupons</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    
    <?php
    error_reporting(0);
    session_start();
    include('conexao.php');
    include('header2.php');
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

    $sql = "SELECT * FROM cupons WHERE byid = '$_SESSION[iduser]'";
    $result = $conn->query($sql);
    
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
    
    function gerarCupom($tamanho = 8, $maiusculas = true, $numeros = true, $simbolos = false)
    {
        $lmin = 'abcdefghijklmnopqrstuvwxyz';
        $lmai = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $num = '1234567890';
        $simb = '!@#$%*-';
        $retorno = '';
        $caracteres = '';
        $caracteres .= $lmin;
        if ($maiusculas) $caracteres .= $lmai;
        if ($numeros) $caracteres .= $num;
        if ($simbolos) $caracteres .= $simb;
        $len = strlen($caracteres);
        for ($n = 1; $n <= $tamanho; $n++) {
            $rand = mt_rand(1, $len);
            $retorno .= $caracteres[$rand - 1];
        }
        return $retorno;
    }
    $cupon = gerarCupom(8, true, true, false);
    
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
    
    if (isset($_POST['adicionarcupom'])) {
        $nome = $_POST['nome'];
        $cupom = $_POST['cupom'];
        $desconto = $_POST['desconto'];
        $vezesuso = $_POST['vezesuso'];
        
        $nome = anti_sql($nome);
        $cupom = anti_sql($cupom);
        $desconto = anti_sql($desconto);
        $vezesuso = anti_sql($vezesuso);

        $sql = "INSERT INTO cupons (nome, cupom, desconto, byid, usado, vezesuso) VALUES ('$nome', '$cupom', '$desconto', '$_SESSION[iduser]', '0', '$vezesuso')";
        if ($conn->query($sql) === TRUE) {
            echo "<script>swal('Sucesso!', 'Cupom Adicionado!', 'success').then((value) => {
                    window.location.href = 'cupons.php';
                  });</script>";
        } else {
            echo "<script>swal('Erro!', 'Cupom NÃ£o Adicionado!', 'error').then((value) => {
                    window.location.href = 'cupons.php';
                  });</script>";
        }
    }

    if (isset($_POST['deletar'])) {
        $id = $_POST['id'];
        $id = anti_sql($id);
        $sql = "DELETE FROM cupons WHERE id='$id'";
        if ($conn->query($sql) === TRUE) {
            echo "<script>swal('Sucesso!', 'Cupom Deletado!', 'success').then((value) => {
                    window.location.href = 'cupons.php';
                  });</script>";
        } else {
            echo "<script>swal('Erro!', 'Cupom NÃ£o Deletado!', 'error').then((value) => {
                    window.location.href = 'cupons.php';
                  });</script>";
        }
    }
    ?>

    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Cupons</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    
    <?php
    error_reporting(0);
    session_start();
    include('conexao.php');
    include('header2.php');
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

    $sql = "SELECT * FROM cupons WHERE byid = '$_SESSION[iduser]'";
    $result = $conn->query($sql);
    
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
    
    function gerarCupom($tamanho = 8, $maiusculas = true, $numeros = true, $simbolos = false)
    {
        $lmin = 'abcdefghijklmnopqrstuvwxyz';
        $lmai = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $num = '1234567890';
        $simb = '!@#$%*-';
        $retorno = '';
        $caracteres = '';
        $caracteres .= $lmin;
        if ($maiusculas) $caracteres .= $lmai;
        if ($numeros) $caracteres .= $num;
        if ($simbolos) $caracteres .= $simb;
        $len = strlen($caracteres);
        for ($n = 1; $n <= $tamanho; $n++) {
            $rand = mt_rand(1, $len);
            $retorno .= $caracteres[$rand - 1];
        }
        return $retorno;
    }
    $cupon = gerarCupom(8, true, true, false);
    
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
    
    if (isset($_POST['adicionarcupom'])) {
        $nome = $_POST['nome'];
        $cupom = $_POST['cupom'];
        $desconto = $_POST['desconto'];
        $vezesuso = $_POST['vezesuso'];
        
        $nome = anti_sql($nome);
        $cupom = anti_sql($cupom);
        $desconto = anti_sql($desconto);
        $vezesuso = anti_sql($vezesuso);

        $sql = "INSERT INTO cupons (nome, cupom, desconto, byid, usado, vezesuso) VALUES ('$nome', '$cupom', '$desconto', '$_SESSION[iduser]', '0', '$vezesuso')";
        if ($conn->query($sql) === TRUE) {
            echo "<script>swal('Sucesso!', 'Cupom Adicionado!', 'success').then((value) => {
                    window.location.href = 'cupons.php';
                  });</script>";
        } else {
            echo "<script>swal('Erro!', 'Cupom NÃ£o Adicionado!', 'error').then((value) => {
                    window.location.href = 'cupons.php';
                  });</script>";
        }
    }

    if (isset($_POST['deletar'])) {
        $id = $_POST['id'];
        $id = anti_sql($id);
        $sql = "DELETE FROM cupons WHERE id='$id'";
        if ($conn->query($sql) === TRUE) {
            echo "<script>swal('Sucesso!', 'Cupom Deletado!', 'success').then((value) => {
                    window.location.href = 'cupons.php';
                  });</script>";
        } else {
            echo "<script>swal('Erro!', 'Cupom NÃ£o Deletado!', 'error').then((value) => {
                    window.location.href = 'cupons.php';
                  });</script>";
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
            --icon-coupon: #f59e0b;
            --icon-percent: #10b981;
            --icon-counter: #818cf8;
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
            max-width: 1650px;
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

        /* Card de CriaÃ§Ã£o */
        .create-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 16px !important;
            padding: 20px !important;
            margin-bottom: 20px !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            position: relative !important;
            overflow: hidden !important;
        }

        .create-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 30%, rgba(200,80,192,0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .create-title {
            font-size: 16px;
            font-weight: 700;
            color: white;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
        }

        .create-title i {
            color: var(--tertiary);
            font-size: 20px;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .form-input {
            flex: 1;
            min-width: 150px;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            font-size: 13px;
            transition: all 0.3s;
            color: white;
        }

        .form-input:focus {
            outline: none;
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-input::placeholder {
            color: rgba(255,255,255,0.3);
        }

        .form-select {
            flex: 1;
            min-width: 150px;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            font-size: 13px;
            color: white;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23C850C0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
        }

        .form-select option {
            background: #1e293b;
            color: white;
        }

        .btn-add {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 8px rgba(16,185,129,0.3);
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(16,185,129,0.4);
        }

        /* Grid de cupons */
        .cupons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 20px;
            width: 100%;
        }

        /* Card de cupom */
        .cupom-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 16px !important;
            overflow: hidden !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            animation: fadeIn 0.4s ease !important;
            position: relative !important;
        }

        .cupom-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 80% 20%, rgba(65,88,208,0.1) 0%, transparent 60%);
            pointer-events: none;
        }

        .cupom-card:hover {
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
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .icon-coupon    { color: #f59e0b; }
        .icon-percent   { color: #10b981; }
        .icon-counter   { color: #818cf8; }
        .icon-name      { color: #60a5fa; }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 8px;
        }

        .grid-2 .info-row {
            margin-bottom: 0;
        }

        .btn-delete {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(220,38,38,0.4);
        }

        .btn-delete i {
            font-size: 14px;
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

        /* ===== AJUSTES MOBILE ===== */
        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .info-badge {
                margin-top: 20px !important;
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
                <i class='bx bx-gift'></i>
                <span>Gerenciar Cupons de Desconto</span>
            </div>

            <!-- Status Info -->
            <div class="status-info">
                <div class="status-item">
                    <i class='bx bx-info-circle'></i>
                    <span>Total de cupons: <?php echo $result->num_rows; ?></span>
                </div>
                <div class="status-item">
                    <i class='bx bx-time icon-time'></i>
                    <span><?php echo date('d/m/Y H:i'); ?></span>
                </div>
            </div>

            <!-- Filtros Card -->
            <div class="filters-card">
                <div class="filters-title">
                    <i class='bx bx-filter-alt'></i>
                    Filtros
                </div>
                <div class="filter-group">
                    <div class="filter-item">
                        <div class="filter-label">BUSCAR POR NOME/CÃ“DIGO</div>
                        <input type="text" class="filter-input" id="searchInput"
                               placeholder="Digite para buscar...">
                    </div>
                    <div class="filter-item">
                        <div class="filter-label">FILTRAR POR DESCONTO</div>
                        <select class="filter-select" id="discountFilter">
                            <option value="todos">Todos</option>
                            <option value="0-25">0% - 25%</option>
                            <option value="26-50">26% - 50%</option>
                            <option value="51-75">51% - 75%</option>
                            <option value="76-100">76% - 100%</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Card de CriaÃ§Ã£o -->
            <div class="create-card">
                <div class="create-title">
                    <i class='bx bx-plus-circle'></i>
                    Criar Novo Cupom
                </div>
                <form action="cupons.php" method="post" class="form-row">
                    <input type="text" name="nome" placeholder="Nome do Cupom" class="form-input" required>
                    <input type="text" name="cupom" placeholder="CÃ³digo" class="form-input" value="<?php echo $cupon ?>">
                    <input type="number" name="vezesuso" placeholder="Limite de usos" class="form-input" value="1" min="1">
                    <select name="desconto" class="form-select" required>
                        <option value="">Desconto %</option>
                        <?php for($i = 1; $i <= 100; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?>%</option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" name="adicionarcupom" class="btn-add">
                        <i class='bx bx-save'></i> Adicionar
                    </button>
                </form>
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

            <!-- Grid de cupons -->
            <div class="cupons-grid" id="cuponsGrid">
                <?php
                if ($result->num_rows > 0) {
                    while ($user_data = mysqli_fetch_assoc($result)) {
                        $usado = $user_data['usado'] ?? 0;
                        $limite = $user_data['vezesuso'];
                        $porcentagem = ($limite > 0) ? round(($usado / $limite) * 100) : 0;
                        $desconto = (int)$user_data['desconto'];
                ?>
                <div class="cupom-card"
                     data-nome="<?php echo strtolower($user_data['nome']); ?>"
                     data-codigo="<?php echo strtolower($user_data['cupom']); ?>"
                     data-desconto="<?php echo $desconto; ?>">
                    <div class="card-header-custom">
                        <div class="header-icon">
                            <i class='bx bx-gift'></i>
                        </div>
                        <div>
                            <div class="header-title"><?php echo htmlspecialchars($user_data['nome']); ?></div>
                            <div class="header-subtitle">CÃ³digo: <?php echo htmlspecialchars($user_data['cupom']); ?></div>
                        </div>
                    </div>

                    <div class="card-body-custom">
                        <!-- CÃ³digo e Desconto -->
                        <div class="grid-2">
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-coupon icon-coupon'></i></div>
                                <div class="info-content">
                                    <div class="info-label">CÃ“DIGO</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user_data['cupom']); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-percent icon-percent'></i></div>
                                <div class="info-content">
                                    <div class="info-label">DESCONTO</div>
                                    <div class="info-value"><?php echo $desconto; ?>%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Usos -->
                        <div class="grid-2">
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-check-circle icon-counter'></i></div>
                                <div class="info-content">
                                    <div class="info-label">USADO</div>
                                    <div class="info-value"><?php echo $usado; ?> vez(es)</div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-bar-chart-alt icon-counter'></i></div>
                                <div class="info-content">
                                    <div class="info-label">LIMITE</div>
                                    <div class="info-value"><?php echo $limite; ?> vez(es)</div>
                                </div>
                            </div>
                        </div>

                        <!-- Barra de progresso -->
                        <div class="info-row" style="flex-direction: column; align-items: stretch;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span style="color: rgba(255,255,255,0.5); font-size: 10px;">UtilizaÃ§Ã£o</span>
                                <span style="color: white; font-size: 10px; font-weight: 600;"><?php echo $porcentagem; ?>%</span>
                            </div>
                            <div style="height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px; overflow: hidden;">
                                <div style="height: 100%; width: <?php echo $porcentagem; ?>%; background: linear-gradient(90deg, #10b981, #34d399); border-radius: 2px;"></div>
                            </div>
                        </div>

                        <!-- BotÃ£o deletar -->
                        <form action="cupons.php" method="post" style="margin-top: 10px;">
                            <input type="hidden" name="id" value="<?php echo $user_data['id']; ?>">
                            <button type="submit" name="deletar" class="btn-delete">
                                <i class='bx bx-trash'></i> Deletar Cupom
                            </button>
                        </form>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo '<div class="empty-state">';
                    echo '<i class="bx bx-gift"></i>';
                    echo '<h3>Nenhum cupom encontrado</h3>';
                    echo '<p>Crie seu primeiro cupom de desconto acima</p>';
                    echo '</div>';
                }
                ?>
            </div>

            <div class="pagination-info" style="text-align:center; margin-top:20px; color:rgba(255,255,255,0.5); font-size:13px;">
                Exibindo <?php echo $result->num_rows; ?> cupom(ns)
            </div>

        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <script>
    // Filtro de busca
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let search = this.value.toLowerCase();
        document.querySelectorAll('.cupom-card').forEach(card => {
            let nome = card.getAttribute('data-nome');
            let codigo = card.getAttribute('data-codigo');
            card.style.display = (nome.includes(search) || codigo.includes(search)) ? 'block' : 'none';
        });
    });

    // Filtro por desconto
    document.getElementById('discountFilter').addEventListener('change', function() {
        let range = this.value;
        document.querySelectorAll('.cupom-card').forEach(card => {
            if (range === 'todos') {
                card.style.display = 'block';
            } else {
                let desconto = parseInt(card.getAttribute('data-desconto'));
                let [min, max] = range.split('-').map(Number);
                card.style.display = (desconto >= min && desconto <= max) ? 'block' : 'none';
            }
        });
    });
    </script>

</body>
</html>
<?php
    }
    aleatorio339313($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
