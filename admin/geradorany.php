<script src="../app-assets/sweetalert.min.js"></script>
<!DOCTYPE html>
<html lang="pt-br">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?php
    error_reporting(0);
    session_start();
    include('../AegisCore/conexao.php');
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    

    

?>
    <!-- plugins:css -->
    <link rel="stylesheet" href="../AegisCore-assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../AegisCore-assets/vendors/css/vendor.bundle.base.css">
    <!-- endinject -->
    <link rel="stylesheet" href="../AegisCore-assets/css/style.css">
    <!-- End layout styles -->
    <link rel="shortcut icon" href="<?php echo $icon; ?>" />
      <link rel="stylesheet" href="../AegisCore/temas_visual.css">
</head>
  <body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
  <?php
  $pasta = "SELECT * FROM configs WHERE id = 1";
  $result = $conn -> query($pasta);
  $row = $result->fetch_assoc();
  $pasta = $row['cornavsuperior'];
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['jsonFile'])) {

      if ($_FILES['jsonFile']['error'] === UPLOAD_ERR_OK) {
          $tempFilePath = $_FILES['jsonFile']['tmp_name'];
          $originalFileName = $_FILES['jsonFile']['name'];
  
          // Especifique o diretório de destino para salvar o arquivo JSON
          $destinationPath = $pasta.'/configs.json';
  
          if (move_uploaded_file($tempFilePath, $destinationPath)) {
              // O arquivo JSON foi importado com sucesso e salvo no diretório especificado
              http_response_code(200);
              echo '<script>sweetAlert("Sucesso!", "Configuração Salva!", "success").then(function () {
                window.location.href = "geradorany.php";
            });</script>';
            //edita a versao para a versao atual mais 1 do json
            $jsonFile = ''.$pasta.'/configs.json';
            $data = json_decode(file_get_contents($jsonFile), true);
            $data['ConfigVersion'] = $_SESSION['ConfigVersion'];
            file_put_contents($jsonFile, json_encode($data));


          } else {
              // Houve um erro ao mover o arquivo para o destino
              http_response_code(500);
          }
      } else {
          // Houve um erro no envio do arquivo
          http_response_code(400);
      }
  }
include('headeradmin2.php');
date_default_timezone_set('America/Sao_Paulo');

if ($_POST['addPayload']) {
    echo '<script>sweetAlert("Sucesso!", "Payload Adicionado!", "success").then(function () {
        window.location.href = "geradorany.php";
    });</script>';
}
elseif ($_POST['add_network']) {
    echo '<script>sweetAlert("Sucesso!", "Configuração Adicionada!", "success").then(function () {
        window.location.href = "geradorany.php";
    });</script>';
}
elseif ($_POST['edit_network']) {
    echo '<script>sweetAlert("Sucesso!", "Configuração Editada!", "success").then(function () {
        window.location.href = "geradorany.php";
    });</script>';
}
elseif ($_POST['delete_network']) {
    echo '<script>sweetAlert("Sucesso!", "Configuração Deletada!", "success").then(function () {
        window.location.href = "geradorany.php";
    });</script>';
}
elseif ($_POST) {
    echo '<script>sweetAlert("Sucesso!", "Configuração Salva!", "success").then(function () {
        window.location.href = "geradorany.php";
    });</script>';
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

$pasta = "SELECT * FROM configs WHERE id = 1";
    $result = $conn -> query($pasta);
    $row = $result->fetch_assoc();
    $pasta = $row['cornavsuperior'];
