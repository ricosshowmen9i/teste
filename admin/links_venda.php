<?php
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if(!$conn){if(isset($_POST['ajax_action'])){echo 'erro_conexao';exit;}die("Erro conexão");}

$iduser = isset($_SESSION['iduser']) ? intval($_SESSION['iduser']) : 0;

// ===== CRIAR TABELAS =====
$conn->query("CREATE TABLE IF NOT EXISTS `links_venda` (
    `id` INT NOT NULL AUTO_INCREMENT, `revendedor_id` INT NOT NULL, `token` VARCHAR(64) NOT NULL,
    `url` VARCHAR(500) NOT NULL, `ativo` TINYINT(1) DEFAULT 1, `visitas` INT DEFAULT 0,
    `vendas` INT DEFAULT 0, `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS `redes_sociais` (
    `id` INT NOT NULL AUTO_INCREMENT, `revendedor_id` INT NOT NULL,
    `whatsapp` VARCHAR(30) DEFAULT '', `telegram` VARCHAR(100) DEFAULT '', `instagram` VARCHAR(100) DEFAULT '',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS `config_pagina_vendas` (
    `id` INT NOT NULL AUTO_INCREMENT, `revendedor_id` INT NOT NULL,
    `titulo_pagina` VARCHAR(200) DEFAULT 'Nossos Planos', `subtitulo_pagina` VARCHAR(500) DEFAULT '',
    `cor_primaria` VARCHAR(20) DEFAULT '#4158D0', `cor_secundaria` VARCHAR(20) DEFAULT '#C850C0',
    `texto_rodape` VARCHAR(500) DEFAULT '', `mostrar_redes` TINYINT(1) DEFAULT 1,
    `mostrar_revenda` TINYINT(1) DEFAULT 1, `logo_url` VARCHAR(500) DEFAULT '',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS `anuncios_vendas` (
    `id` INT NOT NULL AUTO_INCREMENT, `revendedor_id` INT NOT NULL, `titulo` VARCHAR(200) NOT NULL,
    `descricao` TEXT DEFAULT NULL, `tipo` VARCHAR(20) DEFAULT 'banner', `cor` VARCHAR(20) DEFAULT '#4158D0',
    `icone` VARCHAR(50) DEFAULT 'bx-megaphone', `imagem` VARCHAR(500) DEFAULT '',
    `link_url` VARCHAR(500) DEFAULT '', `link_texto` VARCHAR(100) DEFAULT '', `link_tipo` VARCHAR(20) DEFAULT 'url',
    `posicao` VARCHAR(20) DEFAULT 'topo', `ativo` TINYINT(1) DEFAULT 1, `ordem` INT DEFAULT 0,
    `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ===== HELPER =====
function colExiste($conn,$tab,$col){$r=$conn->query("SHOW COLUMNS FROM `$tab` LIKE '$col'");return($r&&$r->num_rows>0);}

// ===== DETECTAR COLUNAS REAIS DA TABELA PLANOS =====
// A tabela pode já existir com nomes diferentes (preco vs valor, etc)
$planos_existe = false;
$r = $conn->query("SHOW TABLES LIKE 'planos'");
if($r && $r->num_rows > 0) $planos_existe = true;

if(!$planos_existe){
    $conn->query("CREATE TABLE `planos` (
        `id` INT NOT NULL AUTO_INCREMENT, `nome` VARCHAR(200) NOT NULL, `descricao` TEXT DEFAULT NULL,
        `valor` DECIMAL(10,2) DEFAULT 0.00, `dias` INT DEFAULT 30, `limite` INT DEFAULT 1,
        `tipo` VARCHAR(20) DEFAULT 'usuario', `ativo` TINYINT(1) DEFAULT 1, `revendedor_id` INT DEFAULT NULL,
        `creditos` INT DEFAULT 0, `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Detectar nome real da coluna de preço: pode ser 'valor' ou 'preco'
$col_preco = 'valor'; // padrão
if(colExiste($conn,'planos','preco') && !colExiste($conn,'planos','valor')){
    $col_preco = 'preco';
} elseif(!colExiste($conn,'planos','preco') && !colExiste($conn,'planos','valor')){
    $conn->query("ALTER TABLE planos ADD COLUMN `valor` DECIMAL(10,2) DEFAULT 0.00 AFTER `descricao`");
    $col_preco = 'valor';
}

// Garantir demais colunas
if(!colExiste($conn,'planos','dias')) $conn->query("ALTER TABLE planos ADD COLUMN `dias` INT DEFAULT 30");
if(!colExiste($conn,'planos','limite')) $conn->query("ALTER TABLE planos ADD COLUMN `limite` INT DEFAULT 1");
if(!colExiste($conn,'planos','tipo')) $conn->query("ALTER TABLE planos ADD COLUMN `tipo` VARCHAR(20) DEFAULT 'usuario'");
if(!colExiste($conn,'planos','ativo')) $conn->query("ALTER TABLE planos ADD COLUMN `ativo` TINYINT(1) DEFAULT 1");
if(!colExiste($conn,'planos','revendedor_id')) $conn->query("ALTER TABLE planos ADD COLUMN `revendedor_id` INT DEFAULT NULL");
if(!colExiste($conn,'planos','creditos')) $conn->query("ALTER TABLE planos ADD COLUMN `creditos` INT DEFAULT 0");
if(!colExiste($conn,'planos','descricao')) $conn->query("ALTER TABLE planos ADD COLUMN `descricao` TEXT DEFAULT NULL");
if(!colExiste($conn,'planos','criado_em')) $conn->query("ALTER TABLE planos ADD COLUMN `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP");

// Garantir colunas anuncios
if(!colExiste($conn,'anuncios_vendas','imagem')) $conn->query("ALTER TABLE anuncios_vendas ADD COLUMN `imagem` VARCHAR(500) DEFAULT '' AFTER `icone`");
if(!colExiste($conn,'anuncios_vendas','link_tipo')) $conn->query("ALTER TABLE anuncios_vendas ADD COLUMN `link_tipo` VARCHAR(20) DEFAULT 'url' AFTER `link_texto`");

// Corrigir ENUMs
$ck=$conn->query("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='planos' AND COLUMN_NAME='tipo'");
if($ck&&$rw=$ck->fetch_assoc()){if(strtolower($rw['DATA_TYPE'])==='enum')$conn->query("ALTER TABLE planos MODIFY COLUMN tipo VARCHAR(20) DEFAULT 'usuario'");}
$ck=$conn->query("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anuncios_vendas' AND COLUMN_NAME='tipo'");
if($ck&&$rw=$ck->fetch_assoc()){if(strtolower($rw['DATA_TYPE'])==='enum')$conn->query("ALTER TABLE anuncios_vendas MODIFY COLUMN tipo VARCHAR(20) DEFAULT 'banner'");}
$ck=$conn->query("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anuncios_vendas' AND COLUMN_NAME='posicao'");
if($ck&&$rw=$ck->fetch_assoc()){if(strtolower($rw['DATA_TYPE'])==='enum')$conn->query("ALTER TABLE anuncios_vendas MODIFY COLUMN posicao VARCHAR(20) DEFAULT 'topo'");}

// Detectar TODAS as colunas reais de planos (para insert dinâmico)
$planos_cols = [];
$r = $conn->query("SHOW COLUMNS FROM planos");
if($r) while($row = $r->fetch_assoc()) $planos_cols[] = $row['Field'];

$upload_anuncios='../uploads/anuncios/';
$upload_logos='../uploads/logos/';
if(!is_dir($upload_anuncios))@mkdir($upload_anuncios,0755,true);
if(!is_dir($upload_logos))@mkdir($upload_logos,0755,true);

// ============================================================
// AJAX
// ============================================================
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_action'])){
    header('Content-Type: text/plain; charset=utf-8');
    if($iduser<1){echo 'erro_sessao';exit;}
    $act=trim($_POST['ajax_action']);

    // GERAR LINK
    if($act==='gerar_link'){
        $conn->query("DELETE FROM links_venda WHERE revendedor_id=$iduser");
        $token=md5(uniqid(mt_rand(),true).time().$iduser);
        $proto=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https://':'http://';
        $url=$proto.$_SERVER['HTTP_HOST'].'/public/vendas.php?ref='.$token;
        $url=mysqli_real_escape_string($conn,$url);$token=mysqli_real_escape_string($conn,$token);
        $ok=$conn->query("INSERT INTO links_venda (revendedor_id,token,url,ativo,criado_em) VALUES ($iduser,'$token','$url',1,NOW())");
        echo $ok?'ok':'erro_sql:'.$conn->error;exit;
    }

    if($act==='remover_link'){$conn->query("DELETE FROM links_venda WHERE revendedor_id=$iduser");echo 'ok';exit;}

    // SALVAR REDES
    if($act==='salvar_redes'){
        $w=mysqli_real_escape_string($conn,preg_replace('/[^0-9]/','',($_POST['whatsapp']??'')));
        $t=mysqli_real_escape_string($conn,str_replace('@','',trim($_POST['telegram_r']??'')));
        $i=mysqli_real_escape_string($conn,str_replace('@','',trim($_POST['instagram']??'')));
        $ck=$conn->query("SELECT id FROM redes_sociais WHERE revendedor_id=$iduser");
        if($ck&&$ck->num_rows>0)$ok=$conn->query("UPDATE redes_sociais SET whatsapp='$w',telegram='$t',instagram='$i' WHERE revendedor_id=$iduser");
        else $ok=$conn->query("INSERT INTO redes_sociais (revendedor_id,whatsapp,telegram,instagram) VALUES ($iduser,'$w','$t','$i')");
        echo $ok?'ok':'erro_sql:'.$conn->error;exit;
    }

    // SALVAR CONFIG + LOGO UPLOAD
    if($act==='salvar_config_pagina'){
        $tp=mysqli_real_escape_string($conn,trim($_POST['titulo_pagina']??'Nossos Planos'));
        $sp=mysqli_real_escape_string($conn,trim($_POST['subtitulo_pagina']??''));
        $c1=mysqli_real_escape_string($conn,trim($_POST['cor_primaria']??'#4158D0'));
        $c2=mysqli_real_escape_string($conn,trim($_POST['cor_secundaria']??'#C850C0'));
        $rd=mysqli_real_escape_string($conn,trim($_POST['texto_rodape']??''));
        $mr=isset($_POST['mostrar_redes'])?1:0;
        $mv=isset($_POST['mostrar_revenda'])?1:0;
        $lg='';
        if(isset($_FILES['logo_file'])&&$_FILES['logo_file']['error']===0){
            $ext=strtolower(pathinfo($_FILES['logo_file']['name'],PATHINFO_EXTENSION));
            if(in_array($ext,['jpg','jpeg','png','gif','webp','svg','ico'])&&$_FILES['logo_file']['size']<=5242880){
                $nm='logo_'.$iduser.'_'.time().'.'.$ext;
                if(move_uploaded_file($_FILES['logo_file']['tmp_name'],$upload_logos.$nm)){
                    $old=$conn->query("SELECT logo_url FROM config_pagina_vendas WHERE revendedor_id=$iduser");
                    if($old&&$o=$old->fetch_assoc()){if(!empty($o['logo_url'])&&strpos($o['logo_url'],'uploads/')===0&&file_exists('../'.$o['logo_url']))@unlink('../'.$o['logo_url']);}
                    $lg='uploads/logos/'.$nm;
                }
            }
        }
        if(empty($lg)&&!empty($_POST['logo_url']))$lg=mysqli_real_escape_string($conn,trim($_POST['logo_url']));
        if(isset($_POST['remover_logo'])&&$_POST['remover_logo']==='1'){
            $old=$conn->query("SELECT logo_url FROM config_pagina_vendas WHERE revendedor_id=$iduser");
            if($old&&$o=$old->fetch_assoc()){if(!empty($o['logo_url'])&&strpos($o['logo_url'],'uploads/')===0&&file_exists('../'.$o['logo_url']))@unlink('../'.$o['logo_url']);}
            $lg='';
        }
        $lg=mysqli_real_escape_string($conn,$lg);
        $ck=$conn->query("SELECT id FROM config_pagina_vendas WHERE revendedor_id=$iduser");
        if($ck&&$ck->num_rows>0){
            if(empty($lg)&&(!isset($_POST['remover_logo'])||$_POST['remover_logo']!=='1'))
                $ok=$conn->query("UPDATE config_pagina_vendas SET titulo_pagina='$tp',subtitulo_pagina='$sp',cor_primaria='$c1',cor_secundaria='$c2',texto_rodape='$rd',mostrar_redes=$mr,mostrar_revenda=$mv WHERE revendedor_id=$iduser");
            else $ok=$conn->query("UPDATE config_pagina_vendas SET titulo_pagina='$tp',subtitulo_pagina='$sp',cor_primaria='$c1',cor_secundaria='$c2',texto_rodape='$rd',mostrar_redes=$mr,mostrar_revenda=$mv,logo_url='$lg' WHERE revendedor_id=$iduser");
        }else $ok=$conn->query("INSERT INTO config_pagina_vendas (revendedor_id,titulo_pagina,subtitulo_pagina,cor_primaria,cor_secundaria,texto_rodape,mostrar_redes,mostrar_revenda,logo_url) VALUES ($iduser,'$tp','$sp','$c1','$c2','$rd',$mr,$mv,'$lg')");
        echo $ok?'ok':'erro_sql:'.$conn->error;exit;
    }

    // ===== ADICIONAR PLANO (dinâmico) =====
    if($act==='adicionar_plano'){
        $n=mysqli_real_escape_string($conn,trim($_POST['plano_nome']??''));
        $d=mysqli_real_escape_string($conn,trim($_POST['plano_descricao']??''));
        $v=floatval(str_replace(',','.',($_POST['plano_valor']??'0')));
        $di=intval($_POST['plano_dias']??30);if($di<1)$di=30;
        $l=intval($_POST['plano_limite']??1);if($l<1)$l=1;
        $t=trim($_POST['plano_tipo']??'usuario');
        if($t!=='usuario'&&$t!=='revenda')$t='usuario';
        $cr=intval($_POST['plano_creditos']??0);
        if(empty($n)){echo 'erro_nome_vazio';exit;}

        // Montar INSERT dinâmico baseado nas colunas reais
        $cols_ins = ['nome'];
        $vals_ins = ["'$n'"];

        if(in_array('descricao',$planos_cols)){ $cols_ins[]='descricao'; $vals_ins[]="'$d'"; }

        // Coluna de preço: valor OU preco
        if(in_array('valor',$planos_cols)){ $cols_ins[]='valor'; $vals_ins[]="$v"; }
        if(in_array('preco',$planos_cols)){ $cols_ins[]='preco'; $vals_ins[]="$v"; }

        if(in_array('dias',$planos_cols)){ $cols_ins[]='dias'; $vals_ins[]="$di"; }
        if(in_array('limite',$planos_cols)){ $cols_ins[]='limite'; $vals_ins[]="$l"; }
        if(in_array('tipo',$planos_cols)){ $cols_ins[]='tipo'; $vals_ins[]="'$t'"; }
        if(in_array('ativo',$planos_cols)){ $cols_ins[]='ativo'; $vals_ins[]="1"; }
        if(in_array('revendedor_id',$planos_cols)){ $cols_ins[]='revendedor_id'; $vals_ins[]="$iduser"; }
        if(in_array('creditos',$planos_cols)){ $cols_ins[]='creditos'; $vals_ins[]="$cr"; }
        if(in_array('criado_em',$planos_cols)){ $cols_ins[]='criado_em'; $vals_ins[]="NOW()"; }

        $sql="INSERT INTO planos (".implode(',',$cols_ins).") VALUES (".implode(',',$vals_ins).")";
        $ok=$conn->query($sql);
        echo $ok?'ok':'erro_sql:'.$conn->error;exit;
    }

    // ===== EDITAR PLANO (dinâmico) =====
    if($act==='editar_plano'){
        $pid=intval($_POST['plano_id']??0);
        $n=mysqli_real_escape_string($conn,trim($_POST['plano_nome']??''));
        $d=mysqli_real_escape_string($conn,trim($_POST['plano_descricao']??''));
        $v=floatval(str_replace(',','.',($_POST['plano_valor']??'0')));
        $di=intval($_POST['plano_dias']??30);if($di<1)$di=30;
        $l=intval($_POST['plano_limite']??1);if($l<1)$l=1;
        $t=trim($_POST['plano_tipo']??'usuario');
        if($t!=='usuario'&&$t!=='revenda')$t='usuario';
        $cr=intval($_POST['plano_creditos']??0);
        if($pid<1){echo 'erro_id';exit;}
        if(empty($n)){echo 'erro_nome_vazio';exit;}

        $sets=["nome='$n'"];
        if(in_array('descricao',$planos_cols)) $sets[]="descricao='$d'";
        if(in_array('valor',$planos_cols)) $sets[]="valor=$v";
        if(in_array('preco',$planos_cols)) $sets[]="preco=$v";
        if(in_array('dias',$planos_cols)) $sets[]="dias=$di";
        if(in_array('limite',$planos_cols)) $sets[]="limite=$l";
        if(in_array('tipo',$planos_cols)) $sets[]="tipo='$t'";
        if(in_array('creditos',$planos_cols)) $sets[]="creditos=$cr";

        $sql="UPDATE planos SET ".implode(',',$sets)." WHERE id=$pid";
        $ok=$conn->query($sql);
        echo $ok?'ok':'erro_sql:'.$conn->error;exit;
    }

    if($act==='toggle_plano'){$pid=intval($_POST['plano_id']??0);$conn->query("UPDATE planos SET ativo=IF(ativo=1,0,1) WHERE id=$pid");echo 'ok';exit;}
    if($act==='excluir_plano'){$pid=intval($_POST['plano_id']??0);$conn->query("DELETE FROM planos WHERE id=$pid");echo 'ok';exit;}

    // ANÚNCIOS
    if($act==='adicionar_anuncio'){
        $tt=mysqli_real_escape_string($conn,trim($_POST['titulo_a']??''));$ds=mysqli_real_escape_string($conn,trim($_POST['descricao_a']??''));
        $tp=mysqli_real_escape_string($conn,trim($_POST['tipo_a']??'banner'));$cr2=mysqli_real_escape_string($conn,trim($_POST['cor_a']??'#4158D0'));
        $ic=mysqli_real_escape_string($conn,trim($_POST['icone_a']??'bx-megaphone'));$lu=mysqli_real_escape_string($conn,trim($_POST['link_url_a']??''));
        $lt=mysqli_real_escape_string($conn,trim($_POST['link_texto_a']??''));$ltp=mysqli_real_escape_string($conn,trim($_POST['link_tipo_a']??'url'));
        $ps=mysqli_real_escape_string($conn,trim($_POST['posicao_a']??'topo'));$img='';
        if(isset($_FILES['imagem_a'])&&$_FILES['imagem_a']['error']===0){$ext=strtolower(pathinfo($_FILES['imagem_a']['name'],PATHINFO_EXTENSION));if(in_array($ext,['jpg','jpeg','png','gif','webp','svg'])&&$_FILES['imagem_a']['size']<=5242880){$nm='a_'.$iduser.'_'.time().'_'.mt_rand(1000,9999).'.'.$ext;if(move_uploaded_file($_FILES['imagem_a']['tmp_name'],$upload_anuncios.$nm))$img='uploads/anuncios/'.$nm;}}
        if(empty($img)&&!empty($_POST['imagem_url_a']))$img=mysqli_real_escape_string($conn,trim($_POST['imagem_url_a']));
        $img=mysqli_real_escape_string($conn,$img);
        $ok=$conn->query("INSERT INTO anuncios_vendas (revendedor_id,titulo,descricao,tipo,cor,icone,imagem,link_url,link_texto,link_tipo,posicao,ativo) VALUES ($iduser,'$tt','$ds','$tp','$cr2','$ic','$img','$lu','$lt','$ltp','$ps',1)");
        echo $ok?'ok':'erro_sql:'.$conn->error;exit;
    }
    if($act==='editar_anuncio'){
        $aid=intval($_POST['anuncio_id']??0);$tt=mysqli_real_escape_string($conn,trim($_POST['titulo_a']??''));
        $ds=mysqli_real_escape_string($conn,trim($_POST['descricao_a']??''));$tp=mysqli_real_escape_string($conn,trim($_POST['tipo_a']??'banner'));
        $cr2=mysqli_real_escape_string($conn,trim($_POST['cor_a']??'#4158D0'));$ic=mysqli_real_escape_string($conn,trim($_POST['icone_a']??'bx-megaphone'));
        $lu=mysqli_real_escape_string($conn,trim($_POST['link_url_a']??''));$lt=mysqli_real_escape_string($conn,trim($_POST['link_texto_a']??''));
        $ltp=mysqli_real_escape_string($conn,trim($_POST['link_tipo_a']??'url'));$ps=mysqli_real_escape_string($conn,trim($_POST['posicao_a']??'topo'));
        $img_sql='';
        if(isset($_FILES['imagem_a'])&&$_FILES['imagem_a']['error']===0){$ext=strtolower(pathinfo($_FILES['imagem_a']['name'],PATHINFO_EXTENSION));if(in_array($ext,['jpg','jpeg','png','gif','webp','svg'])&&$_FILES['imagem_a']['size']<=5242880){$nm='a_'.$iduser.'_'.time().'_'.mt_rand(1000,9999).'.'.$ext;if(move_uploaded_file($_FILES['imagem_a']['tmp_name'],$upload_anuncios.$nm)){$old=$conn->query("SELECT imagem FROM anuncios_vendas WHERE id=$aid AND revendedor_id=$iduser");if($old&&$o=$old->fetch_assoc()){if(!empty($o['imagem'])&&strpos($o['imagem'],'uploads/')===0&&file_exists('../'.$o['imagem']))@unlink('../'.$o['imagem']);}$img_sql=",imagem='uploads/anuncios/$nm'";}}}
        elseif(!empty($_POST['imagem_url_a'])){$iu=mysqli_real_escape_string($conn,trim($_POST['imagem_url_a']));$img_sql=",imagem='$iu'";}
        if(isset($_POST['remover_imagem'])&&$_POST['remover_imagem']==='1'){$old=$conn->query("SELECT imagem FROM anuncios_vendas WHERE id=$aid AND revendedor_id=$iduser");if($old&&$o=$old->fetch_assoc()){if(!empty($o['imagem'])&&strpos($o['imagem'],'uploads/')===0&&file_exists('../'.$o['imagem']))@unlink('../'.$o['imagem']);}$img_sql=",imagem=''";}
        $ok=$conn->query("UPDATE anuncios_vendas SET titulo='$tt',descricao='$ds',tipo='$tp',cor='$cr2',icone='$ic',link_url='$lu',link_texto='$lt',link_tipo='$ltp',posicao='$ps' $img_sql WHERE id=$aid AND revendedor_id=$iduser");
        echo $ok?'ok':'erro_sql:'.$conn->error;exit;
    }
    if($act==='toggle_anuncio'){$aid=intval($_POST['anuncio_id']??0);$conn->query("UPDATE anuncios_vendas SET ativo=IF(ativo=1,0,1) WHERE id=$aid AND revendedor_id=$iduser");echo 'ok';exit;}
    if($act==='excluir_anuncio'){$aid=intval($_POST['anuncio_id']??0);$old=$conn->query("SELECT imagem FROM anuncios_vendas WHERE id=$aid AND revendedor_id=$iduser");if($old&&$o=$old->fetch_assoc()){if(!empty($o['imagem'])&&strpos($o['imagem'],'uploads/')===0&&file_exists('../'.$o['imagem']))@unlink('../'.$o['imagem']);}$conn->query("DELETE FROM anuncios_vendas WHERE id=$aid AND revendedor_id=$iduser");echo 'ok';exit;}

    echo 'erro_acao';exit;
}

// ============================================================
// RENDERIZAÇÃO
// ============================================================
include('headeradmin2.php');
if(file_exists('../AegisCore/temas.php')){include_once '../AegisCore/temas.php';$temaAtual=initTemas($conn);}else{$temaAtual=[];}
if(!file_exists('suspenderrev.php')){exit("<script>alert('Token Invalido!');</script>");}else{include_once 'suspenderrev.php';}
if(!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'])||!isset($_SESSION['token'])||$_SESSION['tokenatual']!=$_SESSION['token']||(isset($_SESSION['token_invalido_'])&&$_SESSION['token_invalido_']===true)){if(function_exists('security')){security();}else{echo "<script>alert('Token Inválido!');location.href='../index.php';</script>";$_SESSION['token_invalido_']=true;exit;}}

// DADOS
$link=null;$r=$conn->query("SELECT * FROM links_venda WHERE revendedor_id=$iduser AND ativo=1 ORDER BY id DESC LIMIT 1");
if($r&&$r->num_rows>0)$link=$r->fetch_assoc();

$redes=['whatsapp'=>'','telegram'=>'','instagram'=>''];
$r=$conn->query("SELECT * FROM redes_sociais WHERE revendedor_id=$iduser");
if($r&&$r->num_rows>0){$rd=$r->fetch_assoc();$redes['whatsapp']=$rd['whatsapp']??'';$redes['telegram']=$rd['telegram']??'';$redes['instagram']=$rd['instagram']??'';}

$cp=['titulo_pagina'=>'Nossos Planos','subtitulo_pagina'=>'','cor_primaria'=>'#4158D0','cor_secundaria'=>'#C850C0','texto_rodape'=>'','mostrar_redes'=>1,'mostrar_revenda'=>1,'logo_url'=>''];
$r=$conn->query("SELECT * FROM config_pagina_vendas WHERE revendedor_id=$iduser");
if($r&&$r->num_rows>0){$c=$r->fetch_assoc();foreach($c as $k=>$v)if(isset($cp[$k])&&$v!==null)$cp[$k]=$v;}

$anuncios=[];$r=$conn->query("SELECT * FROM anuncios_vendas WHERE revendedor_id=$iduser ORDER BY ordem ASC, id DESC");
if($r)while($row=$r->fetch_assoc())$anuncios[]=$row;

// Planos - usar coluna real de preço
$pu=[];$pr=[];
$r=$conn->query("SELECT * FROM planos WHERE tipo='usuario' ORDER BY $col_preco ASC");if($r)while($row=$r->fetch_assoc())$pu[]=$row;
$r=$conn->query("SELECT * FROM planos WHERE tipo='revenda' ORDER BY $col_preco ASC");if($r)while($row=$r->fetch_assoc())$pr[]=$row;

$tu=0;$tr=0;foreach($pu as $p)if($p['ativo'])$tu++;foreach($pr as $p)if($p['ativo'])$tr++;
$gw=[];$gw_on=false;$r=$conn->query("SELECT accesstoken,public_key FROM accounts WHERE id=$iduser");
if($r&&$r->num_rows>0){$gw=$r->fetch_assoc();$gw_on=!empty($gw['accesstoken'])&&!empty($gw['public_key']);}
$tv=0;$r=$conn->query("SELECT SUM(visitas) as v FROM links_venda WHERE revendedor_id=$iduser");
if($r&&$row=$r->fetch_assoc())$tv=intval($row['v']??0);
$aa=0;foreach($anuncios as $a)if($a['ativo'])$aa++;
$logo_src='';if(!empty($cp['logo_url'])){$logo_src=(strpos($cp['logo_url'],'http')===0)?$cp['logo_url']:'../'.$cp['logo_url'];}

// Helper para pegar preço do plano
function getPreco($p, $col) { return isset($p[$col]) ? floatval($p[$col]) : (isset($p['valor']) ? floatval($p['valor']) : (isset($p['preco']) ? floatval($p['preco']) : 0)); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Página de Vendas</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php if(function_exists('getCSSVariables'))echo getCSSVariables($temaAtual);else echo':root{--primaria:#4158D0;--secundaria:#C850C0;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#fff;}'; ?>
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh}.app-content{margin-left:0!important;padding:0!important}.content-wrapper{max-width:1700px;margin:0 auto!important;padding:20px!important}
.sc{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s}.sc:hover{transform:translateY(-2px);border-color:var(--primaria)}.sc-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primaria),var(--secundaria));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:#fff;flex-shrink:0}.sc-c{flex:1}.sc-t{font-size:13px;font-weight:600;color:rgba(255,255,255,.5);text-transform:uppercase;margin-bottom:5px}.sc-v{font-size:36px;font-weight:800;background:linear-gradient(135deg,#fff,var(--primaria));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1}.sc-s{font-size:12px;color:rgba(255,255,255,.4);margin-top:4px}.sc-d{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:.05}
.ms{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}.ms-i{flex:1;min-width:90px;background:rgba(255,255,255,.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,.06);text-align:center;transition:all .2s}.ms-i:hover{border-color:var(--primaria);transform:translateY(-2px)}.ms-v{font-size:18px;font-weight:800}.ms-l{font-size:9px;color:rgba(255,255,255,.35);text-transform:uppercase;margin-top:2px}
.mc{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;margin-bottom:16px;transition:all .2s}.mc:hover{border-color:var(--primaria)}.ch{padding:14px 18px;display:flex;align-items:center;gap:12px}.ch.grn{background:linear-gradient(135deg,#10b981,#059669)}.ch.blu{background:linear-gradient(135deg,#3b82f6,#2563eb)}.ch.org{background:linear-gradient(135deg,#f59e0b,#f97316)}.ch.pnk{background:linear-gradient(135deg,#ec4899,#db2777)}.ch.cyn{background:linear-gradient(135deg,#06b6d4,#0891b2)}.ch.vlt{background:linear-gradient(135deg,#a855f7,#9333ea)}.ch.ind{background:linear-gradient(135deg,#6366f1,#4f46e5)}.hi{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff}.ht{font-size:14px;font-weight:700;color:#fff}.hs{font-size:10px;color:rgba(255,255,255,.7)}.hr{margin-left:auto;flex-shrink:0}.cb{padding:16px}
.tn{display:flex;gap:5px;margin-bottom:16px;overflow-x:auto;flex-wrap:wrap}.tb{padding:9px 18px;border:none;border-radius:10px;font-weight:700;font-size:10px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .2s;font-family:inherit;white-space:nowrap}.tb i{font-size:14px}.tb:hover{transform:translateY(-1px);filter:brightness(1.1)}.tb[data-t="link"]{background:rgba(16,185,129,.08);border:1.5px solid rgba(16,185,129,.15);color:rgba(16,185,129,.8)}.tb[data-t="link"].on{background:linear-gradient(135deg,#10b981,#059669);color:#fff;border-color:transparent;box-shadow:0 4px 12px rgba(16,185,129,.35)}.tb[data-t="planos"]{background:rgba(99,102,241,.08);border:1.5px solid rgba(99,102,241,.15);color:rgba(99,102,241,.8)}.tb[data-t="planos"].on{background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border-color:transparent}.tb[data-t="anuncios"]{background:rgba(245,158,11,.08);border:1.5px solid rgba(245,158,11,.15);color:rgba(245,158,11,.8)}.tb[data-t="anuncios"].on{background:linear-gradient(135deg,#f59e0b,#f97316);color:#fff;border-color:transparent}.tb[data-t="config"]{background:rgba(236,72,153,.08);border:1.5px solid rgba(236,72,153,.15);color:rgba(236,72,153,.8)}.tb[data-t="config"].on{background:linear-gradient(135deg,#ec4899,#db2777);color:#fff;border-color:transparent}.tb[data-t="gw"]{background:rgba(6,182,212,.08);border:1.5px solid rgba(6,182,212,.15);color:rgba(6,182,212,.8)}.tb[data-t="gw"].on{background:linear-gradient(135deg,#06b6d4,#0891b2);color:#fff;border-color:transparent}.tb[data-t="redes"]{background:rgba(168,85,247,.08);border:1.5px solid rgba(168,85,247,.15);color:rgba(168,85,247,.8)}.tb[data-t="redes"].on{background:linear-gradient(135deg,#a855f7,#9333ea);color:#fff;border-color:transparent}.tp{display:none}.tp.on{display:block}
.ig{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}.ic{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;transition:all .2s;border:1px solid rgba(255,255,255,.08)}.ic:hover{transform:translateY(-2px);border-color:var(--primaria)}.ic.off{opacity:.55}.ih{padding:12px;display:flex;align-items:center;justify-content:space-between}.ih.us{background:linear-gradient(135deg,#3b82f6,#2563eb)}.ih.rv{background:linear-gradient(135deg,#a855f7,#9333ea)}.ih.bnh{background:linear-gradient(135deg,#f59e0b,#f97316)}.ih.dsh{background:linear-gradient(135deg,#ec4899,#db2777)}.ih.avh{background:linear-gradient(135deg,#ef4444,#dc2626)}.ih.prh{background:linear-gradient(135deg,#10b981,#059669)}.ii{display:flex;align-items:center;gap:10px;flex:1;min-width:0}.ia{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}.it{flex:1;min-width:0}.in{font-size:14px;font-weight:700;color:#fff;display:flex;align-items:center;gap:5px;word-break:break-all}.is{font-size:10px;color:rgba(255,255,255,.7);margin-top:2px;display:flex;align-items:center;gap:4px}.ip{font-size:18px;font-weight:800;color:#fff;flex-shrink:0;background:rgba(255,255,255,.15);padding:4px 10px;border-radius:8px}.ibg{background:rgba(255,255,255,.2);padding:2px 6px;border-radius:20px;font-size:8px;font-weight:600}.ib{padding:12px}.aim{width:100%;height:130px;object-fit:cover;display:block}
.sr{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px}.si{display:flex;align-items:center;gap:6px;padding:6px 8px;background:rgba(255,255,255,.03);border-radius:8px}.six{width:26px;height:26px;background:rgba(255,255,255,.05);border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}.sc2{flex:1}.sl{font-size:8px;color:rgba(255,255,255,.4);font-weight:600;margin-bottom:1px}.sv{font-size:11px;font-weight:600}.sb{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:16px;font-size:9px;font-weight:600}.sbon{background:rgba(16,185,129,.2);color:#10b981}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px}.fr{display:flex;align-items:center;gap:5px;padding:5px 7px;background:rgba(255,255,255,.03);border-radius:7px}.fi{width:22px;height:22px;background:rgba(255,255,255,.05);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}.fc{flex:1;min-width:0}.fll{font-size:8px;color:rgba(255,255,255,.4);font-weight:600}.fv{font-size:10px;font-weight:600;word-break:break-all;color:var(--texto,#fff)}.fvs{color:#34d399}.fvd{color:#f87171}
.ia2{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}.ab{flex:1;min-width:60px;padding:6px 8px;border:none;border-radius:8px;font-weight:600;font-size:10px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:4px;color:#fff;transition:all .2s;font-family:inherit}.ab:hover{transform:translateY(-1px);filter:brightness(1.05)}.be{background:linear-gradient(135deg,#4158D0,#6366f1)}.bg2{background:linear-gradient(135deg,#10b981,#059669)}.bw{background:linear-gradient(135deg,#f59e0b,#f97316)}.bd{background:linear-gradient(135deg,#dc2626,#b91c1c)}.bc{background:linear-gradient(135deg,#3b82f6,#2563eb)}.bv{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.lbox{background:rgba(255,255,255,.04);border:1.5px solid rgba(16,185,129,.15);border-radius:12px;padding:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px}.lurl{flex:1;min-width:0;font-family:monospace;font-size:11px;color:#34d399;word-break:break-all}.lbtns{display:flex;gap:4px;flex-shrink:0}
.fl{font-size:9px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:flex;align-items:center;gap:5px}.fl i{font-size:14px}.fin,.fsl{width:100%;padding:8px 12px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);border-radius:9px;font-size:12px;color:#fff!important;transition:all .2s;font-family:inherit;outline:none}.fin:focus,.fsl:focus{border-color:var(--primaria);background:rgba(255,255,255,.09)}.fin::placeholder{color:rgba(255,255,255,.3)}.fsl{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}.fsl option{background:#1e293b;color:#fff!important}textarea.fin{resize:vertical;min-height:60px}.fmg{margin-bottom:12px}.fh{font-size:9px;color:rgba(255,255,255,.25);margin-top:3px}.frow{display:grid;grid-template-columns:1fr 1fr;gap:10px}.frow3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
.cw{display:flex;align-items:center;gap:8px}.cp{width:32px;height:32px;border-radius:8px;border:2px solid rgba(255,255,255,.1);flex-shrink:0;cursor:pointer}input[type="color"]{position:absolute;opacity:0;width:0;height:0}
.uz{border:2px dashed rgba(255,255,255,.12);border-radius:12px;padding:16px;text-align:center;cursor:pointer;transition:all .3s;position:relative;overflow:hidden}.uz:hover{border-color:var(--primaria);background:rgba(255,255,255,.03)}.uz.hi2{border-color:rgba(16,185,129,.3);padding:8px}.uz input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;z-index:2}.uph{pointer-events:none}.uph i{font-size:28px;color:rgba(255,255,255,.15);display:block;margin-bottom:6px}.uph p{font-size:10px;color:rgba(255,255,255,.3)}.uph span{font-size:8px;color:rgba(255,255,255,.2);display:block;margin-top:3px}.upv{position:relative;display:inline-block}.upv img{max-width:100%;max-height:150px;border-radius:8px;display:block;margin:0 auto}.upr{position:absolute;top:4px;right:4px;width:22px;height:22px;background:rgba(220,38,38,.9);border-radius:6px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;cursor:pointer;z-index:3;border:none}.uor{display:flex;align-items:center;gap:8px;margin:8px 0;color:rgba(255,255,255,.2);font-size:9px}.uor::before,.uor::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.06)}
.tgr{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;background:rgba(255,255,255,.03);border-radius:8px;margin-bottom:5px}.tgl{font-size:11px;font-weight:600;display:flex;align-items:center;gap:6px}.tgl i{font-size:15px}.tgs{position:relative;width:40px;height:22px;background:rgba(255,255,255,.1);border-radius:11px;cursor:pointer;transition:all .3s}.tgs.on{background:linear-gradient(135deg,#10b981,#059669)}.tgs::after{content:'';position:absolute;width:18px;height:18px;background:#fff;border-radius:50%;top:2px;left:2px;transition:all .3s;box-shadow:0 1px 3px rgba(0,0,0,.3)}.tgs.on::after{left:20px}
.es{grid-column:1/-1;text-align:center;padding:40px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,.08)}.es i{font-size:48px;color:rgba(255,255,255,.15);margin-bottom:10px}.es h3{font-size:15px;margin-bottom:6px}.es p{font-size:11px;color:rgba(255,255,255,.3)}.pi{text-align:center;margin-top:10px;color:rgba(255,255,255,.3);font-size:10px}
.mo{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(8px);padding:16px}.mo.show{display:flex}.mc2{animation:mi .3s ease;max-width:500px;width:92%}@keyframes mi{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}.mcc{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5)}.mh{padding:14px 18px;display:flex;align-items:center;justify-content:space-between}.mh h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff}.mh.suc{background:linear-gradient(135deg,#10b981,#059669)}.mh.err{background:linear-gradient(135deg,#dc2626,#b91c1c)}.mh.proc{background:linear-gradient(135deg,var(--primaria),var(--secundaria))}.mh.orng{background:linear-gradient(135deg,#f59e0b,#f97316)}.mh.indig{background:linear-gradient(135deg,#6366f1,#4f46e5)}.mx{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s}.mx:hover{background:rgba(255,255,255,.25);transform:rotate(90deg)}.mb{padding:18px;max-height:70vh;overflow-y:auto}.mf{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:center;gap:8px}.mic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:ip .5s cubic-bezier(.34,1.56,.64,1) .15s both}@keyframes ip{0%{transform:scale(0)}100%{transform:scale(1)}}.mic.suc{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3)}.mic.err{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3)}.bm{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:#fff;transition:all .2s;font-family:inherit}.bm:hover{transform:translateY(-1px);filter:brightness(1.08)}.bm-c{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}.bm-ok{background:linear-gradient(135deg,#10b981,#059669)}.bm-d{background:linear-gradient(135deg,#dc2626,#b91c1c)}.spw{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0}.spr{width:44px;height:44px;border:3px solid rgba(255,255,255,.08);border-top-color:var(--primaria);border-right-color:var(--secundaria);border-radius:50%;animation:sp .8s linear infinite}@keyframes sp{to{transform:rotate(360deg)}}.tst{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:ti .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3)}.tst.ok{background:linear-gradient(135deg,#10b981,#059669)}.tst.er{background:linear-gradient(135deg,#dc2626,#b91c1c)}@keyframes ti{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}.mb::-webkit-scrollbar{width:4px}.mb::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:10px}
.logo-preview-wrap{display:flex;align-items:center;gap:12px;padding:10px;background:rgba(255,255,255,.03);border-radius:10px;margin-bottom:8px}.logo-preview-img{max-height:50px;border-radius:8px}.logo-preview-rm{background:rgba(220,38,38,.8);border:none;color:#fff;padding:4px 8px;border-radius:6px;font-size:10px;cursor:pointer;font-family:inherit;font-weight:600}
@media(max-width:768px){.app-content{margin-left:0!important}.content-wrapper{padding:10px!important}.ig{grid-template-columns:1fr}.sc{padding:14px;gap:14px}.sc-icon{width:48px;height:48px;font-size:24px}.sc-v{font-size:28px}.frow,.frow3{grid-template-columns:1fr}.ia2{display:grid;grid-template-columns:repeat(3,1fr)}.lbox{flex-direction:column;align-items:flex-start}}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content"><div class="content-overlay"></div><div class="content-wrapper">

<div class="sc"><div class="sc-icon"><i class='bx bx-store-alt'></i></div><div class="sc-c"><div class="sc-t">Página de Vendas</div><div class="sc-v">Configurações</div><div class="sc-s">Link, planos, anúncios e personalização</div></div><div class="sc-d"><i class='bx bx-store-alt'></i></div></div>

<div class="ms"><div class="ms-i"><div class="ms-v" style="color:<?php echo $link?'#34d399':'#f87171';?>"><?php echo $link?'ON':'OFF';?></div><div class="ms-l">Link</div></div><div class="ms-i"><div class="ms-v" style="color:#818cf8"><?php echo $tv;?></div><div class="ms-l">Visitas</div></div><div class="ms-i"><div class="ms-v" style="color:#34d399"><?php echo $tu;?></div><div class="ms-l">P.Usuário</div></div><div class="ms-i"><div class="ms-v" style="color:#e879f9"><?php echo $tr;?></div><div class="ms-l">P.Revenda</div></div><div class="ms-i"><div class="ms-v" style="color:#fbbf24"><?php echo $aa;?></div><div class="ms-l">Anúncios</div></div></div>

<div class="mc"><div class="ch blu"><div class="hi"><i class='bx bx-menu'></i></div><div><div class="ht">Navegação</div><div class="hs">Selecione</div></div></div><div class="cb"><div class="tn">
<button class="tb on" data-t="link" onclick="stb('link',this)"><i class='bx bx-link-alt'></i> Link</button>
<button class="tb" data-t="planos" onclick="stb('planos',this)"><i class='bx bx-package'></i> Planos</button>
<button class="tb" data-t="anuncios" onclick="stb('anuncios',this)"><i class='bx bx-megaphone'></i> Anúncios</button>
<button class="tb" data-t="config" onclick="stb('config',this)"><i class='bx bx-palette'></i> Personalizar</button>
<button class="tb" data-t="gw" onclick="stb('gw',this)"><i class='bx bx-credit-card'></i> Gateway</button>
<button class="tb" data-t="redes" onclick="stb('redes',this)"><i class='bx bx-share-alt'></i> Redes</button>
</div></div></div>

<!-- LINK --><div class="tp on" id="t-link"><div class="mc"><div class="ch grn"><div class="hi"><i class='bx bx-link-alt'></i></div><div><div class="ht">Link</div><div class="hs">Gerar novo apaga anterior</div></div><div class="hr"><button class="ab bg2" onclick="gerarLink()" style="padding:8px 14px"><i class='bx bx-refresh'></i> <?php echo $link?'Gerar Novo':'Gerar Link';?></button></div></div><div class="cb"><?php if($link):?><div class="lbox"><span class="lurl" id="lu"><?php echo htmlspecialchars($link['url']);?></span><div class="lbtns"><button class="ab bc" onclick="copL()" style="min-width:auto"><i class='bx bx-copy'></i> Copiar</button><button class="ab be" onclick="window.open('<?php echo htmlspecialchars($link['url']);?>','_blank')" style="min-width:auto"><i class='bx bx-link-external'></i></button><button class="ab bd" onclick="conf('Remover?','',function(){envS('ajax_action=remover_link','Removido!')})" style="min-width:auto"><i class='bx bx-trash'></i></button></div></div><div class="sr"><div class="si"><div class="six"><i class='bx bx-check-circle' style="color:#34d399"></i></div><div class="sc2"><div class="sl">STATUS</div><span class="sb sbon">Ativo</span></div></div><div class="si"><div class="six"><i class='bx bx-show' style="color:#818cf8"></i></div><div class="sc2"><div class="sl">VISITAS</div><div class="sv"><?php echo $link['visitas'];?></div></div></div></div><?php else:?><div class="es"><i class='bx bx-link-alt'></i><h3>Nenhum link</h3><p>Clique em Gerar Link</p></div><?php endif;?></div></div></div>

<!-- PLANOS --><div class="tp" id="t-planos">
<div class="mc"><div class="ch blu"><div class="hi"><i class='bx bx-user'></i></div><div><div class="ht">Planos Usuário</div><div class="hs"><?php echo count($pu);?></div></div><div class="hr"><button class="ab bc" onclick="abrP('usuario')" style="padding:8px 14px"><i class='bx bx-plus'></i> Novo</button></div></div><div class="cb"><?php if(!empty($pu)):?><div class="ig"><?php foreach($pu as $p):$pv=getPreco($p,$col_preco);?>
<div class="ic <?php echo $p['ativo']?'':'off';?>"><div class="ih us"><div class="ii"><div class="ia"><i class='bx bx-user'></i></div><div class="it"><div class="in"><?php echo htmlspecialchars($p['nome']);?> <span class="ibg">Usuário</span></div><div class="is">#<?php echo $p['id'];?></div></div></div><div class="ip">R$ <?php echo number_format($pv,2,',','.');?></div></div><div class="ib"><div class="fg"><div class="fr"><div class="fi"><i class='bx bx-calendar' style="color:#fbbf24"></i></div><div class="fc"><div class="fll">DIAS</div><div class="fv"><?php echo $p['dias']??30;?></div></div></div><div class="fr"><div class="fi"><i class='bx bx-devices' style="color:#60a5fa"></i></div><div class="fc"><div class="fll">LIMITE</div><div class="fv"><?php echo $p['limite']??1;?></div></div></div></div><div class="ia2"><button class="ab be" onclick='edP(<?php echo json_encode(array_merge($p,["_preco"=>$pv]),JSON_HEX_APOS|JSON_HEX_QUOT);?>)'><i class='bx bx-edit'></i> Editar</button><button class="ab <?php echo $p['ativo']?'bw':'bg2';?>" onclick="envS('ajax_action=toggle_plano&plano_id=<?php echo $p['id'];?>','OK!')"><i class='bx <?php echo $p['ativo']?'bx-hide':'bx-show';?>'></i></button><button class="ab bd" onclick="conf('Excluir?','',function(){envS('ajax_action=excluir_plano&plano_id=<?php echo $p['id'];?>','Excluído!')})"><i class='bx bx-trash'></i></button></div></div></div>
<?php endforeach;?></div><?php else:?><div class="es"><i class='bx bx-user'></i><h3>Nenhum plano</h3><p>Crie planos</p></div><?php endif;?></div></div>

<div class="mc"><div class="ch vlt"><div class="hi"><i class='bx bx-store'></i></div><div><div class="ht">Planos Revenda</div><div class="hs"><?php echo count($pr);?></div></div><div class="hr"><button class="ab bv" onclick="abrP('revenda')" style="padding:8px 14px"><i class='bx bx-plus'></i> Novo</button></div></div><div class="cb"><?php if(!empty($pr)):?><div class="ig"><?php foreach($pr as $p):$pv=getPreco($p,$col_preco);?>
<div class="ic <?php echo $p['ativo']?'':'off';?>"><div class="ih rv"><div class="ii"><div class="ia"><i class='bx bx-store'></i></div><div class="it"><div class="in"><?php echo htmlspecialchars($p['nome']);?> <span class="ibg">Revenda</span></div><div class="is">#<?php echo $p['id'];?></div></div></div><div class="ip">R$ <?php echo number_format($pv,2,',','.');?></div></div><div class="ib"><div class="fg"><div class="fr"><div class="fi"><i class='bx bx-calendar' style="color:#fbbf24"></i></div><div class="fc"><div class="fll">DIAS</div><div class="fv"><?php echo $p['dias']??30;?></div></div></div><div class="fr"><div class="fi"><i class='bx bx-coin' style="color:#34d399"></i></div><div class="fc"><div class="fll">CRÉDITOS</div><div class="fv"><?php echo $p['creditos']??0;?></div></div></div></div><div class="ia2"><button class="ab be" onclick='edP(<?php echo json_encode(array_merge($p,["_preco"=>$pv]),JSON_HEX_APOS|JSON_HEX_QUOT);?>)'><i class='bx bx-edit'></i> Editar</button><button class="ab <?php echo $p['ativo']?'bw':'bg2';?>" onclick="envS('ajax_action=toggle_plano&plano_id=<?php echo $p['id'];?>','OK!')"><i class='bx <?php echo $p['ativo']?'bx-hide':'bx-show';?>'></i></button><button class="ab bd" onclick="conf('Excluir?','',function(){envS('ajax_action=excluir_plano&plano_id=<?php echo $p['id'];?>','Excluído!')})"><i class='bx bx-trash'></i></button></div></div></div>
<?php endforeach;?></div><?php else:?><div class="es"><i class='bx bx-store'></i><h3>Nenhum plano</h3><p>Crie planos</p></div><?php endif;?></div></div></div>

<!-- ANÚNCIOS --><div class="tp" id="t-anuncios"><div class="mc"><div class="ch org"><div class="hi"><i class='bx bx-megaphone'></i></div><div><div class="ht">Anúncios</div><div class="hs"><?php echo count($anuncios);?></div></div><div class="hr"><button class="ab bw" onclick="abrA()" style="padding:8px 14px"><i class='bx bx-plus'></i> Novo</button></div></div><div class="cb"><?php if(!empty($anuncios)):?><div class="ig"><?php foreach($anuncios as $a):$ims='';if(!empty($a['imagem']))$ims=(strpos($a['imagem'],'http')===0)?$a['imagem']:'../'.$a['imagem'];$hc2=$a['tipo']==='banner'?'bnh':($a['tipo']==='destaque'?'dsh':($a['tipo']==='aviso'?'avh':'prh'));?><div class="ic <?php echo $a['ativo']?'':'off';?>"><div class="ih <?php echo $hc2;?>"><div class="ii"><div class="ia"><i class='bx <?php echo htmlspecialchars($a['icone']);?>'></i></div><div class="it"><div class="in"><?php echo htmlspecialchars($a['titulo']);?></div><div class="is"><i class='bx bx-map-pin'></i> <?php echo ucfirst($a['posicao']);?> · <?php echo ucfirst($a['tipo']);?></div></div></div></div><?php if(!empty($ims)):?><img class="aim" src="<?php echo htmlspecialchars($ims);?>" onerror="this.style.display='none'"><?php endif;?><div class="ib"><div class="ia2"><button class="ab be" onclick="edA(<?php echo $a['id'];?>)"><i class='bx bx-edit'></i> Editar</button><button class="ab <?php echo $a['ativo']?'bw':'bg2';?>" onclick="envS('ajax_action=toggle_anuncio&anuncio_id=<?php echo $a['id'];?>','OK!')"><i class='bx <?php echo $a['ativo']?'bx-hide':'bx-show';?>'></i></button><button class="ab bd" onclick="conf('Excluir?','',function(){envS('ajax_action=excluir_anuncio&anuncio_id=<?php echo $a['id'];?>','Excluído!')})"><i class='bx bx-trash'></i></button></div></div></div><?php endforeach;?></div><?php else:?><div class="es"><i class='bx bx-megaphone'></i><h3>Nenhum anúncio</h3><p>Crie banners</p></div><?php endif;?></div></div></div>

<!-- CONFIG --><div class="tp" id="t-config"><div class="mc"><div class="ch pnk"><div class="hi"><i class='bx bx-palette'></i></div><div><div class="ht">Personalizar</div><div class="hs">Visual</div></div></div><div class="cb"><form id="fCfg" enctype="multipart/form-data"><input type="hidden" name="remover_logo" id="rmLogo" value="0">
<div class="fmg"><div class="fl"><i class='bx bx-heading' style="color:#60a5fa"></i> Título</div><input class="fin" name="titulo_pagina" value="<?php echo htmlspecialchars($cp['titulo_pagina']);?>"></div>
<div class="fmg"><div class="fl"><i class='bx bx-text' style="color:#a78bfa"></i> Subtítulo</div><input class="fin" name="subtitulo_pagina" value="<?php echo htmlspecialchars($cp['subtitulo_pagina']);?>"></div>
<div class="frow"><div class="fmg"><div class="fl">Cor 1</div><div class="cw"><div class="cp" id="pc1" style="background:<?php echo htmlspecialchars($cp['cor_primaria']);?>" onclick="document.getElementById('ic1').click()"></div><input type="color" id="ic1" name="cor_primaria" value="<?php echo htmlspecialchars($cp['cor_primaria']);?>" onchange="pc1.style.background=this.value"><input class="fin" value="<?php echo htmlspecialchars($cp['cor_primaria']);?>" style="flex:1" onchange="ic1.value=this.value;pc1.style.background=this.value"></div></div><div class="fmg"><div class="fl">Cor 2</div><div class="cw"><div class="cp" id="pc2" style="background:<?php echo htmlspecialchars($cp['cor_secundaria']);?>" onclick="document.getElementById('ic2').click()"></div><input type="color" id="ic2" name="cor_secundaria" value="<?php echo htmlspecialchars($cp['cor_secundaria']);?>" onchange="pc2.style.background=this.value"><input class="fin" value="<?php echo htmlspecialchars($cp['cor_secundaria']);?>" style="flex:1" onchange="ic2.value=this.value;pc2.style.background=this.value"></div></div></div>
<div class="fmg"><div class="fl"><i class='bx bx-image' style="color:#fbbf24"></i> Logo</div><?php if(!empty($logo_src)):?><div class="logo-preview-wrap" id="logoPrevWrap"><img src="<?php echo htmlspecialchars($logo_src);?>" class="logo-preview-img" onerror="this.parentElement.style.display='none'"><span style="flex:1;font-size:10px;color:rgba(255,255,255,.5);word-break:break-all"><?php echo htmlspecialchars($cp['logo_url']);?></span><button type="button" class="logo-preview-rm" onclick="removerLogo()"><i class='bx bx-trash'></i> Remover</button></div><?php endif;?><div class="uz" id="uzLogo"><input type="file" name="logo_file" id="logoFile" accept="image/*"><div class="uph" id="upLogo"><i class='bx bx-cloud-upload'></i><p>Upload Logo</p><span>JPG PNG GIF WebP SVG · Máx 5MB</span></div><div class="upv" id="upvLogo" style="display:none"><img id="piLogo" src=""><button type="button" class="upr" onclick="riLogo(event)"><i class='bx bx-x'></i></button></div></div><div class="uor">ou URL</div><input class="fin" name="logo_url" id="logoUrl" value="" placeholder="https://..."></div>
<div class="fmg"><div class="fl"><i class='bx bx-message-dots' style="color:#34d399"></i> Rodapé</div><textarea class="fin" name="texto_rodape"><?php echo htmlspecialchars($cp['texto_rodape']);?></textarea></div>
<div class="tgr"><div class="tgl"><i class='bx bx-share-alt' style="color:#0088cc"></i> Exibir redes</div><div class="tgs <?php echo $cp['mostrar_redes']?'on':'';?>" onclick="this.classList.toggle('on')" data-name="mostrar_redes"></div></div>
<div class="tgr"><div class="tgl"><i class='bx bx-store' style="color:#a78bfa"></i> Exibir revendas</div><div class="tgs <?php echo $cp['mostrar_revenda']?'on':'';?>" onclick="this.classList.toggle('on')" data-name="mostrar_revenda"></div></div>
<button type="button" class="ab bg2" style="width:100%;justify-content:center;padding:10px;margin-top:12px" onclick="salvarCfg()"><i class='bx bx-save'></i> Salvar</button></form></div></div></div>

<!-- GW --><div class="tp" id="t-gw"><div class="mc"><div class="ch cyn"><div class="hi"><i class='bx bx-credit-card'></i></div><div><div class="ht">Gateway</div><div class="hs">MercadoPago</div></div></div><div class="cb"><div class="fg" style="grid-template-columns:1fr"><div class="fr"><div class="fi"><i class='bx bx-credit-card' style="color:#009EE3"></i></div><div class="fc"><div class="fll">STATUS</div><div class="fv <?php echo $gw_on?'fvs':'fvd';?>"><?php echo $gw_on?'ATIVO':'INATIVO';?></div></div></div></div><?php if(!$gw_on):?><a href="formaspag.php" class="ab bc" style="width:100%;justify-content:center;padding:10px;margin-top:10px;text-decoration:none"><i class='bx bx-cog'></i> Configurar</a><?php endif;?></div></div></div>

<!-- REDES --><div class="tp" id="t-redes"><div class="mc"><div class="ch vlt"><div class="hi"><i class='bx bx-share-alt'></i></div><div><div class="ht">Redes Sociais</div><div class="hs">Contatos</div></div></div><div class="cb"><form id="fRd">
<div class="fmg"><div class="fl"><i class='bx bxl-whatsapp' style="color:#25D366"></i> WhatsApp</div><input class="fin" name="whatsapp" value="<?php echo htmlspecialchars($redes['whatsapp']);?>" placeholder="5511999999999"><div class="fh">Com DDD e código do país</div></div>
<div class="fmg"><div class="fl"><i class='bx bxl-telegram' style="color:#0088cc"></i> Telegram</div><input class="fin" name="telegram_r" value="<?php echo htmlspecialchars($redes['telegram']);?>" placeholder="seuusuario"></div>
<div class="fmg"><div class="fl"><i class='bx bxl-instagram' style="color:#d62976"></i> Instagram</div><input class="fin" name="instagram" value="<?php echo htmlspecialchars($redes['instagram']);?>" placeholder="seuusuario"></div>
<button type="button" class="ab bg2" style="width:100%;justify-content:center;padding:10px" onclick="salvarRd()"><i class='bx bx-save'></i> Salvar</button></form></div></div></div>

<div class="pi"><?php echo date('d/m/Y H:i:s');?></div></div></div>

<!-- MODAL PLANO --><div id="mP" class="mo"><div class="mc2"><div class="mcc"><div class="mh indig"><h5 id="mPT"><i class='bx bx-package'></i> Plano</h5><button class="mx" onclick="fm('mP')"><i class='bx bx-x'></i></button></div><div class="mb"><div class="fmg"><div class="fl">Nome *</div><input class="fin" id="pN" placeholder="Ex: Plano Básico"></div><div class="fmg"><div class="fl">Descrição</div><textarea class="fin" id="pD" placeholder="Opcional"></textarea></div><div class="frow3"><div class="fmg"><div class="fl">Valor R$</div><input type="number" class="fin" id="pV" step="0.01" min="0" value="0"></div><div class="fmg"><div class="fl">Dias</div><input type="number" class="fin" id="pDi" min="1" value="30"></div><div class="fmg"><div class="fl">Limite</div><input type="number" class="fin" id="pL" min="1" value="1"></div></div><div class="frow"><div class="fmg"><div class="fl">Tipo</div><select class="fsl" id="pT"><option value="usuario">Usuário</option><option value="revenda">Revenda</option></select></div><div class="fmg"><div class="fl">Créditos</div><input type="number" class="fin" id="pC" min="0" value="0"></div></div><input type="hidden" id="pId" value=""></div><div class="mf"><button class="bm bm-c" onclick="fm('mP')"><i class='bx bx-x'></i> Cancelar</button><button class="bm bm-ok" onclick="savP()"><i class='bx bx-check'></i> Salvar</button></div></div></div></div>

<!-- MODAL ANÚNCIO --><div id="mA" class="mo"><div class="mc2"><div class="mcc"><div class="mh orng"><h5 id="mAT"><i class='bx bx-megaphone'></i> Anúncio</h5><button class="mx" onclick="fm('mA')"><i class='bx bx-x'></i></button></div><div class="mb"><form id="fA" enctype="multipart/form-data"><input type="hidden" id="aId" value=""><input type="hidden" id="aRI" value="0"><div class="fmg"><div class="fl">Título</div><input class="fin" name="titulo_a" id="aTt" required></div><div class="fmg"><div class="fl">Descrição</div><textarea class="fin" name="descricao_a" id="aDe"></textarea></div><div class="fmg"><div class="fl"><i class='bx bx-image' style="color:#06b6d4"></i> Imagem</div><div class="uz" id="uzE"><input type="file" name="imagem_a" id="iiE" accept="image/*"><div class="uph" id="upE"><i class='bx bx-cloud-upload'></i><p>Clique ou arraste</p><span>Máx 5MB</span></div><div class="upv" id="upvE" style="display:none"><img id="piE" src=""><button type="button" class="upr" onclick="riE(event)"><i class='bx bx-x'></i></button></div></div><div class="uor">ou URL</div><input class="fin" name="imagem_url_a" id="aIU" placeholder="https://..."></div><div class="frow"><div class="fmg"><div class="fl">Tipo</div><select class="fsl" name="tipo_a" id="aTp"><option value="banner">Banner</option><option value="destaque">Destaque</option><option value="aviso">Aviso</option><option value="promo">Promoção</option></select></div><div class="fmg"><div class="fl">Posição</div><select class="fsl" name="posicao_a" id="aPs"><option value="topo">Topo</option><option value="meio">Meio</option><option value="rodape">Rodapé</option></select></div></div><div class="frow"><div class="fmg"><div class="fl">Cor</div><div class="cw"><div class="cp" id="pcA" style="background:#4158D0" onclick="document.getElementById('icA').click()"></div><input type="color" id="icA" name="cor_a" value="#4158D0" onchange="pcA.style.background=this.value"><input class="fin" id="tcA" value="#4158D0" style="flex:1" onchange="icA.value=this.value;pcA.style.background=this.value"></div></div><div class="fmg"><div class="fl">Ícone</div><input class="fin" name="icone_a" id="aIc" value="bx-megaphone"></div></div><div class="fmg"><div class="fl"><i class='bx bx-link' style="color:#06b6d4"></i> Link Botão</div><div class="frow"><select class="fsl" name="link_tipo_a" id="aLT" onchange="tgLU()"><option value="url">🔗 URL</option><option value="whatsapp">📱 WhatsApp</option><option value="telegram">✈️ Telegram</option><option value="instagram">📷 Instagram</option></select><input class="fin" name="link_url_a" id="aLU" placeholder="https://..."></div></div><div class="fmg"><div class="fl">Texto Botão</div><input class="fin" name="link_texto_a" id="aLTx" placeholder="Saiba Mais"></div></form></div><div class="mf"><button class="bm bm-c" onclick="fm('mA')"><i class='bx bx-x'></i> Cancelar</button><button class="bm bm-ok" onclick="savA()"><i class='bx bx-check'></i> Salvar</button></div></div></div></div>

<!-- CONFIRMAR --><div id="mCf" class="mo"><div class="mc2"><div class="mcc"><div class="mh err"><h5><i class='bx bx-error-circle'></i> Confirmar</h5><button class="mx" onclick="fm('mCf')"><i class='bx bx-x'></i></button></div><div class="mb"><div class="mic err"><i class='bx bx-error-circle'></i></div><p style="text-align:center;font-size:13px" id="cfM"></p></div><div class="mf"><button class="bm bm-c" onclick="fm('mCf')"><i class='bx bx-x'></i> Não</button><button class="bm bm-d" id="cfB"><i class='bx bx-check'></i> Sim</button></div></div></div></div>
<div id="mPr" class="mo"><div class="mc2"><div class="mcc"><div class="mh proc"><h5><i class='bx bx-loader-alt bx-spin'></i> Processando</h5></div><div class="mb"><div class="spw"><div class="spr"></div><p style="font-size:13px;color:rgba(255,255,255,.6)">Aguarde...</p></div></div></div></div></div>
<div id="mSc" class="mo"><div class="mc2"><div class="mcc"><div class="mh suc"><h5><i class='bx bx-check-circle'></i> Sucesso</h5><button class="mx" onclick="fm('mSc');location.reload()"><i class='bx bx-x'></i></button></div><div class="mb"><div class="mic suc"><i class='bx bx-check-circle'></i></div><p style="text-align:center;font-size:13px;font-weight:600" id="scM"></p></div><div class="mf"><button class="bm bm-ok" onclick="fm('mSc');location.reload()"><i class='bx bx-check'></i> OK</button></div></div></div></div>

<script>
var AD=<?php echo json_encode($anuncios,JSON_HEX_APOS|JSON_HEX_QUOT);?>;
function om(i){document.getElementById(i).classList.add('show')}
function fm(i){document.getElementById(i).classList.remove('show')}
document.querySelectorAll('.mo').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show')})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.mo.show').forEach(function(m){m.classList.remove('show')})});
function tst(m,t){var e=document.createElement('div');e.className='tst '+(t==='e'?'er':'ok');e.innerHTML='<i class="bx '+(t==='e'?'bx-error-circle':'bx-check-circle')+'"></i> '+m;document.body.appendChild(e);setTimeout(function(){e.remove()},3500)}
function stb(n,b){document.querySelectorAll('.tp').forEach(function(p){p.classList.remove('on')});document.querySelectorAll('.tb').forEach(function(x){x.classList.remove('on')});document.getElementById('t-'+n).classList.add('on');b.classList.add('on')}
function envS(data,msg){om('mPr');var x=new XMLHttpRequest();x.open('POST',window.location.href,true);x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');x.onload=function(){fm('mPr');var r=x.responseText.trim();if(r==='ok'){document.getElementById('scM').textContent=msg;om('mSc')}else{tst('Erro: '+r,'e')}};x.onerror=function(){fm('mPr');tst('Erro!','e')};x.send(data)}
function envF(fd,msg){om('mPr');var x=new XMLHttpRequest();x.open('POST',window.location.href,true);x.onload=function(){fm('mPr');var r=x.responseText.trim();if(r==='ok'){document.getElementById('scM').textContent=msg;om('mSc')}else{tst('Erro: '+r,'e')}};x.onerror=function(){fm('mPr');tst('Erro!','e')};x.send(fd)}
function conf(t,m,cb){document.getElementById('cfM').innerHTML='<strong>'+t+'</strong>'+(m?'<br>'+m:'');document.getElementById('cfB').onclick=function(){fm('mCf');cb()};om('mCf')}
function copL(){var u=document.getElementById('lu');if(!u)return;navigator.clipboard.writeText(u.textContent).then(function(){tst('Copiado!','o')}).catch(function(){tst('Erro','e')})}
function gerarLink(){conf('Gerar novo link?','O anterior será apagado.',function(){envS('ajax_action=gerar_link','Link gerado!')})}

// PLANOS
function abrP(t){document.getElementById('mPT').innerHTML='<i class="bx bx-package"></i> Novo '+(t==='revenda'?'Revenda':'Usuário');document.getElementById('pId').value='';document.getElementById('pN').value='';document.getElementById('pD').value='';document.getElementById('pV').value='0';document.getElementById('pDi').value='30';document.getElementById('pL').value='1';document.getElementById('pT').value=t;document.getElementById('pC').value='0';om('mP')}
function edP(p){document.getElementById('mPT').innerHTML='<i class="bx bx-edit"></i> Editar';document.getElementById('pId').value=p.id;document.getElementById('pN').value=p.nome;document.getElementById('pD').value=p.descricao||'';document.getElementById('pV').value=p._preco||0;document.getElementById('pDi').value=p.dias||30;document.getElementById('pL').value=p.limite||1;document.getElementById('pT').value=p.tipo||'usuario';document.getElementById('pC').value=p.creditos||0;om('mP')}
function savP(){var nome=document.getElementById('pN').value.trim();if(!nome){tst('Nome obrigatório!','e');return}var id=document.getElementById('pId').value;var d='ajax_action='+(id?'editar_plano':'adicionar_plano');if(id)d+='&plano_id='+encodeURIComponent(id);d+='&plano_nome='+encodeURIComponent(nome);d+='&plano_descricao='+encodeURIComponent(document.getElementById('pD').value.trim());d+='&plano_valor='+encodeURIComponent(document.getElementById('pV').value||'0');d+='&plano_dias='+encodeURIComponent(document.getElementById('pDi').value||'30');d+='&plano_limite='+encodeURIComponent(document.getElementById('pL').value||'1');d+='&plano_tipo='+encodeURIComponent(document.getElementById('pT').value||'usuario');d+='&plano_creditos='+encodeURIComponent(document.getElementById('pC').value||'0');fm('mP');envS(d,id?'Atualizado!':'Criado!')}

// UPLOAD ANÚNCIO
var uzE=document.getElementById('uzE'),iiE=document.getElementById('iiE'),upE=document.getElementById('upE'),upvE=document.getElementById('upvE'),piE=document.getElementById('piE');
if(uzE){uzE.addEventListener('dragover',function(e){e.preventDefault();uzE.classList.add('dg')});uzE.addEventListener('dragleave',function(){uzE.classList.remove('dg')});uzE.addEventListener('drop',function(e){e.preventDefault();uzE.classList.remove('dg');if(e.dataTransfer.files.length>0){iiE.files=e.dataTransfer.files;pvI(e.dataTransfer.files[0])}});iiE.addEventListener('change',function(){if(this.files&&this.files[0])pvI(this.files[0])})}
function pvI(f){if(f.size>5242880){tst('Máx 5MB!','e');return}var r=new FileReader();r.onload=function(e){piE.src=e.target.result;upE.style.display='none';upvE.style.display='block';uzE.classList.add('hi2');document.getElementById('aRI').value='0'};r.readAsDataURL(f)}
function riE(e){e.stopPropagation();e.preventDefault();iiE.value='';piE.src='';upE.style.display='';upvE.style.display='none';uzE.classList.remove('hi2');document.getElementById('aRI').value='1';document.getElementById('aIU').value=''}
function rstU(){if(iiE)iiE.value='';if(piE)piE.src='';if(upE)upE.style.display='';if(upvE)upvE.style.display='none';if(uzE)uzE.classList.remove('hi2');var ri=document.getElementById('aRI');if(ri)ri.value='0';var iu=document.getElementById('aIU');if(iu)iu.value=''}
function tgLU(){var t=document.getElementById('aLT').value;var u=document.getElementById('aLU');if(t==='url'){u.placeholder='https://...';u.disabled=false}else{u.placeholder='Usa rede cadastrada';u.disabled=true;u.value=''}}

// UPLOAD LOGO
var logoFile=document.getElementById('logoFile'),upLogo=document.getElementById('upLogo'),upvLogo=document.getElementById('upvLogo'),piLogo=document.getElementById('piLogo'),uzLogo=document.getElementById('uzLogo');
if(logoFile){logoFile.addEventListener('change',function(){if(this.files&&this.files[0]){var f=this.files[0];if(f.size>5242880){tst('Máx 5MB!','e');return}var r=new FileReader();r.onload=function(e){piLogo.src=e.target.result;upLogo.style.display='none';upvLogo.style.display='block';uzLogo.classList.add('hi2');document.getElementById('rmLogo').value='0'};r.readAsDataURL(f)}})}
function riLogo(e){e.stopPropagation();e.preventDefault();logoFile.value='';piLogo.src='';upLogo.style.display='';upvLogo.style.display='none';uzLogo.classList.remove('hi2');document.getElementById('logoUrl').value='';document.getElementById('rmLogo').value='1'}
function removerLogo(){var w=document.getElementById('logoPrevWrap');if(w)w.style.display='none';document.getElementById('rmLogo').value='1';logoFile.value='';piLogo.src='';upLogo.style.display='';upvLogo.style.display='none';uzLogo.classList.remove('hi2');document.getElementById('logoUrl').value=''}

// ANÚNCIOS
function abrA(){document.getElementById('mAT').innerHTML='<i class="bx bx-megaphone"></i> Novo';document.getElementById('aId').value='';document.getElementById('aTt').value='';document.getElementById('aDe').value='';document.getElementById('aTp').value='banner';document.getElementById('aPs').value='topo';icA.value='#4158D0';pcA.style.background='#4158D0';document.getElementById('tcA').value='#4158D0';document.getElementById('aIc').value='bx-megaphone';document.getElementById('aLU').value='';document.getElementById('aLU').disabled=false;document.getElementById('aLTx').value='';document.getElementById('aLT').value='url';tgLU();rstU();om('mA')}
function edA(id){var a=null;for(var i=0;i<AD.length;i++)if(AD[i].id==id){a=AD[i];break}if(!a)return;document.getElementById('mAT').innerHTML='<i class="bx bx-edit"></i> Editar';document.getElementById('aId').value=a.id;document.getElementById('aTt').value=a.titulo;document.getElementById('aDe').value=a.descricao||'';document.getElementById('aTp').value=a.tipo;document.getElementById('aPs').value=a.posicao;icA.value=a.cor;pcA.style.background=a.cor;document.getElementById('tcA').value=a.cor;document.getElementById('aIc').value=a.icone;document.getElementById('aLU').value=a.link_url||'';document.getElementById('aLTx').value=a.link_texto||'';document.getElementById('aLT').value=a.link_tipo||'url';tgLU();if(a.link_tipo==='url'&&a.link_url)document.getElementById('aLU').value=a.link_url;document.getElementById('aRI').value='0';if(iiE)iiE.value='';if(a.imagem&&a.imagem!=''){var s=a.imagem.indexOf('http')===0?a.imagem:'../'+a.imagem;piE.src=s;upE.style.display='none';upvE.style.display='block';uzE.classList.add('hi2');document.getElementById('aIU').value=(a.imagem.indexOf('http')===0)?a.imagem:''}else{rstU()}om('mA')}
function savA(){var f=document.getElementById('fA');var fd=new FormData(f);var id=document.getElementById('aId').value;fd.append('ajax_action',id?'editar_anuncio':'adicionar_anuncio');if(id)fd.append('anuncio_id',id);fd.append('remover_imagem',document.getElementById('aRI').value);fd.set('cor_a',icA.value);fm('mA');envF(fd,id?'Atualizado!':'Criado!')}
function salvarCfg(){var f=document.getElementById('fCfg');var fd=new FormData(f);fd.append('ajax_action','salvar_config_pagina');fd.set('cor_primaria',ic1.value);fd.set('cor_secundaria',ic2.value);document.querySelectorAll('.tgs').forEach(function(t){if(t.dataset.name&&t.classList.contains('on'))fd.append(t.dataset.name,'1')});envF(fd,'Salvo!')}
function salvarRd(){var f=document.getElementById('fRd');var fd=new FormData(f);fd.append('ajax_action','salvar_redes');envF(fd,'Redes salvas!')}
</script>
</body>
</html>

