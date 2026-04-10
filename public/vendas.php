<?php
error_reporting(0);
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) exit('Erro');

$ref = $_GET['ref'] ?? '';
if (empty($ref)) exit('<h2 style="text-align:center;margin-top:100px;font-family:sans-serif;color:#666;">Link inválido</h2>');

$ref = mysqli_real_escape_string($conn, $ref);
$r = $conn->query("SELECT * FROM links_venda WHERE token='$ref' AND ativo=1 LIMIT 1");
if (!$r || $r->num_rows == 0) exit('<h2 style="text-align:center;margin-top:100px;font-family:sans-serif;color:#666;">Link não encontrado ou expirado</h2>');
$link = $r->fetch_assoc();
$rev_id = intval($link['revendedor_id']);

$conn->query("UPDATE links_venda SET visitas = visitas + 1 WHERE id = {$link['id']}");

$config = ['titulo_pagina'=>'Nossos Planos','subtitulo_pagina'=>'Escolha o melhor plano para você','cor_primaria'=>'#4158D0','cor_secundaria'=>'#C850C0','texto_rodape'=>'','mostrar_redes'=>1,'mostrar_revenda'=>1,'logo_url'=>''];
$r = $conn->query("SELECT * FROM config_pagina_vendas WHERE revendedor_id=$rev_id");
if ($r && $r->num_rows > 0) { $c = $r->fetch_assoc(); foreach ($c as $k => $v) if ($v !== null && isset($config[$k])) $config[$k] = $v; }

$redes = ['whatsapp'=>'','telegram'=>'','instagram'=>''];
$r = $conn->query("SELECT * FROM redes_sociais WHERE revendedor_id=$rev_id");
if ($r && $r->num_rows > 0) { $rd = $r->fetch_assoc(); foreach ($rd as $k => $v) if (isset($redes[$k])) $redes[$k] = $v; }

$anuncios_topo = []; $anuncios_meio = []; $anuncios_rodape = [];
$r = $conn->query("SELECT * FROM anuncios_vendas WHERE revendedor_id=$rev_id AND ativo=1 ORDER BY ordem ASC, id DESC");
if ($r) while ($row = $r->fetch_assoc()) {
    if ($row['posicao'] === 'topo') $anuncios_topo[] = $row;
    elseif ($row['posicao'] === 'meio') $anuncios_meio[] = $row;
    else $anuncios_rodape[] = $row;
}