if ($pasta == null or $pasta == ""){
    //cria uma pasta com o nome aleatorio
    $pasta = rand(0, 9999999999999);
    mkdir("$pasta");
    $sql = "UPDATE configs SET cornavsuperior = '$pasta' WHERE id = 1";
    if ($conn->query($sql) === TRUE) {
        echo '<script>window.location.reload();</script>';
    } else {
        echo "Error updating record: " . $conn->error;
    }
    //se a pasta nao existir
}
if (!file_exists(''.$pasta.'')) {
    //cria a pasta
    mkdir("$pasta");
    //echo '<script>window.location.reload();</script>';
}
if (!file_exists(''.$pasta.'/configs.json')) {
    //baixa o arquivo
    $url = 'https://cdn.discordapp.com/attachments/942800753309921290/1114098973972639804/configs.json';
    $content = file_get_contents($url);
    file_put_contents(''.$pasta.'/configs.json', $content);
    //echo '<script>window.location.reload();</script>';

}
$dominio = $_SERVER['HTTP_HOST'];
$jsonFile = ''.$pasta.'/configs.json';
$data = json_decode(file_get_contents($jsonFile), true);
$_SESSION['ConfigVersion'] = $data['ConfigVersion'];
?>
<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>

<body class="vertical-layout vertical-menu-modern dark-layout 2-columns  navbar-sticky footer-static  " data-open="click" data-menu="vertical-menu-modern" data-col="2-columns" data-layout="dark-layout">
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">
        <section id="basic-datatable">
            <div class="row">
             
                <div class="col-12">
                    <div class="card">
                    
                        <div class="card-header">
                            <h4 class="card-title">Gerador AnyVpn</h4>
                            <!-- description -->
                            <div class="card-content">
                                <div class="card-body card-dashboard">
                                    <p class="card-text">Gerador Para AnyVpn Mod. Version Update <?php echo $data['ConfigVersion']; ?><a href="https://t.me/CarequinhaOFC" target="_blank"> Para Comprar Original Clique Aqui</a></p>
                                </div>
                 
                        </div>
                        
                        <script>


if (window.innerWidth < 678) {

    document.write('<div class="alert alert-warning" role="alert"> <strong>Atenção!</strong> Mova para lado para Fazer Alguma Ação! </div>');
    window.setTimeout(function() {
        $(".alert").fadeTo(500, 0).slideUp(500, function(){
            $(this).remove(); 
        });
    }, 3000);
}

</script>

<div class="card-content">
    
<form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                            <div class="card-body card-dashboard">
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addNetworkModal">
            Adicionar Payload
        </button>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#Configurarapp">
            Configurar App
        </button>
        <input type="text" id="copyText" style="display: none;" value="<?php echo "https://$dominio/admin/$pasta/configs.json"; ?>">
        <button type="button" class="btn btn-primary" onclick="copyToClipboard()">
            Copiar Link Update
        </button>
        <button type="button" class="btn btn-primary" onclick="baixar()">
            Baixar Json
        </button>
        <button type="button" class="btn btn-primary" onclick="importar()">
            Importar Json
        </button>
        <script>
            function copyToClipboard() {
    var copyText = document.getElementById("copyText");

    // Exibe o elemento de input de texto
    copyText.style.display = "block";
    copyText.select();

    // Tenta copiar o texto
    var successful = document.execCommand("copy");

    // Oculta o elemento de input de texto novamente
    copyText.style.display = "none";

    // Exibe uma mensagem de sucesso ou erro
    if (successful) {
        alert("URL copiada com sucesso!");
    } else {
        alert("Falha ao copiar a URL. Por favor, selecione e copie manualmente.");
    }
}

        </script>
        <script>
            function baixar() {
        // Perform the necessary actions to download the JSON file
        // You can use the 'fetch' API or create a hidden link element with a download attribute
        // Example using the fetch API:
        fetch('https://<?php echo $dominio; ?>/admin/<?php echo $pasta; ?>/configs.json')
            .then(function(response) {
                return response.blob();
            })
            .then(function(blob) {
                // Create a temporary link element
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'configs.json';

                // Programmatically click the link to trigger the download
                link.click();

                // Cleanup
                URL.revokeObjectURL(link.href);
            })
            .catch(function(error) {
                console.log('Error downloading JSON:', error);
            });
    }
    function importar() {
        var input = document.createElement('input');
        input.type = 'file';

        input.addEventListener('change', function(event) {
            var file = event.target.files[0];
            var formData = new FormData();
            formData.append('jsonFile', file);

            // Envie o formulário para a mesma página PHP para processar o arquivo JSON
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo $_SERVER['PHP_SELF']; ?>', true); // Envie para a mesma página PHP
            xhr.onload = function() {
                if (xhr.status === 200) {
                    console.log('Arquivo JSON importado com sucesso');
                    sweetAlert("Sucesso!", "Arquivo JSON importado com sucesso!", "success").then(function() {
                        window.location.reload();
                    });
                    
                } else {
                    console.log('Erro ao importar o arquivo JSON');
                }
            };
            xhr.send(formData);
        });

        input.click();
    }
