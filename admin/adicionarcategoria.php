<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio_adicionar_categoria($input)
    {
        ?>
<?php
error_reporting(0);
if (!isset($_SESSION)) { session_start(); }

if(!isset($_SESSION['login']) || !isset($_SESSION['senha'])) {
    session_destroy();
    header('location:index.php');
    exit();
}

require_once '../AegisCore/conexao.php';
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

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

// Resultado do form
$formResult = null;
if (isset($_POST['criarcategoria'])) {
    $nomecategoria = anti_sql($_POST['nomecategoria']);
    $idcategoria = anti_sql($_POST['idcategoria']);
    
    $sql = "SELECT * FROM categorias WHERE subid = '$idcategoria'";
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
        $formResult = ['tipo' => 'erro', 'msg' => 'O ID da categoria já existe!'];
    } else {
        $sql = "INSERT INTO categorias (nome, subid) VALUES ('$nomecategoria', '$idcategoria')";
        if (mysqli_query($conn, $sql)) {
            $formResult = ['tipo' => 'sucesso', 'msg' => 'Categoria "'.$nomecategoria.'" criada com sucesso!'];
        } else {
            $formResult = ['tipo' => 'erro', 'msg' => 'Erro ao criar categoria!'];
        }
    }
}

// Stats
$total_categorias = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM categorias");
if ($r) $total_categorias = $r->fetch_assoc()['t'];

$total_servidores = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM servidores");
if ($r) $total_servidores = $r->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Adicionar Categoria - Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php if (function_exists('getCSSVariables')) echo getCSSVariables($temaAtual); else echo ':root{--primaria:#4158D0;--secundaria:#C850C0;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#ffffff;}'; ?>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:-680px!important;padding:0!important;}
.content-wrapper{max-width:1000px;margin:0 auto!important;padding:20px!important;}

/* ========== STATS CARD ========== */
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

/* Voltar */
.btn-back{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.5);font-size:11px;font-weight:600;text-decoration:none;transition:all .25s;margin-bottom:16px;cursor:pointer;}
.btn-back:hover{background:rgba(255,255,255,0.08);border-color:var(--primaria);color:#fff;transform:translateX(-3px);text-decoration:none;}
.btn-back i{font-size:16px;}

/* Modern Card */
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

/* Form */
.form-group{margin-bottom:14px;}
.form-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:flex;align-items:center;gap:5px;}
.form-label i{font-size:14px;}
.form-input{width:100%;padding:10px 14px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:9px;font-size:13px;color:#ffffff;transition:all .2s;font-family:inherit;outline:none;}
.form-input:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,0.09);}
.form-input::placeholder{color:rgba(255,255,255,0.3);}
.form-hint{font-size:9px;color:rgba(255,255,255,0.3);margin-top:4px;display:flex;align-items:center;gap:4px;}
.form-hint i{font-size:11px;color:#ec4899;}

/* Input com ícone */
.input-wrap{position:relative;}
.input-wrap .form-input{padding-left:38px;}
.input-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:16px;color:rgba(255,255,255,0.25);pointer-events:none;}

/* Row 2 cols */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}

/* Separator */
.form-separator{height:1px;background:rgba(255,255,255,0.06);margin:16px 0;}

/* Pré-visualização */
.preview-card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:14px;margin-top:16px;}
.preview-title{font-size:9px;font-weight:700;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;display:flex;align-items:center;gap:5px;}
.preview-title i{font-size:13px;color:var(--primaria);}
.preview-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;}
.preview-item{background:rgba(255,255,255,0.03);border-radius:8px;padding:8px;border:1px solid rgba(255,255,255,0.04);}
.preview-item-label{font-size:7px;color:rgba(255,255,255,0.3);text-transform:uppercase;font-weight:700;margin-bottom:2px;}
.preview-item-value{font-size:11px;font-weight:600;color:var(--texto);word-break:break-all;}

