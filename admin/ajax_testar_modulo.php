<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio_gerar_token($input)
    {
        ?>
<script src="../app-assets/sweetalert.min.js"></script>
<?php
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
include('headeradmin2.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Falha na conexão: " . mysqli_connect_error());
}

// Verifica se o usuário está autenticado
if (!isset($_SESSION['login']) || !isset($_SESSION['senha'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Verificação de token do sistema
if (!file_exists('suspenderrev.php')) {
    exit ("<script>alert('Token Invalido!');</script>");
}else{
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

// Verifica se é admin
if ($_SESSION['login'] !== 'admin') {
    echo 'Você não tem permissão para acessar essa página';
    exit;
}

// Função anti SQL injection
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

// Função para gerar token aleatório
function gerarToken($tamanho = 32) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $token = '';
    for ($i = 0; $i < $tamanho; $i++) {
        $token .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    return $token;
}

// Processar ações
if (isset($_POST['action'])) {
    $servidor_id = anti_sql($_POST['servidor_id']);
    
    // GERAR TOKEN AUTOMÁTICO
    if ($_POST['action'] === 'gerar_token') {
        $novo_token = gerarToken(32);
        $token_md5 = md5($novo_token);
        
        // Desativa tokens antigos deste servidor
        mysqli_query($conn, "UPDATE servidor_tokens SET status = 'inativo' WHERE servidor_id = '$servidor_id'");
        
        // Insere novo token
        $sql = "INSERT INTO servidor_tokens (servidor_id, token) VALUES ('$servidor_id', '$token_md5')";
        if (mysqli_query($conn, $sql)) {
            echo "<script>
                swal({
                    title: 'Token Gerado!',
                    text: 'Token: $novo_token',
                    icon: 'success',
                    button: 'Copiar Token',
                }).then((value) => {
                    navigator.clipboard.writeText('$novo_token');
                    window.location.href = 'gerar_token.php';
                });
            </script>";
        }
    }
    
    // ADICIONAR TOKEN MANUAL
    if ($_POST['action'] === 'adicionar_manual') {
        $token_manual = anti_sql($_POST['token_manual']);
        
        if (strlen($token_manual) < 5) {
            echo "<script>swal('Erro!', 'Token muito curto!', 'error');</script>";
        } else {
            $token_md5 = md5($token_manual);
            
            // Desativa tokens antigos deste servidor
            mysqli_query($conn, "UPDATE servidor_tokens SET status = 'inativo' WHERE servidor_id = '$servidor_id'");
            
            // Insere novo token manual
            $sql = "INSERT INTO servidor_tokens (servidor_id, token) VALUES ('$servidor_id', '$token_md5')";
            if (mysqli_query($conn, $sql)) {
                echo "<script>
                    swal({
                        title: 'Token Adicionado!',
                        text: 'Token manual salvo com sucesso!',
                        icon: 'success',
                        button: 'OK',
                    }).then((value) => {
                        window.location.href = 'gerar_token.php';
                    });
                </script>";
            } else {
                echo "<script>swal('Erro!', 'Erro ao salvar token: " . mysqli_error($conn) . "', 'error');</script>";
            }
        }
    }
    
    // EDITAR TOKEN
    if ($_POST['action'] === 'editar_token') {
        $token_id = anti_sql($_POST['token_id']);
        $novo_token_manual = anti_sql($_POST['novo_token_manual']);
        
        if (strlen($novo_token_manual) < 5) {
            echo "<script>swal('Erro!', 'Token muito curto!', 'error');</script>";
        } else {
            $token_md5 = md5($novo_token_manual);
            
            $sql = "UPDATE servidor_tokens SET token = '$token_md5' WHERE id = '$token_id'";
            if (mysqli_query($conn, $sql)) {
                echo "<script>
                    swal({
                        title: 'Token Editado!',
                        text: 'Token atualizado com sucesso!',
                        icon: 'success',
                        button: 'OK',
                    }).then((value) => {
                        window.location.href = 'gerar_token.php';
                    });
                </script>";
            } else {
                echo "<script>swal('Erro!', 'Erro ao editar token: " . mysqli_error($conn) . "', 'error');</script>";
            }
        }
    }
    
    // DELETAR TOKEN
    if ($_POST['action'] === 'deletar_token') {
        $token_id = anti_sql($_POST['token_id']);
        
        $sql = "DELETE FROM servidor_tokens WHERE id = '$token_id'";
        if (mysqli_query($conn, $sql)) {
            echo "<script>
                swal({
                    title: 'Token Deletado!',
                    text: 'Token removido com sucesso!',
                    icon: 'success',
                    button: 'OK',
                }).then((value) => {
                    window.location.href = 'gerar_token.php';
                });
            </script>";
        } else {
            echo "<script>swal('Erro!', 'Erro ao deletar token: " . mysqli_error($conn) . "', 'error');</script>";
        }
    }
    
    // ATIVAR/DESATIVAR TOKEN
    if ($_POST['action'] === 'toggle_status') {
        $token_id = anti_sql($_POST['token_id']);
        $status_atual = anti_sql($_POST['status_atual']);
        $novo_status = $status_atual === 'ativo' ? 'inativo' : 'ativo';
        
        if ($novo_status === 'ativo') {
            $sql_token = "SELECT servidor_id FROM servidor_tokens WHERE id = '$token_id'";
            $result_token = mysqli_query($conn, $sql_token);
            $row_token = mysqli_fetch_assoc($result_token);
            $servidor_id = $row_token['servidor_id'];
            mysqli_query($conn, "UPDATE servidor_tokens SET status = 'inativo' WHERE servidor_id = '$servidor_id'");
        }
        
        mysqli_query($conn, "UPDATE servidor_tokens SET status = '$novo_status' WHERE id = '$token_id'");
        echo "<script>window.location.href='gerar_token.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Tokens dos Servidores</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        /* MESMO ESTILO DO CONFIGPAINEL */
        .modern-card {
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.1);
            border: none;
            overflow: hidden;
            background: white;
            margin-bottom: 25px;
        }

        .modern-card .card-header {
            background: linear-gradient(135deg, #4158D0 0%, #C850C0 46%, #FFCC70 100%);
            color: white;
            padding: 25px 30px;
            border-bottom: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modern-card .card-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modern-card .card-title i {
            font-size: 28px;
        }

        .modern-card .card-body {
            padding: 30px 25px;
        }

        /* Info badge */
        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eef2f6;
            color: #2c3e50;
            padding: 10px 18px;
            border-radius: 40px;
            font-size: 14px;
            margin-bottom: 25px;
            border-left: 4px solid #4158D0;
        }

        .info-badge i {
            font-size: 20px;
            color: #4158D0;
        }

        /* Grid de servidores */
        .servidores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        /* Card do servidor */
        .servidor-card {
            background: white;
            border-radius: 20px;
            border: 2px solid #eef2f6;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
        }

        .servidor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            border-color: #4158D0;
        }

        /* Header do servidor */
        .servidor-header {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 1.2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .servidor-header i {
            font-size: 2rem;
            background: rgba(255,255,255,0.2);
            padding: 10px;
            border-radius: 15px;
        }

        .servidor-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .servidor-header p {
            margin: 5px 0 0;
            font-size: 0.85rem;
            opacity: 0.9;
        }

        /* Corpo do servidor */
        .servidor-body {
            padding: 1.5rem;
        }

        /* Token info */
        .token-info {
            background: #f8fafc;
            border-radius: 16px;
            padding: 1.2rem;
            margin-bottom: 1.2rem;
            border: 2px solid #eef2f6;
        }

        .token-label {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        .token-label i {
            color: #4158D0;
            font-size: 1rem;
        }

        .token-value {
            background: white;
            border: 2px solid #eef2f6;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            font-family: monospace;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
            word-break: break-all;
        }

        .copy-btn {
            background: none;
            border: none;
            color: #4158D0;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 1.2rem;
        }

        .copy-btn:hover {
            background: #eef2ff;
            transform: scale(1.1);
        }

        /* Status do token */
        .token-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-ativo {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .status-inativo {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* Botões de ação */
        .token-actions {
            display: flex;
            gap: 0.8rem;
            margin: 1.2rem 0;
            flex-wrap: wrap;
        }

        .btn-action {
            flex: 1;
            min-width: 120px;
            padding: 0.8rem;
            border: none;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border: 2px solid transparent;
        }

        .btn-action i {
            font-size: 1.1rem;
        }

        .btn-generate {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 4px 10px rgba(65, 88, 208, 0.2);
        }

        .btn-generate:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(65, 88, 208, 0.3);
        }

        .btn-manual {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
        }

        .btn-manual:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-history {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 4px 10px rgba(245, 158, 11, 0.2);
        }

        .btn-history:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
        }

        .btn-toggle {
            background: #f3f4f6;
            color: #4b5563;
            border: 2px solid #e5e7eb;
        }

        .btn-toggle:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
        }

        /* Botão voltar */
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .btn-back:hover {
            background: rgba(255,255,255,0.3);
            transform: translateX(-5px);
        }

        /* Modal Styles - IGUAL AO CONFIGPAINEL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            border-radius: 25px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            border: 2px solid #eef2f6;
        }

        .modal-content h3 {
            margin: 0 0 1.5rem 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 1.5rem;
        }

        .modal-content h3 i {
            color: #4158D0;
            font-size: 1.8rem;
        }

        .modal-content .close {
            float: right;
            font-size: 1.8rem;
            font-weight: bold;
            cursor: pointer;
            color: #94a3b8;
            transition: all 0.3s;
            line-height: 1;
        }

        .modal-content .close:hover {
            color: #4158D0;
            transform: rotate(90deg);
        }

        /* Form groups */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group label i {
            color: #4158D0;
        }

        .form-control {
            width: 100%;
            padding: 12px 18px;
            border: 2px solid #eef2f6;
            border-radius: 14px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: #4158D0;
            box-shadow: 0 0 0 4px rgba(65, 88, 208, 0.1);
            background: white;
        }

        /* Botões do modal */
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .btn-modal {
            padding: 12px 25px;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-modal.cancel {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-modal.cancel:hover {
            background: #e2e8f0;
        }

        .btn-modal.save {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
        }

        .btn-modal.save:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-modal.edit {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-modal.edit:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
        }

        /* Histórico de tokens */
        .historico-tokens {
            margin-top: 1rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .historico-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.8rem;
            border: 2px solid #eef2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .historico-item:hover {
            border-color: #4158D0;
            transform: translateX(5px);
        }

        .historico-item .token-preview {
            font-family: monospace;
            color: #4158D0;
            font-weight: 600;
            background: white;
            padding: 0.3rem 0.8rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .historico-item .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-ativo {
            background: #d1fae5;
            color: #065f46;
            border-left: 3px solid #10b981;
        }

        .badge-inativo {
            background: #fee2e2;
            color: #991b1b;
            border-left: 3px solid #ef4444;
        }

        .historico-actions {
            display: flex;
            gap: 0.5rem;
        }

        .historico-actions button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0.3rem;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .btn-edit {
            color: #f59e0b;
        }

        .btn-edit:hover {
            background: #fef3c7;
            transform: scale(1.1);
        }

        .btn-delete {
            color: #ef4444;
        }

        .btn-delete:hover {
            background: #fee2e2;
            transform: scale(1.1);
        }

        /* No servers */
        .no-servers {
            text-align: center;
            padding: 3rem;
            background: #f8fafc;
            border-radius: 20px;
            border: 2px dashed #e2e8f0;
        }

        .no-servers i {
            font-size: 4rem;
            color: #94a3b8;
            margin-bottom: 1rem;
        }

        .no-servers h3 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .no-servers p {
            color: #64748b;
            margin-bottom: 1.5rem;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 1rem;
            }
            
            .servidores-grid {
                grid-template-columns: 1fr;
            }
            
            .token-actions {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
            }
            
            .modern-card .card-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .btn-back {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            <div class="content-header row">
                <div class="col-12">
                    <div class="info-badge">
                        <i class='bx bx-key'></i>
                        <span>Gerencie os tokens de autenticação dos servidores</span>
                    </div>
                </div>
            </div>
            
            <div class="content-body">
                <section id="dashboard-ecommerce">
                    <div class="row">
                        <div class="col-12">
                            <div class="card modern-card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class='bx bx-key'></i>
                                        Tokens dos Servidores
                                    </h4>
                                    <a href="servidores.php" class="btn-back">
                                        <i class='bx bx-arrow-back'></i> Voltar
                                    </a>
                                </div>
                                
                                <div class="card-body">
                                    <?php
                                    // Buscar todos os servidores com seus tokens ativos
                                    $sql_servidores = "SELECT s.* FROM servidores s ORDER BY s.id DESC";
                                    $servidores = mysqli_query($conn, $sql_servidores);
                                    
                                    if (mysqli_num_rows($servidores) > 0):
                                    ?>
                                        <div class="servidores-grid">
                                            <?php while ($s = mysqli_fetch_assoc($servidores)): 
                                                // Buscar token ativo deste servidor
                                                $sql_token_ativo = "SELECT * FROM servidor_tokens WHERE servidor_id = '{$s['id']}' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
                                                $result_token_ativo = mysqli_query($conn, $sql_token_ativo);
                                                $token_ativo = mysqli_fetch_assoc($result_token_ativo);
                                                
                                                // Buscar todos os tokens deste servidor (histórico)
                                                $sql_todos_tokens = "SELECT * FROM servidor_tokens WHERE servidor_id = '{$s['id']}' ORDER BY id DESC LIMIT 5";
                                                $todos_tokens = mysqli_query($conn, $sql_todos_tokens);
                                            ?>
                                                <div class="servidor-card">
                                                    <div class="servidor-header">
                                                        <i class='bx bx-server'></i>
                                                        <div>
                                                            <h3><?php echo htmlspecialchars($s['nome']); ?></h3>
                                                            <p><?php echo $s['ip']; ?>:<?php echo $s['porta']; ?></p>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="servidor-body">
                                                        <!-- Token Ativo -->
                                                        <div class="token-info">
                                                            <div class="token-label">
                                                                <i class='bx bx-key'></i> Token Ativo:
                                                            </div>
                                                            
                                                            <?php if ($token_ativo): ?>
                                                                <div class="token-value">
                                                                    <span><?php echo substr($token_ativo['token'], 0, 20) . '...'; ?></span>
                                                                    <button class="copy-btn" onclick="copiarToken('<?php echo $token_ativo['token']; ?>')" title="Copiar token">
                                                                        <i class='bx bx-copy'></i>
                                                                    </button>
                                                                </div>
                                                                <span class="token-status status-ativo">
                                                                    <i class='bx bx-check-circle'></i> Ativo
                                                                </span>
                                                            <?php else: ?>
                                                                <div class="token-value">
                                                                    <span>Nenhum token ativo</span>
                                                                </div>
                                                                <span class="token-status status-inativo">
                                                                    <i class='bx bx-x-circle'></i> Inativo
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Botões de Ação -->
                                                        <div class="token-actions">
                                                            <!-- Gerar Token Automático -->
                                                            <form method="POST" style="flex: 1;">
                                                                <input type="hidden" name="servidor_id" value="<?php echo $s['id']; ?>">
                                                                <input type="hidden" name="action" value="gerar_token">
                                                                <button type="submit" class="btn-action btn-generate">
                                                                    <i class='bx bx-sync'></i> Gerar Auto
                                                                </button>
                                                            </form>
                                                            
                                                            <!-- Adicionar Token Manual -->
                                                            <button type="button" class="btn-action btn-manual" onclick="abrirModalManual(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['nome']); ?>')">
                                                                <i class='bx bx-pencil'></i> Manual
                                                            </button>
                                                            
                                                            <!-- Ver Histórico -->
                                                            <button type="button" class="btn-action btn-history" onclick="abrirModalHistorico(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['nome']); ?>')">
                                                                <i class='bx bx-history'></i> Histórico
                                                            </button>
                                                        </div>
                                                        
                                                        <!-- Desativar Token Ativo -->
                                                        <?php if ($token_ativo): ?>
                                                            <div style="margin-top: 10px; text-align: center;">
                                                                <form method="POST">
                                                                    <input type="hidden" name="servidor_id" value="<?php echo $s['id']; ?>">
                                                                    <input type="hidden" name="token_id" value="<?php echo $token_ativo['id']; ?>">
                                                                    <input type="hidden" name="status_atual" value="ativo">
                                                                    <input type="hidden" name="action" value="toggle_status">
                                                                    <button type="submit" class="btn-action btn-toggle">
                                                                        <i class='bx bx-power-off'></i> Desativar Token
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-servers">
                                            <i class='bx bx-server'></i>
                                            <h3>Nenhum servidor encontrado</h3>
                                            <p>Adicione um servidor primeiro para gerenciar os tokens.</p>
                                            <a href="adicionarservidor.php" class="btn-action btn-manual" style="display: inline-flex; padding: 12px 30px;">
                                                <i class='bx bx-plus'></i> Adicionar Servidor
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    
    <!-- Modal para Adicionar Token Manual -->
    <div id="modalManual" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal('modalManual')">&times;</span>
            <h3><i class='bx bx-pencil'></i> Adicionar Token Manual</h3>
            <p style="margin-bottom: 20px; color: #64748b;">Servidor: <strong id="servidorNomeManual" style="color: #4158D0;"></strong></p>
            
            <form method="POST" id="formTokenManual">
                <input type="hidden" name="servidor_id" id="servidorIdManual">
                <input type="hidden" name="action" value="adicionar_manual">
                
                <div class="form-group">
                    <label>
                        <i class='bx bx-key'></i>
                        Digite o Token:
                    </label>
                    <input type="text" class="form-control" id="token_manual" name="token_manual" required placeholder="Ex: SsDkpOAUj228ejqBjqBRRlHm2UfyyFyE">
                    <small style="color: #64748b; margin-top: 5px; display: block;">
                        <i class='bx bx-info-circle'></i> O token será salvo em MD5 automaticamente
                    </small>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-modal cancel" onclick="fecharModal('modalManual')">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="submit" class="btn-modal save">
                        <i class='bx bx-check'></i> Salvar Token
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para Editar Token -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal('modalEditar')">&times;</span>
            <h3><i class='bx bx-edit'></i> Editar Token</h3>
            
            <form method="POST" id="formEditarToken">
                <input type="hidden" name="token_id" id="editarTokenId">
                <input type="hidden" name="action" value="editar_token">
                
                <div class="form-group">
                    <label>
                        <i class='bx bx-key'></i>
                        Novo Token:
                    </label>
                    <input type="text" class="form-control" id="novo_token_manual" name="novo_token_manual" required>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-modal cancel" onclick="fecharModal('modalEditar')">
                        <i class='bx bx-x'></i> Cancelar
                    </button>
                    <button type="submit" class="btn-modal edit">
                        <i class='bx bx-save'></i> Atualizar Token
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para Histórico de Tokens -->
    <div id="modalHistorico" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal('modalHistorico')">&times;</span>
            <h3><i class='bx bx-history'></i> Histórico de Tokens</h3>
            <p style="margin-bottom: 20px; color: #64748b;">Servidor: <strong id="servidorNomeHistorico" style="color: #4158D0;"></strong></p>
            
            <div id="historicoTokensList" class="historico-tokens">
                <!-- Carregado via AJAX -->
                <div style="text-align: center; padding: 30px;">
                    <i class='bx bx-loader-alt bx-spin' style="font-size: 2rem; color: #4158D0;"></i>
                    <p style="color: #64748b; margin-top: 10px;">Carregando...</p>
                </div>
            </div>
            
            <div class="modal-buttons" style="margin-top: 20px;">
                <button type="button" class="btn-modal cancel" onclick="fecharModal('modalHistorico')">
                    <i class='bx bx-x'></i> Fechar
                </button>
            </div>
        </div>
    </div>
    
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarToken(token) {
            navigator.clipboard.writeText(token).then(function() {
                swal({
                    title: "Copiado!",
                    text: "Token copiado para a área de transferência",
                    icon: "success",
                    timer: 2000,
                    buttons: false
                });
            });
        }
        
        function abrirModalManual(servidorId, servidorNome) {
            document.getElementById('servidorIdManual').value = servidorId;
            document.getElementById('servidorNomeManual').innerText = servidorNome;
            document.getElementById('modalManual').style.display = 'flex';
        }
        
        function abrirModalEditar(tokenId, tokenAtual) {
            document.getElementById('editarTokenId').value = tokenId;
            document.getElementById('novo_token_manual').value = tokenAtual;
            document.getElementById('modalEditar').style.display = 'flex';
        }
        
        function abrirModalHistorico(servidorId, servidorNome) {
            document.getElementById('servidorNomeHistorico').innerText = servidorNome;
            document.getElementById('modalHistorico').style.display = 'flex';
            
            // Carregar histórico via AJAX
            fetch('get_historico_tokens.php?servidor_id=' + servidorId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('historicoTokensList').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('historicoTokensList').innerHTML = '<div style="text-align: center; padding: 30px; color: #ef4444;"><i class="bx bx-error-circle" style="font-size: 2rem;"></i><p>Erro ao carregar histórico</p></div>';
                });
        }
        
        function deletarToken(tokenId) {
            swal({
                title: "Tem certeza?",
                text: "Esta ação não pode ser desfeita!",
                icon: "warning",
                buttons: ["Cancelar", "Deletar"],
                dangerMode: true,
            }).then((willDelete) => {
                if (willDelete) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="token_id" value="' + tokenId + '"><input type="hidden" name="action" value="deletar_token">';
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        function fecharModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
    
    <style>
        /* Animações */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .servidor-card {
            animation: fadeIn 0.5s ease;
        }
        
        /* Scrollbar personalizada */
        .historico-tokens::-webkit-scrollbar {
            width: 6px;
        }
        
        .historico-tokens::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        .historico-tokens::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            border-radius: 10px;
        }
        
        .historico-tokens::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #C850C0, #4158D0);
        }
    </style>
</body>
</html>
<?php
    }
    aleatorio_gerar_token($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