function colEx($conn,$t,$c){$r=$conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");return($r&&$r->num_rows>0);}
$col_preco = 'valor';
if(colEx($conn,'planos','preco') && !colEx($conn,'planos','valor')) $col_preco = 'preco';

$planos_usuario = []; $planos_revenda = [];
$r = $conn->query("SELECT * FROM planos WHERE tipo='usuario' AND ativo=1 ORDER BY $col_preco ASC");
if ($r) while ($row = $r->fetch_assoc()) $planos_usuario[] = $row;
$r = $conn->query("SELECT * FROM planos WHERE tipo='revenda' AND ativo=1 ORDER BY $col_preco ASC");
if ($r) while ($row = $r->fetch_assoc()) $planos_revenda[] = $row;

function gPreco($p){return isset($p['preco'])?floatval($p['preco']):(isset($p['valor'])?floatval($p['valor']):0);}

$rev_nome = '';
$r = $conn->query("SELECT login FROM accounts WHERE id=$rev_id");
if ($r && $r->num_rows > 0) $rev_nome = $r->fetch_assoc()['login'];

$cor1 = htmlspecialchars($config['cor_primaria']);
$cor2 = htmlspecialchars($config['cor_secundaria']);
$wpp_numero = preg_replace('/[^0-9]/', '', trim($redes['whatsapp'] ?? ''));

$logo_src = '';
if(!empty($config['logo_url'])) $logo_src = (strpos($config['logo_url'],'http')===0)?$config['logo_url']:'../'.$config['logo_url'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($config['titulo_pagina']);?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
:root{--c1:<?php echo $cor1;?>;--c2:<?php echo $cor2;?>;--bg:#0a0e1a;--bg2:#111827;--bg3:#1a2035;--tx:#fff}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh;overflow-x:hidden}
a{text-decoration:none;color:inherit}
html{scroll-behavior:smooth}
.bg-b1{position:fixed;top:-200px;left:-200px;width:500px;height:500px;background:var(--c1);border-radius:50%;filter:blur(200px);opacity:.06;pointer-events:none;z-index:-1}
.bg-b2{position:fixed;bottom:-200px;right:-200px;width:500px;height:500px;background:var(--c2);border-radius:50%;filter:blur(200px);opacity:.06;pointer-events:none;z-index:-1}

.navbar{position:sticky;top:0;z-index:1000;padding:12px 20px}
.navbar-card{max-width:1200px;margin:0 auto;background:rgba(17,24,39,.85);backdrop-filter:blur(20px) saturate(180%);-webkit-backdrop-filter:blur(20px) saturate(180%);border:1px solid rgba(255,255,255,.06);border-radius:18px;padding:14px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.nav-brand{display:flex;align-items:center;gap:12px;flex:1;min-width:0}
.nav-brand img{height:40px;border-radius:10px;flex-shrink:0}
.nav-brand-info{min-width:0}
.nav-brand-name{font-size:22px;font-weight:900;background:linear-gradient(135deg,var(--c1),var(--c2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1.2}
.nav-brand-sub{font-size:10px;color:rgba(255,255,255,.35);font-weight:500;margin-top:2px}
.nav-btns{display:flex;gap:6px;flex-wrap:wrap;align-items:center;flex-shrink:0}
.nav-btn{padding:9px 18px;border:none;border-radius:12px;font-weight:700;font-size:11px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .25s;font-family:inherit;color:#fff;white-space:nowrap}
.nav-btn:hover{transform:translateY(-2px);filter:brightness(1.1);box-shadow:0 4px 15px rgba(0,0,0,.3)}
.nav-btn i{font-size:15px}
.nb-u{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.nb-r{background:linear-gradient(135deg,#a855f7,#9333ea)}
.nb-w{background:linear-gradient(135deg,#25D366,#128C7E)}

.slider-sec{max-width:1200px;margin:16px auto;padding:0 20px}
.slider-w{position:relative;overflow:hidden;border-radius:20px;border:1px solid rgba(255,255,255,.08);background:var(--bg2)}
.slider-t{display:flex;transition:transform .6s cubic-bezier(.4,0,.2,1)}
.sld{min-width:100%;overflow:hidden}
.sld-img{width:100%;height:220px;object-fit:cover;display:block}
.sld-body{padding:20px 24px;display:flex;align-items:center;gap:16px}
.sld-ic{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;flex-shrink:0;background:rgba(255,255,255,.15)}
.sld-inf{flex:1;min-width:0}
.sld-tag{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:12px;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.sld-tag.banner{background:rgba(245,158,11,.15);color:#fbbf24}
.sld-tag.destaque{background:rgba(236,72,153,.15);color:#f472b6}
.sld-tag.aviso{background:rgba(239,68,68,.15);color:#f87171}
.sld-tag.promo{background:rgba(16,185,129,.15);color:#34d399}
.sld-tt{font-size:17px;font-weight:800;margin-bottom:4px;line-height:1.3}
.sld-ds{font-size:12px;color:rgba(255,255,255,.55);line-height:1.5}
.sld-cta{display:inline-flex;align-items:center;gap:5px;margin-top:10px;padding:8px 16px;border-radius:10px;color:#fff;font-size:11px;font-weight:700;transition:all .2s;background:rgba(255,255,255,.18);text-transform:uppercase;letter-spacing:.3px;cursor:pointer;border:none;font-family:inherit}
.sld-cta:hover{transform:translateY(-1px);filter:brightness(1.15);box-shadow:0 4px 12px rgba(0,0,0,.3)}
.sl-nav{position:absolute;top:50%;transform:translateY(-50%);width:34px;height:34px;background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.1);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:5;font-size:18px;backdrop-filter:blur(8px);transition:all .2s}
.sl-nav:hover{background:rgba(0,0,0,.7);transform:translateY(-50%) scale(1.1)}
.sl-p{left:10px}.sl-n{right:10px}
.sl-dots{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);display:flex;gap:6px;z-index:5}
.sl-dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.25);cursor:pointer;transition:all .3s;border:none}
.sl-dot.on{background:#fff;width:24px;border-radius:4px}
.sl-cnt{position:absolute;top:12px;right:12px;background:rgba(0,0,0,.5);backdrop-filter:blur(8px);padding:4px 10px;border-radius:8px;font-size:10px;font-weight:600;color:rgba(255,255,255,.7);z-index:5}

.sec{max-width:1200px;margin:0 auto;padding:40px 20px}
.sec-id{scroll-margin-top:90px}
.sec-hd{text-align:center;margin-bottom:32px}
.sec-bg{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
.sec-bg.bl{background:rgba(59,130,246,.12);color:#60a5fa}
.sec-bg.pr{background:rgba(168,85,247,.12);color:#c084fc}
.sec-tt{font-size:28px;font-weight:900;margin-bottom:8px}
.sec-st{font-size:14px;color:rgba(255,255,255,.45);max-width:500px;margin:0 auto}
.sec-dv{width:60px;height:3px;border-radius:2px;background:linear-gradient(135deg,var(--c1),var(--c2));margin:12px auto 0}

/* ========== PLANOS ========== */
.pl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px}
.pl-card{background:var(--bg2);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.06);transition:all .3s;position:relative}
.pl-card:hover{transform:translateY(-6px);border-color:var(--c1);box-shadow:0 20px 50px rgba(0,0,0,.3)}

/* GLOW NÃO BLOQUEIA CLIQUES */
.pl-glow{position:absolute;top:-50%;left:-50%;width:200%;height:200%;background:radial-gradient(circle,rgba(255,255,255,.02) 0%,transparent 70%);opacity:0;transition:opacity .3s;pointer-events:none;z-index:0}
.pl-card:hover .pl-glow{opacity:1}

.pl-hd{padding:24px 20px 16px;text-align:center;position:relative;z-index:1}
.pl-hd::after{content:'';position:absolute;bottom:0;left:20px;right:20px;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.06),transparent)}
.pl-ic{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:26px;color:#fff;margin:0 auto 12px}
.pl-ic.us{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.pl-ic.rv{background:linear-gradient(135deg,#a855f7,#9333ea)}
.pl-nm{font-size:18px;font-weight:800;margin-bottom:4px}
.pl-tg{display:inline-flex;align-items:center;gap:3px;padding:3px 10px;border-radius:16px;font-size:9px;font-weight:700;text-transform:uppercase}
.pl-tg.us{background:rgba(59,130,246,.12);color:#60a5fa}
.pl-tg.rv{background:rgba(168,85,247,.12);color:#c084fc}
.pl-pw{padding:16px 20px;text-align:center;position:relative;z-index:1}
.pl-cs{font-size:16px;font-weight:700;color:rgba(255,255,255,.5);vertical-align:top}
.pl-pv{font-size:42px;font-weight:900;background:linear-gradient(135deg,var(--c1),var(--c2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
.pl-pp{font-size:12px;color:rgba(255,255,255,.35);margin-top:2px}
.pl-bd{padding:0 20px 16px;position:relative;z-index:1}
.pl-ft{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.03);font-size:12px;color:rgba(255,255,255,.7)}
.pl-ft:last-child{border-bottom:none}
.pl-ft i{width:20px;height:20px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.pl-ft i.ck{background:rgba(16,185,129,.12);color:#34d399}
.pl-ft i.if{background:rgba(59,130,246,.12);color:#60a5fa}

/* BOTÃO COMPRAR - ACIMA DE TUDO */
.pl-fo{padding:0 20px 20px;position:relative;z-index:10}
.pl-btn{
    width:100%;padding:14px;border:none;border-radius:14px;
    font-weight:800;font-size:14px;
    cursor:pointer;
    display:flex;align-items:center;justify-content:center;gap:8px;
    color:#fff;
    font-family:inherit;text-transform:uppercase;letter-spacing:.5px;
    background:linear-gradient(135deg,#25D366,#128C7E);
    position:relative;z-index:10;
    transition:all .3s;
    -webkit-appearance:none;
    appearance:none;
    outline:none;
    user-select:none;
    -webkit-tap-highlight-color:rgba(37,211,102,.3);
}
.pl-btn:hover{
    transform:translateY(-3px);
    box-shadow:0 8px 30px rgba(37,211,102,.5);
    filter:brightness(1.1);
}
.pl-btn:active{
    transform:translateY(0);
    box-shadow:0 2px 10px rgba(37,211,102,.3);
}
.pl-btn i{font-size:18px;pointer-events:none}
.pl-btn-off{width:100%;padding:14px;border:none;border-radius:14px;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;gap:8px;color:rgba(255,255,255,.4);background:rgba(255,255,255,.06);cursor:not-allowed;font-family:inherit;position:relative;z-index:10}

.ct-sec{max-width:1200px;margin:20px auto;padding:0 20px}
.ct-card{background:var(--bg2);border-radius:20px;border:1px solid rgba(255,255,255,.06);overflow:hidden;padding:32px 24px}
.ct-grid{display:flex;justify-content:center;gap:14px;flex-wrap:wrap}
.ct-item{display:flex;align-items:center;gap:10px;padding:14px 20px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:14px;transition:all .25s;min-width:200px;cursor:pointer}
.ct-item:hover{border-color:var(--c1);transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,.2)}
.ct-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0}
.ct-ic.wpp{background:linear-gradient(135deg,#25D366,#128C7E)}
.ct-ic.tg{background:linear-gradient(135deg,#0088cc,#006bb3)}
.ct-ic.ig{background:linear-gradient(135deg,#e1306c,#c13584)}
.ct-lb{font-size:9px;color:rgba(255,255,255,.4);font-weight:700;text-transform:uppercase}
.ct-vl{font-size:13px;font-weight:700}

.ft-sec{max-width:1200px;margin:20px auto;padding:0 20px 30px}
.ft-card{background:var(--bg2);border-radius:20px;border:1px solid rgba(255,255,255,.06);overflow:hidden;padding:30px 24px;text-align:center}
.ft-logo{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:14px}
.ft-logo img{height:34px;border-radius:10px}
.ft-logo-nm{font-size:18px;font-weight:900;background:linear-gradient(135deg,var(--c1),var(--c2));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.ft-txt{font-size:12px;color:rgba(255,255,255,.35);line-height:1.7;margin-bottom:16px;max-width:500px;margin-left:auto;margin-right:auto}
.ft-redes{display:flex;justify-content:center;gap:10px;margin-bottom:16px}
.ft-rd{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;transition:all .3s;border:1px solid rgba(255,255,255,.08);cursor:pointer}
.ft-rd:hover{transform:translateY(-3px);border-color:transparent;box-shadow:0 6px 18px rgba(0,0,0,.3)}
.ft-rd.wpp{background:linear-gradient(135deg,#25D366,#128C7E)}
.ft-rd.tg{background:linear-gradient(135deg,#0088cc,#0077b5)}
.ft-rd.ig{background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888)}
.ft-dv{width:60px;height:2px;background:linear-gradient(135deg,var(--c1),var(--c2));border-radius:2px;margin:0 auto 12px}
.ft-cp{font-size:10px;color:rgba(255,255,255,.2)}

@media(max-width:768px){
    .navbar{padding:8px 12px}
    .navbar-card{padding:10px 14px;border-radius:14px;flex-direction:column;align-items:stretch;gap:10px}
    .nav-brand{justify-content:center}.nav-brand-name{font-size:18px}
    .nav-btns{justify-content:center}.nav-btn{padding:7px 14px;font-size:10px}
    .slider-sec,.ct-sec,.ft-sec{padding:0 12px}
    .sld-img{height:160px}
    .sld-body{padding:14px 16px;flex-direction:column;text-align:center}
    .sld-cta{margin:10px auto 0}
    .sec{padding:24px 12px}.sec-tt{font-size:22px}
    .pl-grid{grid-template-columns:1fr}.pl-pv{font-size:34px}
    .ct-card{padding:24px 16px}.ct-grid{flex-direction:column;align-items:center}
    .ct-item{width:100%;max-width:320px}.ft-card{padding:24px 16px}
}
</style>
</head>
<body>
<div class="bg-b1"></div>
<div class="bg-b2"></div>

<nav class="navbar"><div class="navbar-card">
<div class="nav-brand">
<?php if(!empty($logo_src)):?><img src="<?php echo htmlspecialchars($logo_src);?>" alt="" onerror="this.style.display='none'"><?php endif;?>
<div class="nav-brand-info">
<div class="nav-brand-name"><?php echo htmlspecialchars($config['titulo_pagina']);?></div>
<div class="nav-brand-sub"><?php echo htmlspecialchars($config['subtitulo_pagina']);?></div>
</div>
</div>
<div class="nav-btns">
<a href="#planos-usuario" class="nav-btn nb-u"><i class='bx bx-user'></i> Usuários</a>
<?php if($config['mostrar_revenda']&&!empty($planos_revenda)):?><a href="#planos-revenda" class="nav-btn nb-r"><i class='bx bx-store'></i> Revendas</a><?php endif;?>
<?php if(!empty($wpp_numero)):?>
<button type="button" class="nav-btn nb-w" onclick="abrirWpp('Olá! Vim pela página de vendas.')"><i class='bx bxl-whatsapp'></i> Contato</button>
<?php endif;?>
</div>
</div></nav>

<?php
function renderSlider($anuncios, $sid, $redes, $wpp_numero){
    if(empty($anuncios)) return;
    $tot=count($anuncios);
?>
<div class="slider-sec"><div class="slider-w" id="<?php echo $sid;?>">
<?php if($tot>1):?><span class="sl-cnt" id="<?php echo $sid;?>C">1/<?php echo $tot;?></span><?php endif;?>
<div class="slider-t" id="<?php echo $sid;?>T">
<?php foreach($anuncios as $a):
    $img='';if(!empty($a['imagem']))$img=(strpos($a['imagem'],'http')===0)?$a['imagem']:'../'.$a['imagem'];
    $cor_a=!empty($a['cor'])?$a['cor']:'#4158D0';
    $lt=$a['link_texto']??'';
    $ltipo=$a['link_tipo']??'url';
    // Montar onclick ou href
    $onclick='';$href='';
    if($ltipo==='whatsapp'&&!empty($wpp_numero)){$onclick="abrirWpp('Vi o anúncio: ".addslashes($a['titulo'])."')";}
    elseif($ltipo==='telegram'&&!empty($redes['telegram'])){$href='https://t.me/'.$redes['telegram'];}
    elseif($ltipo==='instagram'&&!empty($redes['instagram'])){$href='https://instagram.com/'.$redes['instagram'];}
    elseif(!empty($a['link_url'])){$href=$a['link_url'];}
    elseif(!empty($wpp_numero)){$onclick="abrirWpp('Vi o anúncio: ".addslashes($a['titulo'])."')";}
?>
<div class="sld">
<?php if(!empty($img)):?><img src="<?php echo htmlspecialchars($img);?>" class="sld-img" onerror="this.style.display='none'"><?php endif;?>
<div class="sld-body" style="background:linear-gradient(135deg,<?php echo htmlspecialchars($cor_a);?>,<?php echo htmlspecialchars($cor_a);?>aa)">
<div class="sld-ic"><i class='bx <?php echo htmlspecialchars($a['icone']??'bx-megaphone');?>'></i></div>
<div class="sld-inf">
<div class="sld-tag <?php echo htmlspecialchars($a['tipo']??'banner');?>"><i class='bx <?php echo htmlspecialchars($a['icone']??'bx-megaphone');?>' style="font-size:10px"></i> <?php echo ucfirst($a['tipo']??'banner');?></div>
<div class="sld-tt"><?php echo htmlspecialchars($a['titulo']);?></div>
<?php if(!empty($a['descricao'])):?><div class="sld-ds"><?php echo htmlspecialchars($a['descricao']);?></div><?php endif;?>
<?php if(!empty($lt)):?>
    <?php if(!empty($onclick)):?>
    <button type="button" class="sld-cta" onclick="<?php echo $onclick;?>"><i class='bx bx-right-arrow-alt'></i> <?php echo htmlspecialchars($lt);?></button>
    <?php elseif(!empty($href)):?>
    <a href="<?php echo htmlspecialchars($href);?>" target="_blank" rel="noopener" class="sld-cta"><i class='bx bx-right-arrow-alt'></i> <?php echo htmlspecialchars($lt);?></a>
    <?php endif;?>
<?php endif;?>
</div></div></div>
<?php endforeach;?>
</div>
<?php if($tot>1):?>
<button type="button" class="sl-nav sl-p" onclick="sGo('<?php echo $sid;?>',-1)"><i class='bx bx-chevron-left'></i></button>
<button type="button" class="sl-nav sl-n" onclick="sGo('<?php echo $sid;?>',1)"><i class='bx bx-chevron-right'></i></button>
<div class="sl-dots" id="<?php echo $sid;?>D"><?php for($i=0;$i<$tot;$i++):?><div class="sl-dot <?php echo $i===0?'on':'';?>" onclick="sTo('<?php echo $sid;?>',<?php echo $i;?>)"></div><?php endfor;?></div>
<?php endif;?>
</div></div>
<?php } ?>

<?php renderSlider($anuncios_topo,'sTop',$redes,$wpp_numero); ?>

<!-- PLANOS USUÁRIO -->
<?php if(!empty($planos_usuario)):?>
<div class="sec sec-id" id="planos-usuario">
<div class="sec-hd"><div class="sec-bg bl"><i class='bx bx-user'></i> Planos</div><h2 class="sec-tt"><?php echo htmlspecialchars($config['titulo_pagina']);?></h2><p class="sec-st"><?php echo htmlspecialchars($config['subtitulo_pagina']);?></p><div class="sec-dv"></div></div>
<div class="pl-grid">
<?php foreach($planos_usuario as $p):
    $pv = gPreco($p);
    $pv_fmt = number_format($pv,2,',','.');
?>
<div class="pl-card">
<div class="pl-glow"></div>
<div class="pl-hd"><div class="pl-ic us"><i class='bx bx-user'></i></div><div class="pl-nm"><?php echo htmlspecialchars($p['nome']);?></div><span class="pl-tg us"><i class='bx bx-user' style="font-size:10px"></i> Usuário</span></div>
<div class="pl-pw"><span class="pl-cs">R$</span><span class="pl-pv"><?php echo $pv_fmt;?></span><div class="pl-pp">/<?php echo $p['dias'];?> dias</div></div>
<div class="pl-bd">
<div class="pl-ft"><i class='bx bx-check ck'></i> <?php echo $p['dias'];?> dias de acesso</div>
<div class="pl-ft"><i class='bx bx-check ck'></i> <?php echo $p['limite']??1;?> conexão(ões)</div>
<?php if(!empty($p['descricao'])):?><div class="pl-ft"><i class='bx bx-info-circle if'></i> <?php echo htmlspecialchars($p['descricao']);?></div><?php endif;?>
<div class="pl-ft"><i class='bx bx-check ck'></i> Suporte via WhatsApp</div>
</div>
<div class="pl-fo">
<button type="button" class="pl-btn" onclick="comprarWpp('<?php echo addslashes($p['nome']);?>','<?php echo $pv_fmt;?>','<?php echo $p['dias'];?>','<?php echo $p['limite']??1;?>','usuario')">
<i class='bx bxl-whatsapp'></i> Comprar via WhatsApp
</button>
</div>
</div>
<?php endforeach;?>
</div></div>
<?php endif;?>

<?php renderSlider($anuncios_meio,'sMid',$redes,$wpp_numero); ?>

<!-- PLANOS REVENDA -->
<?php if($config['mostrar_revenda']&&!empty($planos_revenda)):?>
<div class="sec sec-id" id="planos-revenda" style="padding-top:20px">
<div class="sec-hd"><div class="sec-bg pr"><i class='bx bx-store'></i> Revenda</div><h2 class="sec-tt">Planos de Revenda</h2><p class="sec-st">Comece a revender e lucre</p><div class="sec-dv"></div></div>
<div class="pl-grid">
<?php foreach($planos_revenda as $p):
    $pv = gPreco($p);
    $pv_fmt = number_format($pv,2,',','.');
?>
<div class="pl-card">
<div class="pl-glow"></div>
<div class="pl-hd"><div class="pl-ic rv"><i class='bx bx-store'></i></div><div class="pl-nm"><?php echo htmlspecialchars($p['nome']);?></div><span class="pl-tg rv"><i class='bx bx-store' style="font-size:10px"></i> Revenda</span></div>
<div class="pl-pw"><span class="pl-cs">R$</span><span class="pl-pv"><?php echo $pv_fmt;?></span><div class="pl-pp">/<?php echo $p['dias'];?> dias</div></div>
<div class="pl-bd">
<div class="pl-ft"><i class='bx bx-check ck'></i> <?php echo $p['dias'];?> dias de acesso</div>
<div class="pl-ft"><i class='bx bx-check ck'></i> <?php echo $p['creditos']??0;?> créditos</div>
<?php if(!empty($p['descricao'])):?><div class="pl-ft"><i class='bx bx-info-circle if'></i> <?php echo htmlspecialchars($p['descricao']);?></div><?php endif;?>
<div class="pl-ft"><i class='bx bx-check ck'></i> Painel de revenda completo</div>
<div class="pl-ft"><i class='bx bx-check ck'></i> Suporte via WhatsApp</div>
</div>
<div class="pl-fo">
<button type="button" class="pl-btn" onclick="comprarWpp('<?php echo addslashes($p['nome']);?>','<?php echo $pv_fmt;?>','<?php echo $p['dias'];?>','<?php echo $p['creditos']??0;?>','revenda')">
<i class='bx bxl-whatsapp'></i> Comprar via WhatsApp
</button>
</div>
</div>
<?php endforeach;?>
</div></div>
<?php endif;?>

<?php renderSlider($anuncios_rodape,'sBot',$redes,$wpp_numero); ?>

<!-- CONTATOS -->
<?php if($config['mostrar_redes']&&(!empty($redes['whatsapp'])||!empty($redes['telegram'])||!empty($redes['instagram']))):?>
<div class="ct-sec"><div class="ct-card">
<div class="sec-hd" style="margin-bottom:20px"><div class="sec-bg bl"><i class='bx bx-phone'></i> Contato</div><h2 class="sec-tt" style="font-size:22px">Fale Conosco</h2><div class="sec-dv"></div></div>
<div class="ct-grid">
<?php if(!empty($wpp_numero)):?><div class="ct-item" onclick="abrirWpp('Olá! Vim pela página de vendas.')" style="cursor:pointer"><div class="ct-ic wpp"><i class='bx bxl-whatsapp'></i></div><div><div class="ct-lb">WhatsApp</div><div class="ct-vl"><?php echo htmlspecialchars($redes['whatsapp']);?></div></div></div><?php endif;?>
<?php if(!empty($redes['telegram'])):?><a href="https://t.me/<?php echo htmlspecialchars($redes['telegram']);?>" target="_blank" rel="noopener" class="ct-item"><div class="ct-ic tg"><i class='bx bxl-telegram'></i></div><div><div class="ct-lb">Telegram</div><div class="ct-vl">@<?php echo htmlspecialchars($redes['telegram']);?></div></div></a><?php endif;?>
<?php if(!empty($redes['instagram'])):?><a href="https://instagram.com/<?php echo htmlspecialchars($redes['instagram']);?>" target="_blank" rel="noopener" class="ct-item"><div class="ct-ic ig"><i class='bx bxl-instagram'></i></div><div><div class="ct-lb">Instagram</div><div class="ct-vl">@<?php echo htmlspecialchars($redes['instagram']);?></div></div></a><?php endif;?>
</div></div></div>
<?php endif;?>

<!-- FOOTER -->
<div class="ft-sec"><div class="ft-card">
<div class="ft-logo">
<?php if(!empty($logo_src)):?><img src="<?php echo htmlspecialchars($logo_src);?>" alt="" onerror="this.style.display='none'"><?php endif;?>
<span class="ft-logo-nm"><?php echo htmlspecialchars($config['titulo_pagina']);?></span>
</div>
<?php if(!empty($config['texto_rodape'])):?><div class="ft-txt"><?php echo nl2br(htmlspecialchars($config['texto_rodape']));?></div><?php endif;?>
<div class="ft-redes">
<?php if(!empty($wpp_numero)):?><div class="ft-rd wpp" onclick="abrirWpp('')" style="cursor:pointer"><i class='bx bxl-whatsapp'></i></div><?php endif;?>
<?php if(!empty($redes['telegram'])):?><a href="https://t.me/<?php echo htmlspecialchars($redes['telegram']);?>" target="_blank" rel="noopener" class="ft-rd tg"><i class='bx bxl-telegram'></i></a><?php endif;?>
<?php if(!empty($redes['instagram'])):?><a href="https://instagram.com/<?php echo htmlspecialchars($redes['instagram']);?>" target="_blank" rel="noopener" class="ft-rd ig"><i class='bx bxl-instagram'></i></a><?php endif;?>
</div>
<div class="ft-dv"></div>
<div class="ft-cp"><?php echo htmlspecialchars($config['titulo_pagina']);?> &copy; <?php echo date('Y');?></div>
</div></div>

<script>
// =============================================
// WHATSAPP - NÚMERO DIRETO NO JAVASCRIPT
// =============================================
var WPP = '<?php echo $wpp_numero; ?>';

function abrirWpp(msg){
    if(!WPP || WPP === ''){
        alert('WhatsApp não configurado!');
        return;
    }
    var url = 'https://api.whatsapp.com/send?phone=' + WPP;
    if(msg && msg !== '') url += '&text=' + encodeURIComponent(msg);
    window.open(url, '_blank');
}

function comprarWpp(nome, valor, dias, extra, tipo){
    if(!WPP || WPP === ''){
        alert('WhatsApp não configurado! Peça ao revendedor para cadastrar.');
        return;
    }
    var msg;
    if(tipo === 'revenda'){
        msg = 'Olá! Tenho interesse no plano de revenda *' + nome + '*\n'
            + '💰 Valor: R$ ' + valor + '\n'
            + '📅 Dias: ' + dias + '\n'
            + '🎫 Créditos: ' + extra;
    } else {
        msg = 'Olá! Tenho interesse no plano *' + nome + '*\n'
            + '💰 Valor: R$ ' + valor + '\n'
            + '📅 Dias: ' + dias + '\n'
            + '📱 Limite: ' + extra + ' conexão(ões)';
    }
    var url = 'https://api.whatsapp.com/send?phone=' + WPP + '&text=' + encodeURIComponent(msg);
    window.open(url, '_blank');
}

// =============================================
// SLIDER
// =============================================
var S={};
function sInit(id,ms){var w=document.getElementById(id);if(!w)return;var t=document.getElementById(id+'T');if(!t)return;var n=t.children.length;if(n<2)return;S[id]={i:0,n:n,t:null,ms:ms||6000};sAuto(id);var sx=0;w.addEventListener('touchstart',function(e){sx=e.changedTouches[0].screenX},{passive:true});w.addEventListener('touchend',function(e){var ex=e.changedTouches[0].screenX;if(sx-ex>50)sGo(id,1);else if(ex-sx>50)sGo(id,-1)},{passive:true})}
function sTo(id,idx){var s=S[id];if(!s)return;s.i=idx;if(s.i>=s.n)s.i=0;if(s.i<0)s.i=s.n-1;var t=document.getElementById(id+'T');if(t)t.style.transform='translateX(-'+(s.i*100)+'%)';var d=document.querySelectorAll('#'+id+'D .sl-dot');d.forEach(function(x,j){x.classList.toggle('on',j===s.i)});var c=document.getElementById(id+'C');if(c)c.textContent=(s.i+1)+'/'+s.n;sReset(id)}
function sGo(id,dir){var s=S[id];if(s)sTo(id,s.i+dir)}
function sAuto(id){var s=S[id];if(!s)return;s.t=setInterval(function(){sTo(id,s.i+1)},s.ms)}
function sReset(id){var s=S[id];if(!s)return;clearInterval(s.t);sAuto(id)}
sInit('sTop',6000);sInit('sMid',6000);sInit('sBot',6000);
</script>
</body>
</html>