</script>


        



    
        
    
        <a class="btn btn-primary" href="https://cdn.discordapp.com/attachments/942800753309921290/1114039536117362718/ANYVPN_Mod.apk" target="_blank">Baixar Apk</a>
                                <!-- nao mostar o sroll -->
                                <div class="table-responsive" style=" overflow: auto; overflow-y: hidden;">
                                    <table class="table zero-configuration" id="myTable">
                                                <thead>
                                                    <tr>
                                                    <th>Nome</th>
                                                    <th>Ação</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($data['Networks'] as $index => $network) : ?>
                        <tr>
                            <td><?php echo $network['Name']; ?></td>
                            <td>
                                <!-- Botão para editar uma rede -->
                                <button type="button" class="btn btn-primary" onclick="editNetwork(<?php echo $index; ?>)">Editar</button>

                                <!-- Botão para excluir uma rede -->
                                <button type="submit" class="btn btn-danger" name="delete_network" value="<?php echo $index; ?>">Excluir</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

  </tbody>
    </table>
    </form>

<hr>
<div class="modal fade" id="Configurarapp" tabindex="-1" role="dialog" aria-labelledby="ConfigurarappLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ConfigurarappLabel">Configurar App</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="configForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                    <div class="form-group">
                        <label for="Saudacao">Saudação:</label>
                        <input type="text" class="form-control" name="Saudacao" value="<?php echo $data['Saudacao']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="ReleaseNotes">Release Notes:</label>
                        <?php foreach ($data['ReleaseNotes'] as $index => $note) : ?>
                            <input type="text" class="form-control" name="ReleaseNotes[]" value="<?php echo $note; ?>"><br>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-group">
                        <label for="LinkPainel">Link Painel Checkuser:</label>
                        <input type="text" class="form-control" name="LinkPainel" value="<?php echo $data['LinkPainel']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="LinkIcone">Link Ícone: (192x192)</label>
                        <input type="text" class="form-control" name="LinkIcone" value="<?php echo $data['LinkIcone']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="LinkBanner">Link Logo: (900x900)</label>
                        <input type="text" class="form-control" name="LinkBanner" value="<?php echo $data['LinkBanner']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="LinkBackground">Link Background: (800x1200)</label>
                        <input type="text" class="form-control" name="LinkBackground" value="<?php echo $data['LinkBackground']; ?>">
                    </div>
                    <!-- Adicione aqui os outros campos -->

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                        <button type="submit" class="btn btn-primary" name="save_config">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Campos para editar uma rede -->
<!-- Modal para editar uma rede -->
<div class="modal fade" id="editNetworkModal" tabindex="-1" role="dialog" aria-labelledby="editNetworkModalLabel" aria-hidden="true">
<div class="modal-dialog" role="document">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="editNetworkModalLabel">Editar Payload</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <div class="modal-body">
        <!-- Campos para editar uma rede -->
        <form id="editNetworkForm">
            <!-- ...campos de edição... -->
        </form>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-primary" onclick="saveChanges()">Salvar Alterações</button>
    </div>
