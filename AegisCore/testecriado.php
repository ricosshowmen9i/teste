<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio727584($input)
    {
        ?>
    
<?php 
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
    
}
include_once 'header2.php';
$dominio = $_SERVER['HTTP_HOST'];

$sql = "SELECT * FROM accounts WHERE id = '$_SESSION[iduser]'";
$result = $conn->query($sql);
if ($result->num_rows > 0){
  while ($row = $result->fetch_assoc()) {
    $accesstoken = $row['accesstoken'];
  }
}
$sql = "SELECT * FROM configs WHERE id = '1'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$applink = $row['cortextcard'];

$validade = $_SESSION['validadefin'];

$sucess_servers = isset($_GET['sucess']) ? explode(", ", $_GET['sucess']) : array();
$failed_servers = isset($_GET['failed']) ? explode(", ", $_GET['failed']) : array();
#se for vazio nao mostra o erro
$dominioserver = 'apiwhats.atlaspainel.com.br';
$sqlwhats = "SELECT * FROM whatsapp WHERE byid = '$_SESSION[iduser]'";
$resultwhats = mysqli_query($conn, $sqlwhats);
$rowwhats = mysqli_fetch_assoc($resultwhats);
$tokenwpp = $rowwhats['token'];
$sessaowpp = $rowwhats['sessao'];
$ativewpp = $rowwhats['ativo'];
if ($tokenwpp != '' || $sessaowpp != '') {
    $mensagens = "SELECT * FROM mensagens WHERE ativo = 'ativada' AND funcao = 'criarteste' AND byid = '$_SESSION[iduser]'";
    $resultmensagens = mysqli_query($conn, $mensagens);
    $rowmensagens = mysqli_fetch_assoc($resultmensagens);
    $mensagem = $rowmensagens['mensagem'];
    //remove os <br> da mensagem para enviar no whatsapp
    if (!empty($mensagem)) {
    $mensagem = strip_tags($mensagem);
    $mensagem = str_replace("<br>", "\n", $mensagem);
    $mensagem = str_replace("<br><br>", "\n", $mensagem);
    //se a mensagem nao tiver vazia
    $numerowpp = $_SESSION['whatsapp'];
    $numerowpp = str_replace("+", "", $numerowpp);
    if (!isset($_SESSION['mensagem_enviada'])) {
      $dominio = $_SERVER['HTTP_HOST'];
      $mensagem = str_replace("{login}", $_SESSION['usuariofin'], $mensagem);
      $mensagem = str_replace("{usuario}", $_SESSION['usuariofin'], $mensagem);
      $mensagem = str_replace("{senha}", $_SESSION['senhafin'], $mensagem);
      $mensagem = str_replace("{validade}", $validade, $mensagem);
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
    
      $_SESSION['mensagem_enviada'] = true;
    }

}
}
if (!file_exists('../admin/suspenderrev.php')) {
  exit ("<script>alert('Token Invalido!');</script>");
}else{
  include_once '../admin/suspenderrev.php';
  
}
// 🛡️ Validação de Segurança Reforçada (Proteção contra Bypass)
$secret_salt = "AtlasSecurity_2024_#@!";
$dominio_atual = $_SERVER['HTTP_HOST'];
$token_sessao = isset($_SESSION['token']) ? $_SESSION['token'] : '';
$hash_esperado = hash('sha256', $token_sessao . $secret_salt . $dominio_atual);

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || $_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] !== $hash_esperado || $_SESSION['tokenatual'] != $token_sessao || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) {
        security();
        if ($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] !== $hash_esperado) {
            echo "<script>alert('Erro de Integridade de Segurança!'); window.location.href='../index.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Token Inválido ou Arquivo de Segurança Ausente!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $telegram->sendMessage([
            'chat_id' => '2017803306',
            'text' => "⚠️ BYPASS DETECTADO: O domínio " . $dominio_atual . " tentou burlar a segurança do token!"
        ]);
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}
?>
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
<head>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css" integrity="sha512-9O9Sd6Ia1+A0+KwUO1eUg0Fyb3J6UdTo68joKgY9A20+RzI2HfIQK8pk6FyUdxUGpIq3oUItrW8jYVGf9GYZRg==" crossorigin="anonymous" />
</head>
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
                                                        <?php if (!empty($_SESSION['uuid'])) {
                                                            echo "<p>🔑 UUID V2ray: " . $_SESSION['uuid'] . "</p>";
                                                        }
                                                        ?>
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
                                                    <p style="text-align: center;">✔️ Criado: <?php echo implode(", ", $sucess_servers); ?></p>
                                                    <?php
                                                    if ($failed_servers[0] == ""){
                                                    
                                                    }else{
                                                      echo '
                                                      <p style="text-align: center;">❌ Falha: '.implode(", ", $failed_servers).'</p>
                                                      ';
                                                    }
                                                    ?>
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
                                            <a class="dropdown-item" onclick="shareOnWhatsApp()">Compartilhar no Whatsapp</a>
                                            <a class="dropdown-item" onclick="copytotelegram()">Compartilhar no Telegram</a>
                                        </div>
                                        <button type="button" class="btn btn-light-secondary" data-dismiss="modal">
                                            <i class="bx bx-x d-block d-sm-none"></i>
                                            <span class="d-none d-sm-block">Lista de Usuarios</span>
                                        </button>
                                        
                                    </div>
                                                        <!-- botao de copiar whatsapp  https://cdn.discordapp.com/attachments/968040569769181194/1077873044296585216/whatsapp.png-->
                                                        

