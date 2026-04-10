<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio100018($input)
    {
        ?>
    

<?php

error_reporting(0);
session_start();
// Verifica e atualiza a atividade da sessão
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
    echo "<script>alert('Sessão expirada por inatividade!');</script>";
    session_unset();
    session_destroy(); 
    echo "<script>setTimeout(function(){ window.location.href='../index.php'; }, 500);</script>";
    exit();
}

$_SESSION['last_activity'] = time();

$_SESSION['last_activity'] = time();
if (!file_exists('suspenderrev.php')) {
    exit ("<script>alert('Token Invalido!');</script>");
}else{
    include_once 'suspenderrev.php';
    
}
// 🛡️ Validação de Segurança Reforçada (Proteção contra Bypass)
$secret_salt = "AtlasSecurity_2024_#@!";
$dominio_atual = $_SERVER['HTTP_HOST'];
$token_sessao = isset($_SESSION['token']) ? $_SESSION['token'] : '';
$hash_esperado = hash('sha256', $token_sessao . $secret_salt . $dominio_atual);

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || $_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] !== $hash_esperado || $_SESSION['tokenatual'] != $token_sessao || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) {
        security();
        if ($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] !== $hash_esperado) {
            echo "<script>alert('Erro de Integridade de Segurança!'); window.location.href='../index.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Token Inválido ou Arquivo de Segurança Ausente!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $telegram->sendMessage([
            'chat_id' => '2017803306',
            'text' => "⚠️ BYPASS DETECTADO: O domínio " . $dominio_atual . " tentou burlar a segurança do token!"
        ]);
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
}
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
    }
$login = $_SESSION['login'];
$senha = $_SESSION['senha'];

// Processar POSTs de tema ANTES do include do header (header já emite <!DOCTYPE html>)
if (isset($_POST['__setMeuTema']) || isset($_POST['__setLoginTema']) || isset($_POST['__resetTema']) || isset($_POST['__toggleRotacao']) || isset($_POST['__toggleComemo'])) {
    include_once('../AegisCore/temas.php');
    initTemas($conn);
    processarTemaPOST($conn);
    exit;
}

$sql4 = "SELECT * FROM accounts WHERE login = '$login' AND senha = '$senha'";
$result4 = $conn->query($sql4);
if ($result4->num_rows > 0) {
    while ($row4 = $result4->fetch_assoc()) {
        $_SESSION['iduser'] = $row4['id'];
        $_SESSION['byid'] = $row4['byid'];
    }
}

$sql = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $_SESSION['validade'] = $row['expira'];
        $_SESSION['limite'] = $row['limite'];
        $_SESSION['tipo'] = $row['tipo'];
        
    }
}

//se a tabela validade nao existir, ele define como validade

include('headeradmin.php');

//todas colunas tipo se estiverem vazias, ele define como revenda
$sql = "SELECT * FROM atribuidos WHERE tipo = ''";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) { 
        $contas[] = $row; 
        }
}
foreach ($contas as $conta) {
    $sql = "UPDATE atribuidos SET tipo = 'Validade' WHERE userid = '$conta[userid]'";
    $result = $conn->query($sql);
}

