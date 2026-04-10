<?php
/**
 * ARQUIVO DE SEGURANÇA CRÍTICO - NÃO MODIFICAR
 * Este arquivo valida a licença do painel e protege contra bypass.
 */

error_reporting(0);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../vendor/autoload.php';
use Telegram\Bot\Api;

// Configurações de Notificação
$bot_token = '6225410515:AAF24iFlWCFWC8-A5oYgXW5f3whhIzcKymI';
$admin_chat_id = '2017803306';

/**
 * Função de Segurança Principal
 * Realiza a validação remota do token e protege as variáveis de sessão
 */
function security() {
    global $bot_token, $admin_chat_id;
    
    date_default_timezone_set('America/Sao_Paulo');
    
    // 1. Verificação de Integridade do Arquivo de Conexão
    $filepath = '../AegisCore/conexao.php';
    if (!file_exists($filepath)) {
        die("Erro crítico: Arquivo de configuração não encontrado.");
    }
    
    // Carrega o token real do arquivo de conexão
    include $filepath;
    $token_real = $_SESSION['token']; // O arquivo conexao.php define $_SESSION['token']
    
    if (empty($token_real)) {
        invalid_token_action("Token não configurado no sistema.");
    }

    // 2. Validação Remota (Servidor de Licenciamento)
    $dominio = $_SERVER['HTTP_HOST'];
    $senhatokenacessoss = "7bUqcVkyxD9Bvh6msYvo0VnE0oh9j3fYlcG8LU0czLSe9N4ZvparalelepipedoXincorifolaFofoca.comNinguemmechecomoneysupramultiusoL27CxNhPk7gQZg9hc0iR2lmGypLmf8BEi9AU2k0mEYLvvWqr0t";
    $url = 'https://gerenciador.painelcontrole.xyz/tokenatlas.php?senha=' . $senhatokenacessoss . '&token=' . $token_real . '&dominio=' . $dominio;

    $contextOptions = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.97 Safari/537.36\r\n",
            'timeout' => 10,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];

    $context = stream_context_create($contextOptions);
    $data = @file_get_contents($url, false, $context);

    // 3. Aplicação do Resultado da Validação
    if ($data !== false && trim($data) === 'Token Valido!') {
        // Define as flags de sucesso com um HASH de verificação único
        // Isso impede que alguém apenas sete "true" na sessão
        $secret_salt = "AtlasSecurity_2024_#@!";
        $_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] = hash('sha256', $token_real . $secret_salt . $dominio);
        $_SESSION['token_invalido_'] = false;
        $_SESSION['tokenatual'] = $token_real;
        $_SESSION['last_security_check'] = time();
    } else {
        invalid_token_action("Validação remota falhou ou token expirado.");
    }
}

/**
 * Ação para Token Inválido
 */
function invalid_token_action($reason = "") {
    global $bot_token, $admin_chat_id;
    
    // Limpa flags de segurança
    unset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']);
    $_SESSION['token_invalido_'] = true;
    
    // Notifica via Telegram
    try {
        $telegram = new Api($bot_token);
        $msg = "⚠️ *ALERTA DE SEGURANÇA*\n\n";
        $msg .= "Dominio: " . $_SERVER['HTTP_HOST'] . "\n";
        $msg .= "Token: " . ($_SESSION['token'] ?: 'N/A') . "\n";
        $msg .= "Motivo: " . $reason . "\n";
        $msg .= "Ação: Acesso bloqueado.";
        
        $telegram->sendMessage([
            'chat_id' => $admin_chat_id,
            'text' => $msg,
            'parse_mode' => 'Markdown'
        ]);
    } catch (Exception $e) {}

    echo "<script>alert('Token Inválido ou Expirado! Entre em contato com o suporte.');</script>";
    echo "<script>window.location.href='../index.php';</script>";
    exit();
}

// Se o arquivo for chamado diretamente, executa a segurança
if (basename($_SERVER['PHP_SELF']) == 'suspenderrev.php') {
    security();
}
?>
