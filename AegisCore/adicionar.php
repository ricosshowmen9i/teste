<?php
error_reporting(0);
session_start();

// Configurar fuso horÃ¡rio para BrasÃ­lia
date_default_timezone_set('America/Sao_Paulo');

include 'header2.php';
include('conexao.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

unset($_SESSION['addquantidade']);

$login = $_SESSION['login'];
$senha = $_SESSION['senha'];

$sql4 = "SELECT * FROM accounts WHERE login = '$login' AND senha = '$senha'";
$result4 = $conn->query($sql4);
if ($result4->num_rows > 0) {
    while ($row4 = $result4->fetch_assoc()) {
        $_SESSION['iduser'] = $row4['id'];
        $_SESSION['byid'] = $row4['byid'];
    }
}

if (!file_exists('../admin/suspenderrev.php')) {
    exit ("<script>alert('Token Invalido!');</script>");
} else {
    include_once '../admin/suspenderrev.php';
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

$sql = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $_SESSION['limite'] = $row['limite'];
        $_SESSION['validade'] = $row['expira'];
        $_SESSION['typecont'] = $row['tipo'];
    }
}

$sql2 = "SELECT * FROM accounts WHERE id = '$_SESSION[byid]'";
$result2 = $conn->query($sql2);
if ($result2->num_rows > 0) {
    while ($row2 = $result2->fetch_assoc()) {
        $_SESSION['valorrevenda'] = $row2['valorrevenda'];
        $_SESSION['valorcredito'] = $row2['mainid'];
        $_SESSION['accesstoken'] = $row2['accesstoken'];
        $_SESSION['mp_access_token'] = $row2['mp_access_token'] ?? '';
        $_SESSION['mp_public_key'] = $row2['mp_public_key'] ?? '';
        $_SESSION['mp_invoice_description'] = $row2['mp_invoice_description'] ?? 'PAINEL PRO';
    }
}

$slq2 = "SELECT sum(limite) AS limiterevenda FROM atribuidos where byid='$_SESSION[byid]'";
$result = $conn->prepare($slq2);
$result->execute();
$result->bind_result($limiterevenda);
$result->fetch();
$result->close();

$sql4 = "SELECT * FROM ssh_accounts WHERE byid = '$_SESSION[byid]'";
$sql4 = $conn->prepare($sql4);
$sql4->execute();
$sql4->store_result();
$num_rows = $sql4->num_rows;
$usadousuarios = $num_rows;

$sql55 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[byid]'";
$result55 = $conn->query($sql55);
if ($result55->num_rows > 0) {
    while ($row55 = $result55->fetch_assoc()) {
        $limite = $row55['limite'];
    }
}

$soma = $usadousuarios + $limiterevenda;

if ($_SESSION['byid'] == '1') {
    $limitefinal = 'Ilimitado';
} else {
    if ($_SESSION['typecont'] == 'Credito') {
        $limitefinal = $limite;
    } else {
        $limitefinal = $limite - $soma;
    }
}

// Verifica se o revendedor tem acesso token configurado
if ($_SESSION['accesstoken'] == '') {
    echo '<script>swal("Oops...", "O Revendedor nÃ£o possui uma conta cadastrada!", "error");</script>';
    echo '<script>setTimeout(function(){ window.location.href = "../home.php"; }, 3000);</script>';
}

$minimocompra = "1";

$sql_min = "SELECT * FROM configs WHERE id = '1'";
$result_min = $conn->query($sql_min);
if ($result_min->num_rows > 0) {
    while ($row_min = $result_min->fetch_assoc()) {
        $minimocompra = $row_min['minimocompra'];
    }
}

$error_message = '';
$show_error_modal = false;

