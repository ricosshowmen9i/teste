<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio339313($input)
    {
        ?>
<?php
error_reporting(0);
session_start();
include('conexao.php');
include('header2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

$sql = "SELECT * FROM cupons WHERE byid = '$_SESSION[iduser]'";
$result = $conn->query($sql);

if (!file_exists('../admin/suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
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

function gerarCupom($tamanho = 8, $maiusculas = true, $numeros = true, $simbolos = false)
{
    $lmin = 'abcdefghijklmnopqrstuvwxyz';
    $lmai = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $num = '1234567890';
    $simb = '!@#$%*-';
    $retorno = '';
    $caracteres = '';
    $caracteres .= $lmin;
    if ($maiusculas) $caracteres .= $lmai;
    if ($numeros) $caracteres .= $num;
    if ($simbolos) $caracteres .= $simb;
    $len = strlen($caracteres);
    for ($n = 1; $n <= $tamanho; $n++) {
        $rand = mt_rand(1, $len);
        $retorno .= $caracteres[$rand - 1];
    }
    return $retorno;
}
$cupon = gerarCupom(8, true, true, false);

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

if (isset($_POST['adicionarcupom'])) {
    $nome = $_POST['nome'];
    $cupom = $_POST['cupom'];
    $desconto = $_POST['desconto'];
    $vezesuso = $_POST['vezesuso'];

    $nome = anti_sql($nome);
    $cupom = anti_sql($cupom);
    $desconto = anti_sql($desconto);
    $vezesuso = anti_sql($vezesuso);

    $sql = "INSERT INTO cupons (nome, cupom, desconto, byid, usado, vezesuso) VALUES ('$nome', '$cupom', '$desconto', '$_SESSION[iduser]', '0', '$vezesuso')";
    if ($conn->query($sql) === TRUE) {
        echo "<script>swal('Sucesso!', 'Cupom Adicionado!', 'success').then((value) => {
                window.location.href = 'cupons.php';
              });</script>";
    } else {
        echo "<script>swal('Erro!', 'Cupom Não Adicionado!', 'error').then((value) => {
                window.location.href = 'cupons.php';
              });</script>";
    }
}

if (isset($_POST['deletar'])) {
    $id = $_POST['id'];
    $id = anti_sql($id);
    $sql = "DELETE FROM cupons WHERE id='$id'";
    if ($conn->query($sql) === TRUE) {
        echo "<script>swal('Sucesso!', 'Cupom Deletado!', 'success').then((value) => {
                window.location.href = 'cupons.php';
              });</script>";
    } else {
        echo "<script>swal('Erro!', 'Cupom Não Deletado!', 'error').then((value) => {
                window.location.href = 'cupons.php';
              });</script>";
    }
}

// Compute stats
$total_cupons = $result->num_rows;
$total_ativos = 0;
$total_esgotados = 0;
$total_usado = 0;
$cupons_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $cupons_data[] = $row;
    $usado = (int)($row['usado'] ?? 0);
    $limite = (int)$row['vezesuso'];
    $total_usado += $usado;
    if ($limite > 0 && $usado >= $limite) {
        $total_esgotados++;
    } else {
        $total_ativos++;
    }
}
?>

