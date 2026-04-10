<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio_criado($input)
    {
        ?>
<?php 
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
mysqli_set_charset($conn, "utf8mb4");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

include_once 'headeradmin2.php';
$dominio = $_SERVER['HTTP_HOST'];

$sql = "SELECT * FROM accounts WHERE id = '$_SESSION[iduser]'";
$result = $conn->query($sql);
if ($result->num_rows > 0){
    while ($row = $result->fetch_assoc()) {
        $accesstoken = $row['accesstoken'];
        $acesstokenpaghiper = $row['acesstokenpaghiper'];
    }
}

if (!file_exists('suspenderrev.php')) {
    exit ("<script>alert('Token Invalido!');</script>");
} else {
    include_once 'suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

$sql = "SELECT * FROM configs WHERE id = '1'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$applink = $row['cortextcard'];

$validade = $_SESSION['validadefin'];

$sucess_servers = isset($_GET['sucess']) ? explode(", ", $_GET['sucess']) : array();
$failed_servers = isset($_GET['failed']) ? explode(", ", $_GET['failed']) : array();

$dominioserver = 'apiwhats.atlaspainel.com.br';
$sqlwhats = "SELECT * FROM whatsapp WHERE byid = '$_SESSION[iduser]'";
$resultwhats = mysqli_query($conn, $sqlwhats);
$rowwhats = mysqli_fetch_assoc($resultwhats);
$tokenwpp = $rowwhats['token'];
$sessaowpp = $rowwhats['sessao'];
$ativewpp = $rowwhats['ativo'];

if ($tokenwpp != '' || $sessaowpp != '') {
    $mensagens = "SELECT * FROM mensagens WHERE ativo = 'ativada' AND funcao = 'criarusuario' AND byid = '$_SESSION[iduser]'";
    $resultmensagens = mysqli_query($conn, $mensagens);
    $rowmensagens = mysqli_fetch_assoc($resultmensagens);
    $mensagem = $rowmensagens['mensagem'];
    
    if (!empty($mensagem)) {
        $mensagem = strip_tags($mensagem);
        $mensagem = str_replace("<br>", "\n", $mensagem);
        $mensagem = str_replace("<br><br>", "\n", $mensagem);
        
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
                    textMessage: { text: message },
                    options: { delay: 0, presence: 'composing' }
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
        
            $_SESSION['mensagem_enviada'] = true;
        }
    }
}
?>

<!-- CSS do Modal Moderno (igual ao criar teste) -->
<style>
    @import url('https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css');

    /* Modal Overlay */
    .modal {
        display: flex !important;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
        z-index: 1050;
        backdrop-filter: blur(5px);
    }

    .modal-dialog {
        max-width: 500px;
        width: 90%;
        margin: 0 auto;
        animation: slideInDown 0.5s ease-out;
    }

    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-content {
        background: white;
        border-radius: 25px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        border: none;
        overflow: hidden;
    }

    .modal-header {
        background: linear-gradient(135deg, #4158D0 0%, #C850C0 46%, #FFCC70 100%);
        color: white;
        padding: 20px 25px;
        border-bottom: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .modal-header h5 {
        font-size: 20px;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-header .close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s;
        color: white;
        font-size: 24px;
        padding: 0;
        line-height: 1;
    }

    .modal-header .close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 30px;
        background: #f8fafc;
    }

    .info-card {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        border: 2px solid #eef2f6;
    }

    .divider {
        display: flex;
        align-items: center;
        margin: 20px 0;
    }

    .divider::before,
    .divider::after {
        content: "";
        flex: 1;
        height: 2px;
        background: linear-gradient(90deg, transparent, #4158D0, #C850C0, #FFCC70, transparent);
    }

    .divider-text {
        padding: 0 15px;
        font-size: 24px;
        font-weight: 700;
        background: linear-gradient(135deg, #4158D0, #C850C0, #FFCC70);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .info-row {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        background: #f8fafc;
        border-radius: 14px;
        margin-bottom: 10px;
        border: 2px solid #eef2f6;
        transition: all 0.3s;
    }

    .info-row:hover {
        border-color: #4158D0;
        transform: translateX(5px);
    }

    .info-icon {
        width: 40px;
        height: 40px;
        background: white;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 20px;
        border: 2px solid #eef2f6;
    }

    .info-content {
        flex: 1;
    }

    .info-label {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }

    .info-value {
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
    }

    /* Cores dos ícones (mesmas do criar teste) */
    .icon-user { color: #4ECDC4; }
    .icon-lock { color: #45B7D1; }
    .icon-calendar { color: #FFE194; }
    .icon-group { color: #96CEB4; }
    .icon-uuid { color: #DFAB8C; }
    .icon-money { color: #6C5B7B; }
    .icon-link { color: #6C5B7B; }
    .icon-check { color: #10b981; }
    .icon-error { color: #dc2626; }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 30px;
        font-size: 14px;
        font-weight: 600;
        margin: 5px 0;
    }

    .status-success {
        background: #d1fae5;
        color: #065f46;
        border-left: 4px solid #10b981;
    }

    .status-error {
        background: #fee2e2;
        color: #991b1b;
        border-left: 4px solid #dc2626;
    }

    .modal-footer {
        padding: 20px 25px;
        background: white;
        border-top: 2px solid #eef2f6;
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }

    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 30px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: 2px solid transparent;
        flex: 1;
    }

    .btn-primary {
        background: linear-gradient(135deg, #4158D0, #C850C0);
        color: white;
        box-shadow: 0 4px 10px rgba(65, 88, 208, 0.2);
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(65, 88, 208, 0.3);
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: white;
        box-shadow: 0 4px 10px rgba(220, 38, 38, 0.3);
    }

    .btn-danger:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(220, 38, 38, 0.4);
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #475569;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }

    @media (max-width: 768px) {
        .modal-footer {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
        }
    }
</style>

<!-- Modal -->
<div class="modal show" id="criado" tabindex="-1" role="dialog" aria-labelledby="exampleModalScrollableTitle" aria-hidden="false">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class='bx bx-check-circle'></i>
                    Usuário Criado com Sucesso!
                </h5>
                <button type="button" class="close" onclick="fecharModal()" aria-label="Close">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="info-card" id="divToCopy">
                    <div class="divider">
                        <span class="divider-text">🎉 USUÁRIO CRIADO 🎉</span>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-icon">
                            <i class='bx bx-user icon-user'></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">USUÁRIO</div>
                            <div class="info-value"><?php echo $_SESSION['usuariofin']; ?></div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-icon">
                            <i class='bx bx-lock-alt icon-lock'></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">SENHA</div>
                            <div class="info-value"><?php echo $_SESSION['senhafin']; ?></div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-icon">
                            <i class='bx bx-calendar icon-calendar'></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">VALIDADE</div>
                            <div class="info-value"><?php echo $validade; ?></div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-icon">
                            <i class='bx bx-group icon-group'></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">LIMITE</div>
                            <div class="info-value"><?php echo $_SESSION['limitefin']; ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($_SESSION['uuid'])): ?>
                    <div class="info-row">
                        <div class="info-icon">
                            <i class='bx bx-shield-quarter icon-uuid'></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">UUID V2RAY</div>
                            <div class="info-value"><?php echo $_SESSION['uuid']; ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($accesstoken != '' || $acesstokenpaghiper != ''): ?>
                    <div class="info-row">
                        <div class="info-icon">
                            <i class='bx bx-link icon-link'></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">LINK DE RENOVAÇÃO</div>
                            <div class="info-value">
                                <a href="https://<?php echo $dominio; ?>/renovar.php" target="_blank">
                                    https://<?php echo $dominio; ?>/renovar.php
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <div class="status-badge status-success">
                            <i class='bx bx-check-circle icon-check'></i>
                            ✔️ Criado: <?php echo implode(", ", $sucess_servers); ?>
                        </div>
                        
                        <?php if (!empty($failed_servers[0])): ?>
                        <div class="status-badge status-error" style="margin-top: 10px;">
                            <i class='bx bx-error-circle icon-error'></i>
                            ❌ Falha: <?php echo implode(", ", $failed_servers); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="copiarDados()">
                    <i class='bx bx-copy'></i> Copiar
                </button>
                <button type="button" class="btn btn-danger" onclick="window.location.href='listarusuarios.php'">
                    <i class='bx bx-list-ul'></i> Listar Usuários
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
<script src="../app-assets/sweetalert.min.js"></script>

<script>
function copiarDados() {
    var div = document.getElementById("divToCopy");
    var range = document.createRange();
    range.selectNode(div);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
    
    try {
        document.execCommand("copy");
        window.getSelection().removeAllRanges();
        
        swal({
            title: "Copiado!",
            text: "Informações copiadas para a área de transferência",
            icon: "success",
            timer: 1500,
            buttons: false
        });
    } catch (err) {
        swal({
            title: "Erro!",
            text: "Não foi possível copiar",
            icon: "error",
            timer: 1500,
            buttons: false
        });
    }
}

function fecharModal() {
    window.location.href = 'criarusuario.php';
}

// Fecha o modal com a tecla ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        window.location.href = 'criarusuario.php';
    }
});
</script>

<?php
    }
    aleatorio_criado($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>