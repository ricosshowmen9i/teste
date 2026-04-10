
<?php
//error_reporting(0);
session_start();
include_once("AegisCore/conexao.php");
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if(!$conn ) {
echo $conn->connect_error;
        }
        set_include_path(get_include_path() . PATH_SEPARATOR . 'lib2');
        include ('Net/SSH2.php');
        include('vendor/event/autoload.php');
    use React\EventLoop\Factory;
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
//anti sql injection na $_GET['token']
$_GET['token'] = anti_sql($_GET['token']);

?>



<!DOCTYPE html>
<html lang="pt-br">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?php
    if (!isset($_GET['token'])){
    }else{
        $_SESSION['token'] = $_GET['token'];
    }
    $_SESSION['token'] = mysqli_real_escape_string($conn, $_SESSION['token']);
    $pesquisa_revenda = "SELECT * FROM accounts WHERE tokenvenda = '$_SESSION[token]'";
$pesquisa_revenda = $conn->query($pesquisa_revenda);
if ($pesquisa_revenda->num_rows > 0) {
    $revenda = $pesquisa_revenda->fetch_assoc();
    $valorusuario = $revenda['valorusuario'];
    $access_token = $revenda['accesstoken'];
    $login = $revenda['login'];
    $categoriaadmin = $revenda['tempo'];
}else{
    echo '<script>alert("Token Inválido");</script>';
    exit;
}

     $sql = "SELECT * FROM configs";
     $result = $conn -> query($sql);
     if ($result->num_rows > 0) {
         while($row = $result->fetch_assoc()) {
             $nomepainel = $row["nomepainel"];
             $logo = $row["logo"];
             $icon = $row["icon"];
             $csspersonali = $row["corfundologo"];
         }
     }

     if ($login == 'admin'){
        $categoria = $categoriaadmin;
     }else{
         $atribuicao_cat = "SELECT * FROM atribuidos WHERE userid = '$revenda[id]'";
         $atribuicao_cat = $conn->query($atribuicao_cat);
         if ($atribuicao_cat->num_rows > 0) {
             $atribuicao_cat = $atribuicao_cat->fetch_assoc();
             $categoria = $atribuicao_cat['categoriaid'];
         }
     }

     


     //gerar usuario e senha aleatorio somente letras
        function gerar_senha($tamanho, $maiusculas, $numeros, $simbolos){
            $ma = "abcdefghijklmnopqrstuvwxyz"; // $ma contem as letras minúsculas
            $nu = "0123456789"; // $nu contem os números
            $si = "!@#$%¨&*()_+="; // $si contem os símbolos
            $senha = "";
            $maiusculas = ($maiusculas==true)?'S':'N';
            $numeros = ($numeros==true)?'S':'N';
            $simbolos = ($simbolos==true)?'S':'N';
        
            $caracteres = $ma;
            $caracteres .= ($maiusculas=='S')?$ma:'';
            $caracteres .= ($numeros=='S')?$nu:'';
            $caracteres .= ($simbolos=='S')?$si:'';
        
            $len = strlen($caracteres);
            for ($n = 1; $n <= $tamanho; $n++) {
                $rand = mt_rand(1, $len);
                $senha .= $caracteres[$rand-1];
            }
            return $senha;
        }
        $usuario = gerar_senha(6, true, false, false);

        //deleta tudo da tabela configs
    ?>
 

<!DOCTYPE html>
<html class="loading" lang="pt-br" data-textdirection="ltr">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="author" content="Thomas">
    <title><?php echo $nomepainel; ?> - Teste</title>
    <link rel="apple-touch-icon" href="<?php echo $icon; ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $icon; ?>">
    <link href="https://fonts.googleapis.com/css?family=Rubik:300,400,500,600%7CIBM+Plex+Sans:300,400,500,600,700" rel="stylesheet">


    <link rel="stylesheet" type="text/css" href="../../../app-assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/bootstrap.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/bootstrap-extended.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/colors.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/components.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/themes/dark-layout.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/themes/semi-dark-layout.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/core/menu/menu-types/vertical-menu.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/pages/authentication.css">
    <link rel="stylesheet" type="text/css" href="../../../AegisCore-assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css" integrity="sha512-9O9Sd6Ia1+A0+KwUO1eUg0Fyb3J6UdTo68joKgY9A20+RzI2HfIQK8pk6FyUdxUGpIq3oUItrW8jYVGf9GYZRg==" crossorigin="anonymous" />