<style>
.form-row-cupom{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;}
.form-row-cupom .form-group{flex:1;min-width:140px;margin-bottom:0;}
.progress-bar-wrap{height:4px;background:rgba(255,255,255,.08);border-radius:10px;overflow:hidden;margin-top:6px;}
.progress-bar-fill{height:100%;border-radius:10px;background:linear-gradient(90deg,#10b981,#34d399);}
.progress-bar-fill.high{background:linear-gradient(90deg,#f59e0b,#f97316);}
.progress-bar-fill.full{background:linear-gradient(90deg,#dc2626,#b91c1c);}
.cupom-usage-label{display:flex;justify-content:space-between;font-size:9px;color:rgba(255,255,255,.4);margin-bottom:2px;}
@media(max-width:768px){
    .form-row-cupom{flex-direction:column;}
    .form-row-cupom .form-group{min-width:100%;}
    .form-row-cupom .btn{width:100%;}
}
</style>

<!-- Stats Card -->
<div class="stats-card">
    <div class="stats-card-icon orange"><i class='bx bx-purchase-tag'></i></div>
    <div class="stats-card-content">
        <div class="stats-card-title">Gerenciar Cupons</div>
        <div class="stats-card-value"><?php echo $total_cupons; ?></div>
        <div class="stats-card-subtitle">cupons cadastrados no sistema</div>
    </div>
    <div class="stats-card-decoration"><i class='bx bx-purchase-tag'></i></div>
</div>

<!-- Mini Stats -->
<div class="mini-stats">
    <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_cupons; ?></div><div class="mini-stat-lbl">Total</div></div>
    <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_ativos; ?></div><div class="mini-stat-lbl">Ativos</div></div>
    <div class="mini-stat"><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_esgotados; ?></div><div class="mini-stat-lbl">Esgotados</div></div>
    <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_usado; ?></div><div class="mini-stat-lbl">Usos Total</div></div>
</div>

<!-- Create Form -->
<div class="modern-card">
    <div class="card-header-custom green">
        <div class="header-icon"><i class='bx bx-plus-circle'></i></div>
        <div><div class="header-title">Criar Novo Cupom</div><div class="header-subtitle">Preencha os campos abaixo</div></div>
    </div>
    <div class="card-body-custom">
        <form action="cupons.php" method="post" class="form-row-cupom">
            <div class="form-group">
                <label class="form-label"><i class='bx bx-tag'></i> Nome</label>
                <input type="text" name="nome" class="form-control" placeholder="Nome do Cupom" required>
            </div>
            <div class="form-group">
                <label class="form-label"><i class='bx bx-hash'></i> Código</label>
                <input type="text" name="cupom" class="form-control" value="<?php echo $cupon; ?>" placeholder="Código">
            </div>
            <div class="form-group">
                <label class="form-label"><i class='bx bx-percent'></i> Desconto</label>
                <select name="desconto" class="form-control" required>
                    <option value="">Selecione %</option>
                    <?php for($i = 1; $i <= 100; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?>%</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><i class='bx bx-repeat'></i> Limite de Usos</label>
                <input type="number" name="vezesuso" class="form-control" value="1" min="1" placeholder="Limite">
            </div>
            <div class="form-group" style="flex:0 0 auto;">
                <button type="submit" name="adicionarcupom" class="btn btn-success" style="margin-top:22px;"><i class='bx bx-save'></i> Adicionar</button>
            </div>
        </form>
    </div>
</div>

<!-- Filters -->
<div class="modern-card">
    <div class="card-header-custom blue">
        <div class="header-icon"><i class='bx bx-filter-alt'></i></div>
        <div><div class="header-title">Filtros</div><div class="header-subtitle">Busque e filtre cupons</div></div>
    </div>
    <div class="card-body-custom">
        <div class="filter-group">
            <div class="filter-item">
                <div class="filter-label">Buscar por Nome/Código</div>
                <input type="text" class="filter-input" id="searchInput" placeholder="Digite para buscar...">
            </div>
            <div class="filter-item">
                <div class="filter-label">Filtrar por Desconto</div>
                <select class="filter-select" id="discountFilter">
                    <option value="todos">📋 Todos</option>
                    <option value="0-25">0% - 25%</option>
                    <option value="26-50">26% - 50%</option>
                    <option value="51-75">51% - 75%</option>
                    <option value="76-100">76% - 100%</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Coupon Cards Grid -->
<div class="items-grid" id="cuponsGrid">
<?php
if (count($cupons_data) > 0) {
    foreach ($cupons_data as $user_data) {
        $usado = (int)($user_data['usado'] ?? 0);
        $limite = (int)$user_data['vezesuso'];
        $porcentagem = ($limite > 0) ? round(($usado / $limite) * 100) : 0;
        $desconto = (int)$user_data['desconto'];
        $bar_class = ($porcentagem >= 100) ? 'full' : (($porcentagem >= 75) ? 'high' : '');
        $status_class = ($porcentagem >= 100) ? 'badge-expirado' : 'badge-ativo';
        $status_label = ($porcentagem >= 100) ? 'Esgotado' : 'Ativo';
?>
<div class="item-card"
     data-nome="<?php echo strtolower(htmlspecialchars($user_data['nome'])); ?>"
     data-codigo="<?php echo strtolower(htmlspecialchars($user_data['cupom'])); ?>"
     data-desconto="<?php echo $desconto; ?>">
    <div class="card-header-custom orange">
        <div class="header-icon"><i class='bx bx-purchase-tag'></i></div>
        <div>
            <div class="header-title"><?php echo htmlspecialchars($user_data['nome']); ?></div>
            <div class="header-subtitle"><?php echo htmlspecialchars($user_data['cupom']); ?> &bull; <span class="<?php echo $status_class; ?>" style="padding:1px 6px;border-radius:6px;font-size:9px;"><?php echo $status_label; ?></span></div>
        </div>
    </div>
    <div class="item-card-body">
        <div class="info-grid">
            <div class="info-row"><div class="info-icon"><i class='bx bx-hash' style="color:#f59e0b;"></i></div><div class="info-content"><div class="info-label">CÓDIGO</div><div class="info-value"><?php echo htmlspecialchars($user_data['cupom']); ?></div></div></div>
            <div class="info-row"><div class="info-icon"><i class='bx bx-percent' style="color:#10b981;"></i></div><div class="info-content"><div class="info-label">DESCONTO</div><div class="info-value success"><?php echo $desconto; ?>%</div></div></div>
            <div class="info-row"><div class="info-icon"><i class='bx bx-check-circle' style="color:#818cf8;"></i></div><div class="info-content"><div class="info-label">USADO</div><div class="info-value"><?php echo $usado; ?> vez(es)</div></div></div>
            <div class="info-row"><div class="info-icon"><i class='bx bx-bar-chart-alt' style="color:#60a5fa;"></i></div><div class="info-content"><div class="info-label">LIMITE</div><div class="info-value"><?php echo $limite; ?> vez(es)</div></div></div>
        </div>
        <div style="padding:0 7px;">
            <div class="cupom-usage-label"><span>Utilização</span><span style="color:white;font-weight:600;"><?php echo $porcentagem; ?>%</span></div>
            <div class="progress-bar-wrap"><div class="progress-bar-fill <?php echo $bar_class; ?>" style="width:<?php echo $porcentagem; ?>%;"></div></div>
        </div>
    </div>
    <div class="item-card-footer">
        <button class="action-btn btn-danger" style="flex:1;" onclick="confirmarDeletar(<?php echo $user_data['id']; ?>, '<?php echo htmlspecialchars(addslashes($user_data['nome'])); ?>')"><i class='bx bx-trash'></i> Deletar</button>
    </div>
</div>
<?php
    }
} else {
    echo '<div class="empty-state"><i class="bx bx-purchase-tag"></i><h3>Nenhum cupom encontrado</h3><p>Crie seu primeiro cupom de desconto acima.</p></div>';
}
?>
</div>

<div style="text-align:center;margin-top:16px;color:rgba(255,255,255,0.3);font-size:11px;">
    Exibindo <?php echo $total_cupons; ?> cupom(ns)
</div>

<!-- Modal Confirmar Exclusão -->
<div id="modalConfirmarExclusao" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom danger"><h5><i class='bx bx-trash'></i> Confirmar Exclusão</h5><button class="modal-close" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-trash'></i></div>
        <h3 style="color:white;text-align:center;margin-bottom:8px;font-size:15px;">Deletar Cupom</h3>
        <p style="color:rgba(255,255,255,0.6);text-align:center;font-size:12px;" id="deletar-msg">Tem certeza que deseja deletar este cupom?</p>
        <form id="formDeletar" action="cupons.php" method="post">
            <input type="hidden" name="id" id="deletar-id" value="">
            <input type="hidden" name="deletar" value="1">
        </form>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" onclick="document.getElementById('formDeletar').submit()"><i class='bx bx-trash'></i> Deletar</button>
    </div>
</div></div>
</div>

<script>
function abrirModal(id){ document.getElementById(id).classList.add('show'); }
function fecharModal(id){ document.getElementById(id).classList.remove('show'); }

function confirmarDeletar(id, nome){
    document.getElementById('deletar-id').value = id;
    document.getElementById('deletar-msg').textContent = 'Tem certeza que deseja deletar o cupom "' + nome + '"?';
    abrirModal('modalConfirmarExclusao');
}

document.getElementById('searchInput').addEventListener('keyup', function(){
    var search = this.value.toLowerCase();
    document.querySelectorAll('.item-card').forEach(function(card){
        var nome = card.getAttribute('data-nome') || '';
        var codigo = card.getAttribute('data-codigo') || '';
        card.style.display = (nome.includes(search) || codigo.includes(search)) ? '' : 'none';
    });
});

document.getElementById('discountFilter').addEventListener('change', function(){
    var range = this.value;
    document.querySelectorAll('.item-card').forEach(function(card){
        if (range === 'todos') { card.style.display = ''; return; }
        var desconto = parseInt(card.getAttribute('data-desconto'));
        var parts = range.split('-');
        card.style.display = (desconto >= parseInt(parts[0]) && desconto <= parseInt(parts[1])) ? '' : 'none';
    });
});

document.addEventListener('click', function(e){ if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); });
document.addEventListener('keydown', function(e){ if(e.key==='Escape') document.querySelectorAll('.modal-overlay').forEach(function(m){ m.classList.remove('show'); }); });
</script>

</div></div></body></html>
<?php
    }
    aleatorio339313($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