if (isset($_POST['addlogin'])) {
    $addquantidade = anti_sql($_POST['addquantidade']);
    $_SESSION['cupom'] = anti_sql($_POST['cupom'] ?? '');
    $_SESSION['addquantidade'] = $addquantidade;
    
    // ValidaÃ§Ãµes
    if ($addquantidade < $minimocompra) {
        $error_message = "Quantidade mÃ­nima de compra Ã© $minimocompra!";
        $show_error_modal = true;
    } elseif (is_numeric($limitefinal) && $addquantidade > $limitefinal) {
        $error_message = "Quantidade disponÃ­vel: $limitefinal!";
        $show_error_modal = true;
    }
    
    if (!$show_error_modal) {
        $valor_total = $_SESSION['valorcredito'] * $addquantidade;
        $_SESSION['valor'] = $valor_total;
        echo "<script>location.href='processando.php'</script>";
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

$validade_formatada = date('d/m/Y', strtotime($_SESSION['validade']));
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar CrÃ©ditos</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar CrÃ©ditos</title>
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
            --purple: #8b5cf6;
            --pink: #ec4899;
            --orange: #f97316;
            --cyan: #06b6d4;
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
            margin-left: 260px !important;
            padding: 0 !important;
        }

        .content-wrapper {
            max-width: 800px;
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
            border-left: 4px solid var(--success) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--success);
        }

        /* Card moderno */
        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 20px 24px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--success), #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 20px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            margin-top: 2px;
        }

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        /* Plano Info */
        .plan-info {
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .plan-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .plan-info-row:last-child {
            border-bottom: none;
        }

        .plan-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: rgba(255,255,255,0.6);
        }

        .plan-label .bx-user {
            color: var(--primary);
            font-size: 18px;
        }
        
        .plan-label .bx-layer {
            color: var(--secondary);
            font-size: 18px;
        }
        
        .plan-label .bx-calendar {
            color: var(--warning);
            font-size: 18px;
        }
        
        .plan-label .bx-coin-stack {
            color: var(--success);
            font-size: 18px;
        }

        .plan-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
        }

        .plan-value-large {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--success), #34d399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .badge-limite {
            background: rgba(16,185,129,0.15);
            border: 1px solid rgba(16,185,129,0.3);
            color: #34d399;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group label i {
            color: var(--purple);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            color: white;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(16,185,129,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.3);
        }

        .btn-action {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: inherit;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
            width: 100%;
        }

        /* BotÃ£o Adicionar - Verde */
        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5);
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid rgba(255,255,255,0.2);
            color: white;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .action-buttons .btn-action {
            flex: 1;
        }

        .info-note {
            background: rgba(16, 185, 129, 0.08);
            border-left: 3px solid var(--success);
            padding: 12px 15px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .info-note small {
            color: rgba(255,255,255,0.6);
            font-size: 11px;
            line-height: 1.4;
        }

        .info-note i {
            color: var(--success);
            margin-right: 6px;
        }

        .price-display {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: rgba(16,185,129,0.1);
            border-radius: 16px;
        }

        .price-display .total-value {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--success), #34d399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
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
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--danger), #b91c1c);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 30px 20px;
            color: white;
            text-align: center;
        }

        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .error-icon {
            font-size: 60px;
            color: var(--danger);
            margin-bottom: 15px;
        }

        .btn-modal {
            background: linear-gradient(135deg, var(--danger), #b91c1c);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-modal:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .modern-card .card-header {
                flex-direction: column !important;
                text-align: center !important;
            }

            .action-buttons {
                flex-direction: column !important;
            }

            .plan-info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .plan-value-large {
                font-size: 22px;
            }

            .header-icon {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }

            .header-title {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-coin-stack'></i>
                <span>Adicionar CrÃ©ditos</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(16,185,129,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(139,92,246,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <polygon points="8%,72% 2%,58% 16%,62% 13%,76% 4%,79% 1%,66%" fill="rgba(139,92,246,0.05)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                        <circle cx="50%" cy="2%" r="20" fill="rgba(16,185,129,0.04)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-coin-stack'></i>
                    </div>
                    <div>
                        <div class="header-title">Adicionar CrÃ©ditos</div>
                        <div class="header-subtitle">Compre crÃ©ditos para criar novos usuÃ¡rios</div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- InformaÃ§Ãµes do Plano -->
                    <div class="plan-info">
                        <div class="plan-info-row">
                            <div class="plan-label">
                                <i class='bx bx-user'></i>
                                <span>Seu Login</span>
                            </div>
                            <div class="plan-value"><?php echo htmlspecialchars($_SESSION['login']); ?></div>
                        </div>
                        <div class="plan-info-row">
                            <div class="plan-label">
                                <i class='bx bx-layer'></i>
                                <span>Seu Limite Atual</span>
                            </div>
                            <div class="plan-value">
                                <span class="badge-limite">
                                    <i class='bx bx-bar-chart-alt'></i> <?php echo $_SESSION['limite']; ?> usuÃ¡rios
                                </span>
                            </div>
                        </div>
                        <div class="plan-info-row">
                            <div class="plan-label">
                                <i class='bx bx-calendar'></i>
                                <span>Validade</span>
                            </div>
                            <div class="plan-value"><?php echo $validade_formatada; ?></div>
                        </div>
                        <div class="plan-info-row">
                            <div class="plan-label">
                                <i class='bx bx-coin-stack'></i>
                                <span>Valor por CrÃ©dito</span>
                            </div>
                            <div class="plan-value-large">R$ <?php echo number_format($_SESSION['valorcredito'], 2, ',', '.'); ?></div>
                        </div>
                    </div>

                    <form action="adicionar.php" method="POST" id="formAdd">
                        <div class="form-group">
                            <label>
                                <i class='bx bx-tag'></i>
                                Cupom de Desconto (opcional)
                            </label>
                            <input type="text" class="form-control" name="cupom" placeholder="Digite seu cupom aqui">
                        </div>

                        <div class="form-group">
                            <label>
                                <i class='bx bx-plus-circle'></i>
                                Quantidade de CrÃ©ditos
                            </label>
                            <input type="number" class="form-control" name="addquantidade" 
                                   id="quantidade" placeholder="Quantidade a Adicionar" 
                                   required min="<?php echo $minimocompra; ?>" 
                                   max="<?php echo is_numeric($limitefinal) ? $limitefinal : 999999; ?>"
                                   oninput="calcularTotal()">
                            <small style="color: rgba(255,255,255,0.3); margin-top: 5px; display: block; font-size: 10px;">
                                <i class='bx bx-info-circle'></i> 
                                DisponÃ­vel: <?php echo $limitefinal; ?> crÃ©ditos | 
                                MÃ­nimo: <?php echo $minimocompra; ?> crÃ©ditos
                            </small>
                        </div>

                        <div class="price-display" id="priceDisplay">
                            <span style="font-size: 12px; color: rgba(255,255,255,0.5);">Total a Pagar:</span>
                            <div class="total-value" id="totalValue">R$ 0,00</div>
                        </div>

                        <div class="action-buttons">
                            <a href="home.php" class="btn-action btn-outline">
                                <i class='bx bx-arrow-back'></i> Cancelar
                            </a>
                            <button type="submit" class="btn-action btn-success" name="addlogin">
                                <i class='bx bx-credit-card'></i> Comprar Agora
                            </button>
                        </div>

                        <div class="info-note">
                            <i class='bx bx-info-circle'></i>
                            <small>ApÃ³s o pagamento, seus crÃ©ditos serÃ£o adicionados automaticamente Ã  sua conta. O prazo de confirmaÃ§Ã£o Ã© de atÃ© 24 horas Ãºteis.</small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
        <div class="modal-container">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; font-size: 18px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); font-size: 14px;"><?php echo $error_message; ?></p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-modal" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        const valorCredito = <?php echo $_SESSION['valorcredito']; ?>;
        
        function calcularTotal() {
            const quantidade = document.getElementById('quantidade').value;
            const total = quantidade * valorCredito;
            const totalFormatado = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            document.getElementById('totalValue').innerHTML = totalFormatado;
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalErro').classList.remove('show');
            }
        });

        // Calcular total ao carregar a pÃ¡gina
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('quantidade');
            if (input && input.value) {
                calcularTotal();
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
                <i class='bx bx-coin-stack'></i>
                <span>Adicionar CrÃ©ditos</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(16,185,129,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(139,92,246,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <polygon points="8%,72% 2%,58% 16%,62% 13%,76% 4%,79% 1%,66%" fill="rgba(139,92,246,0.05)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                        <circle cx="50%" cy="2%" r="20" fill="rgba(16,185,129,0.04)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-coin-stack'></i>
                    </div>
                    <div>
                        <div class="header-title">Adicionar CrÃ©ditos</div>
                        <div class="header-subtitle">Compre crÃ©ditos para criar novos usuÃ¡rios</div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- InformaÃ§Ãµes do Plano -->
                    <div class="plan-info">
                        <div class="plan-info-row">
                            <div class="plan-label">
                                <i class='bx bx-user'></i>
                                <span>Seu Login</span>
                            </div>
                            <div class="plan-value"><?php echo htmlspecialchars($_SESSION['login']); ?></div>
                        </div>
                        <div class="plan-info-row">
                            <div class="plan-label">
                                <i class='bx bx-layer'></i>
                                <span>Seu Limite Atual</span>
                            </div>
                            <div class="plan-value">
                                <span class="badge-limite">
                                    <i class='bx bx-bar-chart-alt'></i> <?php echo $_SESSION['limite']; ?> usuÃ¡rios
                                </span>
                            </div>
                        </div>
                        <div class="plan-info-row">
                            <div class="plan-label">
                                <i class='bx bx-calendar'></i>
                                <span>Validade</span>
                            </div>
                            <div class="plan-value"><?php echo $validade_formatada; ?></div>
                        </div>
                        <div class="plan-info-row">
                            <div class="plan-label">
                                <i class='bx bx-coin-stack'></i>
                                <span>Valor por CrÃ©dito</span>
                            </div>
                            <div class="plan-value-large">R$ <?php echo number_format($_SESSION['valorcredito'], 2, ',', '.'); ?></div>
                        </div>
                    </div>

                    <form action="adicionar.php" method="POST" id="formAdd">
                        <div class="form-group">
                            <label>
                                <i class='bx bx-tag'></i>
                                Cupom de Desconto (opcional)
                            </label>
                            <input type="text" class="form-control" name="cupom" placeholder="Digite seu cupom aqui">
                        </div>

                        <div class="form-group">
                            <label>
                                <i class='bx bx-plus-circle'></i>
                                Quantidade de CrÃ©ditos
                            </label>
                            <input type="number" class="form-control" name="addquantidade" 
                                   id="quantidade" placeholder="Quantidade a Adicionar" 
                                   required min="<?php echo $minimocompra; ?>" 
                                   max="<?php echo is_numeric($limitefinal) ? $limitefinal : 999999; ?>"
                                   oninput="calcularTotal()">
                            <small style="color: rgba(255,255,255,0.3); margin-top: 5px; display: block; font-size: 10px;">
                                <i class='bx bx-info-circle'></i> 
                                DisponÃ­vel: <?php echo $limitefinal; ?> crÃ©ditos | 
                                MÃ­nimo: <?php echo $minimocompra; ?> crÃ©ditos
                            </small>
                        </div>

                        <div class="price-display" id="priceDisplay">
                            <span style="font-size: 12px; color: rgba(255,255,255,0.5);">Total a Pagar:</span>
                            <div class="total-value" id="totalValue">R$ 0,00</div>
                        </div>

                        <div class="action-buttons">
                            <a href="home.php" class="btn-action btn-outline">
                                <i class='bx bx-arrow-back'></i> Cancelar
                            </a>
                            <button type="submit" class="btn-action btn-success" name="addlogin">
                                <i class='bx bx-credit-card'></i> Comprar Agora
                            </button>
                        </div>

                        <div class="info-note">
                            <i class='bx bx-info-circle'></i>
                            <small>ApÃ³s o pagamento, seus crÃ©ditos serÃ£o adicionados automaticamente Ã  sua conta. O prazo de confirmaÃ§Ã£o Ã© de atÃ© 24 horas Ãºteis.</small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
        <div class="modal-container">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; font-size: 18px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); font-size: 14px;"><?php echo $error_message; ?></p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-modal" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        const valorCredito = <?php echo $_SESSION['valorcredito']; ?>;
        
        function calcularTotal() {
            const quantidade = document.getElementById('quantidade').value;
            const total = quantidade * valorCredito;
            const totalFormatado = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            document.getElementById('totalValue').innerHTML = totalFormatado;
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalErro').classList.remove('show');
            }
        });

        // Calcular total ao carregar a pÃ¡gina
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('quantidade');
            if (input && input.value) {
                calcularTotal();
            }
        });
    </script>
</body>
</html>



