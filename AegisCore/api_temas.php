<?php
/**
 * API REST - Gerenciador de Temas
 * Somente admin pode criar, editar, excluir e ativar temas.
 * Ações: listar | criar | salvar | excluir | ativar | toggle | exportar | importar
 */

header('Content-Type: application/json');
error_reporting(0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se é admin
if (!isset($_SESSION['login']) || $_SESSION['login'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Somente administradores.']);
    exit;
}

include_once("conexao.php");
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Erro na conexão com o banco de dados.']);
    exit;
}

include_once("temas.php");
initTemas($conn);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ─── LISTAR TODOS OS TEMAS ─────────────────────────────────────────────
    case 'listar':
        $result = mysqli_query($conn, "SELECT * FROM temas ORDER BY id ASC");
        $temas = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $temas[] = $row;
        }
        echo json_encode(['success' => true, 'temas' => $temas]);
        break;

    // ─── CRIAR NOVO TEMA ──────────────────────────────────────────────────
    case 'criar':
        $dados = json_decode(file_get_contents('php://input'), true);
        if (!$dados) $dados = $_POST;

        $nome           = mysqli_real_escape_string($conn, trim($dados['nome'] ?? 'Novo Tema'));
        $cor_primaria   = mysqli_real_escape_string($conn, $dados['cor_primaria']   ?? '#10b981');
        $cor_secundaria = mysqli_real_escape_string($conn, $dados['cor_secundaria'] ?? '#C850C0');
        $cor_terciaria  = mysqli_real_escape_string($conn, $dados['cor_terciaria']  ?? '#FFCC70');
        $cor_fundo      = mysqli_real_escape_string($conn, $dados['cor_fundo']      ?? '#0f172a');
        $cor_fundo_claro= mysqli_real_escape_string($conn, $dados['cor_fundo_claro']?? '#1e293b');
        $cor_texto      = mysqli_real_escape_string($conn, $dados['cor_texto']      ?? '#ffffff');
        $cor_texto_sec  = mysqli_real_escape_string($conn, $dados['cor_texto_sec']  ?? 'rgba(255,255,255,0.6)');
        $cor_borda      = mysqli_real_escape_string($conn, $dados['cor_borda']      ?? 'rgba(255,255,255,0.06)');
        $cor_sucesso    = mysqli_real_escape_string($conn, $dados['cor_sucesso']    ?? '#10b981');
        $cor_erro       = mysqli_real_escape_string($conn, $dados['cor_erro']       ?? '#dc2626');
        $cor_aviso      = mysqli_real_escape_string($conn, $dados['cor_aviso']      ?? '#f59e0b');
        $cor_info       = mysqli_real_escape_string($conn, $dados['cor_info']       ?? '#3b82f6');
        $cor_menu_fundo = mysqli_real_escape_string($conn, $dados['cor_menu_fundo'] ?? 'linear-gradient(180deg,#1a1f3a 0%,#0f1429 100%)');
        $css_customizado= mysqli_real_escape_string($conn, $dados['css_customizado'] ?? '');

        $sql = "INSERT INTO temas 
            (nome, cor_primaria, cor_secundaria, cor_terciaria, cor_fundo, cor_fundo_claro,
             cor_texto, cor_texto_sec, cor_borda, cor_sucesso, cor_erro, cor_aviso, cor_info,
             cor_menu_fundo, css_customizado, ativo)
            VALUES
            ('$nome','$cor_primaria','$cor_secundaria','$cor_terciaria','$cor_fundo','$cor_fundo_claro',
             '$cor_texto','$cor_texto_sec','$cor_borda','$cor_sucesso','$cor_erro','$cor_aviso','$cor_info',
             '$cor_menu_fundo','$css_customizado', 0)";

        if (mysqli_query($conn, $sql)) {
            $novoId = mysqli_insert_id($conn);
            $result = mysqli_query($conn, "SELECT * FROM temas WHERE id = $novoId");
            $tema   = mysqli_fetch_assoc($result);
            echo json_encode(['success' => true, 'message' => 'Tema criado com sucesso!', 'tema' => $tema]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao criar tema: ' . mysqli_error($conn)]);
        }
        break;

    // ─── SALVAR / EDITAR TEMA ────────────────────────────────────────────
    case 'salvar':
        $dados = json_decode(file_get_contents('php://input'), true);
        if (!$dados) $dados = $_POST;

        $id             = intval($dados['id'] ?? 0);
        $nome           = mysqli_real_escape_string($conn, trim($dados['nome'] ?? ''));
        $cor_primaria   = mysqli_real_escape_string($conn, $dados['cor_primaria']   ?? '#10b981');
        $cor_secundaria = mysqli_real_escape_string($conn, $dados['cor_secundaria'] ?? '#C850C0');
        $cor_terciaria  = mysqli_real_escape_string($conn, $dados['cor_terciaria']  ?? '#FFCC70');
        $cor_fundo      = mysqli_real_escape_string($conn, $dados['cor_fundo']      ?? '#0f172a');
        $cor_fundo_claro= mysqli_real_escape_string($conn, $dados['cor_fundo_claro']?? '#1e293b');
        $cor_texto      = mysqli_real_escape_string($conn, $dados['cor_texto']      ?? '#ffffff');
        $cor_texto_sec  = mysqli_real_escape_string($conn, $dados['cor_texto_sec']  ?? 'rgba(255,255,255,0.6)');
        $cor_borda      = mysqli_real_escape_string($conn, $dados['cor_borda']      ?? 'rgba(255,255,255,0.06)');
        $cor_sucesso    = mysqli_real_escape_string($conn, $dados['cor_sucesso']    ?? '#10b981');
        $cor_erro       = mysqli_real_escape_string($conn, $dados['cor_erro']       ?? '#dc2626');
        $cor_aviso      = mysqli_real_escape_string($conn, $dados['cor_aviso']      ?? '#f59e0b');
        $cor_info       = mysqli_real_escape_string($conn, $dados['cor_info']       ?? '#3b82f6');
        $cor_menu_fundo = mysqli_real_escape_string($conn, $dados['cor_menu_fundo'] ?? 'linear-gradient(180deg,#1a1f3a 0%,#0f1429 100%)');
        $css_customizado= mysqli_real_escape_string($conn, $dados['css_customizado'] ?? '');

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de tema inválido.']);
            break;
        }

        $sql = "UPDATE temas SET
            nome='$nome', cor_primaria='$cor_primaria', cor_secundaria='$cor_secundaria',
            cor_terciaria='$cor_terciaria', cor_fundo='$cor_fundo', cor_fundo_claro='$cor_fundo_claro',
            cor_texto='$cor_texto', cor_texto_sec='$cor_texto_sec', cor_borda='$cor_borda',
            cor_sucesso='$cor_sucesso', cor_erro='$cor_erro', cor_aviso='$cor_aviso',
            cor_info='$cor_info', cor_menu_fundo='$cor_menu_fundo', css_customizado='$css_customizado'
            WHERE id=$id";

        if (mysqli_query($conn, $sql)) {
            $result = mysqli_query($conn, "SELECT * FROM temas WHERE id = $id");
            $tema   = mysqli_fetch_assoc($result);
            // Se editou o tema ativo, atualizar sessão
            if ($tema['ativo'] == 1) {
                $_SESSION['tema_atual'] = $tema;
            }
            echo json_encode(['success' => true, 'message' => 'Tema salvo com sucesso!', 'tema' => $tema]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar: ' . mysqli_error($conn)]);
        }
        break;

    // ─── EXCLUIR TEMA ─────────────────────────────────────────────────────
    case 'excluir':
        $dados = json_decode(file_get_contents('php://input'), true);
        if (!$dados) $dados = $_POST;
        $id = intval($dados['id'] ?? $_GET['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            break;
        }

        // Verificar se é o tema ativo
        $check = mysqli_query($conn, "SELECT ativo FROM temas WHERE id = $id");
        $row   = mysqli_fetch_assoc($check);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Tema não encontrado.']);
            break;
        }
        if ($row['ativo'] == 1) {
            echo json_encode(['success' => false, 'message' => 'Não é possível excluir o tema ativo. Ative outro tema primeiro.']);
            break;
        }

        if (mysqli_query($conn, "DELETE FROM temas WHERE id = $id")) {
            echo json_encode(['success' => true, 'message' => 'Tema excluído com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir: ' . mysqli_error($conn)]);
        }
        break;

    // ─── ATIVAR TEMA ──────────────────────────────────────────────────────
    case 'ativar':
        $dados = json_decode(file_get_contents('php://input'), true);
        if (!$dados) $dados = $_POST;
        $id = intval($dados['id'] ?? $_GET['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            break;
        }

        // Verificar se tema existe
        $check = mysqli_query($conn, "SELECT * FROM temas WHERE id = $id");
        if (mysqli_num_rows($check) == 0) {
            echo json_encode(['success' => false, 'message' => 'Tema não encontrado.']);
            break;
        }

        // Desativar todos e ativar o selecionado
        mysqli_query($conn, "UPDATE temas SET ativo = 0");
        mysqli_query($conn, "UPDATE temas SET ativo = 1 WHERE id = $id");

        $result = mysqli_query($conn, "SELECT * FROM temas WHERE id = $id");
        $tema   = mysqli_fetch_assoc($result);
        $_SESSION['tema_atual'] = $tema;

        echo json_encode(['success' => true, 'message' => 'Tema ativado com sucesso!', 'tema' => $tema]);
        break;

    // ─── TOGGLE ATIVO/INATIVO ─────────────────────────────────────────────
    case 'toggle':
        $dados = json_decode(file_get_contents('php://input'), true);
        if (!$dados) $dados = $_POST;
        $id = intval($dados['id'] ?? $_GET['id'] ?? 0);

        $check = mysqli_query($conn, "SELECT * FROM temas WHERE id = $id");
        $row   = mysqli_fetch_assoc($check);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Tema não encontrado.']);
            break;
        }
        if ($row['ativo'] == 1) {
            echo json_encode(['success' => false, 'message' => 'Não é possível desativar o tema ativo. Ative outro primeiro.']);
            break;
        }

        // Somente alterna para temas não ativos (habilita/desabilita disponibilidade)
        // Para habilitar: definir ativo = 1 e desabilitar os demais
        mysqli_query($conn, "UPDATE temas SET ativo = 0");
        mysqli_query($conn, "UPDATE temas SET ativo = 1 WHERE id = $id");

        $result = mysqli_query($conn, "SELECT * FROM temas WHERE id = $id");
        $tema   = mysqli_fetch_assoc($result);
        $_SESSION['tema_atual'] = $tema;

        echo json_encode(['success' => true, 'message' => 'Tema ativado!', 'tema' => $tema]);
        break;

    // ─── EXPORTAR TEMA (JSON) ─────────────────────────────────────────────
    case 'exportar':
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            break;
        }

        $result = mysqli_query($conn, "SELECT * FROM temas WHERE id = $id");
        $tema   = mysqli_fetch_assoc($result);

        if (!$tema) {
            echo json_encode(['success' => false, 'message' => 'Tema não encontrado.']);
            break;
        }

        // Remover campos internos
        unset($tema['id'], $tema['ativo'], $tema['created_at']);
        $tema['_exportado_em'] = date('Y-m-d H:i:s');
        $tema['_versao'] = '1.0';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="tema_' . preg_replace('/[^a-z0-9]/i', '_', $tema['nome']) . '.json"');
        echo json_encode($tema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;

    // ─── IMPORTAR TEMA (JSON) ─────────────────────────────────────────────
    case 'importar':
        if (!isset($_FILES['arquivo_json']) || $_FILES['arquivo_json']['error'] !== 0) {
            echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado ou erro no upload.']);
            break;
        }

        $arquivo = $_FILES['arquivo_json'];
        $ext     = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));

        if ($ext !== 'json') {
            echo json_encode(['success' => false, 'message' => 'Apenas arquivos .json são aceitos.']);
            break;
        }

        $conteudo = file_get_contents($arquivo['tmp_name']);
        $dados    = json_decode($conteudo, true);

        if (!$dados || !isset($dados['nome'])) {
            echo json_encode(['success' => false, 'message' => 'Arquivo JSON inválido ou sem campo "nome".']);
            break;
        }

        // Usar os dados do JSON ou fallback para padrões
        $nome           = mysqli_real_escape_string($conn, trim($dados['nome'] ?? 'Tema Importado'));
        $cor_primaria   = mysqli_real_escape_string($conn, $dados['cor_primaria']   ?? '#10b981');
        $cor_secundaria = mysqli_real_escape_string($conn, $dados['cor_secundaria'] ?? '#C850C0');
        $cor_terciaria  = mysqli_real_escape_string($conn, $dados['cor_terciaria']  ?? '#FFCC70');
        $cor_fundo      = mysqli_real_escape_string($conn, $dados['cor_fundo']      ?? '#0f172a');
        $cor_fundo_claro= mysqli_real_escape_string($conn, $dados['cor_fundo_claro']?? '#1e293b');
        $cor_texto      = mysqli_real_escape_string($conn, $dados['cor_texto']      ?? '#ffffff');
        $cor_texto_sec  = mysqli_real_escape_string($conn, $dados['cor_texto_sec']  ?? 'rgba(255,255,255,0.6)');
        $cor_borda      = mysqli_real_escape_string($conn, $dados['cor_borda']      ?? 'rgba(255,255,255,0.06)');
        $cor_sucesso    = mysqli_real_escape_string($conn, $dados['cor_sucesso']    ?? '#10b981');
        $cor_erro       = mysqli_real_escape_string($conn, $dados['cor_erro']       ?? '#dc2626');
        $cor_aviso      = mysqli_real_escape_string($conn, $dados['cor_aviso']      ?? '#f59e0b');
        $cor_info       = mysqli_real_escape_string($conn, $dados['cor_info']       ?? '#3b82f6');
        $cor_menu_fundo = mysqli_real_escape_string($conn, $dados['cor_menu_fundo'] ?? 'linear-gradient(180deg,#1a1f3a 0%,#0f1429 100%)');
        $css_customizado= mysqli_real_escape_string($conn, $dados['css_customizado'] ?? '');

        $sql = "INSERT INTO temas 
            (nome, cor_primaria, cor_secundaria, cor_terciaria, cor_fundo, cor_fundo_claro,
             cor_texto, cor_texto_sec, cor_borda, cor_sucesso, cor_erro, cor_aviso, cor_info,
             cor_menu_fundo, css_customizado, ativo)
            VALUES
            ('$nome','$cor_primaria','$cor_secundaria','$cor_terciaria','$cor_fundo','$cor_fundo_claro',
             '$cor_texto','$cor_texto_sec','$cor_borda','$cor_sucesso','$cor_erro','$cor_aviso','$cor_info',
             '$cor_menu_fundo','$css_customizado', 0)";

        if (mysqli_query($conn, $sql)) {
            $novoId = mysqli_insert_id($conn);
            $result = mysqli_query($conn, "SELECT * FROM temas WHERE id = $novoId");
            $tema   = mysqli_fetch_assoc($result);
            echo json_encode(['success' => true, 'message' => "Tema \"$nome\" importado com sucesso!", 'tema' => $tema]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao importar: ' . mysqli_error($conn)]);
        }
        break;

    // ─── OBTER TEMA ATIVO ─────────────────────────────────────────────────
    case 'ativo':
        $result = mysqli_query($conn, "SELECT * FROM temas WHERE ativo = 1 LIMIT 1");
        $tema   = mysqli_fetch_assoc($result);
        echo json_encode(['success' => true, 'tema' => $tema]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida. Use: listar, criar, salvar, excluir, ativar, exportar, importar, ativo']);
        break;
}

mysqli_close($conn);
