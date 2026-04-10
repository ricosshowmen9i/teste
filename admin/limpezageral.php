<script src="../app-assets/sweetalert.min.js"></script>
<?php
//error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Verifica se o usuário está autenticado
if (!isset($_SESSION['login']) || !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('Location: index.php');
    exit;
}
include('../vendor/event/autoload.php');
use React\EventLoop\Factory;

if ($_SESSION['login'] !== 'admin') {
    //header('Location: index.php');
    echo 'Você não tem permissão para acessar essa página';
    exit;
}
include('headeradmin2.php');
include('Net/SSH2.php');

$limpeza = 'wget -O limpeza.sh "https://cdn.discordapp.com/attachments/942800753309921290/1146462066673197089/limpeza.sh?ex=65e88780&is=65d61280&hm=10b449386b73250869df87d89468eb1e37621a278acd0cf4dab3d11880855db7" && chmod 777 limpeza.sh && ./limpeza.sh > /dev/null 2>&1';
$sql = "SELECT * FROM servidores";
$result = $conn->query($sql);

$loop = Factory::create();
    $servidores_com_erro = [];
    $sucess = false;         

    while ($user_data = mysqli_fetch_assoc($result)) {
      $tentativas = 0;
      $conectado = false;
  
      while ($tentativas < 2 && !$conectado) {
          $ssh = new Net_SSH2($user_data['ip'], $user_data['porta']);
  
          if ($ssh->login($user_data['usuario'], $user_data['senha'])) {
              $loop->addTimer(0.001, function () use ($ssh, $user_data, $conn, $limpeza) {
                    $ssh->exec($limpeza);
                  $ssh->disconnect();
              });
              $conectado = true;
              $sucess = true;
          } else {
              $tentativas++;
          }
      }
  
      if (!$conectado) {
          $servidores_com_erro[] = $user_data['ip'];
      }
  }
  if ($sucess) {
      echo '<script>sweetAlert("Sucesso!", "Servidores Limpos com sucesso!", "success").then((value) => { window.location.href = "servidores.php"; });</script>';
    } else {
        echo '<script>sweetAlert("Erro!", "Não foi possível limpar os servidores!", "error").then((value) => { window.location.href = "servidores.php"; });</script>';
        }
  $loop->run();



?>