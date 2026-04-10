<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio_dellserv($input)
    {
        ?>
<?php
session_start();
error_reporting(0);

// Verifica se as variáveis de sessão de login e senha existem
if (!isset($_SESSION['login']) || !isset($_SESSION['senha'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Verifica se o usuário logado é um administrador
if ($_SESSION['login'] !== 'admin') {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Verificação de token do sistema
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

require_once '../AegisCore/conexao.php';

// Conecta ao banco de dados usando as credenciais do arquivo de conexão
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Inclui o cabeçalho da página de administração
include 'headeradmin2.php';

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

$id = $_POST['id'] ?? $_GET['id'] ?? 0;
$id = anti_sql($id);

if (!empty($id)) {
    // Primeiro, verificar se existem tokens associados a este servidor
    $sql_check_tokens = "SELECT COUNT(*) as total FROM servidor_tokens WHERE servidor_id = '$id'";
    $result_check = mysqli_query($conn, $sql_check_tokens);
    $row_check = mysqli_fetch_assoc($result_check);
    
    if ($row_check['total'] > 0) {
        // Se existirem tokens, deletá-los primeiro (por causa da foreign key)
        $sql_delete_tokens = "DELETE FROM servidor_tokens WHERE servidor_id = '$id'";
        mysqli_query($conn, $sql_delete_tokens);
    }
    
    // Executa uma consulta SQL para excluir o servidor com esse ID
    $sql = "DELETE FROM servidores WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        echo 'Servidor deletado com sucesso!';
    } else {
        echo 'Erro ao deletar servidor!';
    }
} else {
    echo 'Não foi possível obter o ID do servidor.';
}
?>
<?php
    }
    aleatorio_dellserv($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>