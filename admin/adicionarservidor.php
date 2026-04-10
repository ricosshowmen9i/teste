<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio_adicionar_servidor($input)
    {
        ?>
<script src="../app-assets/sweetalert.min.js"></script>
<?php 
error_reporting(0);
if (!isset($_SESSION)) {
    session_start();
}

if(!isset($_SESSION['login']) || !isset($_SESSION['senha'])) {
    session_destroy();
    header('location:index.php');
    exit();
}

require_once '../AegisCore/conexao.php';
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (file_exists('../AegisCore/temas.php')) {
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
} else { $temaAtual = []; }

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
        $telegram->sendMessage([
            'chat_id' => '2017803306',
            'text' => "O domínio " . $_SERVER['HTTP_HOST'] . " tentou acessar o painel com token - " . $_SESSION['token'] . " inválido!"
        ]);
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

include('headeradmin2.php');

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

if (isset($_POST['adcservidor'])) {
    $ipservidor = $_POST['ipservidor'];
    $nomeservidor = $_POST['nomeservidor'];
    $usuarioservidor = $_POST['usuarioservidor'];
    $senhaservidor = $_POST['senhaservidor'];
    $categoriaservidor = $_POST['categoriaservidor'];
    $portaservidor = $_POST['portaservidor'];
    $confirma = 6;
    
    $_SESSION['ipservidor'] = $ipservidor;
    $_SESSION['nomeservidor'] = $nomeservidor;
    $_SESSION['usuarioservidor'] = $usuarioservidor;
    $_SESSION['senhaservidor'] = $senhaservidor;
    $_SESSION['portaservidor'] = $portaservidor;
    $_SESSION['categoriaservidor'] = $categoriaservidor;
    $_SESSION['confirma'] = $confirma;
    
    $ipservidor = anti_sql($ipservidor);
    $nomeservidor = anti_sql($nomeservidor);
    $usuarioservidor = anti_sql($usuarioservidor);
    $senhaservidor = anti_sql($senhaservidor);
    $portaservidor = anti_sql($portaservidor);
    $categoriaservidor = anti_sql($categoriaservidor);
    
    $sql = "SELECT * FROM servidores WHERE ip = '$ipservidor'";
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
        echo "<script>swal('Erro!', 'Servidor já cadastrado!', 'error').then(function(){window.location.href='adicionarservidor.php';});</script>";
        exit();
    } else {
        if ($confirma > 5) {
            echo "<script>swal('Sucesso!', 'Iniciando Instalação dos Drivers', 'success');</script>";
            echo "<script>setTimeout(function(){ window.location.href='installserv.php'; }, 1000);</script>";
            
            $sql = "INSERT INTO servidores (ip, usuario, nome, senha, porta, subid) VALUES ('$ipservidor', '$usuarioservidor', '$nomeservidor', '$senhaservidor', '$portaservidor', '$categoriaservidor')";
            if (mysqli_query($conn, $sql)) {
            } else {
                echo "Error: " . $sql . "<br>" . mysqli_error($conn);
            }
        } else {
            echo "<script>alert('Você não confirmou o cadastro!');</script>";
        }
    }
}

// Contar servidores e categorias
$total_servidores = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM servidores");
if ($r) $total_servidores = $r->fetch_assoc()['t'];

$total_categorias = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM categorias");
if ($r) $total_categorias = $r->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Adicionar Servidor - Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php if (function_exists('getCSSVariables')) echo getCSSVariables($temaAtual); else echo ':root{--primaria:#4158D0;--secundaria:#C850C0;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#ffffff;}'; ?>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}

/* ===== MESMO LAYOUT DA LISTA DE USUÁRIOS ===== */
.app-content{margin-left:-630px!important;padding:0!important;}
.content-wrapper{max-width:1000px;margin:0 auto!important;padding:20px!important;}