/* Botões */
.form-actions{display:flex;gap:8px;margin-top:18px;}
.form-btn{flex:1;padding:10px 20px;border:none;border-radius:8px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;color:white;transition:all .2s;font-family:inherit;text-decoration:none;outline:none;-webkit-appearance:none;-moz-appearance:none;appearance:none;}
.form-btn:hover{transform:translateY(-1px);filter:brightness(1.05);color:white;text-decoration:none;}
.form-btn:active{transform:translateY(0);}
.form-btn i{font-size:16px;}
.form-btn-cancel{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.form-btn-save{background:linear-gradient(135deg,#10b981,#059669);}

/* ========== MODAIS ========== */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.85);display:none;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:450px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);box-shadow:0 25px 60px rgba(0,0,0,0.5);}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:white;}
.modal-header-custom.success{background:linear-gradient(135deg,#10b981,#059669);}
.modal-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-close{background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,0.25);transform:rotate(90deg);}
.modal-body-custom{padding:18px;}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,0.07);padding:12px 18px;display:flex;justify-content:center;gap:8px;}
.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(0.34,1.56,0.64,1) .15s both;}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3);}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3);}
.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:white;transition:all .2s;font-family:inherit;}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669);}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}

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

    <!-- Stats Card -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-category'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Nova Categoria</div>
            <div class="stats-card-value">Adicionar Categoria</div>
            <div class="stats-card-subtitle">Crie uma nova categoria para organizar seus servidores</div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-category'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $total_categorias; ?></div><div class="mini-stat-lbl">Categorias</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_servidores; ?></div><div class="mini-stat-lbl">Servidores</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><i class='bx bx-plus' style="font-size:16px;"></i></div><div class="mini-stat-lbl">Cadastrar</div></div>
    </div>

    <!-- Voltar -->
    <a href="categorias.php" class="btn-back"><i class='bx bx-arrow-back'></i> Voltar para Categorias</a>

    <!-- Card Formulário -->
    <div class="modern-card">
        <div class="card-header-custom green">
            <div class="header-icon"><i class='bx bx-plus-circle'></i></div>
            <div>
                <div class="header-title">Cadastro de Nova Categoria</div>
                <div class="header-subtitle">Preencha os dados da categoria</div>
            </div>
        </div>
        <div class="card-body-custom">

            <!-- Tip -->
            <div class="tip-box">
                <div class="tip-icon"><i class='bx bx-info-circle'></i></div>
                <div class="tip-text">
                    <strong>Dica:</strong> O ID da categoria será usado para vincular servidores. Use um número único para cada categoria.
                </div>
            </div>

            <form action="adicionarcategoria.php" method="POST" id="formCategoria">

                <!-- Nome -->
                <div class="form-group">
                    <div class="form-label"><i class='bx bx-category' style="color:#4ECDC4;"></i> Nome da Categoria</div>
                    <div class="input-wrap">
                        <i class='bx bx-category input-icon' style="color:#4ECDC4;"></i>
                        <input type="text" class="form-input" name="nomecategoria" id="inputNome" placeholder="Ex: Servidor Premium" required oninput="atualizarPreview()">
                    </div>
                </div>

                <!-- ID -->
                <div class="form-group">
                    <div class="form-label"><i class='bx bx-hash' style="color:#45B7D1;"></i> ID da Categoria</div>
                    <div class="input-wrap">
                        <i class='bx bx-hash input-icon' style="color:#45B7D1;"></i>
                        <input type="text" class="form-input" name="idcategoria" id="inputID" placeholder="Ex: 1, 2, 3..." value="1" required oninput="atualizarPreview()">
                    </div>
                    <div class="form-hint"><i class='bx bx-info-circle'></i> Este ID será usado para vincular servidores a esta categoria</div>
                </div>

                <div class="form-separator"></div>

                <!-- Preview -->
                <div class="preview-card" id="previewCard">
                    <div class="preview-title"><i class='bx bx-show'></i> Pré-visualização</div>
                    <div class="preview-grid">
                        <div class="preview-item">
                            <div class="preview-item-label">Nome</div>
                            <div class="preview-item-value" id="prevNome">—</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-item-label">ID (SubID)</div>
                            <div class="preview-item-value" id="prevID">1</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-item-label">Status</div>
                            <div class="preview-item-value" style="color:#fbbf24;">Pendente</div>
                        </div>
                    </div>
                </div>

                <!-- Botões — Cancelar primeiro, Salvar depois -->
                <div class="form-actions">
                    <a href="categorias.php" class="form-btn form-btn-cancel">
                        <i class='bx bx-x-circle'></i> Cancelar
                    </a>
                    <button type="submit" class="form-btn form-btn-save" name="criarcategoria">
                        <i class='bx bx-check-circle'></i> Criar Categoria
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>
</div>

<!-- ========== MODAIS ========== -->

<!-- Modal Sucesso -->
<div id="modalSucesso" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom success"><h5><i class='bx bx-check-circle'></i> Sucesso!</h5><button class="modal-close" onclick="fecharModal('modalSucesso');window.location.href='categorias.php';"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic success"><i class='bx bx-check-circle'></i></div>
        <p style="text-align:center;font-size:13px;font-weight:600;" id="sucessoMsg"></p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-ok" onclick="fecharModal('modalSucesso');window.location.href='categorias.php';"><i class='bx bx-check'></i> OK</button>
    </div>
</div></div>
</div>

<!-- Modal Erro -->
<div id="modalErro" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-error-circle'></i> Erro!</h5><button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <p style="text-align:center;font-size:13px;font-weight:600;" id="erroMsg"></p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i> Fechar</button>
    </div>
</div></div>
</div>

<script>
// ===== Modais =====
function abrirModal(id){document.getElementById(id).classList.add('show');}
function fecharModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});});

// ===== Preview =====
function atualizarPreview(){
    var nome=document.getElementById('inputNome').value||'—';
    var id=document.getElementById('inputID').value||'—';
    document.getElementById('prevNome').textContent=nome;
    document.getElementById('prevID').textContent=id;
}
document.addEventListener('DOMContentLoaded',atualizarPreview);

// ===== Abrir modais baseado no resultado do PHP =====
<?php if ($formResult): ?>
document.addEventListener('DOMContentLoaded',function(){
    <?php if ($formResult['tipo'] === 'sucesso'): ?>
    document.getElementById('sucessoMsg').textContent='<?php echo addslashes($formResult['msg']); ?>';
    abrirModal('modalSucesso');
    <?php else: ?>
    document.getElementById('erroMsg').textContent='<?php echo addslashes($formResult['msg']); ?>';
    abrirModal('modalErro');
    <?php endif; ?>
});
<?php endif; ?>
</script>
</body>
</html>
<?php
    }
    aleatorio_adicionar_categoria($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