</head>
<style>
        <?php echo $csspersonali; ?>
    </style>     
<script src="app-assets/sweetalert.min.js"></script>

<body class="vertical-layout vertical-menu-modern dark-layout 1-column  navbar-sticky footer-static bg-full-screen-image  blank-page blank-page" data-open="click" data-menu="vertical-menu-modern" data-col="1-column" data-layout="dark-layout">
<div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            <div class="content-header row">
            </div>
            <div class="content-body">
                <section id="auth-login" class="row flexbox-container">
                    <div class="col-xl-8 col-11">
                        
                            
                               
                                    <div class="card disable-rounded-right mb-0 p-2 h-100 d-flex justify-content-center">
                                        <div class="card-header pb-1">
                                            <div class="card-title">
                                                <h4 class="text-center mb-2"><?php echo $nomepainel; ?> - Teste</h4>
                                            </div>
                                        </div>
                                        <div class="card-content">
                                            <div class="card-body">
                                                <div class="divider">
                                                    <div class="divider-text text-uppercase text-muted"><small>Teste Gratis</small>
                                                    
                                                    </div>
                                                    <p class="card-description">Gerar um teste gratuito</p>
                                                </div>
                                                <div>
                                                   
                                                <form action="criarteste.php<?php if (isset($_GET['token'])){echo '?token=' . $_GET['token'];} ?>" method="post">
                                                    <div class="form-group mb-50">
                                                        <label class="text-bold-600">Usuário</label>
                                                        <input type="text" class="form-control" name="login" placeholder="Seu Login" value="<?php echo $usuario ?>" disabled></div>
                                                    <div class="form-group">
                                                        <label class="text-bold-600">Senha</label>
                                                        <input type="text" class="form-control" name="senha" value="<?php echo $usuario ?>" placeholder="Sua Senha" disabled>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="text-bold-600">Seu Numero do whatsapp Com o 55 na frente</label>
                                                        <input type="text" class="form-control" name="whatsapp" value="" placeholder="55988887777">
                                                    </div>
                                                    <div class="form-group d-flex flex-md-row flex-column justify-content-between align-items-center">
                                                        <div class="text-left">
                                                            <div></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <button type="submit" class="btn btn-primary glow w-100 position-relative" name="submit">Gerar Teste<i id="icon-arrow" class="bx bx-right-arrow-alt"></i></button>
                                                </form>
                                                <hr>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                   
                </section>
                <?php
                if (isset($_POST['submit'])){


                    //se o ip e o user agent ja estiverem cadastrados, nao cria o teste
                    //time zone
                    date_default_timezone_set('America/Sao_Paulo');
                    // Data limite (24 horas atrás)
                    
                    $remotea = $_SERVER['HTTP_CF_CONNECTING_IP'];
                        if ($remotea == ""){
                            $remotea = $_SERVER['REMOTE_ADDR'];
                        }
                        date_default_timezone_set('America/Sao_Paulo');

                        // Data atual
                        // Definir a data limite (24 horas atrás)
$dataLimite = strtotime('-12 hours');

$sql = "SELECT * FROM bot WHERE app = ? AND sender = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $remotea, $_SERVER['HTTP_USER_AGENT']);
$stmt->execute();
$result = $stmt->get_result();

// Verificar se o usuário já criou um teste recentemente
while ($row = $result->fetch_assoc()) {
    $dataRegistro = strtotime($row['data']);
    if ($dataRegistro > $dataLimite) {
        echo '<script>sweetAlert("Oops...", "Você já criou um teste!", "error");</script>';
        exit;
    }
}

                        


                    $sql = "SELECT * FROM servidores WHERE subid = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $categoria);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    $usuario = $usuario;
                    $senha = $usuario;
                    $limite = 1;
                    $validade = 120;
                    $loop = Factory::create();

                    while ($user_data = mysqli_fetch_assoc($result)) {
                        $tentativas = 0;
                        $conectado = false;

                        while ($tentativas < 3 && !$conectado) {
                            $ssh = new Net_SSH2($user_data['ip'], $user_data['porta']);

                            if ($ssh->login($user_data['usuario'], $user_data['senha'])) {
                                $loop->addTimer(0.001, function () use ($ssh, $user_data, $conn, $usuario, $senha, $limite, $validade) {
                                        $ssh->exec('clear');
                                        $ssh->exec('./atlasteste.sh ' . $usuario . ' ' . $senha . ' ' . $validade . ' ' . $limite . ' > /dev/null 2>&1 &');
                                        $ssh->exec('./atlasteste.sh ' . $usuario . ' ' . $senha . ' ' . $validade . ' ' . $limite . ' ');
                                });
                                $criado = true;
                                $conectado = true;
                            } else {
                                $tentativas++; 
                            }
                        }
                    }
                    if ($criado){
                        $_SESSION['usuariofin'] = $usuario;
                        $_SESSION['jacriousuario'] = 'sim';
                        $_SESSION['senhafin'] = $senha;
                        $_SESSION['validadefin'] = $validade;
                        $_SESSION['limitefin'] = $limite;
                        $_SESSION['byidfin'] = $revenda['id'];
                        $_SESSION['whatsappfin'] = $_POST['whatsapp'];
                        //se remo
                        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
                        if ($ip == ""){
                            $ip = $_SERVER['REMOTE_ADDR'];
                        }
                        $useragent = $_SERVER['HTTP_USER_AGENT'];
                        $data = date('Y-m-d H:i:s');
                        $insertipuser = "INSERT INTO bot (app, sender, data) VALUES ('$ip', '$useragent', '$data')";
                        $resultipuser = mysqli_query($conn, $insertipuser);
                        $validadedata = date('Y-m-d H:i:s', strtotime("+$validade minutes"));
                        $criadoteste = "INSERT INTO ssh_accounts (login, senha, expira, limite, byid, categoriaid, status, bycredit, mainid, lastview, whatsapp) VALUES ('$usuario', '$senha', '$validadedata', '$limite', '$revenda[id]', '1', 'Offline', '0', '0', 'TESTE WHATSAPP', '$_POST[whatsapp]')";
                        $result9 = mysqli_query($conn, $criadoteste);
                        echo '<script>window.location.href = "criado.php?token=' . $_SESSION['token'] . '";</script>';
                        
                    }else{
                        echo '<script>alert("Erro ao Criar Teste");</script>';
                    }

$loop->run();
                }
    ?>
            </div>
        </div>
    
    <script src="../../../app-assets/vendors/js/vendors.min.js"></script>
    <script src="../../../app-assets/js/scripts/pages/bootstrap-toast.js"></script>
    <script src="../../../app-assets/fonts/LivIconsEvo/js/LivIconsEvo.tools.js"></script>
    <script src="../../../app-assets/fonts/LivIconsEvo/js/LivIconsEvo.defaults.js"></script>
    <script src="../../../app-assets/fonts/LivIconsEvo/js/LivIconsEvo.min.js"></script>
    <script src="../../../app-assets/js/scripts/configs/vertical-menu-dark.js"></script>
    <script src="../../../app-assets/js/core/app-menu.js"></script>
    <script src="../../../app-assets/js/core/app.js"></script>
    <script src="../../../app-assets/js/scripts/components.js"></script>
    <script src="../../../app-assets/js/scripts/footer.js"></script>
    
</body>
</html>