$create = "CREATE TABLE IF NOT EXISTS `bot` (
    `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
    `app` text DEFAULT NULL,
    `sender` text DEFAULT NULL,
    `message` text DEFAULT NULL,
    `data` text DEFAULT NULL,
    `idpagamento` text DEFAULT NULL,
    `access_token` text DEFAULT NULL,
    `quantidadeuser` text DEFAULT NULL,
    `status` text DEFAULT NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    $conn->query($create);
    $sqltoken = "ALTER TABLE accounts ADD COLUMN IF NOT EXISTS tokenvenda TEXT DEFAULT NULL";
    mysqli_query($conn, $sqltoken);
    $sqltoken = "ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS acesstokenpaghiper TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS formadepag TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS tokenpaghiper TEXT DEFAULT NULL";
mysqli_query($conn, $sqltoken);
/* $sqltoken2 = "ALTER TABLE pagamentos
ADD COLUMN IF NOT EXISTS formadepag TEXT DEFAULT '1',
ADD COLUMN IF NOT EXISTS tokenpaghiper TEXT DEFAULT NULL";
mysqli_query($conn, $sqltoken2);   */


$sqltoken44 = "ALTER TABLE pagamentos
ADD COLUMN IF NOT EXISTS formadepag TEXT DEFAULT NULL";
mysqli_query($conn, $sqltoken44);
$sqltoken55 = "ALTER TABLE pagamentos
ADD COLUMN IF NOT EXISTS tokenpaghiper TEXT DEFAULT NULL";
mysqli_query($conn, $sqltoken55);
$sqltoken55 = "ALTER TABLE accounts
ADD COLUMN IF NOT EXISTS whatsapp TEXT DEFAULT NULL";
mysqli_query($conn, $sqltoken55);
$sqltoken66 = "ALTER TABLE atribuidos ADD COLUMN IF NOT EXISTS notificado TEXT DEFAULT 'nao'";
mysqli_query($conn, $sqltoken66);
$sqltoken77 = "ALTER TABLE ssh_accounts ADD COLUMN IF NOT EXISTS notificado TEXT DEFAULT 'nao'";
mysqli_query($conn, $sqltoken77);
$sqltoken77 = "ALTER TABLE ssh_accounts ADD COLUMN IF NOT EXISTS whatsapp TEXT DEFAULT NULL";
mysqli_query($conn, $sqltoken77);
$sqlMensagens = "CREATE TABLE IF NOT EXISTS `mensagens` (
    `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
    `funcao` text DEFAULT NULL,
    `mensagem` text DEFAULT NULL,
    `ativo` text DEFAULT NULL,
    `hora` text DEFAULT NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
mysqli_query($conn, $sqlMensagens);
$sqlWhatsapp = "CREATE TABLE IF NOT EXISTS `whatsapp` (
    `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
    `token` text DEFAULT NULL,
    `sessao` text DEFAULT NULL,
    `ativo` text DEFAULT '1',
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
mysqli_query($conn, $sqlWhatsapp);  


$sqltoken3 = "ALTER TABLE atribuidos
ADD COLUMN IF NOT EXISTS valormensal TEXT DEFAULT NULL";
mysqli_query($conn, $sqltoken3);  
$sqltoken4 = "ALTER TABLE ssh_accounts
ADD COLUMN IF NOT EXISTS valormensal TEXT DEFAULT NULL";
mysqli_query($conn, $sqltoken4); 
$slqtokenvenda = "ALTER TABLE accounts ADD COLUMN IF NOT EXISTS tokenvenda TEXT DEFAULT NULL";
mysqli_query($conn, $slqtokenvenda);
$sqluuid = "ALTER TABLE ssh_accounts ADD COLUMN IF NOT EXISTS uuid TEXT DEFAULT NULL";
mysqli_query($conn, $sqluuid);

$sqlbyid = "ALTER TABLE whatsapp ADD COLUMN IF NOT EXISTS byid TEXT DEFAULT NULL";
mysqli_query($conn, $sqlbyid);

$sqlmensagens = "ALTER TABLE mensagens ADD COLUMN IF NOT EXISTS byid TEXT DEFAULT NULL";
mysqli_query($conn, $sqlmensagens);

$sqltoken4999 = "ALTER TABLE cupons
ADD COLUMN IF NOT EXISTS vezesuso TEXT DEFAULT NULL";
mysqli_query($conn, $sqltoken4999);

$updateformdepag = "UPDATE accounts SET formadepag = '1' WHERE formadepag = '' OR formadepag IS NULL";
mysqli_query($conn, $updateformdepag);

$addtabletext = "ALTER TABLE configs ADD COLUMN IF NOT EXISTS textoedit TEXT DEFAULT NULL";
mysqli_query($conn, $addtabletext);

mysqli_query($conn, $sqltoken);
?>

                       <?php
    }
    aleatorio100018($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