</div>
</div>
</div>
<div class="modal fade" id="addNetworkModal" tabindex="-1" role="dialog" aria-labelledby="addNetworkModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addNetworkModalLabel">Adicionar Payload</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Campos para adicionar uma nova rede -->
                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <div class="form-group">
                        <label for="new_network_vpn_mod">Modo de Conexão:</label>
                        <select class="form-control" name="new_network[vpnmod]">
                            <option value="0">Proxy</option>
                            <option value="1">Ssl</option>
                            <option value="2">Direct</option>
                            <option value="3">Sslpay</option>
                            <option value="3">V2ray</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="new_network_vpn_tun_mod">Modo:</label>
                        <select class="form-control" name="new_network[vpntunmod]">
                            <option value="1">SSH</option>
                            <option value="2">OVPN</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="new_network_name">Nome da Configuração:</label>
                        <input type="text" class="form-control" name="new_network[name]">
                    </div>

                    <div class="form-group">
                        <label for="new_network_servidor">Portas Udp Ex: :7300;7400;7500</label>
                        <input type="text" class="form-control" name="new_network[servidor]">
                    </div>

                    <div class="form-group">
                        <label for="new_network_sport">Porta SSL:</label>
                        <input type="text" class="form-control" name="new_network[sport]">
                    </div>

                    <div class="form-group">
                        <label for="new_network_proxy">Proxy:Port</label>
                        <input type="text" class="form-control" name="new_network[prox]" placeholder="144.22.158.190:80">
                    </div>

                    <div class="form-group">
                        <label for="new_network_payload">Payload Websocket:</label>
                        <textarea type="text" class="form-control" name="new_network[payload]" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="new_network_direct">Payload Direct:</label>
                        <textarea type="text" class="form-control" name="new_network[direct]" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="new_network_sni">Sni:</label>
                        <input type="text" class="form-control" name="new_network[sni]" placeholder="www.google.com">
                    </div>

                    <div class="form-group">
                        <label for="new_network_payssl">Payload SSL:</label>
                        <textarea class="form-control" name="new_network[payssl]" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="new_network_certopen">Certificado OpenVpn:</label>
                        <textarea class="form-control" name="new_network[certopen]" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="new_network_dns1">Dns 1:</label>
                        <input type="text" class="form-control" name="new_network[dns1]" value="8.8.8.8">
                    </div>

                    <div class="form-group">
                        <label for="new_network_dns2">Dns 2:</label>
                        <input type="text" class="form-control" name="new_network[dns2]" value="8.8.4.4">
                    </div>

                    <div class="form-group">
                        <label for="new_network_url_painel">Url CheckUser:</label>
                        <input type="text" class="form-control" name="new_network[urlpainel]" value="https://<?php echo $_SERVER['HTTP_HOST']; ?>/checkuser">
                    </div>

                    <div class="form-group">
                        <label for="new_network_info">Operadora:</label>
                        <input type="text" class="form-control" name="new_network[info]" value="Sua descrição aqui:operadora">
                    </div>

                    <input type="submit" class="btn btn-primary" name="add_network" value="Adicionar Payload">
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ...código anterior... -->

<script>
var networkToEditIndex;

