<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
<?php 
use GuzzleHttp\Psr7\Query;
use LDAP\Result;
if (!isset($_SESSION)){
    error_reporting(0);
session_start();

}
include('../vendor/event/autoload.php');
use React\EventLoop\Factory;
//se a sessão não existir, redireciona para o login
if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
}
include 'headeradmin2.php';
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
    
}
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
        $telegram->sendMessage([
            'chat_id' => '2017803306',
            'text' => "O domínio " . $_SERVER['HTTP_HOST'] . " tentou acessar o painel com token - " . $_SESSION['token'] . " inválido!"
        ]);
        $_SESSION['token_invalido_'] = true;
        exit;
    }
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

//anti sql injection na $_GET['id']
$_GET['id'] = anti_sql($_GET['id']);
//anti sql injection na $_GET['idusuario']
$_GET['idusuario'] = anti_sql($_GET['idusuario']);

    $id = $_GET['idusuario'];
    $idcategoria = $_GET['id'];

$sql = "SELECT * FROM atribuidos WHERE userid = '$id'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categoria = $row['categoriaid'];

    }
}

$contas = array();
set_time_limit(0); // Limite de tempo de execução: 2h. Deixe 0 (zero) para sem limite
ignore_user_abort(true); // Continua a execução mesmo que o usuário cancele o download
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
    include ('Net/SSH2.php');
//consulta todos logins 

  $sql1 = "SELECT * FROM accounts WHERE byid = '$id'";
  $result1 = $conn->query($sql1);
    if ($result1->num_rows > 0) {
        while ($row1 = $result1->fetch_assoc()) {
            $contas[] = $row1;
        }
    }   
    //consulta todos revendedores de $contas
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
            }
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
            }
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
            }
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
            }
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
            }
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
            }
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
            }
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
            }
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
            }
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
            }
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
            }
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
            }
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
                
            }
        }
    }
    $sql3 = "SELECT * FROM accounts WHERE id = '$id'";
    $result3 = $conn->query($sql3);
    if ($result3->num_rows > 0) {
        while ($row3 = $result3->fetch_assoc()) {
            $contas[] = $row3;
            $deletes[] = $row3;
        }
    }
    foreach ($contas as $conta) {
        $sql2 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
        $result2 = $conn->query($sql2);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $contas[] = $row2;
                
            }
        }
    }
    $sql55 = "SELECT * FROM accounts WHERE byid = '$conta[id]'";
    $result55 = $conn->query($sql55);
    if ($result55->num_rows > 0) {
        while ($row55 = $result55->fetch_assoc()) {
            $dells[] = $row55;
            
        }
    }
    
    
    
    
    $contas = array_unique($contas, SORT_REGULAR);
    //pesquisar todos ssh_accouns de $contas e envia um txt para o servidor
    foreach ($contas as $conta) {
        $sql3 = "SELECT * FROM ssh_accounts WHERE byid = '$conta[id]'";
        $result3 = $conn->query($sql3);
        if ($result3->num_rows > 0) {
            while ($row3 = $result3->fetch_assoc()) {
                $ssh_accounts[] = $row3;
            }
        }
    }

        $nome = md5 ( uniqid ( rand (), true ));
        $nome = substr($nome, 0, 10);
        $nome = $nome . ".txt";
        //salvar txt
        $file = fopen("$nome", "w");
        foreach ($ssh_accounts as $ssh_account) {
            $login = $ssh_account['login'];
            $uuid = $ssh_account['uuid'];
            fwrite($file, $uuid . " " . $login . PHP_EOL);
       }


    

    //deletar todas as contas do banco de dados
    //print_r($contas);
    $sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
          $result = $conn -> query($sql2);
          if ($result->num_rows > 0) {
          //time zone
    //salvar os logins em um txt dentro do servidor
    //gerar arquivo txt com nome dinamico

    $loop = Factory::create();
    $servidores_com_erro = [];
    $sucess = false;         

    while ($user_data = mysqli_fetch_assoc($result)) {
      $tentativas = 0;
      $conectado = false;
  
      while ($tentativas < 2 && !$conectado) {
          $ssh = new Net_SSH2($user_data['ip'], $user_data['porta']);
  
          if ($ssh->login($user_data['usuario'], $user_data['senha'])) {
              $loop->addTimer(0.001, function () use ($ssh, $user_data, $conn, $nome) {
                  $local_file = $nome;
                  $limiter_content = file_get_contents($local_file);
                  $ssh->exec('echo "' . $limiter_content . '" > /root/' . $nome);
                  $ssh->exec('python3 /root/delete.py ' . $nome . ' > /dev/null 2>/dev/null &');
                  $ssh->exec('python2 /root/delete.py ' . $nome . ' > /dev/null 2>/dev/null &');
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
  
      foreach ($contas as $conta) {
        $sql5 = "UPDATE ssh_accounts SET categoriaid = '$idcategoria' WHERE byid = '$conta[id]'";
        $result5 = $conn->query($sql5);
        $sql6 = "UPDATE atribuidos SET categoriaid = '$idcategoria' WHERE userid = '$conta[id]'";
        $result6 = $conn->query($sql6);
    }
    foreach ($deletes as $delete) {
        $sql5 = "UPDATE ssh_accounts SET categoriaid = '$idcategoria' WHERE byid = '$delete[id]'";
        $result5 = $conn->query($sql5);
        $sql6 = "UPDATE atribuidos SET categoriaid = '$idcategoria' WHERE userid = '$delete[id]'";
        $result6 = $conn->query($sql6);
    }
    //passa um get para a pagina de sucesso
    echo "<script>location.href='reativarrevenda.php?id=$id';</script>";
$loop->run();
}else{
    foreach ($contas as $conta) {
        $sql5 = "UPDATE ssh_accounts SET categoriaid = '$idcategoria' WHERE byid = '$conta[id]'";
        $result5 = $conn->query($sql5);
        $sql6 = "UPDATE atribuidos SET categoriaid = '$idcategoria' WHERE userid = '$conta[id]'";
        $result6 = $conn->query($sql6);
    }
    foreach ($deletes as $delete) {
        $sql5 = "UPDATE ssh_accounts SET categoriaid = '$idcategoria' WHERE byid = '$delete[id]'";
        $result5 = $conn->query($sql5);
        $sql6 = "UPDATE atribuidos SET categoriaid = '$idcategoria' WHERE userid = '$delete[id]'";
        $result6 = $conn->query($sql6);
    }
    echo "<script>location.href='reativarrevenda.php?id=$id';</script>";
}
unlink($nome);


?>