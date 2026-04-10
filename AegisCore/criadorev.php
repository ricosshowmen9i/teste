<?php
session_start();
include('conexao.php');
include('header2.php');

$dominio = $_SERVER['HTTP_HOST'];

$sql = "SELECT * FROM accounts WHERE id = '$_SESSION[iduser]'";
$result = $conn->query($sql);
if ($result->num_rows > 0){
  while ($row = $result->fetch_assoc()) {
    $accesstoken = $row['accesstoken'];
  }
}

$validade = $_SESSION['validaderevenda_criado'];

$sucess_servers = isset($_GET['sucess']) ? explode(", ", $_GET['sucess']) : array();
$failed_servers = isset($_GET['failed']) ? explode(", ", $_GET['failed']) : array();

// Envio WhatsApp
$dominioserver = 'apiwhats.atlaspainel.com.br';
$sqlwhats = "SELECT * FROM whatsapp WHERE byid = '$_SESSION[iduser]'";
$resultwhats = mysqli_query($conn, $sqlwhats);
$rowwhats = mysqli_fetch_assoc($resultwhats);
$tokenwpp = $rowwhats['token'];
$sessaowpp = $rowwhats['sessao'];
$ativewpp = $rowwhats['ativo'];

if ($tokenwpp != '' || $sessaowpp != '') {
    $mensagens = "SELECT * FROM mensagens WHERE ativo = 'ativada' AND funcao = 'criarrevenda' AND byid = '$_SESSION[iduser]'";
    $resultmensagens = mysqli_query($conn, $mensagens);
    $rowmensagens = mysqli_fetch_assoc($resultmensagens);
    $mensagem = $rowmensagens['mensagem'];
    if (!empty($mensagem)) {
        $mensagem = strip_tags($mensagem);
        $mensagem = str_replace("<br>", "\n", $mensagem);
        $mensagem = str_replace("<br><br>", "\n", $mensagem);
        $numerowpp = $_SESSION['whatsapp_criado'];
        $numerowpp = str_replace("+", "", $numerowpp);
        if (!isset($_SESSION['mensagem_revenda_enviada'])) {
            $dominio = $_SERVER['HTTP_HOST'];
            $mensagem = str_replace("{login}", $_SESSION['usuariorevenda_criado'], $mensagem);
            $mensagem = str_replace("{usuario}", $_SESSION['usuariorevenda_criado'], $mensagem);
            $mensagem = str_replace("{senha}", $_SESSION['senharevenda_criado'], $mensagem);
            $mensagem = str_replace("{validade}", $_SESSION['validaderevenda_criado'], $mensagem);
            $mensagem = str_replace("{limite}", $_SESSION['limiterevenda_criado'], $mensagem);
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
                  if (!enviado) {
                    enviado = true;
                    $.ajax({
                      url: urlsend,
                      type: 'POST',
                      data: JSON.stringify(data),
                      headers: headerssend,
                      success: function(response) {
                        console.log(response);
                      },
                      error: function(error) {
                        console.error('Erro ao enviar mensagem:', error);
                      }
                    });
                  }
                };
                enviar();
              </script>";
            
            $_SESSION['mensagem_revenda_enviada'] = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revendedor Criado</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }
        }
        
        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            border-radius: 20px !important;
        }
        
        .modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.1) !important;
            padding: 15px 20px !important;
        }
        
        .modal-header .modal-title {
            color: white !important;
            font-size: 18px !important;
        }
        
        .modal-header .close {
            color: white !important;
            opacity: 0.8 !important;
        }
        
        .modal-body {
            padding: 20px !important;
            color: white !important;
        }
        
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1) !important;
            padding: 15px 20px !important;
        }
        
        .divider-success {
            border-top: 1px solid rgba(255,255,255,0.1) !important;
            margin: 15px 0 !important;
        }
        
        .divider-text {
            background: linear-gradient(135deg, #4158D0, #C850C0) !important;
            padding: 5px 15px !important;
            border-radius: 20px !important;
            font-size: 18px !important;
            display: inline-block !important;
        }
        
        .alert-alert p {
            margin-bottom: 10px !important;
            font-size: 14px !important;
        }
        
        .btn-light-secondary {
            background: rgba(255,255,255,0.1) !important;
            color: white !important;
            border: none !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            cursor: pointer !important;
        }
        
        .btn-light-secondary:hover {
            background: rgba(255,255,255,0.2) !important;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #C850C0) !important;
            color: white !important;
            border: none !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            cursor: pointer !important;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5);
        }
        
        .dropdown-menu {
            background: #1e293b !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
        }
        
        .dropdown-item {
            color: white !important;
        }
        
        .dropdown-item:hover {
            background: rgba(255,255,255,0.1) !important;
        }
        
        p {
            margin-bottom: 8px;
        }
        
        a {
            color: #10b981 !important;
            text-decoration: none !important;
        }
        
        a:hover {
            text-decoration: underline !important;
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            <div class="col-md-6 col-12">
                <script>
                    $(document).ready(function(){
                        $("#criado").modal('show');
                    });
                </script>
                <div class="modal fade" id="criado" tabindex="-1" role="dialog" aria-labelledby="exampleModalScrollableTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable" role="document">
                        <div class="modal-content">
                            <script>
                                function copyDivToClipboard() {
                                    var range = document.createRange();
                                    range.selectNode(document.getElementById("divToCopy"));
                                    window.getSelection().removeAllRanges();
                                    window.getSelection().addRange(range);
                                    document.execCommand("copy");
                                    window.getSelection().removeAllRanges();
                                    swal("Copiado!", "", "success");
                                }
                            </script>
                            <div class="bg-alert modal-header">
                                <h5 class="modal-title" id="exampleModalScrollableTitle"></h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <i class="bx bx-x"></i>
                                </button>
                            </div>
                            <div class="modal-body" id="divToCopy">
                                <div class="alert alert-alert" role="alert" style="text-align: center; font-size: 18px;">
                                    <div class="divider divider-success">
                                        <strong class="divider-text" style="font-size: 20px;">🎉 Revendedor Criado 🎉</strong>
                                    </div>
                                    <p>🔎 Usuario: <?php echo $_SESSION['usuariorevenda_criado']; ?></p>
                                    <p>🔑 Senha: <?php echo $_SESSION['senharevenda_criado']; ?></p>
                                    <p>🎯 Validade: <?php echo $_SESSION['validaderevenda_criado']; ?></p>
                                    <p>🕟 Limite: <?php echo $_SESSION['limiterevenda_criado']; ?></p>
                                    <p>💥 Obrigado por usar nossos serviços!</p>
                                    <?php
                                    $dominio = $_SERVER['HTTP_HOST'];
                                    echo "<p>🔗 Link do Painel: <a href='https://$dominio/'>https://$dominio/</a></p>";
                                    ?>
                                    <div class="divider divider-success">
                                        <p><strong class="divider-text" style="font-size: 20px;"></strong></p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="btn-group dropup mr-1 mb-1">
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
                                        <span class="d-none d-sm-block">Lista de Revendedores</span>
                                    </button>
                                </div>
                                <script>
                                    function shareOnWhatsApp() {
                                        var text = "🎉 Revendedor Criado! 🎉\n" + 
                                                   "🔎 Usuario: <?php echo $_SESSION['usuariorevenda_criado']; ?>\n" +
                                                   "🔑 Senha: <?php echo $_SESSION['senharevenda_criado']; ?>\n" +
                                                   "🎯 Validade: <?php echo $_SESSION['validaderevenda_criado']; ?>\n" +
                                                   "🕟 Limite: <?php echo $_SESSION['limiterevenda_criado']; ?>\n" +
                                                   "🔗 Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>\n\n" +
                                                   "💥 Obrigado por usar nossos serviços!";
                                        var encodedText = encodeURIComponent(text);
                                        var whatsappUrl = "https://api.whatsapp.com/send?text=" + encodedText;
                                        window.open(whatsappUrl);
                                    }
                                    
                                    function copytotelegram() {
                                        var text = "🎉 Revendedor Criado! 🎉\n" + 
                                                   "🔎 Usuario: <?php echo $_SESSION['usuariorevenda_criado']; ?>\n" +
                                                   "🔑 Senha: <?php echo $_SESSION['senharevenda_criado']; ?>\n" +
                                                   "🎯 Validade: <?php echo $_SESSION['validaderevenda_criado']; ?>\n" +
                                                   "🕟 Limite: <?php echo $_SESSION['limiterevenda_criado']; ?>\n" +
                                                   "🔗 Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>\n\n" +
                                                   "💥 Obrigado por usar nossos serviços!";
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
    
    <script>
        $(document).ready(function() {
            $("#criado").on('hidden.bs.modal', function() {
                window.location.href = "listarrevendas.php";
            });
        });
    </script>
    <script src="../app-assets/sweetalert.min.js"></script>
</body>
</html>
<?php
// Limpar dados da sessão
unset($_SESSION['usuariorevenda_criado']);
unset($_SESSION['senharevenda_criado']);
unset($_SESSION['limiterevenda_criado']);
unset($_SESSION['validaderevenda_criado']);
unset($_SESSION['whatsapp_criado']);
?>h2_tema ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            <div class="col-md-6 col-12">
                <script>
                    $(document).ready(function(){
                        $("#criado").modal('show');
                    });
                </script>
                <div class="modal fade" id="criado" tabindex="-1" role="dialog" aria-labelledby="exampleModalScrollableTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable" role="document">
                        <div class="modal-content">
                            <script>
                                function copyDivToClipboard() {
                                    var range = document.createRange();
                                    range.selectNode(document.getElementById("divToCopy"));
                                    window.getSelection().removeAllRanges();
                                    window.getSelection().addRange(range);
                                    document.execCommand("copy");
                                    window.getSelection().removeAllRanges();
                                    swal("Copiado!", "", "success");
                                }
                            </script>
                            <div class="bg-alert modal-header">
                                <h5 class="modal-title" id="exampleModalScrollableTitle"></h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <i class="bx bx-x"></i>
                                </button>
                            </div>
                            <div class="modal-body" id="divToCopy">
                                <div class="alert alert-alert" role="alert" style="text-align: center; font-size: 18px;">
                                    <div class="divider divider-success">
                                        <strong class="divider-text" style="font-size: 20px;">🎉 Revendedor Criado 🎉</strong>
                                    </div>
                                    <p>🔎 Usuario: <?php echo $_SESSION['usuariorevenda_criado']; ?></p>
                                    <p>🔑 Senha: <?php echo $_SESSION['senharevenda_criado']; ?></p>
                                    <p>🎯 Validade: <?php echo $_SESSION['validaderevenda_criado']; ?></p>
                                    <p>🕟 Limite: <?php echo $_SESSION['limiterevenda_criado']; ?></p>
                                    <p>💥 Obrigado por usar nossos serviços!</p>
                                    <?php
                                    $dominio = $_SERVER['HTTP_HOST'];
                                    echo "<p>🔗 Link do Painel: <a href='https://$dominio/'>https://$dominio/</a></p>";
                                    ?>
                                    <div class="divider divider-success">
                                        <p><strong class="divider-text" style="font-size: 20px;"></strong></p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="btn-group dropup mr-1 mb-1">
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
                                        <span class="d-none d-sm-block">Lista de Revendedores</span>
                                    </button>
                                </div>
                                <script>
                                    function shareOnWhatsApp() {
                                        var text = "🎉 Revendedor Criado! 🎉\n" + 
                                                   "🔎 Usuario: <?php echo $_SESSION['usuariorevenda_criado']; ?>\n" +
                                                   "🔑 Senha: <?php echo $_SESSION['senharevenda_criado']; ?>\n" +
                                                   "🎯 Validade: <?php echo $_SESSION['validaderevenda_criado']; ?>\n" +
                                                   "🕟 Limite: <?php echo $_SESSION['limiterevenda_criado']; ?>\n" +
                                                   "🔗 Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>\n\n" +
                                                   "💥 Obrigado por usar nossos serviços!";
                                        var encodedText = encodeURIComponent(text);
                                        var whatsappUrl = "https://api.whatsapp.com/send?text=" + encodedText;
                                        window.open(whatsappUrl);
                                    }
                                    
                                    function copytotelegram() {
                                        var text = "🎉 Revendedor Criado! 🎉\n" + 
                                                   "🔎 Usuario: <?php echo $_SESSION['usuariorevenda_criado']; ?>\n" +
                                                   "🔑 Senha: <?php echo $_SESSION['senharevenda_criado']; ?>\n" +
                                                   "🎯 Validade: <?php echo $_SESSION['validaderevenda_criado']; ?>\n" +
                                                   "🕟 Limite: <?php echo $_SESSION['limiterevenda_criado']; ?>\n" +
                                                   "🔗 Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>\n\n" +
                                                   "💥 Obrigado por usar nossos serviços!";
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
    
    <script>
        $(document).ready(function() {
            $("#criado").on('hidden.bs.modal', function() {
                window.location.href = "listarrevendas.php";
            });
        });
    </script>
    <script src="../app-assets/sweetalert.min.js"></script>
</body>
</html>
<?php
// Limpar dados da sessão
unset($_SESSION['usuariorevenda_criado']);
unset($_SESSION['senharevenda_criado']);
unset($_SESSION['limiterevenda_criado']);
unset($_SESSION['validaderevenda_criado']);
unset($_SESSION['whatsapp_criado']);
?>