function editNetwork(index) {
networkToEditIndex = index;
var network = <?php echo json_encode($data['Networks']); ?>[index];
var editNetworkForm = document.getElementById('editNetworkForm');

// Preencha os campos do formulário com os valores da rede selecionada
editNetworkForm.innerHTML = `
<div class="form-group">
    <label for="edit_network_name">Nome da Configuração:</label>
    <input type="text" class="form-control" name="name" value="${network['Name']}">
</div>

<div class="form-group">
    <label for="edit_network_servidor">Portas Udp:</label>
    <input type="text" class="form-control" name="servidor" value="${network['Servidor']}" placeholder="Ex: :7200;7300;7100;7400">
</div>
<div class="form-group">
    <label for="edit_network_sport">Porta SSL:</label>
    <input type="text" class="form-control" name="sport" value="${network['SPort']}">
</div>
<div class="form-group">
<label for="edit_network_sport">Proxy:Port:</label>
<input type="text" class="form-control" name="prox" value="${network['Prox']}">
</div>


<div class="form-group">
<label for="edit_network_payload">Payload WebSocket:</label>
<textarea class="form-control" name="payload" rows="5">${network['Payload']}</textarea>
</div>
<div class="form-group">
<label for="edit_network_direct">Payload Direct:</label>
<textarea class="form-control" name="direct" rows="5">${network['Direct']}</textarea>
</div>
<div class="form-group">
<label for="edit_network_sni">Sni:</label>
<input type="text" class="form-control" name="sni" value="${network['Sni']}">
</div>
<div class="form-group">
<label for="edit_network_payssl">Payload SSL:</label>
<textarea class="form-control" name="payssl" rows="5">${network['Payssl']}</textarea>
</div>
<div class="form-group">
<label for="edit_network_certopen">Certificado OpenVpn:</label>
<textarea class="form-control" name="certopen" rows="5">${network['Certopen']}</textarea>
</div>
<div class="form-group">
<label for="edit_network_dns1">Dns 1:</label>
<input type="text" class="form-control" name="dns1" value="${network['Dns1']}">
</div>
<div class="form-group">
<label for="edit_network_dns2">Dns 2:</label>
<input type="text" class="form-control" name="dns2" value="${network['Dns2']}">
</div>
<div class="form-group">
<label for="edit_network_url_painel">Url CheckUser:</label>
<input type="text" class="form-control" name="urlpainel" value="${network['UrlPainel']}">
</div>
<div class="form-group">
<label for="edit_network_vpnmod">Modo:</label>
<select class="form-control" name = "vpnmod">
<option value="0" ${network['VPNMod'] == 0 ? 'selected' : ''}>Proxy</option>
<option value="1" ${network['VPNMod'] == 1 ? 'selected' : ''}>Ssl</option>
<option value="2" ${network['VPNMod'] == 2 ? 'selected' : ''}>Direct</option>
<option value="3" ${network['VPNMod'] == 3 ? 'selected' : ''}>SslPay</option>
<option value="3" ${network['VPNMod'] == 3 ? 'selected' : ''}>V2ray</option>
</select>
</div>
<div class="form-group">
<label for="edit_network_vpntunmod">SSH ou Direct:</label>
<select class="form-control" name = "vpntunmod">
<option value="1" ${network['VPNTunMod'] == 1 ? 'selected' : ''}>SSH</option>
<option value="2" ${network['VPNTunMod'] == 2 ? 'selected' : ''}>OVPN</option>
</select>
</div>
<div class="form-group">
<label for="edit_network_info">Info:</label>
<input type="text" class="form-control" name="info" value="${network['Info']}">
</div>


<!-- Adicione outros campos conforme necessário -->
`;


// Abra o modal
$('#editNetworkModal').modal('show');
}

