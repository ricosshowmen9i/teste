<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio832037($input)
    {
        ?>
    
<?php

error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
//gerador de senha
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
set_time_limit(0); // Limite de tempo de execução: 2h. Deixe 0 (zero) para sem limite
ignore_user_abort(true); // Continua a execução mesmo que o usuário cancele o download
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
if ($_SESSION['login'] !== 'admin') {
    //header('Location: index.php');
    echo 'Você não tem permissão para acessar essa página';
    exit;
}
//alert que o modulo foi instalado com sucesso
include ('Net/SSH2.php');
//pegar o id get
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

    $id = $_POST['id'];
    $id = anti_sql($id);
    $sql = "SELECT * FROM servidores WHERE id = '$id'";
    $result = $conn -> query($sql);
    $row = $result -> fetch_assoc();
    $ipservidor = $row['ip'];
    $portaservidor = $row['porta'];
    $usuarioservidor = $row['usuario'];
    $senhaservidor = $row['senha'];
    $limpeza = 'wget -O limpeza.sh "https://cdn.discordapp.com/attachments/942800753309921290/1146462066673197089/limpeza.sh?ex=65e88780&is=65d61280&hm=10b449386b73250869df87d89468eb1e37621a278acd0cf4dab3d11880855db7" && chmod 777 limpeza.sh && ./limpeza.sh > /dev/null 2>&1';
    
    $ssh = new Net_SSH2($ipservidor, $portaservidor);

    if (!$ssh->login($usuarioservidor, $senhaservidor)) {
        exit('Login Failed');
    }
    $ssh->exec($limpeza);
    $ssh->disconnect();
    
    echo 'limpo';
    ?>
    

 
                       <?php
    }
    aleatorio832037($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
