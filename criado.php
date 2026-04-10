<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio967425($input)
    {
        ?>
    
<?php 
//error_reporting(0);
session_start();
include('AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
mysqli_set_charset($conn, "utf8mb4");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
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
//anti sql injection na $_GET['token']
$_GET['token'] = anti_sql($_GET['token']);

$sql = "SELECT * FROM accounts WHERE tokenvenda = '$_GET[token]'";
$result = $conn->query($sql);
if ($result->num_rows > 0){
  while ($row = $result->fetch_assoc()) {
    $accesstoken = $row['accesstoken'];
    $acesstokenpaghiper = $row['acesstokenpaghiper'];
    $iduser = $row['id'];
  }
}

$sql = "SELECT * FROM configs WHERE id = '1'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$applink = $row['cortextcard'];
$nomepainel = $row['nomepainel'];
$icon = $row['icon'];
$csspersonali = $row["corfundologo"];

$sucess_servers = isset($_GET['sucess']) ? explode(", ", $_GET['sucess']) : array();
$failed_servers = isset($_GET['failed']) ? explode(", ", $_GET['failed']) : array();
#se for vazio nao mostra o erro

$dominio = $_SERVER['HTTP_HOST'];
date_default_timezone_set('America/Sao_Paulo');
$validade = $_SESSION['validadefin'];
?>
<head>

    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title><?php echo $nomepainel; ?> - Criado</title>
    <link rel="apple-touch-icon" href="<?php echo $icon; ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $icon; ?>">
    <link href="https://fonts.googleapis.com/css?family=Rubik:300,400,500,600%7CIBM+Plex+Sans:300,400,500,600,700" rel="stylesheet">

    <!-- BEGIN: Vendor CSS-->
    <link rel="stylesheet" type="text/css" href="../../../app-assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/vendors/css/charts/apexcharts.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/vendors/css/extensions/dragula.min.css">
    <!-- END: Vendor CSS-->

    <!-- BEGIN: Theme CSS-->
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/bootstrap.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/bootstrap-extended.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/colors.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/components.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/themes/dark-layout.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/themes/semi-dark-layout.css">
    <!-- END: Theme CSS-->

    <!-- BEGIN: Page CSS-->
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/core/menu/menu-types/vertical-menu.css">
    <link rel="stylesheet" type="text/css" href="../../../app-assets/css/pages/dashboard-analytics.css">
    <!-- END: Page CSS-->

    <!-- BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="../../../AegisCore-assets/css/style.css">
    <!-- END: Custom CSS-->

</head>

<style>
        <?php echo $csspersonali; ?>
    </style>     
<body class="vertical-layout vertical-menu-modern dark-layout 2-columns  navbar-sticky footer-static  " data-open="click" data-menu="vertical-menu-modern" data-col="2-columns" data-layout="dark-layout">

</body>

    <script src="../../../app-assets/vendors/js/vendors.min.js"></script>
    <script src="../../../app-assets/fonts/LivIconsEvo/js/LivIconsEvo.tools.js"></script>
    <script src="../../../app-assets/fonts/LivIconsEvo/js/LivIconsEvo.defaults.js"></script>
    <script src="../../../app-assets/fonts/LivIconsEvo/js/LivIconsEvo.min.js"></script>
    <script src="../../../app-assets/vendors/js/forms/spinner/jquery.bootstrap-touchspin.js"></script>
    <script src="../../../app-assets/js/scripts/configs/vertical-menu-dark.js"></script>
    <script src="../../../app-assets/js/core/app-menu.js"></script>
    <script src="../../../app-assets/js/core/app.js"></script>
    <script src="../../../app-assets/js/scripts/components.js"></script>
    <script src="../../../app-assets/js/scripts/footer.js"></script>
    <script src="../../../app-assets/js/scripts/forms/number-input.js"></script>
    <script src="../../../app-assets/vendors/js/tables/datatable/datatables.min.js"></script>
    <script src="../../../app-assets/vendors/js/tables/datatable/dataTables.bootstrap4.min.js"></script>
    <script src="../../../app-assets/vendors/js/tables/datatable/dataTables.buttons.min.js"></script>
    <script src="../../../app-assets/vendors/js/tables/datatable/buttons.html5.min.js"></script>
    <script src="../../../app-assets/vendors/js/tables/datatable/buttons.print.min.js"></script>
    <script src="../../../app-assets/vendors/js/tables/datatable/buttons.bootstrap.min.js"></script>
    <script src="../../../app-assets/vendors/js/tables/datatable/pdfmake.min.js"></script>
    <script src="../../../app-assets/vendors/js/tables/datatable/vfs_fonts.js"></script>
 
</body>
</html>
 <!--scrolling content Modal -->
 <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
<div class="col-md-6 col-12">
<script>
    $(document).ready(function(){

        $("#criado").modal('show');
    });
</script>
<?php
                        $dominioserver = 'apiwhats.atlaspainel.com.br';
                        $sqlwhats = "SELECT * FROM whatsapp WHERE byid = '$iduser'";
                        $resultwhats = mysqli_query($conn, $sqlwhats);
                        $rowwhats = mysqli_fetch_assoc($resultwhats);
                        $tokenwpp = $rowwhats['token'];
                        $sessaowpp = $rowwhats['sessao'];
                        if ($tokenwpp != '' || $sessaowpp != '') {
                            $mensagens = "SELECT * FROM mensagens WHERE ativo = 'ativada' AND funcao = 'criarteste' AND byid = '$iduser'";
                            $resultmensagens = mysqli_query($conn, $mensagens);
                            $rowmensagens = mysqli_fetch_assoc($resultmensagens);
                            $mensagem = $rowmensagens['mensagem'];
                            $funcao = $rowmensagens['funcao'];
                            //remove os <br> da mensagem para enviar no whatsapp
                            if (!empty($mensagem)) {
                            $mensagem = strip_tags($mensagem);
                            $mensagem = str_replace("<br>", "\n", $mensagem);
                            $mensagem = str_replace("<br><br>", "\n", $mensagem);
                            //se a mensagem nao tiver vazia
                            $numerowpp = $_SESSION['whatsappfin'];
                            $numerowpp = str_replace("+", "", $numerowpp);
                            $dominio = $_SERVER['HTTP_HOST'];
                            $mensagem = str_replace("{login}", $_SESSION['usuariofin'], $mensagem);
                            $mensagem = str_replace("{usuario}", $_SESSION['usuariofin'], $mensagem);
                            $mensagem = str_replace("{senha}", $_SESSION['senhafin'], $mensagem);
                            $mensagem = str_replace("{validade}", $_SESSION['validadefin'], $mensagem);
                            $mensagem = str_replace("{limite}", $_SESSION['limitefin'], $mensagem);
                            $mensagem = str_replace("{dominio}", $dominio, $mensagem);
                            $mensagem = addslashes($mensagem);
                            $mensagem = json_encode($mensagem);
                            $mensagem = str_replace('"', '', $mensagem);
                            echo "<script>
                            
                                var enviado = false;
                                var phoneNumber = '{$numerowpp}';
                                const message = '{$mensagem}';
                            
                                const data = {
                                number: phoneNumber,
                                textMessage: {
                                    text: message
                                },
                                options: {
                                    delay: 0,
                                    presence: 'composing'
                                }
                                };        
                                const urlsend = 'https://{$dominioserver}/message/sendText/$sessaowpp';
                                const headerssend = {
                                accept: '*/*',
                                Authorization: 'Bearer {$tokenwpp}',
                                'Content-Type': 'application/json'
                                };
                            
                                const enviar = () => {
                                if (!enviado) { // Verifica se a mensagem ainda não foi enviada
                                    enviado = true; // Define a variável como true para evitar novo envio
                            
                                    $.ajax({
                                    url: urlsend,
                                    type: 'POST',
                                    data: JSON.stringify(data),
                                    headers: headerssend,
                                    success: function(response) {
                                        console.log(response);
                                        if (response.status == 'success') {
                                        // Exiba uma mensagem de sucesso ou faça qualquer outra ação necessária
                                        } else {
                                        // Trate o erro de envio da mensagem
                                        }
                                    },
                                    error: function(error) {
                                        console.error('Erro ao enviar mensagem:', error);
                                    }
                                    });
                                }
                                };
                                
                                enviar();
                            </script>";
                        
                            }

                        }
                        
                        ?>
 <div class="modal fade" id="criado" tabindex="-1" role="dialog" aria-labelledby="exampleModalScrollableTitle" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-scrollable" role="document">
                                                <div class="modal-content">
                                                    
                                                <script>
                      function copyDivToClipboard() {
                        var range = document.createRange();
                        range.selectNode(document.getElementById("divToCopy"));
                        window.getSelection().removeAllRanges(); // clear current selection
                        window.getSelection().addRange(range); // to select text
                        document.execCommand("copy");
                        window.getSelection().removeAllRanges();// to deselect
                        //alert
                        swal("Copiado!", "", "success");

                      }
                    </script>
                                                    <div class="bg-alert modal-header">
                                                        <h5 class="modal-title" id="exampleModalScrollableTitle"></h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <i class="bx bx-x"></i>
                                                        </button>
                                                    </div>
                                                    <style>
                                                        p {
                                                            margin-bottom: 8px;
                                                            }
                                                    </style>
                                                    <div class="modal-body" id="divToCopy">
                                                    <div class="alert alert-alert" role="alert" style="text-align: center; font-size: 18px;">
                                                       <div class="divider divider-success">
                                                            
                                                        <strong class="divider-text" style="font-size: 20px;">🎉 Teste Criado 🎉</strong>
                                                        </div>
                                                        <p>🔎 Usuario: <?php echo $_SESSION['usuariofin']; ?></p>
                                                        <p>🔑 Senha: <?php echo $_SESSION['senhafin']; ?></p>
                                                        <p>🎯 Validade: <?php echo $validade; ?> Minutos</p>
                                                        <p>🕟 Limite: <?php echo $_SESSION['limitefin']; ?></p>
                                                        <?php
                                                        echo '<p>'.$applink.'</p>';
                                                        if ($accesstoken == ''){
                                                        }else{
                                                          echo '
                                                          <p>🌍Link de Renovação: https://'.$dominio.'/renovar.php</p>
                                                          <p>Esse link 👆 servirá para você fazer as suas renovações</p>
                                                          ';
                                                        }
                                                        ?>
                                                        <div class="divider divider-success">
                                                            <p><strong class="divider-text" style="font-size: 20px;"></strong></p>
                                                        </div>
                                                        
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="modal-footer">
                                                    <div class="btn-group dropup mr-1 mb-1">
                                                        <style>
                                                            button {
                                                                /* espaço entre os botoes */
                                                                margin-right: 5px;
                                                                }
                                                        </style>
                                        <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            Copiar
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" onclick="copyDivToClipboard()">Copiar</a>
                                            <a class="dropdown-item" onclick="copyusuarioesenha()">Copiar Usuario e Senha</a>
                                        </div>
                                        <button type="button" class="btn btn-light-secondary" data-dismiss="modal" id="btnRenovar">
    <i class="bx bx-x d-block d-sm-none"></i>
    <span class="d-none d-sm-block">Renovar 30 Dias</span>
</button>

<script>
    document.getElementById('btnRenovar').addEventListener('click', function() {
        window.location.href = 'renovar.php';
    });
</script>
<script>
    function copyusuarioesenha() {
        //copiar variavel $_SESSION['usuariofin']
        var usuario = "<?php echo $_SESSION['usuariofin']; ?>";
        var senha = "<?php echo $_SESSION['senhafin']; ?>";
        var texto = usuario;
        navigator.clipboard.writeText(texto);
        swal("Copiado!", "", "success");
    }
</script>
                                        
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

                            //se o usuario fechar o modal, ele volta para a lista de usuarios
                            $(document).ready(function() {
                                $("#criado").on('hidden.bs.modal', function() {
                                    window.location.href = "renovar.php";
                                });
                            });

                        </script>

                       
 
                       <script src="../../../app-assets/js/scripts/pages/bootstrap-toast.js"></script>
                       <script src="../app-assets/sweetalert.min.js"></script>
                       <?php
    }
    aleatorio967425($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
