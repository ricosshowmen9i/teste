<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio144196($input)
    {
        ?>
    
<?php 
if (!isset($_SESSION)){
    error_reporting(0);
session_start();

}

//se a sessão não existir, redireciona para o login
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

if ($_SESSION['login'] == 'admin') {
}else{
  echo "<script>alert('Você não tem permissão para acessar essa página!');window.location.href='../logout.php';</script>";
  exit();
}
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


if (!empty($_POST['id'])) {
    $id = anti_sql($_POST['id']);
    $comando = anti_sql($_POST['comando']);
}

  
          set_time_limit(0); // Limite de tempo de execução: 2h. Deixe 0 (zero) para sem limite
          ignore_user_abort(true); // Continua a execução mesmo que o usuário cancele o download
          
          set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
          include ('Net/SSH2.php');
            //pegar senha e login do servidor
            $sql2 = "SELECT * FROM servidores WHERE id = '$id'";
            $result = $conn -> query($sql2);
            $row = mysqli_fetch_assoc($result);
            $login = $row['usuario'];
            $senha = $row['senha'];
            $porta = $row['porta'];
            $ip = $row['ip'];


            try {
                $ssh = new Net_SSH2($ip, $porta);
                if (!$ssh->login($login, $senha)) {
                    echo "Não foi possível autenticar";
                } else {
                    $ssh->exec("$comando > /dev/null 2>&1 &");
                    $ssh->disconnect();
                    echo "Comando enviado com sucesso";
                }
            } catch (Exception $e) {
                echo "Não foi possível conectar ao servidor";
            }
             

?>

                       <?php
    }
    aleatorio144196($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
