<?php
error_reporting(0);
session_start();

// Configurar fuso horÃ¡rio para BrasÃ­lia
date_default_timezone_set('America/Sao_Paulo');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:../index.php');
}

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$id = $_SESSION['iduser'];
include_once 'header2.php';

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
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Buscar dados do usuÃ¡rio logado
$sql = "SELECT login, senha, nome_completo, telefone, profile_image, email, contato FROM accounts WHERE id = '$id'";
$result = $conn->query($sql);
$user_data = $result->fetch_assoc();

// Buscar mensagens do WhatsApp
$sql_whatsapp = "SELECT * FROM mensagens WHERE byid = '$id'";
$result_whatsapp = mysqli_query($conn, $sql_whatsapp);
$whatsapp_mensagens = [];
if ($result_whatsapp && mysqli_num_rows($result_whatsapp) > 0) {
    while ($row = mysqli_fetch_assoc($result_whatsapp)) {
        $whatsapp_mensagens[$row['funcao']] = $row;
    }
}

// Buscar mensagens do Modal
$sql_modal = "SELECT * FROM mensagens_modal WHERE byid = '$id'";
$result_modal = mysqli_query($conn, $sql_modal);
$modal_mensagens = [];
if ($result_modal && mysqli_num_rows($result_modal) > 0) {
    while ($row = mysqli_fetch_assoc($result_modal)) {
        $modal_mensagens[$row['funcao']] = $row['mensagem'];
    }
}

