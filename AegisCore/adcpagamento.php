<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio741420($input)
    {
        ?>
    
<?php 

error_reporting(0);
session_start();
include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
    }
$login = $_SESSION['login'];
require_once '../vendor/pix/autoload.php';
//se existir o qr code
/* if ($_SESSION['qr_code_base64'] != null) {
    echo ("<script>window.location = 'pagamento.php';</script>");
} */
if (!file_exists('../admin/suspenderrev.php')) {
    exit ("<script>alert('Token Invalido!');</script>");
  }else{
    include_once '../admin/suspenderrev.php';
    
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
?>

<?php

set_time_limit(0); // Limite de tempo de execução: 2h. Deixe 0 (zero) para sem limite
ignore_user_abort(true); // Continua a execução mesmo que o usuário cancele o download

     
     include('conexao.php');
     $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
     if (!$conn) {
         die("Connection failed: " . mysqli_connect_error());
         
     }
     //se nao existir o qr code
     /* if ($_SESSION['qr_code_base64'] == null) {
         echo ("<script>window.location = 'pagamento.php';</script>");
     } */
     #mostra o tempo restante  $_SESSION['expiracaopix'] em tempo real com ajax
        $expiracaopix = $_SESSION['expiracaopix'];
        ?>
<?php
include('header2.php');
$valor = $_SESSION['valoradd'];
?>
 <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
<div class="col-md-6 col-12">
<script>
    $(document).ready(function(){

        $("#criado").modal('show');
    });
    
</script>
<head>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css" integrity="sha512-9O9Sd6Ia1+A0+KwUO1eUg0Fyb3J6UdTo68joKgY9A20+RzI2HfIQK8pk6FyUdxUGpIq3oUItrW8jYVGf9GYZRg==" crossorigin="anonymous" />
</head>
 <div class="modal fade" id="criado" tabindex="-1" role="dialog" aria-labelledby="exampleModalScrollableTitle" aria-hidden="true">
     <div class="modal-dialog modal-dialog-scrollable" role="document">
         <div class="modal-content">
             <!-- title modal -->
             
             <script>
                 function copyDivToClipboard() {
                     let textoCopiado = document.getElementById("qrcode");
                            textoCopiado.select();
                            textoCopiado.setSelectionRange(0, 99999)
                            document.execCommand("copy");
                            alert("Copiado com Sucesso!");
                     
                    }
                    </script>
                    <script>
    function atualizarTempoRestante() {
        var agora = new Date();
        var expira = new Date('<?php echo $expiracaopix ?>');
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
</script>
                                                    <div class="bg-alert modal-header">
                                                        <h5 style="text-align: center;">N° Pedido: <?= $_SESSION['payment_id']?></h5>
                                                        <h5 class="modal-title" id="exampleModalScrollableTitle"></h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <i class="bx bx-x"></i>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body" id="divToCopy">
                                                    <div class="alert alert-alert" role="alert" style="text-align: center; font-size: 18px;">
                                                       <div class="divider divider-success">
                                                            
                                                        <strong class="divider-text" style="font-size: 20px;">INFORMAÇÕES</strong>
                                                        </div>
                                                        <p>Valor a Pagar: <?= $valor ?> R$</p>
                                                        <p>Após Efetuar o Pagamento Aguarde o Pagamento ser Concluido</p>
                                                        

                                                        <img style="width: 160px;" class="qr_code" src="data:image/png;base64,<?= $_SESSION['qr_code_base64']?>">
                                                        <hr>
                                                        <input type="text" name="texto" id="qrcode" class="form-control" value="<?= $_SESSION['qr_code']?>">
                                                        <br>
                                                        <div id="tempo-restante" style="text-align: center; font-size: 18px;"></div>
                                                    </div>
                                                    <button type="button" class="btn btn-primary" onclick="copyDivToClipboard()">Copiar</button>
                                                    <button type="button" class="btn btn-primary" onclick="window.location.href='pagamento.php'">Voltar</button>    
                                                    </div>
                                                    <p style="text-align: center;"><?php echo implode(", ", $sucess_servers); ?></p>
                                                    <div class="modal-footer">
                                                    <div class="btn-group dropup mr-1 mb-1">
                                    </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            //mostra toast
                            $(document).ready(function() {
                                $("#toast-toggler").click();
                            });

                        </script>

                       
 
                       <script src="../../../app-assets/js/scripts/pages/bootstrap-toast.js"></script>
                       <script src="../app-assets/sweetalert.min.js"></script>


    <!-- End custom js for this page -->
  </body>
</html>
<script>
    /* ao clicar fora do modal  */
    $(document).on('click', function(e) {
        if ($(e.target).is('#criado')) {
            window.location.href = "pagamento.php";    
        }
    }); 
</script>






</body>
</html>
   
                       <?php
    }
    aleatorio741420($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