<script>
function shareOnWhatsApp() {
  var text = "🎉 Conta Criada com Sucesso! 🎉\n" + 
             "🔎 Usuario: <?php echo $_SESSION['usuariofin']; ?>\n" +
             "🔑 Senha: <?php echo $_SESSION['senhafin']; ?>\n" +
             "🎯 Validade: <?php echo $validade; ?> Minutos\n" +
             "🕟 Limite: <?php echo $_SESSION['limitefin']; ?>\n" +
             <?php
             echo '"'.$applink.'\n" +' ;
               if ($accesstoken == ''){
                echo '" "';
            }else{
              echo '
              "🌍Link de Renovação: https://'.$dominio.'/renovar.php\n" +
                "Esse link servirá para você fazer as suas renovações.\n\n";
                ';
            }
            ?>                                         
             

  var encodedText = encodeURIComponent(text);
  var whatsappUrl = "https://api.whatsapp.com/send?text=" + encodedText;
  
  window.open(whatsappUrl);
}
</script>
<script>
function copytotelegram() {
    /* monoespaçado */
    var text = "🎉 Conta Criada com Sucesso! 🎉\n" +
        "🔎 Usuario: <?php echo $_SESSION['usuariofin']; ?>\n" +
        "🔑 Senha: <?php echo $_SESSION['senhafin']; ?>\n" +
        "🎯 Validade: <?php echo $validade; ?> Minutos\n" +
        "🕟 Limite: <?php echo $_SESSION['limitefin']; ?>\n" +
        <?php
        echo '"'.$applink.'\n" +' ;
               if ($accesstoken == ''){
                echo '" "';
            }else{
              echo '
              "🌍Link de Renovação: https://'.$dominio.'/renovar.php\n" +
                "Esse link servirá para você fazer as suas renovações.\n\n";
                ';
            }
            ?>   

    var encodedText = encodeURIComponent(text);
    var telegramUrl = "https://t.me/share/url?url=" + encodedText;

    window.open(telegramUrl);
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
                        <script>
                            //mostra toast
                            $(document).ready(function() {
                                $("#toast-toggler").click();
                            });

                            //se o usuario fechar o modal, ele volta para a lista de usuarios
                            $(document).ready(function() {
                                $("#criado").on('hidden.bs.modal', function() {
                                    window.location.href = "listarusuarios.php";
                                });
                            });

                        </script>

                       
 
                       <script src="../../../app-assets/js/scripts/pages/bootstrap-toast.js"></script>
                       <script src="../app-assets/sweetalert.min.js"></script>
                       <?php
    }
    aleatorio727584($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