/* ========== STATS CARD — igual lista de usuários ========== */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s ease;}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981);}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:white;flex-shrink:0;}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#fff,var(--primaria,#10b981));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Mini Stats */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{flex:1;min-width:90px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;}
.mini-stat:hover{border-color:var(--primaria,#10b981);transform:translateY(-2px);}
.mini-stat-val{font-size:18px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* Modern Card — igual lista de usuários */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;margin-bottom:16px;transition:all .2s;}
.modern-card:hover{border-color:var(--primaria,#10b981);}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.green{background:linear-gradient(135deg,#10b981,#059669);}
.card-header-custom.blue{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body-custom{padding:16px;}

/* Tip box */
.tip-box{display:flex;align-items:flex-start;gap:10px;background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.12);border-radius:10px;padding:12px;margin-bottom:16px;}
.tip-icon{width:32px;height:32px;background:rgba(59,130,246,0.12);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;color:#60a5fa;flex-shrink:0;}
.tip-text{font-size:11px;color:rgba(255,255,255,0.45);line-height:1.5;}
.tip-text strong{color:rgba(255,255,255,0.7);}

/* Form — mesmo estilo dos filtros da lista de usuários */
.form-group{margin-bottom:14px;}
.form-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:flex;align-items:center;gap:5px;}
.form-label i{font-size:14px;}
.form-input{width:100%;padding:10px 14px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:9px;font-size:13px;color:#ffffff;transition:all .2s;font-family:inherit;outline:none;}
.form-input:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,0.09);}
.form-input::placeholder{color:rgba(255,255,255,0.3);}
select.form-input{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
select.form-input option{background:#1e293b;color:#ffffff;}

/* Input com ícone */
.input-wrap{position:relative;}
.input-wrap .form-input{padding-left:38px;}
.input-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:16px;color:rgba(255,255,255,0.25);pointer-events:none;}
.input-icon.clickable{pointer-events:auto;cursor:pointer;}

/* Row 2 cols */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}

/* Separator */
.form-separator{height:1px;background:rgba(255,255,255,0.06);margin:16px 0;}

/* Botões — mesmo estilo action-btn da lista */
.form-actions{display:flex;gap:8px;margin-top:18px;}
.form-btn{
    flex:1;
    padding:10px 20px;
    border:none;
    border-radius:8px;
    font-weight:600;
    font-size:12px;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    color:white;
    transition:all .2s;
    font-family:inherit;
    text-decoration:none;
    outline:none;
    -webkit-appearance:none;
    -moz-appearance:none;
    appearance:none;
}
.form-btn:hover{transform:translateY(-1px);filter:brightness(1.05);color:white;text-decoration:none;}
.form-btn:active{transform:translateY(0);}
.form-btn i{font-size:16px;}
.form-btn-cancel{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.form-btn-save{background:linear-gradient(135deg,#10b981,#059669);}

/* Voltar */
.btn-back{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.5);font-size:11px;font-weight:600;text-decoration:none;transition:all .25s;margin-bottom:16px;cursor:pointer;}
.btn-back:hover{background:rgba(255,255,255,0.08);border-color:var(--primaria);color:#fff;transform:translateX(-3px);text-decoration:none;}
.btn-back i{font-size:16px;}

/* Pré-visualização do servidor */
.preview-card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:14px;margin-top:16px;}
.preview-title{font-size:9px;font-weight:700;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;display:flex;align-items:center;gap:5px;}
.preview-title i{font-size:13px;color:var(--primaria);}
.preview-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;}
.preview-item{background:rgba(255,255,255,0.03);border-radius:8px;padding:8px;border:1px solid rgba(255,255,255,0.04);}
.preview-item-label{font-size:7px;color:rgba(255,255,255,0.3);text-transform:uppercase;font-weight:700;margin-bottom:2px;}
.preview-item-value{font-size:11px;font-weight:600;color:var(--texto);word-break:break-all;}

/* SweetAlert dark */
.swal-modal{background:var(--fundo_claro,#1e293b)!important;border:1px solid rgba(255,255,255,0.1)!important;border-radius:18px!important;}
.swal-title{color:#fff!important;font-family:'Inter',sans-serif!important;}
.swal-text{color:rgba(255,255,255,0.6)!important;font-family:'Inter',sans-serif!important;}
.swal-button{border-radius:10px!important;font-family:'Inter',sans-serif!important;font-weight:600!important;}
.swal-button--confirm{background:linear-gradient(135deg,var(--primaria),var(--secundaria))!important;}
.swal-button--cancel{background:rgba(255,255,255,0.08)!important;color:rgba(255,255,255,0.6)!important;}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .form-row{grid-template-columns:1fr;}
    .form-actions{flex-direction:column;}
    .preview-grid{grid-template-columns:1fr 1fr;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
    .mini-stats{flex-wrap:wrap;}
    .mini-stat{min-width:80px;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <!-- Stats Card — mesmo estilo da lista de usuários -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-server'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Adicionar Servidor</div>
            <div class="stats-card-value">Novo Servidor</div>
            <div class="stats-card-subtitle">Cadastre um novo servidor SSH no sistema</div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-server'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_servidores; ?></div><div class="mini-stat-lbl">Servidores</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_categorias; ?></div><div class="mini-stat-lbl">Categorias</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><i class='bx bx-plus' style="font-size:16px;"></i></div><div class="mini-stat-lbl">Cadastrar</div></div>
    </div>

    <!-- Voltar -->
    <a href="servidores.php" class="btn-back"><i class='bx bx-arrow-back'></i> Voltar para Servidores</a>

    <!-- Card Formulário — mesmo estilo modern-card da lista -->
    <div class="modern-card">
        <div class="card-header-custom green">
            <div class="header-icon"><i class='bx bx-plus-circle'></i></div>
            <div>
                <div class="header-title">Cadastro de Novo Servidor</div>
                <div class="header-subtitle">Preencha os dados de acesso SSH</div>
            </div>
        </div>
        <div class="card-body-custom">

            <!-- Tip -->
            <div class="tip-box">
                <div class="tip-icon"><i class='bx bx-info-circle'></i></div>
                <div class="tip-text">
                    <strong>Importante:</strong> Certifique-se que o servidor está acessível via SSH na porta informada.
                    Após salvar, os módulos serão instalados automaticamente no servidor.
                </div>
            </div>

            <form action="adicionarservidor.php" method="POST" id="formServidor">

                <!-- Nome -->
                <div class="form-group">
                    <div class="form-label"><i class='bx bx-server' style="color:#FF6B6B;"></i> Nome do Servidor</div>
                    <div class="input-wrap">
                        <i class='bx bx-server input-icon' style="color:#FF6B6B;"></i>
                        <input type="text" class="form-input" name="nomeservidor" id="inputNome" placeholder="Ex: Servidor Principal" required oninput="atualizarPreview()">
                    </div>
                </div>

                <!-- IP + Porta -->
                <div class="form-row">
                    <div class="form-group">
                        <div class="form-label"><i class='bx bx-network-chart' style="color:#4ECDC4;"></i> Endereço IP</div>
                        <div class="input-wrap">
                            <i class='bx bx-network-chart input-icon' style="color:#4ECDC4;"></i>
                            <input type="text" class="form-input" name="ipservidor" id="inputIP" placeholder="Ex: 192.168.1.100" required oninput="atualizarPreview()">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-label"><i class='bx bx-plug' style="color:#FFE194;"></i> Porta SSH</div>
                        <div class="input-wrap">
                            <i class='bx bx-plug input-icon' style="color:#FFE194;"></i>
                            <input type="text" class="form-input" name="portaservidor" id="inputPorta" value="22" placeholder="22" oninput="atualizarPreview()">
                        </div>
                    </div>
                </div>

                <div class="form-separator"></div>

                <!-- Usuário + Senha -->
                <div class="form-row">
                    <div class="form-group">
                        <div class="form-label"><i class='bx bx-user' style="color:#45B7D1;"></i> Usuário SSH</div>
                        <div class="input-wrap">
                            <i class='bx bx-user input-icon' style="color:#45B7D1;"></i>
                            <input type="text" class="form-input" name="usuarioservidor" id="inputUsuario" value="root" placeholder="root" oninput="atualizarPreview()">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-label"><i class='bx bx-lock-alt' style="color:#96CEB4;"></i> Senha SSH</div>
                        <div class="input-wrap">
                            <i class='bx bx-lock-alt input-icon clickable' style="color:#96CEB4;" id="toggleSenha"></i>
                            <input type="password" class="form-input" name="senhaservidor" id="inputSenha" placeholder="Senha do servidor" required>
                        </div>
                    </div>
                </div>

                <div class="form-separator"></div>

                <!-- Categoria -->
                <div class="form-group">
                    <div class="form-label"><i class='bx bx-category' style="color:#DFAB8C;"></i> Categoria</div>
                    <select class="form-input" name="categoriaservidor" id="inputCategoria" onchange="atualizarPreview()">
                        <?php
                        $sql_cat = "SELECT * FROM categorias ORDER BY id ASC";
                        $result_cat = $conn->query($sql_cat);
                        while ($row_cat = $result_cat->fetch_assoc()) {
                            echo "<option value='" . $row_cat['subid'] . "'>" . htmlspecialchars($row_cat['nome']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Pré-visualização -->
                <div class="preview-card" id="previewCard">
                    <div class="preview-title"><i class='bx bx-show'></i> Pré-visualização</div>
                    <div class="preview-grid">
                        <div class="preview-item">
                            <div class="preview-item-label">Nome</div>
                            <div class="preview-item-value" id="prevNome">—</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-item-label">IP : Porta</div>
                            <div class="preview-item-value" id="prevIP">—</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-item-label">Usuário</div>
                            <div class="preview-item-value" id="prevUsuario">root</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-item-label">Porta</div>
                            <div class="preview-item-value" id="prevPorta">22</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-item-label">Categoria</div>
                            <div class="preview-item-value" id="prevCategoria">—</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-item-label">Status</div>
                            <div class="preview-item-value" style="color:#fbbf24;">Pendente</div>
                        </div>
                    </div>
                </div>

                <!-- Botões — Cancelar primeiro, Salvar depois -->
                <div class="form-actions">
                    <a href="servidores.php" class="form-btn form-btn-cancel">
                        <i class='bx bx-x-circle'></i> Cancelar
                    </a>
                    <button type="submit" class="form-btn form-btn-save" name="adcservidor">
                        <i class='bx bx-check-circle'></i> Salvar Servidor
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>
</div>

<script src="../app-assets/sweetalert.min.js"></script>
<script>
// Toggle senha
document.getElementById('toggleSenha').addEventListener('click', function() {
    var input = document.getElementById('inputSenha');
    if (input.type === 'password') {
        input.type = 'text';
        this.classList.remove('bx-lock-alt');
        this.classList.add('bx-lock-open-alt');
    } else {
        input.type = 'password';
        this.classList.remove('bx-lock-open-alt');
        this.classList.add('bx-lock-alt');
    }
});

// Pré-visualização em tempo real
function atualizarPreview() {
    var nome = document.getElementById('inputNome').value || '—';
    var ip = document.getElementById('inputIP').value || '—';
    var porta = document.getElementById('inputPorta').value || '22';
    var usuario = document.getElementById('inputUsuario').value || 'root';
    var cat = document.getElementById('inputCategoria');
    var catNome = cat.options[cat.selectedIndex] ? cat.options[cat.selectedIndex].text : '—';

    document.getElementById('prevNome').textContent = nome;
    document.getElementById('prevIP').textContent = ip + ':' + porta;
    document.getElementById('prevUsuario').textContent = usuario;
    document.getElementById('prevPorta').textContent = porta;
    document.getElementById('prevCategoria').textContent = catNome;
}

// Inicializar preview
document.addEventListener('DOMContentLoaded', atualizarPreview);
</script>
</body>
</html>
<?php
    }
    aleatorio_adicionar_servidor($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

