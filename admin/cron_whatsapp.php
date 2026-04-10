<?php
/**
 * CRON — Disparos automáticos de WhatsApp (vencimentos)
 * Crontab: * * * * * php /caminho/admin/cron_whatsapp.php >> /var/log/cron_wpp.log 2>&1
 * Ou via URL: /admin/cron_whatsapp.php?chave=AtlasWhatsAppV1
 */
if (php_sapi_name() !== 'cli') {
    $chave = $_GET['chave'] ?? '';
    if ($chave !== 'AtlasWhatsAppV1') { http_response_code(403); exit('Acesso negado.'); }
}

error_reporting(0);
set_time_limit(0);
ignore_user_abort(true);

include('../AegisCore/conexao.php');
include('../AegisCore/functions.whatsapp.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
$conn->set_charset("utf8mb4");

date_default_timezone_set('America/Sao_Paulo');
$horaAtual = date('H:i');
$hoje      = date('Y-m-d');
$amanha    = date('Y-m-d', strtotime('+1 day'));

echo "[" . date('d/m/Y H:i:s') . "] Cron iniciado — hora={$horaAtual}\n";

// Buscar todos os revendedores com WhatsApp ativo
$sql_rev = "SELECT a.id AS byid, a.dominio, w.sessao, w.token, w.apiurl
            FROM accounts a
            INNER JOIN whatsapp w ON w.byid = a.id
            WHERE w.sessao != '' AND w.token != '' AND w.apiurl != '' AND w.ativo = '1'";
$res_rev = $conn->query($sql_rev);

if (!$res_rev || $res_rev->num_rows === 0) {
    echo "[" . date('H:i:s') . "] Nenhum revendedor com WhatsApp ativo.\n";
    exit;
}

while ($rev = $res_rev->fetch_assoc()) {
    $byid    = $rev['byid'];
    $dominio = $rev['dominio'] ?? $_SERVER['HTTP_HOST'] ?? '';

    echo "[" . date('H:i:s') . "] byid={$byid}\n";

    // ── 1. USUÁRIOS VENCENDO (hoje ou amanhã) ──────────────────────
    $cfg_cu = $conn->query("SELECT * FROM mensagens WHERE funcao='contaexpirada' AND ativo='ativada' AND byid='$byid' LIMIT 1");
    if ($cfg_cu && $cfg_cu->num_rows > 0) {
        $m = $cfg_cu->fetch_assoc();
        $hora_disp = substr($m['hora'] ?? '08:00', 0, 5);

        if ($horaAtual === $hora_disp) {
            $res_u = $conn->query("SELECT login, senha, expira, limite, whatsapp
                                   FROM ssh_accounts
                                   WHERE byid='$byid'
                                     AND whatsapp != '' AND whatsapp IS NOT NULL
                                     AND (DATE(expira)='$hoje' OR DATE(expira)='$amanha')");
            while ($u = $res_u->fetch_assoc()) {
                $dados = [
                    'usuario'  => $u['login'],
                    'senha'    => $u['senha'],
                    'validade' => date('d/m/Y', strtotime($u['expira'])),
                    'limite'   => $u['limite'],
                    'whatsapp' => $u['whatsapp'],
                    'dominio'  => $dominio,
                ];
                $r = dispararMensagemAutomatica($conn, $byid, 'contaexpirada', $dados);
                echo "  [USER] " . ($r['ok']?'OK':'ERRO') . " {$u['login']} -> {$u['whatsapp']}\n";
                usleep(600000);
            }
        }
    }

    // ── 2. REVENDAS VENCENDO (hoje ou amanhã) ──────────────────────
    $cfg_rv = $conn->query("SELECT * FROM mensagens WHERE funcao='revendaexpirada' AND ativo='ativada' AND byid='$byid' LIMIT 1");
    if ($cfg_rv && $cfg_rv->num_rows > 0) {
        $m = $cfg_rv->fetch_assoc();
        $hora_disp = substr($m['hora'] ?? '08:00', 0, 5);

        if ($horaAtual === $hora_disp) {
            $res_r = $conn->query("SELECT ac.login, ac.senha, ac.whatsapp, at.expira, at.limite
                                   FROM accounts ac
                                   INNER JOIN atribuidos at ON at.userid = ac.id
                                   WHERE at.byid='$byid'
                                     AND ac.whatsapp != '' AND ac.whatsapp IS NOT NULL
                                     AND (DATE(at.expira)='$hoje' OR DATE(at.expira)='$amanha')
                                     AND ac.login != 'admin'");
            while ($r2 = $res_r->fetch_assoc()) {
                $dados = [
                    'usuario'  => $r2['login'],
                    'senha'    => $r2['senha'],
                    'validade' => date('d/m/Y', strtotime($r2['expira'])),
                    'limite'   => $r2['limite'],
                    'whatsapp' => $r2['whatsapp'],
                    'dominio'  => $dominio,
                ];
                $r = dispararMensagemAutomatica($conn, $byid, 'revendaexpirada', $dados);
                echo "  [REV ] " . ($r['ok']?'OK':'ERRO') . " {$r2['login']} -> {$r2['whatsapp']}\n";
                usleep(600000);
            }
        }
    }
}

echo "[" . date('d/m/Y H:i:s') . "] Cron finalizado.\n";