// FunÃ§Ã£o anti-SQL injection
function anti_sql($input)
{
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

// --- LÃ“GICA PARA ATUALIZAR OS DADOS ---
$mensagem_erro = '';
$mensagem_sucesso = '';
$show_modal = false;
$modal_type = '';

// 1. Atualizar informaÃ§Ãµes da conta
if (isset($_POST['salvar_conta'])) {
    $nome_completo = anti_sql($_POST['nome_completo']);
    $email = anti_sql($_POST['email']);
    $telefone = anti_sql($_POST['telefone']);

    $sql_update = "UPDATE accounts SET nome_completo='$nome_completo', email='$email', telefone='$telefone', contato='$telefone' WHERE id='$id'";
    if (mysqli_query($conn, $sql_update)) {
        $mensagem_sucesso = "InformaÃ§Ãµes atualizadas com sucesso!";
        $modal_type = 'success';
        $show_modal = true;
        $user_data['nome_completo'] = $nome_completo;
        $user_data['email'] = $email;
        $user_data['telefone'] = $telefone;
    } else {
        $mensagem_erro = "Erro ao atualizar informaÃ§Ãµes: " . mysqli_error($conn);
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 2. Atualizar a senha
if (isset($_POST['alterar_senha'])) {
    $senha_atual = trim($_POST['senha_atual']);
    $nova_senha = trim($_POST['nova_senha']);
    $confirmar_senha = trim($_POST['confirmar_senha']);

    $sql_senha = "SELECT senha FROM accounts WHERE id='$id'";
    $result_senha = $conn->query($sql_senha);
    $row_senha = $result_senha->fetch_assoc();
    $senha_banco = trim($row_senha['senha']);
    
    if ($senha_atual === $senha_banco) {
        if (strlen($nova_senha) < 5) {
            $mensagem_erro = "A nova senha deve ter no mÃ­nimo 5 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (strlen($nova_senha) > 10) {
            $mensagem_erro = "A nova senha deve ter no mÃ¡ximo 10 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (preg_match('/[^a-z0-9]/i', $nova_senha)) {
            $mensagem_erro = "A senha nÃ£o pode conter caracteres especiais!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif ($nova_senha !== $confirmar_senha) {
            $mensagem_erro = "A confirmaÃ§Ã£o da nova senha nÃ£o coincide!";
            $modal_type = 'error';
            $show_modal = true;
        } else {
            $nova_senha_segura = anti_sql($nova_senha);
            $sql_update_senha = "UPDATE accounts SET senha='$nova_senha_segura' WHERE id='$id'";
            if (mysqli_query($conn, $sql_update_senha)) {
                $_SESSION['senha'] = $nova_senha_segura;
                $mensagem_sucesso = "Senha alterada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao alterar senha: " . mysqli_error($conn);
                $modal_type = 'error';
                $show_modal = true;
            }
        }
    } else {
        $mensagem_erro = "Senha atual incorreta! Verifique se digitou corretamente.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 3. Upload da foto de perfil
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $dir = '../uploads/profiles/';
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $fname = 'profile_' . $id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $fname)) {
                if (!empty($user_data['profile_image']) && file_exists($dir . $user_data['profile_image'])) {
                    unlink($dir . $user_data['profile_image']);
                }
                $conn->query("UPDATE accounts SET profile_image='$fname' WHERE id='$id'");
                $user_data['profile_image'] = $fname;
                $mensagem_sucesso = "Foto de perfil atualizada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao fazer upload da imagem.";
                $modal_type = 'error';
                $show_modal = true;
            }
        } else {
            $mensagem_erro = "Formato de imagem invÃ¡lido. Use JPG, JPEG, PNG, GIF ou WEBP.";
            $modal_type = 'error';
            $show_modal = true;
        }
    } else {
        $mensagem_erro = "Nenhum arquivo selecionado ou erro no upload.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 4. Salvar mensagens do WhatsApp
if (isset($_POST['salvar_mensagens_whatsapp'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda', 'contaexpirada', 'revendaexpirada'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_whatsapp_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do WhatsApp configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $whatsapp_mensagens[$funcao]['mensagem'] = $_POST['mensagem_whatsapp_' . $funcao] ?? '';
    }
}

// 5. Salvar mensagens do Modal
if (isset($_POST['salvar_mensagens_modal'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_modal_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens_modal WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens_modal SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens_modal (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do Modal configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $modal_mensagens[$funcao] = $_POST['mensagem_modal_' . $funcao] ?? '';
    }
}

// Remover parÃ¢metros da URL se existirem
if (isset($_GET['success']) || isset($_GET['error'])) {
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    echo "<script>window.history.replaceState({}, document.title, '$url');</script>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
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
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-server: #3a0ca3;
            --icon-time: #b5179e;
            --icon-credit: #fb8b24;
            --icon-message: #a78bfa;
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
            max-width: 1000px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .profile-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-upload-area:hover {
            border-color: var(--primary);
            background: rgba(65,88,208,0.05);
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .password-requirements {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .password-requirements i {
            color: var(--warning);
        }

        .tabs-container {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tabs-buttons {
            display: flex;
            gap: 5px;
            background: rgba(0,0,0,0.2);
            padding: 5px;
            border-radius: 40px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 2px 10px rgba(65,88,208,0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-preview {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            border-left: 2px solid var(--primary);
        }

        .mensagem-preview i {
            color: var(--tertiary);
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

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
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
        }

        .modal-body-custom {
            padding: 20px;
            color: white;
            text-align: center;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }

        .error-icon i {
            font-size: 60px;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .btn-back {
                margin-left: 0 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .tabs-buttons {
                width: 100%;
            }

            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
error_reporting(0);
session_start();

// Configurar fuso horÃ¡rio para BrasÃ­lia
date_default_timezone_set('America/Sao_Paulo');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:../index.php');
}

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$id = $_SESSION['iduser'];
include_once 'header2.php';

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
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Buscar dados do usuÃ¡rio logado
$sql = "SELECT login, senha, nome_completo, telefone, profile_image, email, contato FROM accounts WHERE id = '$id'";
$result = $conn->query($sql);
$user_data = $result->fetch_assoc();

// Buscar mensagens do WhatsApp
$sql_whatsapp = "SELECT * FROM mensagens WHERE byid = '$id'";
$result_whatsapp = mysqli_query($conn, $sql_whatsapp);
$whatsapp_mensagens = [];
if ($result_whatsapp && mysqli_num_rows($result_whatsapp) > 0) {
    while ($row = mysqli_fetch_assoc($result_whatsapp)) {
        $whatsapp_mensagens[$row['funcao']] = $row;
    }
}

// Buscar mensagens do Modal
$sql_modal = "SELECT * FROM mensagens_modal WHERE byid = '$id'";
$result_modal = mysqli_query($conn, $sql_modal);
$modal_mensagens = [];
if ($result_modal && mysqli_num_rows($result_modal) > 0) {
    while ($row = mysqli_fetch_assoc($result_modal)) {
        $modal_mensagens[$row['funcao']] = $row['mensagem'];
    }
}

// FunÃ§Ã£o anti-SQL injection
function anti_sql($input)
{
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

// --- LÃ“GICA PARA ATUALIZAR OS DADOS ---
$mensagem_erro = '';
$mensagem_sucesso = '';
$show_modal = false;
$modal_type = '';

// 1. Atualizar informaÃ§Ãµes da conta
if (isset($_POST['salvar_conta'])) {
    $nome_completo = anti_sql($_POST['nome_completo']);
    $email = anti_sql($_POST['email']);
    $telefone = anti_sql($_POST['telefone']);

    $sql_update = "UPDATE accounts SET nome_completo='$nome_completo', email='$email', telefone='$telefone', contato='$telefone' WHERE id='$id'";
    if (mysqli_query($conn, $sql_update)) {
        $mensagem_sucesso = "InformaÃ§Ãµes atualizadas com sucesso!";
        $modal_type = 'success';
        $show_modal = true;
        $user_data['nome_completo'] = $nome_completo;
        $user_data['email'] = $email;
        $user_data['telefone'] = $telefone;
    } else {
        $mensagem_erro = "Erro ao atualizar informaÃ§Ãµes: " . mysqli_error($conn);
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 2. Atualizar a senha
if (isset($_POST['alterar_senha'])) {
    $senha_atual = trim($_POST['senha_atual']);
    $nova_senha = trim($_POST['nova_senha']);
    $confirmar_senha = trim($_POST['confirmar_senha']);

    $sql_senha = "SELECT senha FROM accounts WHERE id='$id'";
    $result_senha = $conn->query($sql_senha);
    $row_senha = $result_senha->fetch_assoc();
    $senha_banco = trim($row_senha['senha']);
    
    if ($senha_atual === $senha_banco) {
        if (strlen($nova_senha) < 5) {
            $mensagem_erro = "A nova senha deve ter no mÃ­nimo 5 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (strlen($nova_senha) > 10) {
            $mensagem_erro = "A nova senha deve ter no mÃ¡ximo 10 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (preg_match('/[^a-z0-9]/i', $nova_senha)) {
            $mensagem_erro = "A senha nÃ£o pode conter caracteres especiais!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif ($nova_senha !== $confirmar_senha) {
            $mensagem_erro = "A confirmaÃ§Ã£o da nova senha nÃ£o coincide!";
            $modal_type = 'error';
            $show_modal = true;
        } else {
            $nova_senha_segura = anti_sql($nova_senha);
            $sql_update_senha = "UPDATE accounts SET senha='$nova_senha_segura' WHERE id='$id'";
            if (mysqli_query($conn, $sql_update_senha)) {
                $_SESSION['senha'] = $nova_senha_segura;
                $mensagem_sucesso = "Senha alterada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao alterar senha: " . mysqli_error($conn);
                $modal_type = 'error';
                $show_modal = true;
            }
        }
    } else {
        $mensagem_erro = "Senha atual incorreta! Verifique se digitou corretamente.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 3. Upload da foto de perfil
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $dir = '../uploads/profiles/';
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $fname = 'profile_' . $id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $fname)) {
                if (!empty($user_data['profile_image']) && file_exists($dir . $user_data['profile_image'])) {
                    unlink($dir . $user_data['profile_image']);
                }
                $conn->query("UPDATE accounts SET profile_image='$fname' WHERE id='$id'");
                $user_data['profile_image'] = $fname;
                $mensagem_sucesso = "Foto de perfil atualizada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao fazer upload da imagem.";
                $modal_type = 'error';
                $show_modal = true;
            }
        } else {
            $mensagem_erro = "Formato de imagem invÃ¡lido. Use JPG, JPEG, PNG, GIF ou WEBP.";
            $modal_type = 'error';
            $show_modal = true;
        }
    } else {
        $mensagem_erro = "Nenhum arquivo selecionado ou erro no upload.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 4. Salvar mensagens do WhatsApp
if (isset($_POST['salvar_mensagens_whatsapp'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda', 'contaexpirada', 'revendaexpirada'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_whatsapp_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do WhatsApp configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $whatsapp_mensagens[$funcao]['mensagem'] = $_POST['mensagem_whatsapp_' . $funcao] ?? '';
    }
}

// 5. Salvar mensagens do Modal
if (isset($_POST['salvar_mensagens_modal'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_modal_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens_modal WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens_modal SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens_modal (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do Modal configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $modal_mensagens[$funcao] = $_POST['mensagem_modal_' . $funcao] ?? '';
    }
}

// Remover parÃ¢metros da URL se existirem
if (isset($_GET['success']) || isset($_GET['error'])) {
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    echo "<script>window.history.replaceState({}, document.title, '$url');</script>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
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
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-server: #3a0ca3;
            --icon-time: #b5179e;
            --icon-credit: #fb8b24;
            --icon-message: #a78bfa;
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
            max-width: 1000px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .profile-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-upload-area:hover {
            border-color: var(--primary);
            background: rgba(65,88,208,0.05);
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .password-requirements {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .password-requirements i {
            color: var(--warning);
        }

        .tabs-container {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tabs-buttons {
            display: flex;
            gap: 5px;
            background: rgba(0,0,0,0.2);
            padding: 5px;
            border-radius: 40px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 2px 10px rgba(65,88,208,0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-preview {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            border-left: 2px solid var(--primary);
        }

        .mensagem-preview i {
            color: var(--tertiary);
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

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
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
        }

        .modal-body-custom {
            padding: 20px;
            color: white;
            text-align: center;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }

        .error-icon i {
            font-size: 60px;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .btn-back {
                margin-left: 0 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .tabs-buttons {
                width: 100%;
            }

            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-circle'></i>
                <span>Editar Perfil</span>
            </div>

            <!-- Card Principal - Foto de Perfil -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-camera'></i>
                    </div>
                    <div>
                        <div class="header-title">Foto de Perfil</div>
                        <div class="header-subtitle">Atualize sua foto de perfil</div>
                    </div>
                    <a href="../home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="profile-upload-area">
                        <?php
                        $avatar_url = !empty($user_data['profile_image']) 
                            ? '../uploads/profiles/' . $user_data['profile_image'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['login']) . '&size=100&background=4158D0&color=fff&bold=true&length=2';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="current-avatar" id="profile-avatar-preview">
                        <form method="post" enctype="multipart/form-data" style="width: 100%;">
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <label class="btn-action btn-primary" style="cursor: pointer; margin: 0;">
                                    <i class='bx bx-upload'></i> Escolher Foto
                                    <input type="file" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                </label>
                                <button type="submit" name="upload_image" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Foto
                                </button>
                            </div>
                        </form>
                        <small style="color: rgba(255,255,255,0.3); margin-top: 10px; display: block; font-size: 9px;">
                            <i class='bx bx-info-circle'></i> Formatos permitidos: JPG, JPEG, PNG, GIF, WEBP
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card com Abas - ConfiguraÃ§Ãµes -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes da Conta</div>
                        <div class="header-subtitle">Gerencie suas informaÃ§Ãµes e mensagens</div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Abas -->
                    <div class="tabs-container">
                        <div class="tabs-buttons">
                            <button class="tab-btn active" onclick="switchTab('conta')">
                                <i class='bx bx-user'></i> Conta
                            </button>
                            <button class="tab-btn" onclick="switchTab('seguranca')">
                                <i class='bx bx-lock-alt'></i> SeguranÃ§a
                            </button>
                            <button class="tab-btn" onclick="switchTab('mensagens')">
                                <i class='bx bx-message-detail'></i> WhatsApp
                            </button>
                            <button class="tab-btn" onclick="switchTab('modal-mensagens')">
                                <i class='bx bx-message-rounded-dots'></i> Modal
                            </button>
                        </div>
                    </div>

                    <!-- Aba: InformaÃ§Ãµes da Conta -->
                    <div id="tab-conta" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        NOME COMPLETO
                                    </label>
                                    <input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ''); ?>" placeholder="Digite seu nome completo">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-envelope icon-user'></i>
                                        E-MAIL
                                    </label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="seu@email.com">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        USUÃRIO
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']); ?>" disabled readonly>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> O nome de usuÃ¡rio nÃ£o pode ser alterado
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                        NÃšMERO DE TELEFONE
                                    </label>
                                    <div style="display: flex; gap: 8px;">
                                        <select style="width: 80px; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; padding: 8px 8px; font-size: 12px;">
                                            <option>BR (+55)</option>
                                        </select>
                                        <input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>" placeholder="Exemplo: 99 9 9999-9999" style="flex: 1;">
                                    </div>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> Digite apenas nÃºmeros, ex: 11999999999
                                    </small>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_conta" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar alteraÃ§Ãµes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: SeguranÃ§a (Alterar Senha) -->
                    <div id="tab-seguranca" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-key icon-lock'></i>
                                        SENHA ATUAL
                                    </label>
                                    <input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-lock-alt icon-lock'></i>
                                        NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="nova_senha" placeholder="Digite a nova senha" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-check-shield icon-lock'></i>
                                        CONFIRMAR NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme a nova senha" autocomplete="off">
                                </div>
                            </div>

                            <div class="password-requirements">
                                <i class='bx bx-info-circle'></i> <strong>Requisitos de senha:</strong>
                                <ul>
                                    <li>MÃ­nimo de 5 caracteres e mÃ¡ximo de 10 caracteres</li>
                                    <li>Apenas letras e nÃºmeros (sem caracteres especiais)</li>
                                    <li>Letras maiÃºsculas e minÃºsculas sÃ£o permitidas</li>
                                </ul>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="alterar_senha" class="btn-action btn-success">
                                    <i class='bx bx-key'></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: Mensagens WhatsApp -->
                    <div id="tab-mensagens" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-user-plus icon-message'></i>
                                        Mensagem ao Criar UsuÃ¡rio (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarusuario" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($whatsapp_mensagens['criarusuario']['mensagem'] ?? 'ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-test-tube icon-message'></i>
                                        Mensagem ao Criar Teste (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarteste" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um teste..."><?php echo htmlspecialchars($whatsapp_mensagens['criarteste']['mensagem'] ?? 'ðŸŽ‰ Teste Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nâ±ï¸ DuraÃ§Ã£o: {validade} minutos\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store-alt icon-message'></i>
                                        Mensagem ao Criar Revenda (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarrevenda" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar uma revenda..."><?php echo htmlspecialchars($whatsapp_mensagens['criarrevenda']['mensagem'] ?? 'ðŸŽ‰ Revenda Criada! ðŸŽ‰\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-calendar-x icon-message'></i>
                                        Mensagem de Conta Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_contaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a conta expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['contaexpirada']['mensagem'] ?? 'ðŸ˜© Sua conta estÃ¡ prestes a vencer!\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Renove agora: https://{dominio}/renovacao_login.php'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store icon-message'></i>
                                        Mensagem de Revenda Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_revendaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a revenda expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['revendaexpirada']['mensagem'] ?? 'ðŸ˜© Sua revenda estÃ¡ prestes a vencer!\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Acesse o painel para renovar: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_mensagens_whatsapp" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Mensagens WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>

                 
        <!-- Aba: Mensagens Modal -->
<div id="tab-modal-mensagens" class="tab-content">
    <form method="POST">
        <div class="form-grid">
            <div class="form-field full-width">
                <label>
                    <i class='bx bx-user-plus icon-message'></i>
                    Mensagem ao Criar UsuÃ¡rio (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarusuario" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($modal_mensagens['criarusuario'] ?? 'ðŸŽ‰ Obrigado por escolher nossos serviÃ§os!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ”„ Renove seu plano:\nðŸ”— https://{dominio}/renovacao_login.php\n\nðŸ’¥ Aproveite os melhores servidores!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-test-tube icon-message'></i>
                    Mensagem ao Criar Teste (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarteste" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um teste..."><?php echo htmlspecialchars($modal_mensagens['criarteste'] ?? 'ðŸŽ‰ Teste liberado! Aproveite nosso serviÃ§o!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ’¥ Experimente a melhor conexÃ£o!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-store-alt icon-message'></i>
                    Mensagem ao Criar Revenda (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarrevenda" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar uma revenda..."><?php echo htmlspecialchars($modal_mensagens['criarrevenda'] ?? 'ðŸŽ‰ Revenda criada com sucesso!\n\nðŸ’¥ Comece a vender agora mesmo!\nðŸ”— Link do painel: https://{dominio}/\n\nðŸ“± Indique nosso aplicativo para seus clientes:\nðŸ”— https://{dominio}/aplicativos.php'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" name="salvar_mensagens_modal" class="btn-action btn-success">
                <i class='bx bx-save'></i> Salvar Mensagens Modal
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">OperaÃ§Ã£o realizada!</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_sucesso; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_erro; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function switchTab(tab) {
            document.getElementById('tab-conta').classList.remove('active');
            document.getElementById('tab-seguranca').classList.remove('active');
            document.getElementById('tab-mensagens').classList.remove('active');
            document.getElementById('tab-modal-mensagens').classList.remove('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (tab === 'conta') {
                document.getElementById('tab-conta').classList.add('active');
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (tab === 'seguranca') {
                document.getElementById('tab-seguranca').classList.add('active');
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else if (tab === 'mensagens') {
                document.getElementById('tab-mensagens').classList.add('active');
                document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            } else if (tab === 'modal-mensagens') {
                document.getElementById('tab-modal-mensagens').classList.add('active');
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Remove os parÃ¢metros da URL sem recarregar a pÃ¡gina
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }
        }

        <?php if ($show_modal && $modal_type == 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php elseif ($show_modal && $modal_type == 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });
    </script>
</body>
error_reporting(0);
session_start();

// Configurar fuso horÃ¡rio para BrasÃ­lia
date_default_timezone_set('America/Sao_Paulo');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:../index.php');
}

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$id = $_SESSION['iduser'];
include_once 'header2.php';

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
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Buscar dados do usuÃ¡rio logado
$sql = "SELECT login, senha, nome_completo, telefone, profile_image, email, contato FROM accounts WHERE id = '$id'";
$result = $conn->query($sql);
$user_data = $result->fetch_assoc();

// Buscar mensagens do WhatsApp
$sql_whatsapp = "SELECT * FROM mensagens WHERE byid = '$id'";
$result_whatsapp = mysqli_query($conn, $sql_whatsapp);
$whatsapp_mensagens = [];
if ($result_whatsapp && mysqli_num_rows($result_whatsapp) > 0) {
    while ($row = mysqli_fetch_assoc($result_whatsapp)) {
        $whatsapp_mensagens[$row['funcao']] = $row;
    }
}

// Buscar mensagens do Modal
$sql_modal = "SELECT * FROM mensagens_modal WHERE byid = '$id'";
$result_modal = mysqli_query($conn, $sql_modal);
$modal_mensagens = [];
if ($result_modal && mysqli_num_rows($result_modal) > 0) {
    while ($row = mysqli_fetch_assoc($result_modal)) {
        $modal_mensagens[$row['funcao']] = $row['mensagem'];
    }
}

// FunÃ§Ã£o anti-SQL injection
function anti_sql($input)
{
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

// --- LÃ“GICA PARA ATUALIZAR OS DADOS ---
$mensagem_erro = '';
$mensagem_sucesso = '';
$show_modal = false;
$modal_type = '';

// 1. Atualizar informaÃ§Ãµes da conta
if (isset($_POST['salvar_conta'])) {
    $nome_completo = anti_sql($_POST['nome_completo']);
    $email = anti_sql($_POST['email']);
    $telefone = anti_sql($_POST['telefone']);

    $sql_update = "UPDATE accounts SET nome_completo='$nome_completo', email='$email', telefone='$telefone', contato='$telefone' WHERE id='$id'";
    if (mysqli_query($conn, $sql_update)) {
        $mensagem_sucesso = "InformaÃ§Ãµes atualizadas com sucesso!";
        $modal_type = 'success';
        $show_modal = true;
        $user_data['nome_completo'] = $nome_completo;
        $user_data['email'] = $email;
        $user_data['telefone'] = $telefone;
    } else {
        $mensagem_erro = "Erro ao atualizar informaÃ§Ãµes: " . mysqli_error($conn);
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 2. Atualizar a senha
if (isset($_POST['alterar_senha'])) {
    $senha_atual = trim($_POST['senha_atual']);
    $nova_senha = trim($_POST['nova_senha']);
    $confirmar_senha = trim($_POST['confirmar_senha']);

    $sql_senha = "SELECT senha FROM accounts WHERE id='$id'";
    $result_senha = $conn->query($sql_senha);
    $row_senha = $result_senha->fetch_assoc();
    $senha_banco = trim($row_senha['senha']);
    
    if ($senha_atual === $senha_banco) {
        if (strlen($nova_senha) < 5) {
            $mensagem_erro = "A nova senha deve ter no mÃ­nimo 5 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (strlen($nova_senha) > 10) {
            $mensagem_erro = "A nova senha deve ter no mÃ¡ximo 10 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (preg_match('/[^a-z0-9]/i', $nova_senha)) {
            $mensagem_erro = "A senha nÃ£o pode conter caracteres especiais!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif ($nova_senha !== $confirmar_senha) {
            $mensagem_erro = "A confirmaÃ§Ã£o da nova senha nÃ£o coincide!";
            $modal_type = 'error';
            $show_modal = true;
        } else {
            $nova_senha_segura = anti_sql($nova_senha);
            $sql_update_senha = "UPDATE accounts SET senha='$nova_senha_segura' WHERE id='$id'";
            if (mysqli_query($conn, $sql_update_senha)) {
                $_SESSION['senha'] = $nova_senha_segura;
                $mensagem_sucesso = "Senha alterada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao alterar senha: " . mysqli_error($conn);
                $modal_type = 'error';
                $show_modal = true;
            }
        }
    } else {
        $mensagem_erro = "Senha atual incorreta! Verifique se digitou corretamente.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 3. Upload da foto de perfil
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $dir = '../uploads/profiles/';
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $fname = 'profile_' . $id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $fname)) {
                if (!empty($user_data['profile_image']) && file_exists($dir . $user_data['profile_image'])) {
                    unlink($dir . $user_data['profile_image']);
                }
                $conn->query("UPDATE accounts SET profile_image='$fname' WHERE id='$id'");
                $user_data['profile_image'] = $fname;
                $mensagem_sucesso = "Foto de perfil atualizada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao fazer upload da imagem.";
                $modal_type = 'error';
                $show_modal = true;
            }
        } else {
            $mensagem_erro = "Formato de imagem invÃ¡lido. Use JPG, JPEG, PNG, GIF ou WEBP.";
            $modal_type = 'error';
            $show_modal = true;
        }
    } else {
        $mensagem_erro = "Nenhum arquivo selecionado ou erro no upload.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 4. Salvar mensagens do WhatsApp
if (isset($_POST['salvar_mensagens_whatsapp'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda', 'contaexpirada', 'revendaexpirada'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_whatsapp_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do WhatsApp configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $whatsapp_mensagens[$funcao]['mensagem'] = $_POST['mensagem_whatsapp_' . $funcao] ?? '';
    }
}

// 5. Salvar mensagens do Modal
if (isset($_POST['salvar_mensagens_modal'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_modal_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens_modal WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens_modal SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens_modal (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do Modal configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $modal_mensagens[$funcao] = $_POST['mensagem_modal_' . $funcao] ?? '';
    }
}

// Remover parÃ¢metros da URL se existirem
if (isset($_GET['success']) || isset($_GET['error'])) {
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    echo "<script>window.history.replaceState({}, document.title, '$url');</script>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
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
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-server: #3a0ca3;
            --icon-time: #b5179e;
            --icon-credit: #fb8b24;
            --icon-message: #a78bfa;
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
            max-width: 1000px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .profile-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-upload-area:hover {
            border-color: var(--primary);
            background: rgba(65,88,208,0.05);
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .password-requirements {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .password-requirements i {
            color: var(--warning);
        }

        .tabs-container {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tabs-buttons {
            display: flex;
            gap: 5px;
            background: rgba(0,0,0,0.2);
            padding: 5px;
            border-radius: 40px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 2px 10px rgba(65,88,208,0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-preview {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            border-left: 2px solid var(--primary);
        }

        .mensagem-preview i {
            color: var(--tertiary);
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

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
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
        }

        .modal-body-custom {
            padding: 20px;
            color: white;
            text-align: center;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }

        .error-icon i {
            font-size: 60px;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .btn-back {
                margin-left: 0 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .tabs-buttons {
                width: 100%;
            }

            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-circle'></i>
                <span>Editar Perfil</span>
            </div>

            <!-- Card Principal - Foto de Perfil -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-camera'></i>
                    </div>
                    <div>
                        <div class="header-title">Foto de Perfil</div>
                        <div class="header-subtitle">Atualize sua foto de perfil</div>
                    </div>
                    <a href="../home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="profile-upload-area">
                        <?php
                        $avatar_url = !empty($user_data['profile_image']) 
                            ? '../uploads/profiles/' . $user_data['profile_image'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['login']) . '&size=100&background=4158D0&color=fff&bold=true&length=2';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="current-avatar" id="profile-avatar-preview">
                        <form method="post" enctype="multipart/form-data" style="width: 100%;">
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <label class="btn-action btn-primary" style="cursor: pointer; margin: 0;">
                                    <i class='bx bx-upload'></i> Escolher Foto
                                    <input type="file" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                </label>
                                <button type="submit" name="upload_image" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Foto
                                </button>
                            </div>
                        </form>
                        <small style="color: rgba(255,255,255,0.3); margin-top: 10px; display: block; font-size: 9px;">
                            <i class='bx bx-info-circle'></i> Formatos permitidos: JPG, JPEG, PNG, GIF, WEBP
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card com Abas - ConfiguraÃ§Ãµes -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes da Conta</div>
                        <div class="header-subtitle">Gerencie suas informaÃ§Ãµes e mensagens</div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Abas -->
                    <div class="tabs-container">
                        <div class="tabs-buttons">
                            <button class="tab-btn active" onclick="switchTab('conta')">
                                <i class='bx bx-user'></i> Conta
                            </button>
                            <button class="tab-btn" onclick="switchTab('seguranca')">
                                <i class='bx bx-lock-alt'></i> SeguranÃ§a
                            </button>
                            <button class="tab-btn" onclick="switchTab('mensagens')">
                                <i class='bx bx-message-detail'></i> WhatsApp
                            </button>
                            <button class="tab-btn" onclick="switchTab('modal-mensagens')">
                                <i class='bx bx-message-rounded-dots'></i> Modal
                            </button>
                        </div>
                    </div>

                    <!-- Aba: InformaÃ§Ãµes da Conta -->
                    <div id="tab-conta" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        NOME COMPLETO
                                    </label>
                                    <input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ''); ?>" placeholder="Digite seu nome completo">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-envelope icon-user'></i>
                                        E-MAIL
                                    </label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="seu@email.com">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        USUÃRIO
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']); ?>" disabled readonly>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> O nome de usuÃ¡rio nÃ£o pode ser alterado
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                        NÃšMERO DE TELEFONE
                                    </label>
                                    <div style="display: flex; gap: 8px;">
                                        <select style="width: 80px; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; padding: 8px 8px; font-size: 12px;">
                                            <option>BR (+55)</option>
                                        </select>
                                        <input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>" placeholder="Exemplo: 99 9 9999-9999" style="flex: 1;">
                                    </div>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> Digite apenas nÃºmeros, ex: 11999999999
                                    </small>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_conta" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar alteraÃ§Ãµes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: SeguranÃ§a (Alterar Senha) -->
                    <div id="tab-seguranca" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-key icon-lock'></i>
                                        SENHA ATUAL
                                    </label>
                                    <input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-lock-alt icon-lock'></i>
                                        NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="nova_senha" placeholder="Digite a nova senha" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-check-shield icon-lock'></i>
                                        CONFIRMAR NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme a nova senha" autocomplete="off">
                                </div>
                            </div>

                            <div class="password-requirements">
                                <i class='bx bx-info-circle'></i> <strong>Requisitos de senha:</strong>
                                <ul>
                                    <li>MÃ­nimo de 5 caracteres e mÃ¡ximo de 10 caracteres</li>
                                    <li>Apenas letras e nÃºmeros (sem caracteres especiais)</li>
                                    <li>Letras maiÃºsculas e minÃºsculas sÃ£o permitidas</li>
                                </ul>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="alterar_senha" class="btn-action btn-success">
                                    <i class='bx bx-key'></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: Mensagens WhatsApp -->
                    <div id="tab-mensagens" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-user-plus icon-message'></i>
                                        Mensagem ao Criar UsuÃ¡rio (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarusuario" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($whatsapp_mensagens['criarusuario']['mensagem'] ?? 'ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-test-tube icon-message'></i>
                                        Mensagem ao Criar Teste (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarteste" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um teste..."><?php echo htmlspecialchars($whatsapp_mensagens['criarteste']['mensagem'] ?? 'ðŸŽ‰ Teste Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nâ±ï¸ DuraÃ§Ã£o: {validade} minutos\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store-alt icon-message'></i>
                                        Mensagem ao Criar Revenda (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarrevenda" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar uma revenda..."><?php echo htmlspecialchars($whatsapp_mensagens['criarrevenda']['mensagem'] ?? 'ðŸŽ‰ Revenda Criada! ðŸŽ‰\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-calendar-x icon-message'></i>
                                        Mensagem de Conta Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_contaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a conta expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['contaexpirada']['mensagem'] ?? 'ðŸ˜© Sua conta estÃ¡ prestes a vencer!\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Renove agora: https://{dominio}/renovacao_login.php'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store icon-message'></i>
                                        Mensagem de Revenda Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_revendaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a revenda expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['revendaexpirada']['mensagem'] ?? 'ðŸ˜© Sua revenda estÃ¡ prestes a vencer!\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Acesse o painel para renovar: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_mensagens_whatsapp" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Mensagens WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>

                 
        <!-- Aba: Mensagens Modal -->
<div id="tab-modal-mensagens" class="tab-content">
    <form method="POST">
        <div class="form-grid">
            <div class="form-field full-width">
                <label>
                    <i class='bx bx-user-plus icon-message'></i>
                    Mensagem ao Criar UsuÃ¡rio (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarusuario" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($modal_mensagens['criarusuario'] ?? 'ðŸŽ‰ Obrigado por escolher nossos serviÃ§os!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ”„ Renove seu plano:\nðŸ”— https://{dominio}/renovacao_login.php\n\nðŸ’¥ Aproveite os melhores servidores!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-test-tube icon-message'></i>
                    Mensagem ao Criar Teste (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarteste" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um teste..."><?php echo htmlspecialchars($modal_mensagens['criarteste'] ?? 'ðŸŽ‰ Teste liberado! Aproveite nosso serviÃ§o!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ’¥ Experimente a melhor conexÃ£o!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-store-alt icon-message'></i>
                    Mensagem ao Criar Revenda (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarrevenda" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar uma revenda..."><?php echo htmlspecialchars($modal_mensagens['criarrevenda'] ?? 'ðŸŽ‰ Revenda criada com sucesso!\n\nðŸ’¥ Comece a vender agora mesmo!\nðŸ”— Link do painel: https://{dominio}/\n\nðŸ“± Indique nosso aplicativo para seus clientes:\nðŸ”— https://{dominio}/aplicativos.php'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" name="salvar_mensagens_modal" class="btn-action btn-success">
                <i class='bx bx-save'></i> Salvar Mensagens Modal
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">OperaÃ§Ã£o realizada!</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_sucesso; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_erro; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function switchTab(tab) {
            document.getElementById('tab-conta').classList.remove('active');
            document.getElementById('tab-seguranca').classList.remove('active');
            document.getElementById('tab-mensagens').classList.remove('active');
            document.getElementById('tab-modal-mensagens').classList.remove('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (tab === 'conta') {
                document.getElementById('tab-conta').classList.add('active');
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (tab === 'seguranca') {
                document.getElementById('tab-seguranca').classList.add('active');
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else if (tab === 'mensagens') {
                document.getElementById('tab-mensagens').classList.add('active');
                document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            } else if (tab === 'modal-mensagens') {
                document.getElementById('tab-modal-mensagens').classList.add('active');
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Remove os parÃ¢metros da URL sem recarregar a pÃ¡gina
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }
        }

        <?php if ($show_modal && $modal_type == 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php elseif ($show_modal && $modal_type == 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });
    </script>
</body>
        }
        ?>

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
            max-width: 1000px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .profile-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-upload-area:hover {
            border-color: var(--primary);
            background: rgba(65,88,208,0.05);
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .password-requirements {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .password-requirements i {
            color: var(--warning);
        }

        .tabs-container {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tabs-buttons {
            display: flex;
            gap: 5px;
            background: rgba(0,0,0,0.2);
            padding: 5px;
            border-radius: 40px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 2px 10px rgba(65,88,208,0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-preview {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            border-left: 2px solid var(--primary);
        }

        .mensagem-preview i {
            color: var(--tertiary);
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

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
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
        }

        .modal-body-custom {
            padding: 20px;
            color: white;
            text-align: center;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }

        .error-icon i {
            font-size: 60px;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .btn-back {
                margin-left: 0 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .tabs-buttons {
                width: 100%;
            }

            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-circle'></i>
                <span>Editar Perfil</span>
            </div>

            <!-- Card Principal - Foto de Perfil -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-camera'></i>
                    </div>
                    <div>
                        <div class="header-title">Foto de Perfil</div>
                        <div class="header-subtitle">Atualize sua foto de perfil</div>
                    </div>
                    <a href="../home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="profile-upload-area">
                        <?php
                        $avatar_url = !empty($user_data['profile_image']) 
                            ? '../uploads/profiles/' . $user_data['profile_image'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['login']) . '&size=100&background=4158D0&color=fff&bold=true&length=2';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="current-avatar" id="profile-avatar-preview">
                        <form method="post" enctype="multipart/form-data" style="width: 100%;">
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <label class="btn-action btn-primary" style="cursor: pointer; margin: 0;">
                                    <i class='bx bx-upload'></i> Escolher Foto
                                    <input type="file" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                </label>
                                <button type="submit" name="upload_image" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Foto
                                </button>
                            </div>
                        </form>
                        <small style="color: rgba(255,255,255,0.3); margin-top: 10px; display: block; font-size: 9px;">
                            <i class='bx bx-info-circle'></i> Formatos permitidos: JPG, JPEG, PNG, GIF, WEBP
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card com Abas - ConfiguraÃ§Ãµes -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes da Conta</div>
                        <div class="header-subtitle">Gerencie suas informaÃ§Ãµes e mensagens</div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Abas -->
                    <div class="tabs-container">
                        <div class="tabs-buttons">
                            <button class="tab-btn active" onclick="switchTab('conta')">
                                <i class='bx bx-user'></i> Conta
                            </button>
                            <button class="tab-btn" onclick="switchTab('seguranca')">
                                <i class='bx bx-lock-alt'></i> SeguranÃ§a
                            </button>
                            <button class="tab-btn" onclick="switchTab('mensagens')">
                                <i class='bx bx-message-detail'></i> WhatsApp
                            </button>
                            <button class="tab-btn" onclick="switchTab('modal-mensagens')">
                                <i class='bx bx-message-rounded-dots'></i> Modal
                            </button>
                        </div>
                    </div>

                    <!-- Aba: InformaÃ§Ãµes da Conta -->
                    <div id="tab-conta" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        NOME COMPLETO
                                    </label>
                                    <input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ''); ?>" placeholder="Digite seu nome completo">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-envelope icon-user'></i>
                                        E-MAIL
                                    </label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="seu@email.com">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        USUÃRIO
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']); ?>" disabled readonly>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> O nome de usuÃ¡rio nÃ£o pode ser alterado
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                        NÃšMERO DE TELEFONE
                                    </label>
                                    <div style="display: flex; gap: 8px;">
                                        <select style="width: 80px; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; padding: 8px 8px; font-size: 12px;">
                                            <option>BR (+55)</option>
                                        </select>
                                        <input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>" placeholder="Exemplo: 99 9 9999-9999" style="flex: 1;">
                                    </div>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> Digite apenas nÃºmeros, ex: 11999999999
                                    </small>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_conta" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar alteraÃ§Ãµes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: SeguranÃ§a (Alterar Senha) -->
                    <div id="tab-seguranca" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-key icon-lock'></i>
                                        SENHA ATUAL
                                    </label>
                                    <input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-lock-alt icon-lock'></i>
                                        NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="nova_senha" placeholder="Digite a nova senha" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-check-shield icon-lock'></i>
                                        CONFIRMAR NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme a nova senha" autocomplete="off">
                                </div>
                            </div>

                            <div class="password-requirements">
                                <i class='bx bx-info-circle'></i> <strong>Requisitos de senha:</strong>
                                <ul>
                                    <li>MÃ­nimo de 5 caracteres e mÃ¡ximo de 10 caracteres</li>
                                    <li>Apenas letras e nÃºmeros (sem caracteres especiais)</li>
                                    <li>Letras maiÃºsculas e minÃºsculas sÃ£o permitidas</li>
                                </ul>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="alterar_senha" class="btn-action btn-success">
                                    <i class='bx bx-key'></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: Mensagens WhatsApp -->
                    <div id="tab-mensagens" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-user-plus icon-message'></i>
                                        Mensagem ao Criar UsuÃ¡rio (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarusuario" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($whatsapp_mensagens['criarusuario']['mensagem'] ?? 'ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-test-tube icon-message'></i>
                                        Mensagem ao Criar Teste (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarteste" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um teste..."><?php echo htmlspecialchars($whatsapp_mensagens['criarteste']['mensagem'] ?? 'ðŸŽ‰ Teste Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nâ±ï¸ DuraÃ§Ã£o: {validade} minutos\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store-alt icon-message'></i>
                                        Mensagem ao Criar Revenda (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarrevenda" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar uma revenda..."><?php echo htmlspecialchars($whatsapp_mensagens['criarrevenda']['mensagem'] ?? 'ðŸŽ‰ Revenda Criada! ðŸŽ‰\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-calendar-x icon-message'></i>
                                        Mensagem de Conta Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_contaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a conta expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['contaexpirada']['mensagem'] ?? 'ðŸ˜© Sua conta estÃ¡ prestes a vencer!\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Renove agora: https://{dominio}/renovacao_login.php'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store icon-message'></i>
                                        Mensagem de Revenda Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_revendaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a revenda expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['revendaexpirada']['mensagem'] ?? 'ðŸ˜© Sua revenda estÃ¡ prestes a vencer!\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Acesse o painel para renovar: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_mensagens_whatsapp" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Mensagens WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>

                 
        <!-- Aba: Mensagens Modal -->
<div id="tab-modal-mensagens" class="tab-content">
    <form method="POST">
        <div class="form-grid">
            <div class="form-field full-width">
                <label>
                    <i class='bx bx-user-plus icon-message'></i>
                    Mensagem ao Criar UsuÃ¡rio (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarusuario" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($modal_mensagens['criarusuario'] ?? 'ðŸŽ‰ Obrigado por escolher nossos serviÃ§os!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ”„ Renove seu plano:\nðŸ”— https://{dominio}/renovacao_login.php\n\nðŸ’¥ Aproveite os melhores servidores!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-test-tube icon-message'></i>
                    Mensagem ao Criar Teste (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarteste" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um teste..."><?php echo htmlspecialchars($modal_mensagens['criarteste'] ?? 'ðŸŽ‰ Teste liberado! Aproveite nosso serviÃ§o!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ’¥ Experimente a melhor conexÃ£o!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-store-alt icon-message'></i>
                    Mensagem ao Criar Revenda (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarrevenda" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar uma revenda..."><?php echo htmlspecialchars($modal_mensagens['criarrevenda'] ?? 'ðŸŽ‰ Revenda criada com sucesso!\n\nðŸ’¥ Comece a vender agora mesmo!\nðŸ”— Link do painel: https://{dominio}/\n\nðŸ“± Indique nosso aplicativo para seus clientes:\nðŸ”— https://{dominio}/aplicativos.php'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" name="salvar_mensagens_modal" class="btn-action btn-success">
                <i class='bx bx-save'></i> Salvar Mensagens Modal
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">OperaÃ§Ã£o realizada!</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_sucesso; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_erro; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function switchTab(tab) {
            document.getElementById('tab-conta').classList.remove('active');
            document.getElementById('tab-seguranca').classList.remove('active');
            document.getElementById('tab-mensagens').classList.remove('active');
            document.getElementById('tab-modal-mensagens').classList.remove('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (tab === 'conta') {
                document.getElementById('tab-conta').classList.add('active');
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (tab === 'seguranca') {
                document.getElementById('tab-seguranca').classList.add('active');
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else if (tab === 'mensagens') {
                document.getElementById('tab-mensagens').classList.add('active');
                document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            } else if (tab === 'modal-mensagens') {
                document.getElementById('tab-modal-mensagens').classList.add('active');
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Remove os parÃ¢metros da URL sem recarregar a pÃ¡gina
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }
        }

        <?php if ($show_modal && $modal_type == 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php elseif ($show_modal && $modal_type == 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
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
                <i class='bx bx-user-circle'></i>
                <span>Editar Perfil</span>
            </div>

            <!-- Card Principal - Foto de Perfil -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-camera'></i>
                    </div>
                    <div>
                        <div class="header-title">Foto de Perfil</div>
                        <div class="header-subtitle">Atualize sua foto de perfil</div>
                    </div>
                    <a href="../home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="profile-upload-area">
                        <?php
                        $avatar_url = !empty($user_data['profile_image']) 
                            ? '../uploads/profiles/' . $user_data['profile_image'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['login']) . '&size=100&background=4158D0&color=fff&bold=true&length=2';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="current-avatar" id="profile-avatar-preview">
                        <form method="post" enctype="multipart/form-data" style="width: 100%;">
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <label class="btn-action btn-primary" style="cursor: pointer; margin: 0;">
                                    <i class='bx bx-upload'></i> Escolher Foto
                                    <input type="file" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                </label>
                                <button type="submit" name="upload_image" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Foto
                                </button>
                            </div>
                        </form>
                        <small style="color: rgba(255,255,255,0.3); margin-top: 10px; display: block; font-size: 9px;">
                            <i class='bx bx-info-circle'></i> Formatos permitidos: JPG, JPEG, PNG, GIF, WEBP
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card com Abas - ConfiguraÃ§Ãµes -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes da Conta</div>
                        <div class="header-subtitle">Gerencie suas informaÃ§Ãµes e mensagens</div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Abas -->
                    <div class="tabs-container">
                        <div class="tabs-buttons">
                            <button class="tab-btn active" onclick="switchTab('conta')">
                                <i class='bx bx-user'></i> Conta
                            </button>
                            <button class="tab-btn" onclick="switchTab('seguranca')">
                                <i class='bx bx-lock-alt'></i> SeguranÃ§a
                            </button>
                            <button class="tab-btn" onclick="switchTab('mensagens')">
                                <i class='bx bx-message-detail'></i> WhatsApp
                            </button>
                            <button class="tab-btn" onclick="switchTab('modal-mensagens')">
                                <i class='bx bx-message-rounded-dots'></i> Modal
                            </button>
                        </div>
                    </div>

                    <!-- Aba: InformaÃ§Ãµes da Conta -->
                    <div id="tab-conta" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        NOME COMPLETO
                                    </label>
                                    <input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ''); ?>" placeholder="Digite seu nome completo">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-envelope icon-user'></i>
                                        E-MAIL
                                    </label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="seu@email.com">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        USUÃRIO
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']); ?>" disabled readonly>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> O nome de usuÃ¡rio nÃ£o pode ser alterado
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                        NÃšMERO DE TELEFONE
                                    </label>
                                    <div style="display: flex; gap: 8px;">
                                        <select style="width: 80px; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; padding: 8px 8px; font-size: 12px;">
                                            <option>BR (+55)</option>
                                        </select>
                                        <input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>" placeholder="Exemplo: 99 9 9999-9999" style="flex: 1;">
                                    </div>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> Digite apenas nÃºmeros, ex: 11999999999
                                    </small>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_conta" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar alteraÃ§Ãµes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: SeguranÃ§a (Alterar Senha) -->
                    <div id="tab-seguranca" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-key icon-lock'></i>
                                        SENHA ATUAL
                                    </label>
                                    <input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-lock-alt icon-lock'></i>
                                        NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="nova_senha" placeholder="Digite a nova senha" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-check-shield icon-lock'></i>
                                        CONFIRMAR NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme a nova senha" autocomplete="off">
                                </div>
                            </div>

                            <div class="password-requirements">
                                <i class='bx bx-info-circle'></i> <strong>Requisitos de senha:</strong>
                                <ul>
                                    <li>MÃ­nimo de 5 caracteres e mÃ¡ximo de 10 caracteres</li>
                                    <li>Apenas letras e nÃºmeros (sem caracteres especiais)</li>
                                    <li>Letras maiÃºsculas e minÃºsculas sÃ£o permitidas</li>
                                </ul>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="alterar_senha" class="btn-action btn-success">
                                    <i class='bx bx-key'></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: Mensagens WhatsApp -->
                    <div id="tab-mensagens" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-user-plus icon-message'></i>
                                        Mensagem ao Criar UsuÃ¡rio (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarusuario" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($whatsapp_mensagens['criarusuario']['mensagem'] ?? 'ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-test-tube icon-message'></i>
                                        Mensagem ao Criar Teste (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarteste" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um teste..."><?php echo htmlspecialchars($whatsapp_mensagens['criarteste']['mensagem'] ?? 'ðŸŽ‰ Teste Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nâ±ï¸ DuraÃ§Ã£o: {validade} minutos\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store-alt icon-message'></i>
                                        Mensagem ao Criar Revenda (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarrevenda" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar uma revenda..."><?php echo htmlspecialchars($whatsapp_mensagens['criarrevenda']['mensagem'] ?? 'ðŸŽ‰ Revenda Criada! ðŸŽ‰\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-calendar-x icon-message'></i>
                                        Mensagem de Conta Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_contaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a conta expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['contaexpirada']['mensagem'] ?? 'ðŸ˜© Sua conta estÃ¡ prestes a vencer!\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Renove agora: https://{dominio}/renovacao_login.php'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store icon-message'></i>
                                        Mensagem de Revenda Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_revendaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a revenda expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['revendaexpirada']['mensagem'] ?? 'ðŸ˜© Sua revenda estÃ¡ prestes a vencer!\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Acesse o painel para renovar: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_mensagens_whatsapp" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Mensagens WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>

                 
        <!-- Aba: Mensagens Modal -->
<div id="tab-modal-mensagens" class="tab-content">
    <form method="POST">
        <div class="form-grid">
            <div class="form-field full-width">
                <label>
                    <i class='bx bx-user-plus icon-message'></i>
                    Mensagem ao Criar UsuÃ¡rio (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarusuario" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($modal_mensagens['criarusuario'] ?? 'ðŸŽ‰ Obrigado por escolher nossos serviÃ§os!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ”„ Renove seu plano:\nðŸ”— https://{dominio}/renovacao_login.php\n\nðŸ’¥ Aproveite os melhores servidores!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-test-tube icon-message'></i>
                    Mensagem ao Criar Teste (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarteste" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um teste..."><?php echo htmlspecialchars($modal_mensagens['criarteste'] ?? 'ðŸŽ‰ Teste liberado! Aproveite nosso serviÃ§o!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ’¥ Experimente a melhor conexÃ£o!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-store-alt icon-message'></i>
                    Mensagem ao Criar Revenda (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarrevenda" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar uma revenda..."><?php echo htmlspecialchars($modal_mensagens['criarrevenda'] ?? 'ðŸŽ‰ Revenda criada com sucesso!\n\nðŸ’¥ Comece a vender agora mesmo!\nðŸ”— Link do painel: https://{dominio}/\n\nðŸ“± Indique nosso aplicativo para seus clientes:\nðŸ”— https://{dominio}/aplicativos.php'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" name="salvar_mensagens_modal" class="btn-action btn-success">
                <i class='bx bx-save'></i> Salvar Mensagens Modal
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">OperaÃ§Ã£o realizada!</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_sucesso; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_erro; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function switchTab(tab) {
            document.getElementById('tab-conta').classList.remove('active');
            document.getElementById('tab-seguranca').classList.remove('active');
            document.getElementById('tab-mensagens').classList.remove('active');
            document.getElementById('tab-modal-mensagens').classList.remove('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (tab === 'conta') {
                document.getElementById('tab-conta').classList.add('active');
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (tab === 'seguranca') {
                document.getElementById('tab-seguranca').classList.add('active');
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else if (tab === 'mensagens') {
                document.getElementById('tab-mensagens').classList.add('active');
                document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            } else if (tab === 'modal-mensagens') {
                document.getElementById('tab-modal-mensagens').classList.add('active');
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Remove os parÃ¢metros da URL sem recarregar a pÃ¡gina
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }
        }

        <?php if ($show_modal && $modal_type == 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php elseif ($show_modal && $modal_type == 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });
    </script>
</body>
error_reporting(0);
session_start();

// Configurar fuso horÃ¡rio para BrasÃ­lia
date_default_timezone_set('America/Sao_Paulo');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:../index.php');
}

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$id = $_SESSION['iduser'];
include_once 'header2.php';

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
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Buscar dados do usuÃ¡rio logado
$sql = "SELECT login, senha, nome_completo, telefone, profile_image, email, contato FROM accounts WHERE id = '$id'";
$result = $conn->query($sql);
$user_data = $result->fetch_assoc();

// Buscar mensagens do WhatsApp
$sql_whatsapp = "SELECT * FROM mensagens WHERE byid = '$id'";
$result_whatsapp = mysqli_query($conn, $sql_whatsapp);
$whatsapp_mensagens = [];
if ($result_whatsapp && mysqli_num_rows($result_whatsapp) > 0) {
    while ($row = mysqli_fetch_assoc($result_whatsapp)) {
        $whatsapp_mensagens[$row['funcao']] = $row;
    }
}

// Buscar mensagens do Modal
$sql_modal = "SELECT * FROM mensagens_modal WHERE byid = '$id'";
$result_modal = mysqli_query($conn, $sql_modal);
$modal_mensagens = [];
if ($result_modal && mysqli_num_rows($result_modal) > 0) {
    while ($row = mysqli_fetch_assoc($result_modal)) {
        $modal_mensagens[$row['funcao']] = $row['mensagem'];
    }
}

// FunÃ§Ã£o anti-SQL injection
function anti_sql($input)
{
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

// --- LÃ“GICA PARA ATUALIZAR OS DADOS ---
$mensagem_erro = '';
$mensagem_sucesso = '';
$show_modal = false;
$modal_type = '';

// 1. Atualizar informaÃ§Ãµes da conta
if (isset($_POST['salvar_conta'])) {
    $nome_completo = anti_sql($_POST['nome_completo']);
    $email = anti_sql($_POST['email']);
    $telefone = anti_sql($_POST['telefone']);

    $sql_update = "UPDATE accounts SET nome_completo='$nome_completo', email='$email', telefone='$telefone', contato='$telefone' WHERE id='$id'";
    if (mysqli_query($conn, $sql_update)) {
        $mensagem_sucesso = "InformaÃ§Ãµes atualizadas com sucesso!";
        $modal_type = 'success';
        $show_modal = true;
        $user_data['nome_completo'] = $nome_completo;
        $user_data['email'] = $email;
        $user_data['telefone'] = $telefone;
    } else {
        $mensagem_erro = "Erro ao atualizar informaÃ§Ãµes: " . mysqli_error($conn);
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 2. Atualizar a senha
if (isset($_POST['alterar_senha'])) {
    $senha_atual = trim($_POST['senha_atual']);
    $nova_senha = trim($_POST['nova_senha']);
    $confirmar_senha = trim($_POST['confirmar_senha']);

    $sql_senha = "SELECT senha FROM accounts WHERE id='$id'";
    $result_senha = $conn->query($sql_senha);
    $row_senha = $result_senha->fetch_assoc();
    $senha_banco = trim($row_senha['senha']);
    
    if ($senha_atual === $senha_banco) {
        if (strlen($nova_senha) < 5) {
            $mensagem_erro = "A nova senha deve ter no mÃ­nimo 5 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (strlen($nova_senha) > 10) {
            $mensagem_erro = "A nova senha deve ter no mÃ¡ximo 10 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (preg_match('/[^a-z0-9]/i', $nova_senha)) {
            $mensagem_erro = "A senha nÃ£o pode conter caracteres especiais!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif ($nova_senha !== $confirmar_senha) {
            $mensagem_erro = "A confirmaÃ§Ã£o da nova senha nÃ£o coincide!";
            $modal_type = 'error';
            $show_modal = true;
        } else {
            $nova_senha_segura = anti_sql($nova_senha);
            $sql_update_senha = "UPDATE accounts SET senha='$nova_senha_segura' WHERE id='$id'";
            if (mysqli_query($conn, $sql_update_senha)) {
                $_SESSION['senha'] = $nova_senha_segura;
                $mensagem_sucesso = "Senha alterada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao alterar senha: " . mysqli_error($conn);
                $modal_type = 'error';
                $show_modal = true;
            }
        }
    } else {
        $mensagem_erro = "Senha atual incorreta! Verifique se digitou corretamente.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 3. Upload da foto de perfil
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $dir = '../uploads/profiles/';
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $fname = 'profile_' . $id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $fname)) {
                if (!empty($user_data['profile_image']) && file_exists($dir . $user_data['profile_image'])) {
                    unlink($dir . $user_data['profile_image']);
                }
                $conn->query("UPDATE accounts SET profile_image='$fname' WHERE id='$id'");
                $user_data['profile_image'] = $fname;
                $mensagem_sucesso = "Foto de perfil atualizada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao fazer upload da imagem.";
                $modal_type = 'error';
                $show_modal = true;
            }
        } else {
            $mensagem_erro = "Formato de imagem invÃ¡lido. Use JPG, JPEG, PNG, GIF ou WEBP.";
            $modal_type = 'error';
            $show_modal = true;
        }
    } else {
        $mensagem_erro = "Nenhum arquivo selecionado ou erro no upload.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 4. Salvar mensagens do WhatsApp
if (isset($_POST['salvar_mensagens_whatsapp'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda', 'contaexpirada', 'revendaexpirada'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_whatsapp_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do WhatsApp configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $whatsapp_mensagens[$funcao]['mensagem'] = $_POST['mensagem_whatsapp_' . $funcao] ?? '';
    }
}

// 5. Salvar mensagens do Modal
if (isset($_POST['salvar_mensagens_modal'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_modal_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens_modal WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens_modal SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens_modal (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do Modal configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $modal_mensagens[$funcao] = $_POST['mensagem_modal_' . $funcao] ?? '';
    }
}

// Remover parÃ¢metros da URL se existirem
if (isset($_GET['success']) || isset($_GET['error'])) {
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    echo "<script>window.history.replaceState({}, document.title, '$url');</script>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
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
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-server: #3a0ca3;
            --icon-time: #b5179e;
            --icon-credit: #fb8b24;
            --icon-message: #a78bfa;
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
            max-width: 1000px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .profile-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-upload-area:hover {
            border-color: var(--primary);
            background: rgba(65,88,208,0.05);
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .password-requirements {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .password-requirements i {
            color: var(--warning);
        }

        .tabs-container {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tabs-buttons {
            display: flex;
            gap: 5px;
            background: rgba(0,0,0,0.2);
            padding: 5px;
            border-radius: 40px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 2px 10px rgba(65,88,208,0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-preview {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            border-left: 2px solid var(--primary);
        }

        .mensagem-preview i {
            color: var(--tertiary);
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

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
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
        }

        .modal-body-custom {
            padding: 20px;
            color: white;
            text-align: center;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }

        .error-icon i {
            font-size: 60px;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .btn-back {
                margin-left: 0 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .tabs-buttons {
                width: 100%;
            }

            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
error_reporting(0);
session_start();

// Configurar fuso horÃ¡rio para BrasÃ­lia
date_default_timezone_set('America/Sao_Paulo');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:../index.php');
}

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$id = $_SESSION['iduser'];
include_once 'header2.php';

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
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Buscar dados do usuÃ¡rio logado
$sql = "SELECT login, senha, nome_completo, telefone, profile_image, email, contato FROM accounts WHERE id = '$id'";
$result = $conn->query($sql);
$user_data = $result->fetch_assoc();

// Buscar mensagens do WhatsApp
$sql_whatsapp = "SELECT * FROM mensagens WHERE byid = '$id'";
$result_whatsapp = mysqli_query($conn, $sql_whatsapp);
$whatsapp_mensagens = [];
if ($result_whatsapp && mysqli_num_rows($result_whatsapp) > 0) {
    while ($row = mysqli_fetch_assoc($result_whatsapp)) {
        $whatsapp_mensagens[$row['funcao']] = $row;
    }
}

// Buscar mensagens do Modal
$sql_modal = "SELECT * FROM mensagens_modal WHERE byid = '$id'";
$result_modal = mysqli_query($conn, $sql_modal);
$modal_mensagens = [];
if ($result_modal && mysqli_num_rows($result_modal) > 0) {
    while ($row = mysqli_fetch_assoc($result_modal)) {
        $modal_mensagens[$row['funcao']] = $row['mensagem'];
    }
}

// FunÃ§Ã£o anti-SQL injection
function anti_sql($input)
{
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

// --- LÃ“GICA PARA ATUALIZAR OS DADOS ---
$mensagem_erro = '';
$mensagem_sucesso = '';
$show_modal = false;
$modal_type = '';

// 1. Atualizar informaÃ§Ãµes da conta
if (isset($_POST['salvar_conta'])) {
    $nome_completo = anti_sql($_POST['nome_completo']);
    $email = anti_sql($_POST['email']);
    $telefone = anti_sql($_POST['telefone']);

    $sql_update = "UPDATE accounts SET nome_completo='$nome_completo', email='$email', telefone='$telefone', contato='$telefone' WHERE id='$id'";
    if (mysqli_query($conn, $sql_update)) {
        $mensagem_sucesso = "InformaÃ§Ãµes atualizadas com sucesso!";
        $modal_type = 'success';
        $show_modal = true;
        $user_data['nome_completo'] = $nome_completo;
        $user_data['email'] = $email;
        $user_data['telefone'] = $telefone;
    } else {
        $mensagem_erro = "Erro ao atualizar informaÃ§Ãµes: " . mysqli_error($conn);
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 2. Atualizar a senha
if (isset($_POST['alterar_senha'])) {
    $senha_atual = trim($_POST['senha_atual']);
    $nova_senha = trim($_POST['nova_senha']);
    $confirmar_senha = trim($_POST['confirmar_senha']);

    $sql_senha = "SELECT senha FROM accounts WHERE id='$id'";
    $result_senha = $conn->query($sql_senha);
    $row_senha = $result_senha->fetch_assoc();
    $senha_banco = trim($row_senha['senha']);
    
    if ($senha_atual === $senha_banco) {
        if (strlen($nova_senha) < 5) {
            $mensagem_erro = "A nova senha deve ter no mÃ­nimo 5 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (strlen($nova_senha) > 10) {
            $mensagem_erro = "A nova senha deve ter no mÃ¡ximo 10 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (preg_match('/[^a-z0-9]/i', $nova_senha)) {
            $mensagem_erro = "A senha nÃ£o pode conter caracteres especiais!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif ($nova_senha !== $confirmar_senha) {
            $mensagem_erro = "A confirmaÃ§Ã£o da nova senha nÃ£o coincide!";
            $modal_type = 'error';
            $show_modal = true;
        } else {
            $nova_senha_segura = anti_sql($nova_senha);
            $sql_update_senha = "UPDATE accounts SET senha='$nova_senha_segura' WHERE id='$id'";
            if (mysqli_query($conn, $sql_update_senha)) {
                $_SESSION['senha'] = $nova_senha_segura;
                $mensagem_sucesso = "Senha alterada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao alterar senha: " . mysqli_error($conn);
                $modal_type = 'error';
                $show_modal = true;
            }
        }
    } else {
        $mensagem_erro = "Senha atual incorreta! Verifique se digitou corretamente.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 3. Upload da foto de perfil
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $dir = '../uploads/profiles/';
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $fname = 'profile_' . $id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $fname)) {
                if (!empty($user_data['profile_image']) && file_exists($dir . $user_data['profile_image'])) {
                    unlink($dir . $user_data['profile_image']);
                }
                $conn->query("UPDATE accounts SET profile_image='$fname' WHERE id='$id'");
                $user_data['profile_image'] = $fname;
                $mensagem_sucesso = "Foto de perfil atualizada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao fazer upload da imagem.";
                $modal_type = 'error';
                $show_modal = true;
            }
        } else {
            $mensagem_erro = "Formato de imagem invÃ¡lido. Use JPG, JPEG, PNG, GIF ou WEBP.";
            $modal_type = 'error';
            $show_modal = true;
        }
    } else {
        $mensagem_erro = "Nenhum arquivo selecionado ou erro no upload.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 4. Salvar mensagens do WhatsApp
if (isset($_POST['salvar_mensagens_whatsapp'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda', 'contaexpirada', 'revendaexpirada'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_whatsapp_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do WhatsApp configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $whatsapp_mensagens[$funcao]['mensagem'] = $_POST['mensagem_whatsapp_' . $funcao] ?? '';
    }
}

// 5. Salvar mensagens do Modal
if (isset($_POST['salvar_mensagens_modal'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_modal_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens_modal WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens_modal SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens_modal (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do Modal configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $modal_mensagens[$funcao] = $_POST['mensagem_modal_' . $funcao] ?? '';
    }
}

// Remover parÃ¢metros da URL se existirem
if (isset($_GET['success']) || isset($_GET['error'])) {
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    echo "<script>window.history.replaceState({}, document.title, '$url');</script>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
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
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-server: #3a0ca3;
            --icon-time: #b5179e;
            --icon-credit: #fb8b24;
            --icon-message: #a78bfa;
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
            max-width: 1000px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .profile-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-upload-area:hover {
            border-color: var(--primary);
            background: rgba(65,88,208,0.05);
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .password-requirements {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .password-requirements i {
            color: var(--warning);
        }

        .tabs-container {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tabs-buttons {
            display: flex;
            gap: 5px;
            background: rgba(0,0,0,0.2);
            padding: 5px;
            border-radius: 40px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 2px 10px rgba(65,88,208,0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-preview {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            border-left: 2px solid var(--primary);
        }

        .mensagem-preview i {
            color: var(--tertiary);
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

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
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
        }

        .modal-body-custom {
            padding: 20px;
            color: white;
            text-align: center;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }

        .error-icon i {
            font-size: 60px;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .btn-back {
                margin-left: 0 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .tabs-buttons {
                width: 100%;
            }

            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-circle'></i>
                <span>Editar Perfil</span>
            </div>

            <!-- Card Principal - Foto de Perfil -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-camera'></i>
                    </div>
                    <div>
                        <div class="header-title">Foto de Perfil</div>
                        <div class="header-subtitle">Atualize sua foto de perfil</div>
                    </div>
                    <a href="../home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="profile-upload-area">
                        <?php
                        $avatar_url = !empty($user_data['profile_image']) 
                            ? '../uploads/profiles/' . $user_data['profile_image'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['login']) . '&size=100&background=4158D0&color=fff&bold=true&length=2';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="current-avatar" id="profile-avatar-preview">
                        <form method="post" enctype="multipart/form-data" style="width: 100%;">
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <label class="btn-action btn-primary" style="cursor: pointer; margin: 0;">
                                    <i class='bx bx-upload'></i> Escolher Foto
                                    <input type="file" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                </label>
                                <button type="submit" name="upload_image" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Foto
                                </button>
                            </div>
                        </form>
                        <small style="color: rgba(255,255,255,0.3); margin-top: 10px; display: block; font-size: 9px;">
                            <i class='bx bx-info-circle'></i> Formatos permitidos: JPG, JPEG, PNG, GIF, WEBP
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card com Abas - ConfiguraÃ§Ãµes -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes da Conta</div>
                        <div class="header-subtitle">Gerencie suas informaÃ§Ãµes e mensagens</div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Abas -->
                    <div class="tabs-container">
                        <div class="tabs-buttons">
                            <button class="tab-btn active" onclick="switchTab('conta')">
                                <i class='bx bx-user'></i> Conta
                            </button>
                            <button class="tab-btn" onclick="switchTab('seguranca')">
                                <i class='bx bx-lock-alt'></i> SeguranÃ§a
                            </button>
                            <button class="tab-btn" onclick="switchTab('mensagens')">
                                <i class='bx bx-message-detail'></i> WhatsApp
                            </button>
                            <button class="tab-btn" onclick="switchTab('modal-mensagens')">
                                <i class='bx bx-message-rounded-dots'></i> Modal
                            </button>
                        </div>
                    </div>

                    <!-- Aba: InformaÃ§Ãµes da Conta -->
                    <div id="tab-conta" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        NOME COMPLETO
                                    </label>
                                    <input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ''); ?>" placeholder="Digite seu nome completo">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-envelope icon-user'></i>
                                        E-MAIL
                                    </label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="seu@email.com">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        USUÃRIO
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']); ?>" disabled readonly>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> O nome de usuÃ¡rio nÃ£o pode ser alterado
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                        NÃšMERO DE TELEFONE
                                    </label>
                                    <div style="display: flex; gap: 8px;">
                                        <select style="width: 80px; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; padding: 8px 8px; font-size: 12px;">
                                            <option>BR (+55)</option>
                                        </select>
                                        <input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>" placeholder="Exemplo: 99 9 9999-9999" style="flex: 1;">
                                    </div>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> Digite apenas nÃºmeros, ex: 11999999999
                                    </small>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_conta" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar alteraÃ§Ãµes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: SeguranÃ§a (Alterar Senha) -->
                    <div id="tab-seguranca" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-key icon-lock'></i>
                                        SENHA ATUAL
                                    </label>
                                    <input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-lock-alt icon-lock'></i>
                                        NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="nova_senha" placeholder="Digite a nova senha" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-check-shield icon-lock'></i>
                                        CONFIRMAR NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme a nova senha" autocomplete="off">
                                </div>
                            </div>

                            <div class="password-requirements">
                                <i class='bx bx-info-circle'></i> <strong>Requisitos de senha:</strong>
                                <ul>
                                    <li>MÃ­nimo de 5 caracteres e mÃ¡ximo de 10 caracteres</li>
                                    <li>Apenas letras e nÃºmeros (sem caracteres especiais)</li>
                                    <li>Letras maiÃºsculas e minÃºsculas sÃ£o permitidas</li>
                                </ul>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="alterar_senha" class="btn-action btn-success">
                                    <i class='bx bx-key'></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: Mensagens WhatsApp -->
                    <div id="tab-mensagens" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-user-plus icon-message'></i>
                                        Mensagem ao Criar UsuÃ¡rio (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarusuario" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($whatsapp_mensagens['criarusuario']['mensagem'] ?? 'ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-test-tube icon-message'></i>
                                        Mensagem ao Criar Teste (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarteste" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um teste..."><?php echo htmlspecialchars($whatsapp_mensagens['criarteste']['mensagem'] ?? 'ðŸŽ‰ Teste Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nâ±ï¸ DuraÃ§Ã£o: {validade} minutos\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store-alt icon-message'></i>
                                        Mensagem ao Criar Revenda (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarrevenda" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar uma revenda..."><?php echo htmlspecialchars($whatsapp_mensagens['criarrevenda']['mensagem'] ?? 'ðŸŽ‰ Revenda Criada! ðŸŽ‰\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-calendar-x icon-message'></i>
                                        Mensagem de Conta Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_contaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a conta expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['contaexpirada']['mensagem'] ?? 'ðŸ˜© Sua conta estÃ¡ prestes a vencer!\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Renove agora: https://{dominio}/renovacao_login.php'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store icon-message'></i>
                                        Mensagem de Revenda Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_revendaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a revenda expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['revendaexpirada']['mensagem'] ?? 'ðŸ˜© Sua revenda estÃ¡ prestes a vencer!\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Acesse o painel para renovar: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_mensagens_whatsapp" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Mensagens WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>

                 
        <!-- Aba: Mensagens Modal -->
<div id="tab-modal-mensagens" class="tab-content">
    <form method="POST">
        <div class="form-grid">
            <div class="form-field full-width">
                <label>
                    <i class='bx bx-user-plus icon-message'></i>
                    Mensagem ao Criar UsuÃ¡rio (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarusuario" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($modal_mensagens['criarusuario'] ?? 'ðŸŽ‰ Obrigado por escolher nossos serviÃ§os!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ”„ Renove seu plano:\nðŸ”— https://{dominio}/renovacao_login.php\n\nðŸ’¥ Aproveite os melhores servidores!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-test-tube icon-message'></i>
                    Mensagem ao Criar Teste (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarteste" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um teste..."><?php echo htmlspecialchars($modal_mensagens['criarteste'] ?? 'ðŸŽ‰ Teste liberado! Aproveite nosso serviÃ§o!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ’¥ Experimente a melhor conexÃ£o!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-store-alt icon-message'></i>
                    Mensagem ao Criar Revenda (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarrevenda" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar uma revenda..."><?php echo htmlspecialchars($modal_mensagens['criarrevenda'] ?? 'ðŸŽ‰ Revenda criada com sucesso!\n\nðŸ’¥ Comece a vender agora mesmo!\nðŸ”— Link do painel: https://{dominio}/\n\nðŸ“± Indique nosso aplicativo para seus clientes:\nðŸ”— https://{dominio}/aplicativos.php'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" name="salvar_mensagens_modal" class="btn-action btn-success">
                <i class='bx bx-save'></i> Salvar Mensagens Modal
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">OperaÃ§Ã£o realizada!</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_sucesso; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_erro; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function switchTab(tab) {
            document.getElementById('tab-conta').classList.remove('active');
            document.getElementById('tab-seguranca').classList.remove('active');
            document.getElementById('tab-mensagens').classList.remove('active');
            document.getElementById('tab-modal-mensagens').classList.remove('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (tab === 'conta') {
                document.getElementById('tab-conta').classList.add('active');
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (tab === 'seguranca') {
                document.getElementById('tab-seguranca').classList.add('active');
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else if (tab === 'mensagens') {
                document.getElementById('tab-mensagens').classList.add('active');
                document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            } else if (tab === 'modal-mensagens') {
                document.getElementById('tab-modal-mensagens').classList.add('active');
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Remove os parÃ¢metros da URL sem recarregar a pÃ¡gina
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }
        }

        <?php if ($show_modal && $modal_type == 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php elseif ($show_modal && $modal_type == 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });
    </script>
</body>
error_reporting(0);
session_start();

// Configurar fuso horÃ¡rio para BrasÃ­lia
date_default_timezone_set('America/Sao_Paulo');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:../index.php');
}

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$id = $_SESSION['iduser'];
include_once 'header2.php';

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
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Buscar dados do usuÃ¡rio logado
$sql = "SELECT login, senha, nome_completo, telefone, profile_image, email, contato FROM accounts WHERE id = '$id'";
$result = $conn->query($sql);
$user_data = $result->fetch_assoc();

// Buscar mensagens do WhatsApp
$sql_whatsapp = "SELECT * FROM mensagens WHERE byid = '$id'";
$result_whatsapp = mysqli_query($conn, $sql_whatsapp);
$whatsapp_mensagens = [];
if ($result_whatsapp && mysqli_num_rows($result_whatsapp) > 0) {
    while ($row = mysqli_fetch_assoc($result_whatsapp)) {
        $whatsapp_mensagens[$row['funcao']] = $row;
    }
}

// Buscar mensagens do Modal
$sql_modal = "SELECT * FROM mensagens_modal WHERE byid = '$id'";
$result_modal = mysqli_query($conn, $sql_modal);
$modal_mensagens = [];
if ($result_modal && mysqli_num_rows($result_modal) > 0) {
    while ($row = mysqli_fetch_assoc($result_modal)) {
        $modal_mensagens[$row['funcao']] = $row['mensagem'];
    }
}

// FunÃ§Ã£o anti-SQL injection
function anti_sql($input)
{
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

// --- LÃ“GICA PARA ATUALIZAR OS DADOS ---
$mensagem_erro = '';
$mensagem_sucesso = '';
$show_modal = false;
$modal_type = '';

// 1. Atualizar informaÃ§Ãµes da conta
if (isset($_POST['salvar_conta'])) {
    $nome_completo = anti_sql($_POST['nome_completo']);
    $email = anti_sql($_POST['email']);
    $telefone = anti_sql($_POST['telefone']);

    $sql_update = "UPDATE accounts SET nome_completo='$nome_completo', email='$email', telefone='$telefone', contato='$telefone' WHERE id='$id'";
    if (mysqli_query($conn, $sql_update)) {
        $mensagem_sucesso = "InformaÃ§Ãµes atualizadas com sucesso!";
        $modal_type = 'success';
        $show_modal = true;
        $user_data['nome_completo'] = $nome_completo;
        $user_data['email'] = $email;
        $user_data['telefone'] = $telefone;
    } else {
        $mensagem_erro = "Erro ao atualizar informaÃ§Ãµes: " . mysqli_error($conn);
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 2. Atualizar a senha
if (isset($_POST['alterar_senha'])) {
    $senha_atual = trim($_POST['senha_atual']);
    $nova_senha = trim($_POST['nova_senha']);
    $confirmar_senha = trim($_POST['confirmar_senha']);

    $sql_senha = "SELECT senha FROM accounts WHERE id='$id'";
    $result_senha = $conn->query($sql_senha);
    $row_senha = $result_senha->fetch_assoc();
    $senha_banco = trim($row_senha['senha']);
    
    if ($senha_atual === $senha_banco) {
        if (strlen($nova_senha) < 5) {
            $mensagem_erro = "A nova senha deve ter no mÃ­nimo 5 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (strlen($nova_senha) > 10) {
            $mensagem_erro = "A nova senha deve ter no mÃ¡ximo 10 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (preg_match('/[^a-z0-9]/i', $nova_senha)) {
            $mensagem_erro = "A senha nÃ£o pode conter caracteres especiais!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif ($nova_senha !== $confirmar_senha) {
            $mensagem_erro = "A confirmaÃ§Ã£o da nova senha nÃ£o coincide!";
            $modal_type = 'error';
            $show_modal = true;
        } else {
            $nova_senha_segura = anti_sql($nova_senha);
            $sql_update_senha = "UPDATE accounts SET senha='$nova_senha_segura' WHERE id='$id'";
            if (mysqli_query($conn, $sql_update_senha)) {
                $_SESSION['senha'] = $nova_senha_segura;
                $mensagem_sucesso = "Senha alterada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao alterar senha: " . mysqli_error($conn);
                $modal_type = 'error';
                $show_modal = true;
            }
        }
    } else {
        $mensagem_erro = "Senha atual incorreta! Verifique se digitou corretamente.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 3. Upload da foto de perfil
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $dir = '../uploads/profiles/';
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $fname = 'profile_' . $id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $fname)) {
                if (!empty($user_data['profile_image']) && file_exists($dir . $user_data['profile_image'])) {
                    unlink($dir . $user_data['profile_image']);
                }
                $conn->query("UPDATE accounts SET profile_image='$fname' WHERE id='$id'");
                $user_data['profile_image'] = $fname;
                $mensagem_sucesso = "Foto de perfil atualizada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao fazer upload da imagem.";
                $modal_type = 'error';
                $show_modal = true;
            }
        } else {
            $mensagem_erro = "Formato de imagem invÃ¡lido. Use JPG, JPEG, PNG, GIF ou WEBP.";
            $modal_type = 'error';
            $show_modal = true;
        }
    } else {
        $mensagem_erro = "Nenhum arquivo selecionado ou erro no upload.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 4. Salvar mensagens do WhatsApp
if (isset($_POST['salvar_mensagens_whatsapp'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda', 'contaexpirada', 'revendaexpirada'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_whatsapp_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do WhatsApp configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $whatsapp_mensagens[$funcao]['mensagem'] = $_POST['mensagem_whatsapp_' . $funcao] ?? '';
    }
}

// 5. Salvar mensagens do Modal
if (isset($_POST['salvar_mensagens_modal'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_modal_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens_modal WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens_modal SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens_modal (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do Modal configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $modal_mensagens[$funcao] = $_POST['mensagem_modal_' . $funcao] ?? '';
    }
}

// Remover parÃ¢metros da URL se existirem
if (isset($_GET['success']) || isset($_GET['error'])) {
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    echo "<script>window.history.replaceState({}, document.title, '$url');</script>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
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
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-server: #3a0ca3;
            --icon-time: #b5179e;
            --icon-credit: #fb8b24;
            --icon-message: #a78bfa;
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
            max-width: 1000px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .profile-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-upload-area:hover {
            border-color: var(--primary);
            background: rgba(65,88,208,0.05);
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .password-requirements {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .password-requirements i {
            color: var(--warning);
        }

        .tabs-container {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tabs-buttons {
            display: flex;
            gap: 5px;
            background: rgba(0,0,0,0.2);
            padding: 5px;
            border-radius: 40px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 2px 10px rgba(65,88,208,0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-preview {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            border-left: 2px solid var(--primary);
        }

        .mensagem-preview i {
            color: var(--tertiary);
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

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
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
        }

        .modal-body-custom {
            padding: 20px;
            color: white;
            text-align: center;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }

        .error-icon i {
            font-size: 60px;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .btn-back {
                margin-left: 0 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .tabs-buttons {
                width: 100%;
            }

            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-circle'></i>
                <span>Editar Perfil</span>
            </div>

            <!-- Card Principal - Foto de Perfil -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-camera'></i>
                    </div>
                    <div>
                        <div class="header-title">Foto de Perfil</div>
                        <div class="header-subtitle">Atualize sua foto de perfil</div>
                    </div>
                    <a href="../home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="profile-upload-area">
                        <?php
                        $avatar_url = !empty($user_data['profile_image']) 
                            ? '../uploads/profiles/' . $user_data['profile_image'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['login']) . '&size=100&background=4158D0&color=fff&bold=true&length=2';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="current-avatar" id="profile-avatar-preview">
                        <form method="post" enctype="multipart/form-data" style="width: 100%;">
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <label class="btn-action btn-primary" style="cursor: pointer; margin: 0;">
                                    <i class='bx bx-upload'></i> Escolher Foto
                                    <input type="file" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                </label>
                                <button type="submit" name="upload_image" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Foto
                                </button>
                            </div>
                        </form>
                        <small style="color: rgba(255,255,255,0.3); margin-top: 10px; display: block; font-size: 9px;">
                            <i class='bx bx-info-circle'></i> Formatos permitidos: JPG, JPEG, PNG, GIF, WEBP
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card com Abas - ConfiguraÃ§Ãµes -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes da Conta</div>
                        <div class="header-subtitle">Gerencie suas informaÃ§Ãµes e mensagens</div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Abas -->
                    <div class="tabs-container">
                        <div class="tabs-buttons">
                            <button class="tab-btn active" onclick="switchTab('conta')">
                                <i class='bx bx-user'></i> Conta
                            </button>
                            <button class="tab-btn" onclick="switchTab('seguranca')">
                                <i class='bx bx-lock-alt'></i> SeguranÃ§a
                            </button>
                            <button class="tab-btn" onclick="switchTab('mensagens')">
                                <i class='bx bx-message-detail'></i> WhatsApp
                            </button>
                            <button class="tab-btn" onclick="switchTab('modal-mensagens')">
                                <i class='bx bx-message-rounded-dots'></i> Modal
                            </button>
                        </div>
                    </div>

                    <!-- Aba: InformaÃ§Ãµes da Conta -->
                    <div id="tab-conta" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        NOME COMPLETO
                                    </label>
                                    <input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ''); ?>" placeholder="Digite seu nome completo">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-envelope icon-user'></i>
                                        E-MAIL
                                    </label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="seu@email.com">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        USUÃRIO
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']); ?>" disabled readonly>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> O nome de usuÃ¡rio nÃ£o pode ser alterado
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                        NÃšMERO DE TELEFONE
                                    </label>
                                    <div style="display: flex; gap: 8px;">
                                        <select style="width: 80px; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; padding: 8px 8px; font-size: 12px;">
                                            <option>BR (+55)</option>
                                        </select>
                                        <input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>" placeholder="Exemplo: 99 9 9999-9999" style="flex: 1;">
                                    </div>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> Digite apenas nÃºmeros, ex: 11999999999
                                    </small>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_conta" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar alteraÃ§Ãµes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: SeguranÃ§a (Alterar Senha) -->
                    <div id="tab-seguranca" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-key icon-lock'></i>
                                        SENHA ATUAL
                                    </label>
                                    <input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-lock-alt icon-lock'></i>
                                        NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="nova_senha" placeholder="Digite a nova senha" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-check-shield icon-lock'></i>
                                        CONFIRMAR NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme a nova senha" autocomplete="off">
                                </div>
                            </div>

                            <div class="password-requirements">
                                <i class='bx bx-info-circle'></i> <strong>Requisitos de senha:</strong>
                                <ul>
                                    <li>MÃ­nimo de 5 caracteres e mÃ¡ximo de 10 caracteres</li>
                                    <li>Apenas letras e nÃºmeros (sem caracteres especiais)</li>
                                    <li>Letras maiÃºsculas e minÃºsculas sÃ£o permitidas</li>
                                </ul>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="alterar_senha" class="btn-action btn-success">
                                    <i class='bx bx-key'></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: Mensagens WhatsApp -->
                    <div id="tab-mensagens" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-user-plus icon-message'></i>
                                        Mensagem ao Criar UsuÃ¡rio (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarusuario" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($whatsapp_mensagens['criarusuario']['mensagem'] ?? 'ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-test-tube icon-message'></i>
                                        Mensagem ao Criar Teste (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarteste" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um teste..."><?php echo htmlspecialchars($whatsapp_mensagens['criarteste']['mensagem'] ?? 'ðŸŽ‰ Teste Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nâ±ï¸ DuraÃ§Ã£o: {validade} minutos\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store-alt icon-message'></i>
                                        Mensagem ao Criar Revenda (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarrevenda" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar uma revenda..."><?php echo htmlspecialchars($whatsapp_mensagens['criarrevenda']['mensagem'] ?? 'ðŸŽ‰ Revenda Criada! ðŸŽ‰\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-calendar-x icon-message'></i>
                                        Mensagem de Conta Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_contaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a conta expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['contaexpirada']['mensagem'] ?? 'ðŸ˜© Sua conta estÃ¡ prestes a vencer!\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Renove agora: https://{dominio}/renovacao_login.php'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store icon-message'></i>
                                        Mensagem de Revenda Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_revendaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a revenda expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['revendaexpirada']['mensagem'] ?? 'ðŸ˜© Sua revenda estÃ¡ prestes a vencer!\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Acesse o painel para renovar: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_mensagens_whatsapp" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Mensagens WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>

                 
        <!-- Aba: Mensagens Modal -->
<div id="tab-modal-mensagens" class="tab-content">
    <form method="POST">
        <div class="form-grid">
            <div class="form-field full-width">
                <label>
                    <i class='bx bx-user-plus icon-message'></i>
                    Mensagem ao Criar UsuÃ¡rio (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarusuario" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($modal_mensagens['criarusuario'] ?? 'ðŸŽ‰ Obrigado por escolher nossos serviÃ§os!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ”„ Renove seu plano:\nðŸ”— https://{dominio}/renovacao_login.php\n\nðŸ’¥ Aproveite os melhores servidores!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-test-tube icon-message'></i>
                    Mensagem ao Criar Teste (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarteste" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um teste..."><?php echo htmlspecialchars($modal_mensagens['criarteste'] ?? 'ðŸŽ‰ Teste liberado! Aproveite nosso serviÃ§o!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ’¥ Experimente a melhor conexÃ£o!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-store-alt icon-message'></i>
                    Mensagem ao Criar Revenda (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarrevenda" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar uma revenda..."><?php echo htmlspecialchars($modal_mensagens['criarrevenda'] ?? 'ðŸŽ‰ Revenda criada com sucesso!\n\nðŸ’¥ Comece a vender agora mesmo!\nðŸ”— Link do painel: https://{dominio}/\n\nðŸ“± Indique nosso aplicativo para seus clientes:\nðŸ”— https://{dominio}/aplicativos.php'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" name="salvar_mensagens_modal" class="btn-action btn-success">
                <i class='bx bx-save'></i> Salvar Mensagens Modal
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">OperaÃ§Ã£o realizada!</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_sucesso; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_erro; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function switchTab(tab) {
            document.getElementById('tab-conta').classList.remove('active');
            document.getElementById('tab-seguranca').classList.remove('active');
            document.getElementById('tab-mensagens').classList.remove('active');
            document.getElementById('tab-modal-mensagens').classList.remove('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (tab === 'conta') {
                document.getElementById('tab-conta').classList.add('active');
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (tab === 'seguranca') {
                document.getElementById('tab-seguranca').classList.add('active');
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else if (tab === 'mensagens') {
                document.getElementById('tab-mensagens').classList.add('active');
                document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            } else if (tab === 'modal-mensagens') {
                document.getElementById('tab-modal-mensagens').classList.add('active');
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Remove os parÃ¢metros da URL sem recarregar a pÃ¡gina
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }
        }

        <?php if ($show_modal && $modal_type == 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php elseif ($show_modal && $modal_type == 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });
    </script>
</body>
        }
        ?>

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
            max-width: 1000px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .profile-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-upload-area:hover {
            border-color: var(--primary);
            background: rgba(65,88,208,0.05);
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .password-requirements {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .password-requirements i {
            color: var(--warning);
        }

        .tabs-container {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tabs-buttons {
            display: flex;
            gap: 5px;
            background: rgba(0,0,0,0.2);
            padding: 5px;
            border-radius: 40px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 2px 10px rgba(65,88,208,0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-preview {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            border-left: 2px solid var(--primary);
        }

        .mensagem-preview i {
            color: var(--tertiary);
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

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
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
        }

        .modal-body-custom {
            padding: 20px;
            color: white;
            text-align: center;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }

        .error-icon i {
            font-size: 60px;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .btn-back {
                margin-left: 0 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .tabs-buttons {
                width: 100%;
            }

            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-circle'></i>
                <span>Editar Perfil</span>
            </div>

            <!-- Card Principal - Foto de Perfil -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-camera'></i>
                    </div>
                    <div>
                        <div class="header-title">Foto de Perfil</div>
                        <div class="header-subtitle">Atualize sua foto de perfil</div>
                    </div>
                    <a href="../home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="profile-upload-area">
                        <?php
                        $avatar_url = !empty($user_data['profile_image']) 
                            ? '../uploads/profiles/' . $user_data['profile_image'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['login']) . '&size=100&background=4158D0&color=fff&bold=true&length=2';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="current-avatar" id="profile-avatar-preview">
                        <form method="post" enctype="multipart/form-data" style="width: 100%;">
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <label class="btn-action btn-primary" style="cursor: pointer; margin: 0;">
                                    <i class='bx bx-upload'></i> Escolher Foto
                                    <input type="file" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                </label>
                                <button type="submit" name="upload_image" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Foto
                                </button>
                            </div>
                        </form>
                        <small style="color: rgba(255,255,255,0.3); margin-top: 10px; display: block; font-size: 9px;">
                            <i class='bx bx-info-circle'></i> Formatos permitidos: JPG, JPEG, PNG, GIF, WEBP
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card com Abas - ConfiguraÃ§Ãµes -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes da Conta</div>
                        <div class="header-subtitle">Gerencie suas informaÃ§Ãµes e mensagens</div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Abas -->
                    <div class="tabs-container">
                        <div class="tabs-buttons">
                            <button class="tab-btn active" onclick="switchTab('conta')">
                                <i class='bx bx-user'></i> Conta
                            </button>
                            <button class="tab-btn" onclick="switchTab('seguranca')">
                                <i class='bx bx-lock-alt'></i> SeguranÃ§a
                            </button>
                            <button class="tab-btn" onclick="switchTab('mensagens')">
                                <i class='bx bx-message-detail'></i> WhatsApp
                            </button>
                            <button class="tab-btn" onclick="switchTab('modal-mensagens')">
                                <i class='bx bx-message-rounded-dots'></i> Modal
                            </button>
                        </div>
                    </div>

                    <!-- Aba: InformaÃ§Ãµes da Conta -->
                    <div id="tab-conta" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        NOME COMPLETO
                                    </label>
                                    <input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ''); ?>" placeholder="Digite seu nome completo">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-envelope icon-user'></i>
                                        E-MAIL
                                    </label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="seu@email.com">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        USUÃRIO
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']); ?>" disabled readonly>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> O nome de usuÃ¡rio nÃ£o pode ser alterado
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                        NÃšMERO DE TELEFONE
                                    </label>
                                    <div style="display: flex; gap: 8px;">
                                        <select style="width: 80px; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; padding: 8px 8px; font-size: 12px;">
                                            <option>BR (+55)</option>
                                        </select>
                                        <input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>" placeholder="Exemplo: 99 9 9999-9999" style="flex: 1;">
                                    </div>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> Digite apenas nÃºmeros, ex: 11999999999
                                    </small>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_conta" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar alteraÃ§Ãµes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: SeguranÃ§a (Alterar Senha) -->
                    <div id="tab-seguranca" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-key icon-lock'></i>
                                        SENHA ATUAL
                                    </label>
                                    <input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-lock-alt icon-lock'></i>
                                        NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="nova_senha" placeholder="Digite a nova senha" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-check-shield icon-lock'></i>
                                        CONFIRMAR NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme a nova senha" autocomplete="off">
                                </div>
                            </div>

                            <div class="password-requirements">
                                <i class='bx bx-info-circle'></i> <strong>Requisitos de senha:</strong>
                                <ul>
                                    <li>MÃ­nimo de 5 caracteres e mÃ¡ximo de 10 caracteres</li>
                                    <li>Apenas letras e nÃºmeros (sem caracteres especiais)</li>
                                    <li>Letras maiÃºsculas e minÃºsculas sÃ£o permitidas</li>
                                </ul>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="alterar_senha" class="btn-action btn-success">
                                    <i class='bx bx-key'></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: Mensagens WhatsApp -->
                    <div id="tab-mensagens" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-user-plus icon-message'></i>
                                        Mensagem ao Criar UsuÃ¡rio (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarusuario" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($whatsapp_mensagens['criarusuario']['mensagem'] ?? 'ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-test-tube icon-message'></i>
                                        Mensagem ao Criar Teste (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarteste" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um teste..."><?php echo htmlspecialchars($whatsapp_mensagens['criarteste']['mensagem'] ?? 'ðŸŽ‰ Teste Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nâ±ï¸ DuraÃ§Ã£o: {validade} minutos\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store-alt icon-message'></i>
                                        Mensagem ao Criar Revenda (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarrevenda" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar uma revenda..."><?php echo htmlspecialchars($whatsapp_mensagens['criarrevenda']['mensagem'] ?? 'ðŸŽ‰ Revenda Criada! ðŸŽ‰\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-calendar-x icon-message'></i>
                                        Mensagem de Conta Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_contaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a conta expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['contaexpirada']['mensagem'] ?? 'ðŸ˜© Sua conta estÃ¡ prestes a vencer!\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Renove agora: https://{dominio}/renovacao_login.php'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store icon-message'></i>
                                        Mensagem de Revenda Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_revendaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a revenda expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['revendaexpirada']['mensagem'] ?? 'ðŸ˜© Sua revenda estÃ¡ prestes a vencer!\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Acesse o painel para renovar: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_mensagens_whatsapp" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Mensagens WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>

                 
        <!-- Aba: Mensagens Modal -->
<div id="tab-modal-mensagens" class="tab-content">
    <form method="POST">
        <div class="form-grid">
            <div class="form-field full-width">
                <label>
                    <i class='bx bx-user-plus icon-message'></i>
                    Mensagem ao Criar UsuÃ¡rio (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarusuario" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($modal_mensagens['criarusuario'] ?? 'ðŸŽ‰ Obrigado por escolher nossos serviÃ§os!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ”„ Renove seu plano:\nðŸ”— https://{dominio}/renovacao_login.php\n\nðŸ’¥ Aproveite os melhores servidores!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-test-tube icon-message'></i>
                    Mensagem ao Criar Teste (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarteste" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um teste..."><?php echo htmlspecialchars($modal_mensagens['criarteste'] ?? 'ðŸŽ‰ Teste liberado! Aproveite nosso serviÃ§o!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ’¥ Experimente a melhor conexÃ£o!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-store-alt icon-message'></i>
                    Mensagem ao Criar Revenda (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarrevenda" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar uma revenda..."><?php echo htmlspecialchars($modal_mensagens['criarrevenda'] ?? 'ðŸŽ‰ Revenda criada com sucesso!\n\nðŸ’¥ Comece a vender agora mesmo!\nðŸ”— Link do painel: https://{dominio}/\n\nðŸ“± Indique nosso aplicativo para seus clientes:\nðŸ”— https://{dominio}/aplicativos.php'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" name="salvar_mensagens_modal" class="btn-action btn-success">
                <i class='bx bx-save'></i> Salvar Mensagens Modal
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">OperaÃ§Ã£o realizada!</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_sucesso; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_erro; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function switchTab(tab) {
            document.getElementById('tab-conta').classList.remove('active');
            document.getElementById('tab-seguranca').classList.remove('active');
            document.getElementById('tab-mensagens').classList.remove('active');
            document.getElementById('tab-modal-mensagens').classList.remove('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (tab === 'conta') {
                document.getElementById('tab-conta').classList.add('active');
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (tab === 'seguranca') {
                document.getElementById('tab-seguranca').classList.add('active');
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else if (tab === 'mensagens') {
                document.getElementById('tab-mensagens').classList.add('active');
                document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            } else if (tab === 'modal-mensagens') {
                document.getElementById('tab-modal-mensagens').classList.add('active');
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Remove os parÃ¢metros da URL sem recarregar a pÃ¡gina
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }
        }

        <?php if ($show_modal && $modal_type == 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php elseif ($show_modal && $modal_type == 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
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
                <i class='bx bx-user-circle'></i>
                <span>Editar Perfil</span>
            </div>

            <!-- Card Principal - Foto de Perfil -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-camera'></i>
                    </div>
                    <div>
                        <div class="header-title">Foto de Perfil</div>
                        <div class="header-subtitle">Atualize sua foto de perfil</div>
                    </div>
                    <a href="../home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="profile-upload-area">
                        <?php
                        $avatar_url = !empty($user_data['profile_image']) 
                            ? '../uploads/profiles/' . $user_data['profile_image'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['login']) . '&size=100&background=4158D0&color=fff&bold=true&length=2';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="current-avatar" id="profile-avatar-preview">
                        <form method="post" enctype="multipart/form-data" style="width: 100%;">
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <label class="btn-action btn-primary" style="cursor: pointer; margin: 0;">
                                    <i class='bx bx-upload'></i> Escolher Foto
                                    <input type="file" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                </label>
                                <button type="submit" name="upload_image" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Foto
                                </button>
                            </div>
                        </form>
                        <small style="color: rgba(255,255,255,0.3); margin-top: 10px; display: block; font-size: 9px;">
                            <i class='bx bx-info-circle'></i> Formatos permitidos: JPG, JPEG, PNG, GIF, WEBP
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card com Abas - ConfiguraÃ§Ãµes -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes da Conta</div>
                        <div class="header-subtitle">Gerencie suas informaÃ§Ãµes e mensagens</div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Abas -->
                    <div class="tabs-container">
                        <div class="tabs-buttons">
                            <button class="tab-btn active" onclick="switchTab('conta')">
                                <i class='bx bx-user'></i> Conta
                            </button>
                            <button class="tab-btn" onclick="switchTab('seguranca')">
                                <i class='bx bx-lock-alt'></i> SeguranÃ§a
                            </button>
                            <button class="tab-btn" onclick="switchTab('mensagens')">
                                <i class='bx bx-message-detail'></i> WhatsApp
                            </button>
                            <button class="tab-btn" onclick="switchTab('modal-mensagens')">
                                <i class='bx bx-message-rounded-dots'></i> Modal
                            </button>
                        </div>
                    </div>

                    <!-- Aba: InformaÃ§Ãµes da Conta -->
                    <div id="tab-conta" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        NOME COMPLETO
                                    </label>
                                    <input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ''); ?>" placeholder="Digite seu nome completo">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-envelope icon-user'></i>
                                        E-MAIL
                                    </label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="seu@email.com">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        USUÃRIO
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']); ?>" disabled readonly>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> O nome de usuÃ¡rio nÃ£o pode ser alterado
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                        NÃšMERO DE TELEFONE
                                    </label>
                                    <div style="display: flex; gap: 8px;">
                                        <select style="width: 80px; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; padding: 8px 8px; font-size: 12px;">
                                            <option>BR (+55)</option>
                                        </select>
                                        <input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>" placeholder="Exemplo: 99 9 9999-9999" style="flex: 1;">
                                    </div>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> Digite apenas nÃºmeros, ex: 11999999999
                                    </small>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_conta" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar alteraÃ§Ãµes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: SeguranÃ§a (Alterar Senha) -->
                    <div id="tab-seguranca" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-key icon-lock'></i>
                                        SENHA ATUAL
                                    </label>
                                    <input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-lock-alt icon-lock'></i>
                                        NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="nova_senha" placeholder="Digite a nova senha" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-check-shield icon-lock'></i>
                                        CONFIRMAR NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme a nova senha" autocomplete="off">
                                </div>
                            </div>

                            <div class="password-requirements">
                                <i class='bx bx-info-circle'></i> <strong>Requisitos de senha:</strong>
                                <ul>
                                    <li>MÃ­nimo de 5 caracteres e mÃ¡ximo de 10 caracteres</li>
                                    <li>Apenas letras e nÃºmeros (sem caracteres especiais)</li>
                                    <li>Letras maiÃºsculas e minÃºsculas sÃ£o permitidas</li>
                                </ul>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="alterar_senha" class="btn-action btn-success">
                                    <i class='bx bx-key'></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: Mensagens WhatsApp -->
                    <div id="tab-mensagens" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-user-plus icon-message'></i>
                                        Mensagem ao Criar UsuÃ¡rio (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarusuario" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($whatsapp_mensagens['criarusuario']['mensagem'] ?? 'ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-test-tube icon-message'></i>
                                        Mensagem ao Criar Teste (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarteste" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um teste..."><?php echo htmlspecialchars($whatsapp_mensagens['criarteste']['mensagem'] ?? 'ðŸŽ‰ Teste Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nâ±ï¸ DuraÃ§Ã£o: {validade} minutos\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store-alt icon-message'></i>
                                        Mensagem ao Criar Revenda (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarrevenda" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar uma revenda..."><?php echo htmlspecialchars($whatsapp_mensagens['criarrevenda']['mensagem'] ?? 'ðŸŽ‰ Revenda Criada! ðŸŽ‰\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-calendar-x icon-message'></i>
                                        Mensagem de Conta Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_contaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a conta expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['contaexpirada']['mensagem'] ?? 'ðŸ˜© Sua conta estÃ¡ prestes a vencer!\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Renove agora: https://{dominio}/renovacao_login.php'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store icon-message'></i>
                                        Mensagem de Revenda Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_revendaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a revenda expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['revendaexpirada']['mensagem'] ?? 'ðŸ˜© Sua revenda estÃ¡ prestes a vencer!\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Acesse o painel para renovar: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_mensagens_whatsapp" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Mensagens WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>

                 
        <!-- Aba: Mensagens Modal -->
<div id="tab-modal-mensagens" class="tab-content">
    <form method="POST">
        <div class="form-grid">
            <div class="form-field full-width">
                <label>
                    <i class='bx bx-user-plus icon-message'></i>
                    Mensagem ao Criar UsuÃ¡rio (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarusuario" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($modal_mensagens['criarusuario'] ?? 'ðŸŽ‰ Obrigado por escolher nossos serviÃ§os!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ”„ Renove seu plano:\nðŸ”— https://{dominio}/renovacao_login.php\n\nðŸ’¥ Aproveite os melhores servidores!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-test-tube icon-message'></i>
                    Mensagem ao Criar Teste (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarteste" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um teste..."><?php echo htmlspecialchars($modal_mensagens['criarteste'] ?? 'ðŸŽ‰ Teste liberado! Aproveite nosso serviÃ§o!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ’¥ Experimente a melhor conexÃ£o!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-store-alt icon-message'></i>
                    Mensagem ao Criar Revenda (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarrevenda" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar uma revenda..."><?php echo htmlspecialchars($modal_mensagens['criarrevenda'] ?? 'ðŸŽ‰ Revenda criada com sucesso!\n\nðŸ’¥ Comece a vender agora mesmo!\nðŸ”— Link do painel: https://{dominio}/\n\nðŸ“± Indique nosso aplicativo para seus clientes:\nðŸ”— https://{dominio}/aplicativos.php'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" name="salvar_mensagens_modal" class="btn-action btn-success">
                <i class='bx bx-save'></i> Salvar Mensagens Modal
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">OperaÃ§Ã£o realizada!</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_sucesso; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_erro; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function switchTab(tab) {
            document.getElementById('tab-conta').classList.remove('active');
            document.getElementById('tab-seguranca').classList.remove('active');
            document.getElementById('tab-mensagens').classList.remove('active');
            document.getElementById('tab-modal-mensagens').classList.remove('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (tab === 'conta') {
                document.getElementById('tab-conta').classList.add('active');
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (tab === 'seguranca') {
                document.getElementById('tab-seguranca').classList.add('active');
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else if (tab === 'mensagens') {
                document.getElementById('tab-mensagens').classList.add('active');
                document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            } else if (tab === 'modal-mensagens') {
                document.getElementById('tab-modal-mensagens').classList.add('active');
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Remove os parÃ¢metros da URL sem recarregar a pÃ¡gina
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }
        }

        <?php if ($show_modal && $modal_type == 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php elseif ($show_modal && $modal_type == 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });
    </script>
</body>
        }
        ?>

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
            max-width: 1000px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .profile-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-upload-area:hover {
            border-color: var(--primary);
            background: rgba(65,88,208,0.05);
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .password-requirements {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .password-requirements i {
            color: var(--warning);
        }

        .tabs-container {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tabs-buttons {
            display: flex;
            gap: 5px;
            background: rgba(0,0,0,0.2);
            padding: 5px;
            border-radius: 40px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 2px 10px rgba(65,88,208,0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-preview {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            border-left: 2px solid var(--primary);
        }

        .mensagem-preview i {
            color: var(--tertiary);
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

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
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
        }

        .modal-body-custom {
            padding: 20px;
            color: white;
            text-align: center;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }

        .error-icon i {
            font-size: 60px;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .btn-back {
                margin-left: 0 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .tabs-buttons {
                width: 100%;
            }

            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
error_reporting(0);
session_start();

// Configurar fuso horÃ¡rio para BrasÃ­lia
date_default_timezone_set('America/Sao_Paulo');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:../index.php');
}

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$id = $_SESSION['iduser'];
include_once 'header2.php';

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
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Buscar dados do usuÃ¡rio logado
$sql = "SELECT login, senha, nome_completo, telefone, profile_image, email, contato FROM accounts WHERE id = '$id'";
$result = $conn->query($sql);
$user_data = $result->fetch_assoc();

// Buscar mensagens do WhatsApp
$sql_whatsapp = "SELECT * FROM mensagens WHERE byid = '$id'";
$result_whatsapp = mysqli_query($conn, $sql_whatsapp);
$whatsapp_mensagens = [];
if ($result_whatsapp && mysqli_num_rows($result_whatsapp) > 0) {
    while ($row = mysqli_fetch_assoc($result_whatsapp)) {
        $whatsapp_mensagens[$row['funcao']] = $row;
    }
}

// Buscar mensagens do Modal
$sql_modal = "SELECT * FROM mensagens_modal WHERE byid = '$id'";
$result_modal = mysqli_query($conn, $sql_modal);
$modal_mensagens = [];
if ($result_modal && mysqli_num_rows($result_modal) > 0) {
    while ($row = mysqli_fetch_assoc($result_modal)) {
        $modal_mensagens[$row['funcao']] = $row['mensagem'];
    }
}

// FunÃ§Ã£o anti-SQL injection
function anti_sql($input)
{
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

// --- LÃ“GICA PARA ATUALIZAR OS DADOS ---
$mensagem_erro = '';
$mensagem_sucesso = '';
$show_modal = false;
$modal_type = '';

// 1. Atualizar informaÃ§Ãµes da conta
if (isset($_POST['salvar_conta'])) {
    $nome_completo = anti_sql($_POST['nome_completo']);
    $email = anti_sql($_POST['email']);
    $telefone = anti_sql($_POST['telefone']);

    $sql_update = "UPDATE accounts SET nome_completo='$nome_completo', email='$email', telefone='$telefone', contato='$telefone' WHERE id='$id'";
    if (mysqli_query($conn, $sql_update)) {
        $mensagem_sucesso = "InformaÃ§Ãµes atualizadas com sucesso!";
        $modal_type = 'success';
        $show_modal = true;
        $user_data['nome_completo'] = $nome_completo;
        $user_data['email'] = $email;
        $user_data['telefone'] = $telefone;
    } else {
        $mensagem_erro = "Erro ao atualizar informaÃ§Ãµes: " . mysqli_error($conn);
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 2. Atualizar a senha
if (isset($_POST['alterar_senha'])) {
    $senha_atual = trim($_POST['senha_atual']);
    $nova_senha = trim($_POST['nova_senha']);
    $confirmar_senha = trim($_POST['confirmar_senha']);

    $sql_senha = "SELECT senha FROM accounts WHERE id='$id'";
    $result_senha = $conn->query($sql_senha);
    $row_senha = $result_senha->fetch_assoc();
    $senha_banco = trim($row_senha['senha']);
    
    if ($senha_atual === $senha_banco) {
        if (strlen($nova_senha) < 5) {
            $mensagem_erro = "A nova senha deve ter no mÃ­nimo 5 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (strlen($nova_senha) > 10) {
            $mensagem_erro = "A nova senha deve ter no mÃ¡ximo 10 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (preg_match('/[^a-z0-9]/i', $nova_senha)) {
            $mensagem_erro = "A senha nÃ£o pode conter caracteres especiais!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif ($nova_senha !== $confirmar_senha) {
            $mensagem_erro = "A confirmaÃ§Ã£o da nova senha nÃ£o coincide!";
            $modal_type = 'error';
            $show_modal = true;
        } else {
            $nova_senha_segura = anti_sql($nova_senha);
            $sql_update_senha = "UPDATE accounts SET senha='$nova_senha_segura' WHERE id='$id'";
            if (mysqli_query($conn, $sql_update_senha)) {
                $_SESSION['senha'] = $nova_senha_segura;
                $mensagem_sucesso = "Senha alterada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao alterar senha: " . mysqli_error($conn);
                $modal_type = 'error';
                $show_modal = true;
            }
        }
    } else {
        $mensagem_erro = "Senha atual incorreta! Verifique se digitou corretamente.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 3. Upload da foto de perfil
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $dir = '../uploads/profiles/';
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $fname = 'profile_' . $id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $fname)) {
                if (!empty($user_data['profile_image']) && file_exists($dir . $user_data['profile_image'])) {
                    unlink($dir . $user_data['profile_image']);
                }
                $conn->query("UPDATE accounts SET profile_image='$fname' WHERE id='$id'");
                $user_data['profile_image'] = $fname;
                $mensagem_sucesso = "Foto de perfil atualizada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao fazer upload da imagem.";
                $modal_type = 'error';
                $show_modal = true;
            }
        } else {
            $mensagem_erro = "Formato de imagem invÃ¡lido. Use JPG, JPEG, PNG, GIF ou WEBP.";
            $modal_type = 'error';
            $show_modal = true;
        }
    } else {
        $mensagem_erro = "Nenhum arquivo selecionado ou erro no upload.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 4. Salvar mensagens do WhatsApp
if (isset($_POST['salvar_mensagens_whatsapp'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda', 'contaexpirada', 'revendaexpirada'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_whatsapp_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do WhatsApp configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $whatsapp_mensagens[$funcao]['mensagem'] = $_POST['mensagem_whatsapp_' . $funcao] ?? '';
    }
}

// 5. Salvar mensagens do Modal
if (isset($_POST['salvar_mensagens_modal'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_modal_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens_modal WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens_modal SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens_modal (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do Modal configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $modal_mensagens[$funcao] = $_POST['mensagem_modal_' . $funcao] ?? '';
    }
}

// Remover parÃ¢metros da URL se existirem
if (isset($_GET['success']) || isset($_GET['error'])) {
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    echo "<script>window.history.replaceState({}, document.title, '$url');</script>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
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
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-server: #3a0ca3;
            --icon-time: #b5179e;
            --icon-credit: #fb8b24;
            --icon-message: #a78bfa;
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
            max-width: 1000px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .profile-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-upload-area:hover {
            border-color: var(--primary);
            background: rgba(65,88,208,0.05);
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .password-requirements {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .password-requirements i {
            color: var(--warning);
        }

        .tabs-container {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tabs-buttons {
            display: flex;
            gap: 5px;
            background: rgba(0,0,0,0.2);
            padding: 5px;
            border-radius: 40px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 2px 10px rgba(65,88,208,0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-preview {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            border-left: 2px solid var(--primary);
        }

        .mensagem-preview i {
            color: var(--tertiary);
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

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
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
        }

        .modal-body-custom {
            padding: 20px;
            color: white;
            text-align: center;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }

        .error-icon i {
            font-size: 60px;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .btn-back {
                margin-left: 0 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .tabs-buttons {
                width: 100%;
            }

            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-circle'></i>
                <span>Editar Perfil</span>
            </div>

            <!-- Card Principal - Foto de Perfil -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-camera'></i>
                    </div>
                    <div>
                        <div class="header-title">Foto de Perfil</div>
                        <div class="header-subtitle">Atualize sua foto de perfil</div>
                    </div>
                    <a href="../home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="profile-upload-area">
                        <?php
                        $avatar_url = !empty($user_data['profile_image']) 
                            ? '../uploads/profiles/' . $user_data['profile_image'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['login']) . '&size=100&background=4158D0&color=fff&bold=true&length=2';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="current-avatar" id="profile-avatar-preview">
                        <form method="post" enctype="multipart/form-data" style="width: 100%;">
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <label class="btn-action btn-primary" style="cursor: pointer; margin: 0;">
                                    <i class='bx bx-upload'></i> Escolher Foto
                                    <input type="file" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                </label>
                                <button type="submit" name="upload_image" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Foto
                                </button>
                            </div>
                        </form>
                        <small style="color: rgba(255,255,255,0.3); margin-top: 10px; display: block; font-size: 9px;">
                            <i class='bx bx-info-circle'></i> Formatos permitidos: JPG, JPEG, PNG, GIF, WEBP
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card com Abas - ConfiguraÃ§Ãµes -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes da Conta</div>
                        <div class="header-subtitle">Gerencie suas informaÃ§Ãµes e mensagens</div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Abas -->
                    <div class="tabs-container">
                        <div class="tabs-buttons">
                            <button class="tab-btn active" onclick="switchTab('conta')">
                                <i class='bx bx-user'></i> Conta
                            </button>
                            <button class="tab-btn" onclick="switchTab('seguranca')">
                                <i class='bx bx-lock-alt'></i> SeguranÃ§a
                            </button>
                            <button class="tab-btn" onclick="switchTab('mensagens')">
                                <i class='bx bx-message-detail'></i> WhatsApp
                            </button>
                            <button class="tab-btn" onclick="switchTab('modal-mensagens')">
                                <i class='bx bx-message-rounded-dots'></i> Modal
                            </button>
                        </div>
                    </div>

                    <!-- Aba: InformaÃ§Ãµes da Conta -->
                    <div id="tab-conta" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        NOME COMPLETO
                                    </label>
                                    <input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ''); ?>" placeholder="Digite seu nome completo">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-envelope icon-user'></i>
                                        E-MAIL
                                    </label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="seu@email.com">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        USUÃRIO
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']); ?>" disabled readonly>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> O nome de usuÃ¡rio nÃ£o pode ser alterado
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                        NÃšMERO DE TELEFONE
                                    </label>
                                    <div style="display: flex; gap: 8px;">
                                        <select style="width: 80px; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; padding: 8px 8px; font-size: 12px;">
                                            <option>BR (+55)</option>
                                        </select>
                                        <input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>" placeholder="Exemplo: 99 9 9999-9999" style="flex: 1;">
                                    </div>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> Digite apenas nÃºmeros, ex: 11999999999
                                    </small>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_conta" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar alteraÃ§Ãµes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: SeguranÃ§a (Alterar Senha) -->
                    <div id="tab-seguranca" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-key icon-lock'></i>
                                        SENHA ATUAL
                                    </label>
                                    <input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-lock-alt icon-lock'></i>
                                        NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="nova_senha" placeholder="Digite a nova senha" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-check-shield icon-lock'></i>
                                        CONFIRMAR NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme a nova senha" autocomplete="off">
                                </div>
                            </div>

                            <div class="password-requirements">
                                <i class='bx bx-info-circle'></i> <strong>Requisitos de senha:</strong>
                                <ul>
                                    <li>MÃ­nimo de 5 caracteres e mÃ¡ximo de 10 caracteres</li>
                                    <li>Apenas letras e nÃºmeros (sem caracteres especiais)</li>
                                    <li>Letras maiÃºsculas e minÃºsculas sÃ£o permitidas</li>
                                </ul>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="alterar_senha" class="btn-action btn-success">
                                    <i class='bx bx-key'></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: Mensagens WhatsApp -->
                    <div id="tab-mensagens" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-user-plus icon-message'></i>
                                        Mensagem ao Criar UsuÃ¡rio (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarusuario" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($whatsapp_mensagens['criarusuario']['mensagem'] ?? 'ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-test-tube icon-message'></i>
                                        Mensagem ao Criar Teste (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarteste" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um teste..."><?php echo htmlspecialchars($whatsapp_mensagens['criarteste']['mensagem'] ?? 'ðŸŽ‰ Teste Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nâ±ï¸ DuraÃ§Ã£o: {validade} minutos\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store-alt icon-message'></i>
                                        Mensagem ao Criar Revenda (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarrevenda" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar uma revenda..."><?php echo htmlspecialchars($whatsapp_mensagens['criarrevenda']['mensagem'] ?? 'ðŸŽ‰ Revenda Criada! ðŸŽ‰\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-calendar-x icon-message'></i>
                                        Mensagem de Conta Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_contaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a conta expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['contaexpirada']['mensagem'] ?? 'ðŸ˜© Sua conta estÃ¡ prestes a vencer!\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Renove agora: https://{dominio}/renovacao_login.php'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store icon-message'></i>
                                        Mensagem de Revenda Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_revendaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a revenda expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['revendaexpirada']['mensagem'] ?? 'ðŸ˜© Sua revenda estÃ¡ prestes a vencer!\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Acesse o painel para renovar: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_mensagens_whatsapp" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Mensagens WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>

                 
        <!-- Aba: Mensagens Modal -->
<div id="tab-modal-mensagens" class="tab-content">
    <form method="POST">
        <div class="form-grid">
            <div class="form-field full-width">
                <label>
                    <i class='bx bx-user-plus icon-message'></i>
                    Mensagem ao Criar UsuÃ¡rio (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarusuario" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($modal_mensagens['criarusuario'] ?? 'ðŸŽ‰ Obrigado por escolher nossos serviÃ§os!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ”„ Renove seu plano:\nðŸ”— https://{dominio}/renovacao_login.php\n\nðŸ’¥ Aproveite os melhores servidores!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-test-tube icon-message'></i>
                    Mensagem ao Criar Teste (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarteste" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um teste..."><?php echo htmlspecialchars($modal_mensagens['criarteste'] ?? 'ðŸŽ‰ Teste liberado! Aproveite nosso serviÃ§o!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ’¥ Experimente a melhor conexÃ£o!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-store-alt icon-message'></i>
                    Mensagem ao Criar Revenda (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarrevenda" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar uma revenda..."><?php echo htmlspecialchars($modal_mensagens['criarrevenda'] ?? 'ðŸŽ‰ Revenda criada com sucesso!\n\nðŸ’¥ Comece a vender agora mesmo!\nðŸ”— Link do painel: https://{dominio}/\n\nðŸ“± Indique nosso aplicativo para seus clientes:\nðŸ”— https://{dominio}/aplicativos.php'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" name="salvar_mensagens_modal" class="btn-action btn-success">
                <i class='bx bx-save'></i> Salvar Mensagens Modal
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">OperaÃ§Ã£o realizada!</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_sucesso; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_erro; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function switchTab(tab) {
            document.getElementById('tab-conta').classList.remove('active');
            document.getElementById('tab-seguranca').classList.remove('active');
            document.getElementById('tab-mensagens').classList.remove('active');
            document.getElementById('tab-modal-mensagens').classList.remove('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (tab === 'conta') {
                document.getElementById('tab-conta').classList.add('active');
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (tab === 'seguranca') {
                document.getElementById('tab-seguranca').classList.add('active');
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else if (tab === 'mensagens') {
                document.getElementById('tab-mensagens').classList.add('active');
                document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            } else if (tab === 'modal-mensagens') {
                document.getElementById('tab-modal-mensagens').classList.add('active');
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Remove os parÃ¢metros da URL sem recarregar a pÃ¡gina
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }
        }

        <?php if ($show_modal && $modal_type == 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php elseif ($show_modal && $modal_type == 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });
    </script>
</body>
error_reporting(0);
session_start();

// Configurar fuso horÃ¡rio para BrasÃ­lia
date_default_timezone_set('America/Sao_Paulo');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:../index.php');
}

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$id = $_SESSION['iduser'];
include_once 'header2.php';

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
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Buscar dados do usuÃ¡rio logado
$sql = "SELECT login, senha, nome_completo, telefone, profile_image, email, contato FROM accounts WHERE id = '$id'";
$result = $conn->query($sql);
$user_data = $result->fetch_assoc();

// Buscar mensagens do WhatsApp
$sql_whatsapp = "SELECT * FROM mensagens WHERE byid = '$id'";
$result_whatsapp = mysqli_query($conn, $sql_whatsapp);
$whatsapp_mensagens = [];
if ($result_whatsapp && mysqli_num_rows($result_whatsapp) > 0) {
    while ($row = mysqli_fetch_assoc($result_whatsapp)) {
        $whatsapp_mensagens[$row['funcao']] = $row;
    }
}

// Buscar mensagens do Modal
$sql_modal = "SELECT * FROM mensagens_modal WHERE byid = '$id'";
$result_modal = mysqli_query($conn, $sql_modal);
$modal_mensagens = [];
if ($result_modal && mysqli_num_rows($result_modal) > 0) {
    while ($row = mysqli_fetch_assoc($result_modal)) {
        $modal_mensagens[$row['funcao']] = $row['mensagem'];
    }
}

// FunÃ§Ã£o anti-SQL injection
function anti_sql($input)
{
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

// --- LÃ“GICA PARA ATUALIZAR OS DADOS ---
$mensagem_erro = '';
$mensagem_sucesso = '';
$show_modal = false;
$modal_type = '';

// 1. Atualizar informaÃ§Ãµes da conta
if (isset($_POST['salvar_conta'])) {
    $nome_completo = anti_sql($_POST['nome_completo']);
    $email = anti_sql($_POST['email']);
    $telefone = anti_sql($_POST['telefone']);

    $sql_update = "UPDATE accounts SET nome_completo='$nome_completo', email='$email', telefone='$telefone', contato='$telefone' WHERE id='$id'";
    if (mysqli_query($conn, $sql_update)) {
        $mensagem_sucesso = "InformaÃ§Ãµes atualizadas com sucesso!";
        $modal_type = 'success';
        $show_modal = true;
        $user_data['nome_completo'] = $nome_completo;
        $user_data['email'] = $email;
        $user_data['telefone'] = $telefone;
    } else {
        $mensagem_erro = "Erro ao atualizar informaÃ§Ãµes: " . mysqli_error($conn);
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 2. Atualizar a senha
if (isset($_POST['alterar_senha'])) {
    $senha_atual = trim($_POST['senha_atual']);
    $nova_senha = trim($_POST['nova_senha']);
    $confirmar_senha = trim($_POST['confirmar_senha']);

    $sql_senha = "SELECT senha FROM accounts WHERE id='$id'";
    $result_senha = $conn->query($sql_senha);
    $row_senha = $result_senha->fetch_assoc();
    $senha_banco = trim($row_senha['senha']);
    
    if ($senha_atual === $senha_banco) {
        if (strlen($nova_senha) < 5) {
            $mensagem_erro = "A nova senha deve ter no mÃ­nimo 5 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (strlen($nova_senha) > 10) {
            $mensagem_erro = "A nova senha deve ter no mÃ¡ximo 10 caracteres!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif (preg_match('/[^a-z0-9]/i', $nova_senha)) {
            $mensagem_erro = "A senha nÃ£o pode conter caracteres especiais!";
            $modal_type = 'error';
            $show_modal = true;
        } elseif ($nova_senha !== $confirmar_senha) {
            $mensagem_erro = "A confirmaÃ§Ã£o da nova senha nÃ£o coincide!";
            $modal_type = 'error';
            $show_modal = true;
        } else {
            $nova_senha_segura = anti_sql($nova_senha);
            $sql_update_senha = "UPDATE accounts SET senha='$nova_senha_segura' WHERE id='$id'";
            if (mysqli_query($conn, $sql_update_senha)) {
                $_SESSION['senha'] = $nova_senha_segura;
                $mensagem_sucesso = "Senha alterada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao alterar senha: " . mysqli_error($conn);
                $modal_type = 'error';
                $show_modal = true;
            }
        }
    } else {
        $mensagem_erro = "Senha atual incorreta! Verifique se digitou corretamente.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 3. Upload da foto de perfil
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $dir = '../uploads/profiles/';
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $fname = 'profile_' . $id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $fname)) {
                if (!empty($user_data['profile_image']) && file_exists($dir . $user_data['profile_image'])) {
                    unlink($dir . $user_data['profile_image']);
                }
                $conn->query("UPDATE accounts SET profile_image='$fname' WHERE id='$id'");
                $user_data['profile_image'] = $fname;
                $mensagem_sucesso = "Foto de perfil atualizada com sucesso!";
                $modal_type = 'success';
                $show_modal = true;
            } else {
                $mensagem_erro = "Erro ao fazer upload da imagem.";
                $modal_type = 'error';
                $show_modal = true;
            }
        } else {
            $mensagem_erro = "Formato de imagem invÃ¡lido. Use JPG, JPEG, PNG, GIF ou WEBP.";
            $modal_type = 'error';
            $show_modal = true;
        }
    } else {
        $mensagem_erro = "Nenhum arquivo selecionado ou erro no upload.";
        $modal_type = 'error';
        $show_modal = true;
    }
}

// 4. Salvar mensagens do WhatsApp
if (isset($_POST['salvar_mensagens_whatsapp'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda', 'contaexpirada', 'revendaexpirada'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_whatsapp_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do WhatsApp configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $whatsapp_mensagens[$funcao]['mensagem'] = $_POST['mensagem_whatsapp_' . $funcao] ?? '';
    }
}

// 5. Salvar mensagens do Modal
if (isset($_POST['salvar_mensagens_modal'])) {
    $funcoes = ['criarusuario', 'criarteste', 'criarrevenda'];
    
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_modal_' . $funcao]);
        if (!empty($mensagem)) {
            $sql_check = "SELECT id FROM mensagens_modal WHERE funcao = '$funcao' AND byid = '$id'";
            $result_check = mysqli_query($conn, $sql_check);
            
            if (mysqli_num_rows($result_check) > 0) {
                $sql_update = "UPDATE mensagens_modal SET mensagem = '$mensagem', ativo = 'ativada' WHERE funcao = '$funcao' AND byid = '$id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_insert = "INSERT INTO mensagens_modal (funcao, mensagem, ativo, byid) VALUES ('$funcao', '$mensagem', 'ativada', '$id')";
                mysqli_query($conn, $sql_insert);
            }
        }
    }
    
    $mensagem_sucesso = "Mensagens do Modal configuradas com sucesso!";
    $modal_type = 'success';
    $show_modal = true;
    
    // Atualizar dados locais
    foreach ($funcoes as $funcao) {
        $modal_mensagens[$funcao] = $_POST['mensagem_modal_' . $funcao] ?? '';
    }
}

// Remover parÃ¢metros da URL se existirem
if (isset($_GET['success']) || isset($_GET['error'])) {
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    echo "<script>window.history.replaceState({}, document.title, '$url');</script>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
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
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-server: #3a0ca3;
            --icon-time: #b5179e;
            --icon-credit: #fb8b24;
            --icon-message: #a78bfa;
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
            max-width: 1000px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .profile-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-upload-area:hover {
            border-color: var(--primary);
            background: rgba(65,88,208,0.05);
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .password-requirements {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .password-requirements i {
            color: var(--warning);
        }

        .tabs-container {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tabs-buttons {
            display: flex;
            gap: 5px;
            background: rgba(0,0,0,0.2);
            padding: 5px;
            border-radius: 40px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 2px 10px rgba(65,88,208,0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-preview {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            border-left: 2px solid var(--primary);
        }

        .mensagem-preview i {
            color: var(--tertiary);
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

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
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
        }

        .modal-body-custom {
            padding: 20px;
            color: white;
            text-align: center;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }

        .error-icon i {
            font-size: 60px;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .btn-back {
                margin-left: 0 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .tabs-buttons {
                width: 100%;
            }

            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-circle'></i>
                <span>Editar Perfil</span>
            </div>

            <!-- Card Principal - Foto de Perfil -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-camera'></i>
                    </div>
                    <div>
                        <div class="header-title">Foto de Perfil</div>
                        <div class="header-subtitle">Atualize sua foto de perfil</div>
                    </div>
                    <a href="../home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="profile-upload-area">
                        <?php
                        $avatar_url = !empty($user_data['profile_image']) 
                            ? '../uploads/profiles/' . $user_data['profile_image'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['login']) . '&size=100&background=4158D0&color=fff&bold=true&length=2';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="current-avatar" id="profile-avatar-preview">
                        <form method="post" enctype="multipart/form-data" style="width: 100%;">
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <label class="btn-action btn-primary" style="cursor: pointer; margin: 0;">
                                    <i class='bx bx-upload'></i> Escolher Foto
                                    <input type="file" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                </label>
                                <button type="submit" name="upload_image" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Foto
                                </button>
                            </div>
                        </form>
                        <small style="color: rgba(255,255,255,0.3); margin-top: 10px; display: block; font-size: 9px;">
                            <i class='bx bx-info-circle'></i> Formatos permitidos: JPG, JPEG, PNG, GIF, WEBP
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card com Abas - ConfiguraÃ§Ãµes -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes da Conta</div>
                        <div class="header-subtitle">Gerencie suas informaÃ§Ãµes e mensagens</div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Abas -->
                    <div class="tabs-container">
                        <div class="tabs-buttons">
                            <button class="tab-btn active" onclick="switchTab('conta')">
                                <i class='bx bx-user'></i> Conta
                            </button>
                            <button class="tab-btn" onclick="switchTab('seguranca')">
                                <i class='bx bx-lock-alt'></i> SeguranÃ§a
                            </button>
                            <button class="tab-btn" onclick="switchTab('mensagens')">
                                <i class='bx bx-message-detail'></i> WhatsApp
                            </button>
                            <button class="tab-btn" onclick="switchTab('modal-mensagens')">
                                <i class='bx bx-message-rounded-dots'></i> Modal
                            </button>
                        </div>
                    </div>

                    <!-- Aba: InformaÃ§Ãµes da Conta -->
                    <div id="tab-conta" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        NOME COMPLETO
                                    </label>
                                    <input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ''); ?>" placeholder="Digite seu nome completo">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-envelope icon-user'></i>
                                        E-MAIL
                                    </label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="seu@email.com">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        USUÃRIO
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']); ?>" disabled readonly>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> O nome de usuÃ¡rio nÃ£o pode ser alterado
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                        NÃšMERO DE TELEFONE
                                    </label>
                                    <div style="display: flex; gap: 8px;">
                                        <select style="width: 80px; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; padding: 8px 8px; font-size: 12px;">
                                            <option>BR (+55)</option>
                                        </select>
                                        <input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>" placeholder="Exemplo: 99 9 9999-9999" style="flex: 1;">
                                    </div>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> Digite apenas nÃºmeros, ex: 11999999999
                                    </small>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_conta" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar alteraÃ§Ãµes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: SeguranÃ§a (Alterar Senha) -->
                    <div id="tab-seguranca" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-key icon-lock'></i>
                                        SENHA ATUAL
                                    </label>
                                    <input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-lock-alt icon-lock'></i>
                                        NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="nova_senha" placeholder="Digite a nova senha" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-check-shield icon-lock'></i>
                                        CONFIRMAR NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme a nova senha" autocomplete="off">
                                </div>
                            </div>

                            <div class="password-requirements">
                                <i class='bx bx-info-circle'></i> <strong>Requisitos de senha:</strong>
                                <ul>
                                    <li>MÃ­nimo de 5 caracteres e mÃ¡ximo de 10 caracteres</li>
                                    <li>Apenas letras e nÃºmeros (sem caracteres especiais)</li>
                                    <li>Letras maiÃºsculas e minÃºsculas sÃ£o permitidas</li>
                                </ul>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="alterar_senha" class="btn-action btn-success">
                                    <i class='bx bx-key'></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: Mensagens WhatsApp -->
                    <div id="tab-mensagens" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-user-plus icon-message'></i>
                                        Mensagem ao Criar UsuÃ¡rio (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarusuario" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($whatsapp_mensagens['criarusuario']['mensagem'] ?? 'ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-test-tube icon-message'></i>
                                        Mensagem ao Criar Teste (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarteste" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um teste..."><?php echo htmlspecialchars($whatsapp_mensagens['criarteste']['mensagem'] ?? 'ðŸŽ‰ Teste Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nâ±ï¸ DuraÃ§Ã£o: {validade} minutos\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store-alt icon-message'></i>
                                        Mensagem ao Criar Revenda (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarrevenda" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar uma revenda..."><?php echo htmlspecialchars($whatsapp_mensagens['criarrevenda']['mensagem'] ?? 'ðŸŽ‰ Revenda Criada! ðŸŽ‰\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-calendar-x icon-message'></i>
                                        Mensagem de Conta Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_contaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a conta expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['contaexpirada']['mensagem'] ?? 'ðŸ˜© Sua conta estÃ¡ prestes a vencer!\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Renove agora: https://{dominio}/renovacao_login.php'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store icon-message'></i>
                                        Mensagem de Revenda Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_revendaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a revenda expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['revendaexpirada']['mensagem'] ?? 'ðŸ˜© Sua revenda estÃ¡ prestes a vencer!\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Acesse o painel para renovar: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_mensagens_whatsapp" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Mensagens WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>

                 
        <!-- Aba: Mensagens Modal -->
<div id="tab-modal-mensagens" class="tab-content">
    <form method="POST">
        <div class="form-grid">
            <div class="form-field full-width">
                <label>
                    <i class='bx bx-user-plus icon-message'></i>
                    Mensagem ao Criar UsuÃ¡rio (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarusuario" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($modal_mensagens['criarusuario'] ?? 'ðŸŽ‰ Obrigado por escolher nossos serviÃ§os!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ”„ Renove seu plano:\nðŸ”— https://{dominio}/renovacao_login.php\n\nðŸ’¥ Aproveite os melhores servidores!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-test-tube icon-message'></i>
                    Mensagem ao Criar Teste (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarteste" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um teste..."><?php echo htmlspecialchars($modal_mensagens['criarteste'] ?? 'ðŸŽ‰ Teste liberado! Aproveite nosso serviÃ§o!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ’¥ Experimente a melhor conexÃ£o!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-store-alt icon-message'></i>
                    Mensagem ao Criar Revenda (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarrevenda" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar uma revenda..."><?php echo htmlspecialchars($modal_mensagens['criarrevenda'] ?? 'ðŸŽ‰ Revenda criada com sucesso!\n\nðŸ’¥ Comece a vender agora mesmo!\nðŸ”— Link do painel: https://{dominio}/\n\nðŸ“± Indique nosso aplicativo para seus clientes:\nðŸ”— https://{dominio}/aplicativos.php'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" name="salvar_mensagens_modal" class="btn-action btn-success">
                <i class='bx bx-save'></i> Salvar Mensagens Modal
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">OperaÃ§Ã£o realizada!</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_sucesso; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_erro; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function switchTab(tab) {
            document.getElementById('tab-conta').classList.remove('active');
            document.getElementById('tab-seguranca').classList.remove('active');
            document.getElementById('tab-mensagens').classList.remove('active');
            document.getElementById('tab-modal-mensagens').classList.remove('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (tab === 'conta') {
                document.getElementById('tab-conta').classList.add('active');
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (tab === 'seguranca') {
                document.getElementById('tab-seguranca').classList.add('active');
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else if (tab === 'mensagens') {
                document.getElementById('tab-mensagens').classList.add('active');
                document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            } else if (tab === 'modal-mensagens') {
                document.getElementById('tab-modal-mensagens').classList.add('active');
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Remove os parÃ¢metros da URL sem recarregar a pÃ¡gina
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }
        }

        <?php if ($show_modal && $modal_type == 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php elseif ($show_modal && $modal_type == 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });
    </script>
</body>
        }
        ?>

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
            max-width: 1000px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .profile-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-upload-area:hover {
            border-color: var(--primary);
            background: rgba(65,88,208,0.05);
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .password-requirements {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .password-requirements i {
            color: var(--warning);
        }

        .tabs-container {
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tabs-buttons {
            display: flex;
            gap: 5px;
            background: rgba(0,0,0,0.2);
            padding: 5px;
            border-radius: 40px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            box-shadow: 0 2px 10px rgba(65,88,208,0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-preview {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            border-left: 2px solid var(--primary);
        }

        .mensagem-preview i {
            color: var(--tertiary);
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

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #4158D0, #C850C0);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h5 {
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
        }

        .modal-body-custom {
            padding: 20px;
            color: white;
            text-align: center;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }

        .error-icon i {
            font-size: 60px;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .btn-back {
                margin-left: 0 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .tabs-buttons {
                width: 100%;
            }

            .tab-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-circle'></i>
                <span>Editar Perfil</span>
            </div>

            <!-- Card Principal - Foto de Perfil -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-camera'></i>
                    </div>
                    <div>
                        <div class="header-title">Foto de Perfil</div>
                        <div class="header-subtitle">Atualize sua foto de perfil</div>
                    </div>
                    <a href="../home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="profile-upload-area">
                        <?php
                        $avatar_url = !empty($user_data['profile_image']) 
                            ? '../uploads/profiles/' . $user_data['profile_image'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['login']) . '&size=100&background=4158D0&color=fff&bold=true&length=2';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="current-avatar" id="profile-avatar-preview">
                        <form method="post" enctype="multipart/form-data" style="width: 100%;">
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <label class="btn-action btn-primary" style="cursor: pointer; margin: 0;">
                                    <i class='bx bx-upload'></i> Escolher Foto
                                    <input type="file" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                </label>
                                <button type="submit" name="upload_image" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Foto
                                </button>
                            </div>
                        </form>
                        <small style="color: rgba(255,255,255,0.3); margin-top: 10px; display: block; font-size: 9px;">
                            <i class='bx bx-info-circle'></i> Formatos permitidos: JPG, JPEG, PNG, GIF, WEBP
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card com Abas - ConfiguraÃ§Ãµes -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes da Conta</div>
                        <div class="header-subtitle">Gerencie suas informaÃ§Ãµes e mensagens</div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Abas -->
                    <div class="tabs-container">
                        <div class="tabs-buttons">
                            <button class="tab-btn active" onclick="switchTab('conta')">
                                <i class='bx bx-user'></i> Conta
                            </button>
                            <button class="tab-btn" onclick="switchTab('seguranca')">
                                <i class='bx bx-lock-alt'></i> SeguranÃ§a
                            </button>
                            <button class="tab-btn" onclick="switchTab('mensagens')">
                                <i class='bx bx-message-detail'></i> WhatsApp
                            </button>
                            <button class="tab-btn" onclick="switchTab('modal-mensagens')">
                                <i class='bx bx-message-rounded-dots'></i> Modal
                            </button>
                        </div>
                    </div>

                    <!-- Aba: InformaÃ§Ãµes da Conta -->
                    <div id="tab-conta" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        NOME COMPLETO
                                    </label>
                                    <input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ''); ?>" placeholder="Digite seu nome completo">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-envelope icon-user'></i>
                                        E-MAIL
                                    </label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="seu@email.com">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        USUÃRIO
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']); ?>" disabled readonly>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> O nome de usuÃ¡rio nÃ£o pode ser alterado
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                        NÃšMERO DE TELEFONE
                                    </label>
                                    <div style="display: flex; gap: 8px;">
                                        <select style="width: 80px; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; padding: 8px 8px; font-size: 12px;">
                                            <option>BR (+55)</option>
                                        </select>
                                        <input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>" placeholder="Exemplo: 99 9 9999-9999" style="flex: 1;">
                                    </div>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> Digite apenas nÃºmeros, ex: 11999999999
                                    </small>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_conta" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar alteraÃ§Ãµes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: SeguranÃ§a (Alterar Senha) -->
                    <div id="tab-seguranca" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-key icon-lock'></i>
                                        SENHA ATUAL
                                    </label>
                                    <input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-lock-alt icon-lock'></i>
                                        NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="nova_senha" placeholder="Digite a nova senha" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-check-shield icon-lock'></i>
                                        CONFIRMAR NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme a nova senha" autocomplete="off">
                                </div>
                            </div>

                            <div class="password-requirements">
                                <i class='bx bx-info-circle'></i> <strong>Requisitos de senha:</strong>
                                <ul>
                                    <li>MÃ­nimo de 5 caracteres e mÃ¡ximo de 10 caracteres</li>
                                    <li>Apenas letras e nÃºmeros (sem caracteres especiais)</li>
                                    <li>Letras maiÃºsculas e minÃºsculas sÃ£o permitidas</li>
                                </ul>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="alterar_senha" class="btn-action btn-success">
                                    <i class='bx bx-key'></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: Mensagens WhatsApp -->
                    <div id="tab-mensagens" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-user-plus icon-message'></i>
                                        Mensagem ao Criar UsuÃ¡rio (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarusuario" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($whatsapp_mensagens['criarusuario']['mensagem'] ?? 'ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-test-tube icon-message'></i>
                                        Mensagem ao Criar Teste (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarteste" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um teste..."><?php echo htmlspecialchars($whatsapp_mensagens['criarteste']['mensagem'] ?? 'ðŸŽ‰ Teste Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nâ±ï¸ DuraÃ§Ã£o: {validade} minutos\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store-alt icon-message'></i>
                                        Mensagem ao Criar Revenda (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarrevenda" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar uma revenda..."><?php echo htmlspecialchars($whatsapp_mensagens['criarrevenda']['mensagem'] ?? 'ðŸŽ‰ Revenda Criada! ðŸŽ‰\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-calendar-x icon-message'></i>
                                        Mensagem de Conta Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_contaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a conta expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['contaexpirada']['mensagem'] ?? 'ðŸ˜© Sua conta estÃ¡ prestes a vencer!\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Renove agora: https://{dominio}/renovacao_login.php'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store icon-message'></i>
                                        Mensagem de Revenda Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_revendaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a revenda expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['revendaexpirada']['mensagem'] ?? 'ðŸ˜© Sua revenda estÃ¡ prestes a vencer!\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Acesse o painel para renovar: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_mensagens_whatsapp" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Mensagens WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>

                 
        <!-- Aba: Mensagens Modal -->
<div id="tab-modal-mensagens" class="tab-content">
    <form method="POST">
        <div class="form-grid">
            <div class="form-field full-width">
                <label>
                    <i class='bx bx-user-plus icon-message'></i>
                    Mensagem ao Criar UsuÃ¡rio (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarusuario" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($modal_mensagens['criarusuario'] ?? 'ðŸŽ‰ Obrigado por escolher nossos serviÃ§os!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ”„ Renove seu plano:\nðŸ”— https://{dominio}/renovacao_login.php\n\nðŸ’¥ Aproveite os melhores servidores!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-test-tube icon-message'></i>
                    Mensagem ao Criar Teste (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarteste" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um teste..."><?php echo htmlspecialchars($modal_mensagens['criarteste'] ?? 'ðŸŽ‰ Teste liberado! Aproveite nosso serviÃ§o!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ’¥ Experimente a melhor conexÃ£o!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-store-alt icon-message'></i>
                    Mensagem ao Criar Revenda (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarrevenda" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar uma revenda..."><?php echo htmlspecialchars($modal_mensagens['criarrevenda'] ?? 'ðŸŽ‰ Revenda criada com sucesso!\n\nðŸ’¥ Comece a vender agora mesmo!\nðŸ”— Link do painel: https://{dominio}/\n\nðŸ“± Indique nosso aplicativo para seus clientes:\nðŸ”— https://{dominio}/aplicativos.php'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" name="salvar_mensagens_modal" class="btn-action btn-success">
                <i class='bx bx-save'></i> Salvar Mensagens Modal
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">OperaÃ§Ã£o realizada!</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_sucesso; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_erro; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function switchTab(tab) {
            document.getElementById('tab-conta').classList.remove('active');
            document.getElementById('tab-seguranca').classList.remove('active');
            document.getElementById('tab-mensagens').classList.remove('active');
            document.getElementById('tab-modal-mensagens').classList.remove('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (tab === 'conta') {
                document.getElementById('tab-conta').classList.add('active');
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (tab === 'seguranca') {
                document.getElementById('tab-seguranca').classList.add('active');
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else if (tab === 'mensagens') {
                document.getElementById('tab-mensagens').classList.add('active');
                document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            } else if (tab === 'modal-mensagens') {
                document.getElementById('tab-modal-mensagens').classList.add('active');
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Remove os parÃ¢metros da URL sem recarregar a pÃ¡gina
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }
        }

        <?php if ($show_modal && $modal_type == 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php elseif ($show_modal && $modal_type == 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
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
                <i class='bx bx-user-circle'></i>
                <span>Editar Perfil</span>
            </div>

            <!-- Card Principal - Foto de Perfil -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-camera'></i>
                    </div>
                    <div>
                        <div class="header-title">Foto de Perfil</div>
                        <div class="header-subtitle">Atualize sua foto de perfil</div>
                    </div>
                    <a href="../home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="profile-upload-area">
                        <?php
                        $avatar_url = !empty($user_data['profile_image']) 
                            ? '../uploads/profiles/' . $user_data['profile_image'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['login']) . '&size=100&background=4158D0&color=fff&bold=true&length=2';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="current-avatar" id="profile-avatar-preview">
                        <form method="post" enctype="multipart/form-data" style="width: 100%;">
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <label class="btn-action btn-primary" style="cursor: pointer; margin: 0;">
                                    <i class='bx bx-upload'></i> Escolher Foto
                                    <input type="file" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                </label>
                                <button type="submit" name="upload_image" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Foto
                                </button>
                            </div>
                        </form>
                        <small style="color: rgba(255,255,255,0.3); margin-top: 10px; display: block; font-size: 9px;">
                            <i class='bx bx-info-circle'></i> Formatos permitidos: JPG, JPEG, PNG, GIF, WEBP
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card com Abas - ConfiguraÃ§Ãµes -->
            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes da Conta</div>
                        <div class="header-subtitle">Gerencie suas informaÃ§Ãµes e mensagens</div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Abas -->
                    <div class="tabs-container">
                        <div class="tabs-buttons">
                            <button class="tab-btn active" onclick="switchTab('conta')">
                                <i class='bx bx-user'></i> Conta
                            </button>
                            <button class="tab-btn" onclick="switchTab('seguranca')">
                                <i class='bx bx-lock-alt'></i> SeguranÃ§a
                            </button>
                            <button class="tab-btn" onclick="switchTab('mensagens')">
                                <i class='bx bx-message-detail'></i> WhatsApp
                            </button>
                            <button class="tab-btn" onclick="switchTab('modal-mensagens')">
                                <i class='bx bx-message-rounded-dots'></i> Modal
                            </button>
                        </div>
                    </div>

                    <!-- Aba: InformaÃ§Ãµes da Conta -->
                    <div id="tab-conta" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        NOME COMPLETO
                                    </label>
                                    <input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ''); ?>" placeholder="Digite seu nome completo">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-envelope icon-user'></i>
                                        E-MAIL
                                    </label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="seu@email.com">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-user icon-user'></i>
                                        USUÃRIO
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']); ?>" disabled readonly>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> O nome de usuÃ¡rio nÃ£o pode ser alterado
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                        NÃšMERO DE TELEFONE
                                    </label>
                                    <div style="display: flex; gap: 8px;">
                                        <select style="width: 80px; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; padding: 8px 8px; font-size: 12px;">
                                            <option>BR (+55)</option>
                                        </select>
                                        <input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>" placeholder="Exemplo: 99 9 9999-9999" style="flex: 1;">
                                    </div>
                                    <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                        <i class='bx bx-info-circle'></i> Digite apenas nÃºmeros, ex: 11999999999
                                    </small>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_conta" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar alteraÃ§Ãµes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: SeguranÃ§a (Alterar Senha) -->
                    <div id="tab-seguranca" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-key icon-lock'></i>
                                        SENHA ATUAL
                                    </label>
                                    <input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-lock-alt icon-lock'></i>
                                        NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="nova_senha" placeholder="Digite a nova senha" autocomplete="off">
                                </div>

                                <div class="form-field">
                                    <label>
                                        <i class='bx bx-check-shield icon-lock'></i>
                                        CONFIRMAR NOVA SENHA
                                    </label>
                                    <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme a nova senha" autocomplete="off">
                                </div>
                            </div>

                            <div class="password-requirements">
                                <i class='bx bx-info-circle'></i> <strong>Requisitos de senha:</strong>
                                <ul>
                                    <li>MÃ­nimo de 5 caracteres e mÃ¡ximo de 10 caracteres</li>
                                    <li>Apenas letras e nÃºmeros (sem caracteres especiais)</li>
                                    <li>Letras maiÃºsculas e minÃºsculas sÃ£o permitidas</li>
                                </ul>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="alterar_senha" class="btn-action btn-success">
                                    <i class='bx bx-key'></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Aba: Mensagens WhatsApp -->
                    <div id="tab-mensagens" class="tab-content">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-user-plus icon-message'></i>
                                        Mensagem ao Criar UsuÃ¡rio (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarusuario" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($whatsapp_mensagens['criarusuario']['mensagem'] ?? 'ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-test-tube icon-message'></i>
                                        Mensagem ao Criar Teste (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarteste" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar um teste..."><?php echo htmlspecialchars($whatsapp_mensagens['criarteste']['mensagem'] ?? 'ðŸŽ‰ Teste Criado! ðŸŽ‰\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nâ±ï¸ DuraÃ§Ã£o: {validade} minutos\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store-alt icon-message'></i>
                                        Mensagem ao Criar Revenda (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_criarrevenda" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada ao criar uma revenda..."><?php echo htmlspecialchars($whatsapp_mensagens['criarrevenda']['mensagem'] ?? 'ðŸŽ‰ Revenda Criada! ðŸŽ‰\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸŒ Link: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-calendar-x icon-message'></i>
                                        Mensagem de Conta Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_contaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a conta expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['contaexpirada']['mensagem'] ?? 'ðŸ˜© Sua conta estÃ¡ prestes a vencer!\n\nðŸ”Ž UsuÃ¡rio: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Renove agora: https://{dominio}/renovacao_login.php'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>

                                <div class="form-field full-width">
                                    <label>
                                        <i class='bx bx-store icon-message'></i>
                                        Mensagem de Revenda Expirada (WhatsApp)
                                    </label>
                                    <textarea class="form-control" name="mensagem_whatsapp_revendaexpirada" rows="5" placeholder="Digite a mensagem que serÃ¡ enviada quando a revenda expirar..."><?php echo htmlspecialchars($whatsapp_mensagens['revendaexpirada']['mensagem'] ?? 'ðŸ˜© Sua revenda estÃ¡ prestes a vencer!\n\nðŸ”Ž Revenda: {usuario}\nðŸ”‘ Senha: {senha}\nðŸŽ¯ Validade: {validade}\nðŸ•Ÿ Limite: {limite}\n\nðŸ”„ Acesse o painel para renovar: https://{dominio}/'); ?></textarea>
                                    <div class="mensagem-preview">
                                        <i class='bx bx-info-circle'></i> VariÃ¡veis: {usuario}, {senha}, {validade}, {limite}, {dominio}
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="salvar_mensagens_whatsapp" class="btn-action btn-success">
                                    <i class='bx bx-save'></i> Salvar Mensagens WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>

                 
        <!-- Aba: Mensagens Modal -->
<div id="tab-modal-mensagens" class="tab-content">
    <form method="POST">
        <div class="form-grid">
            <div class="form-field full-width">
                <label>
                    <i class='bx bx-user-plus icon-message'></i>
                    Mensagem ao Criar UsuÃ¡rio (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarusuario" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um novo usuÃ¡rio..."><?php echo htmlspecialchars($modal_mensagens['criarusuario'] ?? 'ðŸŽ‰ Obrigado por escolher nossos serviÃ§os!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ”„ Renove seu plano:\nðŸ”— https://{dominio}/renovacao_login.php\n\nðŸ’¥ Aproveite os melhores servidores!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-test-tube icon-message'></i>
                    Mensagem ao Criar Teste (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarteste" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar um teste..."><?php echo htmlspecialchars($modal_mensagens['criarteste'] ?? 'ðŸŽ‰ Teste liberado! Aproveite nosso serviÃ§o!\n\nðŸ“± Baixe nosso aplicativo VPN:\nðŸ”— https://{dominio}/aplicativos.php\n\nðŸ’¥ Experimente a melhor conexÃ£o!'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>

            <div class="form-field full-width">
                <label>
                    <i class='bx bx-store-alt icon-message'></i>
                    Mensagem ao Criar Revenda (Modal)
                </label>
                <textarea class="form-control" name="mensagem_modal_criarrevenda" rows="5" placeholder="Digite a mensagem que aparecerÃ¡ no modal ao criar uma revenda..."><?php echo htmlspecialchars($modal_mensagens['criarrevenda'] ?? 'ðŸŽ‰ Revenda criada com sucesso!\n\nðŸ’¥ Comece a vender agora mesmo!\nðŸ”— Link do painel: https://{dominio}/\n\nðŸ“± Indique nosso aplicativo para seus clientes:\nðŸ”— https://{dominio}/aplicativos.php'); ?></textarea>
                <div class="mensagem-preview">
                    <i class='bx bx-info-circle'></i> VariÃ¡veis disponÃ­veis: {dominio}
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" name="salvar_mensagens_modal" class="btn-action btn-success">
                <i class='bx bx-save'></i> Salvar Mensagens Modal
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">OperaÃ§Ã£o realizada!</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_sucesso; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $mensagem_erro; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function switchTab(tab) {
            document.getElementById('tab-conta').classList.remove('active');
            document.getElementById('tab-seguranca').classList.remove('active');
            document.getElementById('tab-mensagens').classList.remove('active');
            document.getElementById('tab-modal-mensagens').classList.remove('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (tab === 'conta') {
                document.getElementById('tab-conta').classList.add('active');
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (tab === 'seguranca') {
                document.getElementById('tab-seguranca').classList.add('active');
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else if (tab === 'mensagens') {
                document.getElementById('tab-mensagens').classList.add('active');
                document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            } else if (tab === 'modal-mensagens') {
                document.getElementById('tab-modal-mensagens').classList.add('active');
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Remove os parÃ¢metros da URL sem recarregar a pÃ¡gina
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }
        }

        <?php if ($show_modal && $modal_type == 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php elseif ($show_modal && $modal_type == 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                if (window.history && window.history.replaceState) {
                    var url = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, url);
                }
            }
        });
    </script>
</body>
</html>



