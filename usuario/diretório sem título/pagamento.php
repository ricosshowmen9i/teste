<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio926520($input)
    {
        ?>
    

<?php
error_reporting(0);
session_start();
ignore_user_abort(true);
set_time_limit(0);
include_once("../AegisCore/conexao.php");
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
          include ('Net/SSH2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if(! $conn ) {

  echo $conn->connect_error;
        }

$sql = "SELECT * FROM accounts WHERE id = '$_SESSION[byid]'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $_SESSION['tokenaccess'] = $row['accesstoken'];
        $_SESSION['valorusuario'] = $row['valorusuario'];
        $_SESSION['tokenaccess'] = $row['accesstoken'];
$_SESSION['formadepag'] = $row['formadepag'];
$_SESSION['email'] = $row['contato'];
$_SESSION['nome'] = $row['nome'];
$_SESSION['acesstokenpaghiper'] = $row['acesstokenpaghiper'];
$_SESSION['tokenpaghiper'] = $row['tokenpaghiper'];
    }
}

//time zzone
date_default_timezone_set('America/Sao_Paulo');
$datahoje = date('Y-m-d H:i:s');
if ($_SESSION['expira'] < $datahoje) {
    $_SESSION['expira'] = $datahoje;
}

$_SESSION['vencimento'] = date('d/m/Y', strtotime($_SESSION['expira']));
//mais 30 dais
$data = date('Y-m-d H:i:s', strtotime('+31 days', strtotime($_SESSION['expira'])));

$login = $_SESSION['login'];
$limites = $_SESSION['limite'];

$novadata = $data;
$novadata = date('Y-m-d H:i:s', strtotime($novadata));
$data = date('Y-m-d');

$diferenca = strtotime($novadata) - strtotime($data);
$dias = floor($diferenca / (60 * 60 * 24));
$diasrestante = $dias;
$sql = "SELECT * FROM configs";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $nomepainel = $row['nomepainel'];
        $logopainel = $row['logo'];
        $logopainelmini = $row['icon'];

    }
    if ($_SESSION['formadepag'] == 1) {
        $expiracaopix = $_SESSION['expiracaopix'];
        echo "<script>
        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('$expiracaopix');
            var diferenca = expira - agora;
            var minutos = Math.floor((diferenca / 1000) / 60);
            var segundos = Math.floor((diferenca / 1000) % 60);
    
            if (diferenca > 0) {
                document.getElementById('tempo-restante').innerHTML = 'Tempo restante : ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = 'Tempo expirado';
            }
        }
    
        setInterval(atualizarTempoRestante, 1000);
    </script>";
    }
    ?>




<!DOCTYPE html>
<html lang="pt-br">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?php
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

     $expiracaopix = $_SESSION['expiracaopix'];
    ?>
<!DOCTYPE html>
<html class="loading" lang="pt-br" data-textdirection="ltr">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="author" content="Thomas">
    <title><?php echo $nomepainel; ?> - Renovação</title>
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
    <script src="../app-assets/sweetalert.min.js"></script>

</head>
<style>
        <?php echo $csspersonali; ?>
    </style>     

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
                                                <h4 class="text-center mb-2">N° Pedido: <?= $_SESSION['payment_id']?></h4>
                                            </div>
                                        </div>
                                        <div class="card-content">
                                            <div class="card-body">
                                            <div class="col-xl mb-lg-0 lg-4">
          <div class="card border border-2 border-primary">
            <div class="card-body">
              <div class="d-flex justify-content-between flex-wrap mb-3">
              </div>

              <div class="text-center position-relative mb-4 pb-1">
                <div class="mb-2 d-flex">
                  </div>
                  <h1 class="price-toggle text-primary price-yearly mb-0" style="text-align: center;">INFORMAÇÕES</h1>
              </div>
              <center>
              <p>Valor a Pagar: <?= $_SESSION['valor'] ?> R$</p>
              <img style="width: 160px;" class="qr_code" src="data:image/png;base64,<?= $_SESSION['qr_code_base64']?>">
              </center>
              
              <ul class="list-unstyled pt-2 pb-1">
              <input type="text" name="texto" id="texto" class="form-control" value="<?= $_SESSION['qr_code']?>">
              <hr>
              <div id="tempo-restante" style="text-align: center; font-size: 18px;"></div>
                <center>
                
                </center>        
               
              </ul>
              
              <button type="submit" class="btn btn-primary d-grid w-100" value="Copiar Código" onclick="copiarTexto()">Copiar Codigo</button>
            </div>
          </div>
        </div>

                                                <hr>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <script src="../../../app-assets/vendors/js/vendors.min.js"></script>
    <script src="../../../app-assets/fonts/LivIconsEvo/js/LivIconsEvo.tools.js"></script>
    <script src="../../../app-assets/fonts/LivIconsEvo/js/LivIconsEvo.defaults.js"></script>
    <script src="../../../app-assets/fonts/LivIconsEvo/js/LivIconsEvo.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../app-assets/js/scripts/configs/vertical-menu-dark.js"></script>
    <script src="../../../app-assets/js/core/app-menu.js"></script>
    <script src="../../../app-assets/js/core/app.js"></script>
    <script src="../../../app-assets/js/scripts/components.js"></script>
    <script src="../../../app-assets/js/scripts/footer.js"></script>
    <script>
                        function copiarTexto() {
                            let textoCopiado = document.getElementById("texto");
                            textoCopiado.select();
                            textoCopiado.setSelectionRange(0, 99999)
                            document.execCommand("copy");
                            alert("Copiado com Sucesso!");
                        }

                        //checar se o verify.php retornou Aprovado
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script type="text/javascript">

//Calling function
repeatAjax();


function repeatAjax(){
jQuery.ajax({
          type: "POST",
          url: 'verifica.php',
          dataType: 'text',
          success: function(resp) {
          	if(resp == 'Aprovado')
          	{
              $(".qr_code").attr('src','https://www.pngplay.com/wp-content/uploads/2/Approved-PNG-Photos.png');
          	  window.location.replace("aprovado.php");

                    jQuery('.teste').html(resp);
                    }

          },
          complete: function() {
                setTimeout(repeatAjax,1000); //After completion of request, time to redo it after a second
             }
        });
}
</script>

</body>
</html>
                       <?php
    }
    aleatorio926520($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
