<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio793558($input)
    {
        ?>
    

<?php
              error_reporting(0);
session_start();
include_once("../AegisCore/conexao.php");
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if(! $conn ) {

  echo $conn->connect_error;
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
?>
<!DOCTYPE html>
<html lang="pt-br">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<!DOCTYPE html>
<html class="loading" lang="pt-br" data-textdirection="ltr">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="author" content="Thomas">
    <title>Aprovado</title>
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
              <p>Valor Pago: <?= $_SESSION['valor'] ?> R$</p>
              <img style="width: 150px;" src="https://cdn.discordapp.com/attachments/942800753309921290/1160108187719049216/Sem_titulo-removebg-preview.png?ex=65e2cd71&is=65d05871&hm=1cd5ba3a739ea5323b04eaf3744772714ee6d7a8198a150858a13102c3d3999a&" alt="aprovado">
              <hr>
              <p>Seu pedido foi aprovado.</p>
              </center>
              
              <ul class="list-unstyled pt-2 pb-1">
              </ul> </div>
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
</body>
<?php 
  session_destroy();
  ?>
</html>
                       <?php
    }
    aleatorio793558($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