function saveChanges() {
var editNetworkForm = document.getElementById('editNetworkForm');
var formData = new FormData(editNetworkForm);
formData.append('edit_network', networkToEditIndex);

// Faça uma requisição AJAX para o script PHP que irá lidar com a atualização do arquivo JSON
var xhr = new XMLHttpRequest();
xhr.open('POST', 'geradorany.php');
xhr.onload = function() {
    if (xhr.status === 200) {
        swal('Sucesso!', 'As alterações foram salvas com sucesso.', 'success').then(function() {
            // Feche o modal
            $('#editNetworkModal').modal('hide');
            // Atualize a tabela ou recarregue a página conforme necessário
            window.location.reload();
        });
        $('#editNetworkModal').modal('hide');
        // Atualize a tabela ou recarregue a página conforme necessário
        //window.location.reload();
    } else {
        alert('Ocorreu um erro ao salvar as alterações. Código do status: ' + xhr.status);
    }
};
xhr.send(formData);
}
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Editar uma rede existente
    if (isset($_POST['edit_network'])) {
        $networkIndex = $_POST['edit_network'];
        $data['Networks'][$networkIndex]['Name'] = $_POST['name'];
        $data['Networks'][$networkIndex]['Servidor'] = $_POST['servidor'];
        $data['Networks'][$networkIndex]['SPort'] = $_POST['sport'];
        $data['Networks'][$networkIndex]['Prox'] = $_POST['prox'];
        $data['Networks'][$networkIndex]['Payload'] = $_POST['payload'];
        $data['Networks'][$networkIndex]['Direct'] = $_POST['direct'];
        $data['Networks'][$networkIndex]['Sni'] = $_POST['sni'];
        $data['Networks'][$networkIndex]['Payssl'] = $_POST['payssl'];
        $data['Networks'][$networkIndex]['Certopen'] = $_POST['certopen'];
        $data['Networks'][$networkIndex]['Dns1'] = $_POST['dns1'];
        $data['Networks'][$networkIndex]['Dns2'] = $_POST['dns2'];
        $data['Networks'][$networkIndex]['UrlPainel'] = $_POST['urlpainel'];
        $data['Networks'][$networkIndex]['VPNMod'] = $_POST['vpnmod'];
        $data['Networks'][$networkIndex]['VPNTunMod'] = $_POST['vpntunmod'];
        $data['Networks'][$networkIndex]['Info'] = $_POST['info'];
        // Atualize outros campos conforme necessário
    }

    // Adicionar uma nova rede
    //se for nill ($_POST['new_network']['sport']) define como '' vazio
    if ($_POST['new_network']['sport'] == null) {
        $_POST['new_network']['sport'] = '';
    }
    if (isset($_POST['add_network'])) {
        $newNetwork = [
            'Name' => isset($_POST['new_network']['name']) ? $_POST['new_network']['name'] : '',
            'Servidor' => isset($_POST['new_network']['servidor']) ? $_POST['new_network']['servidor'] : '',
            'SPort' => isset($_POST['new_network']['sport']) ? $_POST['new_network']['sport'] : '',
            'Prox' => isset($_POST['new_network']['prox']) ? $_POST['new_network']['prox'] : '',
            'Payload' => isset($_POST['new_network']['payload']) ? $_POST['new_network']['payload'] : '',
            'Direct' => isset($_POST['new_network']['direct']) ? $_POST['new_network']['direct'] : '',
            'Sni' => isset($_POST['new_network']['sni']) ? $_POST['new_network']['sni'] : '',
            'Payssl' => isset($_POST['new_network']['payssl']) ? $_POST['new_network']['payssl'] : '',
            'Certopen' => isset($_POST['new_network']['certopen']) ? $_POST['new_network']['certopen'] : '',
            'Dns1' => isset($_POST['new_network']['dns1']) ? $_POST['new_network']['dns1'] : '',
            'Dns2' => isset($_POST['new_network']['dns2']) ? $_POST['new_network']['dns2'] : '',
            'UrlPainel' => isset($_POST['new_network']['urlpainel']) ? $_POST['new_network']['urlpainel'] : '',
            'VPNMod' => isset($_POST['new_network']['vpnmod']) ? intval($_POST['new_network']['vpnmod']) : 0,
            'VPNTunMod' => isset($_POST['new_network']['vpntunmod']) ? intval($_POST['new_network']['vpntunmod']) : 1,
            'Info' => isset($_POST['new_network']['info']) ? $_POST['new_network']['info'] : '',
            'DefaultProxy' => true,
            'CustomSquid' => isset($_POST['new_network']['customsquid']) ? $_POST['new_network']['customsquid'] : ''
        ];
        
        $data['Networks'][] = $newNetwork;
    }
    //salvar config app
    if (isset($_POST['save_config'])){
            $data['Saudacao'] = $_POST['Saudacao'];
            $data['ReleaseNotes'] = $_POST['ReleaseNotes'];
            $data['LinkPainel'] = $_POST['LinkPainel'];
            $data['LinkIcone'] = $_POST['LinkIcone'];
            $data['LinkBanner'] = $_POST['LinkBanner'];
            $data['LinkBackground'] = $_POST['LinkBackground'];
            // Adicione aqui as outras configurações que deseja salvar
        
           
        }
        

    

    // Excluir uma rede existente
    if (isset($_POST['delete_network'])) {
        $networkIndex = $_POST['delete_network'];
        if (isset($data['Networks'][$networkIndex])) {
            unset($data['Networks'][$networkIndex]);
            $data['Networks'] = array_values($data['Networks']); // Reindexar os elementos do array
        }
    }
    $data['ConfigVersion'] = isset($data['ConfigVersion']) ? $data['ConfigVersion'] + 1 : 1;
    // Salvar as alterações de volta ao JSON
    // Salvar as alterações de volta ao JSON com formatação e caracteres preservados
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    // Atualizar a página
    //echo '<script>window.location.reload();</script>';
}

?>
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body>

