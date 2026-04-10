<?php
if (session_status() == PHP_SESSION_NONE) session_start();

// ──────────────────────────────────────────────────────────────────────────
// SISTEMA DE TEMAS COMPLETO — AegisCore v9.0 AUTO-THEME ENGINE
// Com rotação automática (dia 3), datas comemorativas e controle admin
// ──────────────────────────────────────────────────────────────────────────

// Remove emojis de uma string (para exibição segura sem problemas de encoding)
function stripEmoji($str) {
    return trim(preg_replace('/[\x{1F000}-\x{1FFFF}]|[\x{2600}-\x{27BF}]|[\x{FE00}-\x{FE0F}]|[\x{200D}]|[\x{20E3}]|[\x{E0020}-\x{E007F}]|[\x{2700}-\x{27BF}]|[\x{2300}-\x{23FF}]|[\x{2B50}]|[\x{FE0F}]/u', '', $str));
}

function getTemasDisponiveis() {
    return [
        1  => ['nome'=>'🌙 Dark Original',   'classe'=>'theme-dark',       'desc'=>'Glassmorphism clássico roxo', 'categoria'=>'padrao', 'preview'=>'#6366f1'],
        4  => ['nome'=>'💜 Roxo Neon',       'classe'=>'theme-neon-roxo',  'desc'=>'Neon tech futurista', 'categoria'=>'moderno', 'preview'=>'#a855f7'],
        9  => ['nome'=>'🤖 Cyber',           'classe'=>'theme-cyber',      'desc'=>'Amarelo neon tecnológico', 'categoria'=>'moderno', 'preview'=>'#facc15'],
        11 => ['nome'=>'❄️ Arctic',          'classe'=>'theme-arctic',     'desc'=>'Azul gelo minimalista', 'categoria'=>'moderno', 'preview'=>'#38bdf8'],
        14 => ['nome'=>'💜 Violeta',         'classe'=>'theme-violet',     'desc'=>'Escudo roxo premium', 'categoria'=>'moderno', 'preview'=>'#7c3aed'],
        20 => ['nome'=>'🔥 Inferno',         'classe'=>'theme-inferno',    'desc'=>'Laranja e vermelho intenso', 'categoria'=>'moderno', 'preview'=>'#dc2626'],
        5  => ['nome'=>'🌊 Ocean',           'classe'=>'theme-ocean',      'desc'=>'Ondas orgânicas azul profundo', 'categoria'=>'natureza', 'preview'=>'#0ea5e9'],
        6  => ['nome'=>'🔥 Sunset',          'classe'=>'theme-sunset',     'desc'=>'Trapézio laranja quente', 'categoria'=>'natureza', 'preview'=>'#f97316'],
        7  => ['nome'=>'🌿 Esmeralda',       'classe'=>'theme-emerald',    'desc'=>'Hexagonal verde escuro', 'categoria'=>'natureza', 'preview'=>'#10b981'],
        8  => ['nome'=>'🌸 Sakura',          'classe'=>'theme-sakura',     'desc'=>'Rosa japonês arredondado', 'categoria'=>'natureza', 'preview'=>'#ec4899'],
        10 => ['nome'=>'☕ Caramelo',        'classe'=>'theme-caramel',    'desc'=>'Tons quentes terra', 'categoria'=>'natureza', 'preview'=>'#d97706'],
        12 => ['nome'=>'🌌 Galaxy',          'classe'=>'theme-galaxy',     'desc'=>'Oval cósmico com estrelas', 'categoria'=>'premium', 'preview'=>'#8b5cf6'],
        13 => ['nome'=>'🌹 Rose',            'classe'=>'theme-rose',       'desc'=>'Coração vermelho elegante', 'categoria'=>'premium', 'preview'=>'#f43f5e'],
        15 => ['nome'=>'🌿 Mint',            'classe'=>'theme-mint',       'desc'=>'Pílula verde água', 'categoria'=>'natureza', 'preview'=>'#14b8a6'],
        16 => ['nome'=>'💜 Lavender',        'classe'=>'theme-lavender',   'desc'=>'Nuvem arredondada lilás', 'categoria'=>'natureza', 'preview'=>'#9333ea'],
        17 => ['nome'=>'💧 Aqua',            'classe'=>'theme-aqua',       'desc'=>'Cápsula ciano aquático', 'categoria'=>'natureza', 'preview'=>'#0891b2'],
        18 => ['nome'=>'🌟 Gold',            'classe'=>'theme-gold',       'desc'=>'Hexagonal dourado luxuoso', 'categoria'=>'premium', 'preview'=>'#d97706'],
        19 => ['nome'=>'🔶 Copper',          'classe'=>'theme-copper',     'desc'=>'Industrial bronze cortado', 'categoria'=>'natureza', 'preview'=>'#b45309'],
        22 => ['nome'=>'🌸 Primavera',       'classe'=>'theme-primavera',     'desc'=>'Flores de cerejeira', 'categoria'=>'natureza', 'preview'=>'#f472b6'],
        24 => ['nome'=>'🦇 Vampire Gothic',  'classe'=>'theme-vampire',    'desc'=>'Arcos e formato de caixão', 'categoria'=>'premium', 'preview'=>'#991b1b'],
        25 => ['nome'=>'🍥 Naruto',          'classe'=>'theme-naruto',     'desc'=>'Pergaminhos ninja laranja', 'categoria'=>'anime', 'preview'=>'#f97316'],
        26 => ['nome'=>'🐉 Dragon Ball Z',   'classe'=>'theme-dbz',        'desc'=>'Esfera do dragão dourada', 'categoria'=>'anime', 'preview'=>'#f97316'],
       
        28 => ['nome'=>'🏴‍☠️ One Piece',    'classe'=>'theme-onepiece',   'desc'=>'Chapéu de palha pirata', 'categoria'=>'anime', 'preview'=>'#dc2626'],
        40 => ['nome'=>'🕹️ Retro Games',     'classe'=>'theme-retrogames', 'desc'=>'Pixel art 8-bit quadrado', 'categoria'=>'games', 'preview'=>'#e94560'],
        41 => ['nome'=>'🔫 Cyberpunk',       'classe'=>'theme-cyberpunk',  'desc'=>'Hexágonos neon rosa-ciano', 'categoria'=>'games', 'preview'=>'#ff00ff'],
        42 => ['nome'=>'⚙️ Steampunk',       'classe'=>'theme-steampunk',  'desc'=>'Engrenagens metálicas', 'categoria'=>'games', 'preview'=>'#d97706'],
        43 => ['nome'=>'🧬 Matrix',          'classe'=>'theme-matrix',     'desc'=>'Código cibernético verde', 'categoria'=>'games', 'preview'=>'#00ff41'],
        44 => ['nome'=>'⚡ Pokémon',         'classe'=>'theme-pokemon',    'desc'=>'Pokebola arredondada', 'categoria'=>'games', 'preview'=>'#dc2626'],
        2  => ['nome'=>'🎄 Natal',           'classe'=>'theme-natal',      'desc'=>'Árvore verde e vermelho festivo', 'categoria'=>'datas', 'preview'=>'#22c55e'],
        3  => ['nome'=>'🎆 Ano Novo',        'classe'=>'theme-anonovo',    'desc'=>'Fogos dourados festivos', 'categoria'=>'datas', 'preview'=>'#fbbf24'],
        21 => ['nome'=>'🎃 Halloween',       'classe'=>'theme-halloween',  'desc'=>'Teia de aranha laranja-roxo', 'categoria'=>'datas', 'preview'=>'#f97316'],
        23 => ['nome'=>'💘 Namorados',       'classe'=>'theme-valentine',  'desc'=>'Coração rosa romântico', 'categoria'=>'datas', 'preview'=>'#ef4444'],
        29 => ['nome'=>'🎄 Natal Neve',      'classe'=>'theme-natalneve',       'desc'=>'Árvore nevada festiva', 'categoria'=>'datas', 'preview'=>'#dc2626'],
        30 => ['nome'=>'🎭 Carnaval',        'classe'=>'theme-carnaval',   'desc'=>'Festa e alegria colorida', 'categoria'=>'datas', 'preview'=>'#fbbf24'],
        31 => ['nome'=>'🐣 Páscoa',          'classe'=>'theme-pascoa',     'desc'=>'Coelhinhos ovais coloridos', 'categoria'=>'datas', 'preview'=>'#f472b6'],
        32 => ['nome'=>'🌽 Festa Junina',    'classe'=>'theme-festajunina','desc'=>'Bandeirinhas e fogueira', 'categoria'=>'datas', 'preview'=>'#dc2626'],
        33 => ['nome'=>'🧸 Dia Crianças',    'classe'=>'theme-dcriancas',  'desc'=>'Balões e brinquedos', 'categoria'=>'datas', 'preview'=>'#fbbf24'],
        34 => ['nome'=>'🦷 Dia Dentista',    'classe'=>'theme-dentista',   'desc'=>'Saúde bucal e sorrisos', 'categoria'=>'datas', 'preview'=>'#3b82f6'],
        35 => ['nome'=>'👔 Trabalhador',     'classe'=>'theme-trabalhador','desc'=>'Engrenagens e construção', 'categoria'=>'datas', 'preview'=>'#dc2626'],
        36 => ['nome'=>'👩 Mulheres',        'classe'=>'theme-mulheres',   'desc'=>'Flores e empoderamento', 'categoria'=>'datas', 'preview'=>'#ec4899'],
        37 => ['nome'=>'💐 Dia das Mães',    'classe'=>'theme-maes',       'desc'=>'Coração com flores', 'categoria'=>'datas', 'preview'=>'#f472b6'],
        38 => ['nome'=>'👨 Dia dos Pais',    'classe'=>'theme-pais',       'desc'=>'Estrela paternidade robusta', 'categoria'=>'datas', 'preview'=>'#3b82f6'],
        39 => ['nome'=>'🎆 Ano Novo Fogo',   'classe'=>'theme-anonovo-fogo',         'desc'=>'Celebração e fogos dourados', 'categoria'=>'datas', 'preview'=>'#fbbf24'],
    ];
}

// ──────────────────────────────────────────────────────────────────────────
// SISTEMA AUTOMÁTICO DE TEMAS — DATAS COMEMORATIVAS + ROTAÇÃO MENSAL
// ──────────────────────────────────────────────────────────────────────────

/**
 * Mapa de datas comemorativas com seus temas.
 * Cada tema ativa 7 dias ANTES da data e desativa 2 dias DEPOIS.
 * Formato: 'MM-DD' => tema_id
 */
function getDatasComemoTemas() {
    return [
        // Mês-Dia => tema_id
        '01-01' => 3,   // Ano Novo (ativa 25/dez, desativa 03/jan)
        '02-14' => 23,  // Dia dos Namorados Internacional (Valentine)
        '03-08' => 36,  // Dia das Mulheres
        '03-25' => 34,  // Dia do Dentista (25 de outubro - usando como ref)
        '05-01' => 35,  // Dia do Trabalhador
        '05-11' => 37,  // Dia das Mães (2º domingo de maio - fixado em 11 como referência)
        '06-12' => 23,  // Dia dos Namorados (Brasil)
        '06-24' => 32,  // Festa Junina (período junino)
        '08-10' => 38,  // Dia dos Pais (2º domingo de agosto - fixado em 10 como referência)
        '10-12' => 33,  // Dia das Crianças
        '10-25' => 34,  // Dia do Dentista
        '10-31' => 21,  // Halloween
        '12-25' => 2,   // Natal
        '12-31' => 39,  // Ano Novo Fogo (réveillon)
    ];

    // Notas sobre temas comemorativos:
    // 29 => Natal Neve (alternativa ao Natal, pode ser usada em anos alternados)
    // 30 => Carnaval (data móvel - tratada separadamente)
    // 31 => Páscoa (data móvel - tratada separadamente)
}

/**
 * Retorna IDs de todos os temas comemorativos (categoria 'datas').
 * Estes NUNCA entram na rotação automática do dia 3.
 */
function getTemasComemoIDs() {
    $temas = getTemasDisponiveis();
    $ids = [];
    foreach ($temas as $id => $t) {
        if ($t['categoria'] === 'datas') {
            $ids[] = $id;
        }
    }
    return $ids;
}

/**
 * Calcula a data da Páscoa para um dado ano (algoritmo de Gauss).
 * Retorna string 'MM-DD'.
 */
function calcularPascoa($ano) {
    if (function_exists('easter_date')) {
        $ts = easter_date($ano);
        return date('m-d', $ts);
    }
    // Fallback: algoritmo anônimo de Meeus para calcular Páscoa sem ext-calendar
    $a = $ano % 19;
    $b = intdiv($ano, 100);
    $c = $ano % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $mes = intdiv($h + $l - 7 * $m + 114, 31);
    $dia = (($h + $l - 7 * $m + 114) % 31) + 1;
    return sprintf('%02d-%02d', $mes, $dia);
}

/**
 * Calcula a data do Carnaval (47 dias antes da Páscoa).
 * Retorna string 'MM-DD'.
 */
function calcularCarnaval($ano) {
    $pascoaMD = calcularPascoa($ano);
    $pascoa = new DateTime("$ano-$pascoaMD");
    $carnaval = (clone $pascoa)->modify('-47 days');
    return $carnaval->format('m-d');
}

/**
 * Calcula o 2º domingo de maio (Dia das Mães no Brasil).
 */
function calcularDiaMaes($ano) {
    $dt = new DateTime("$ano-05-01");
    // Avança até o primeiro domingo
    while ($dt->format('w') != 0) $dt->modify('+1 day');
    // Segundo domingo
    $dt->modify('+7 days');
    return $dt->format('m-d');
}

/**
 * Calcula o 2º domingo de agosto (Dia dos Pais no Brasil).
 */
function calcularDiaPais($ano) {
    $dt = new DateTime("$ano-08-01");
    while ($dt->format('w') != 0) $dt->modify('+1 day');
    $dt->modify('+7 days');
    return $dt->format('m-d');
}

/**
 * Verifica se há algum tema comemorativo ativo AGORA.
 * Ativa 7 dias antes da data, desativa 2 dias depois.
 * Retorna o tema_id ou null se nenhum estiver ativo.
 */
function getTemaComemorativoAtivo() {
    $hoje = new DateTime();
    $ano  = (int)$hoje->format('Y');

    // Mapa base de datas fixas
    $datas = getDatasComemoTemas();

    // Adicionar datas móveis do ano corrente
    $pascoaMD = calcularPascoa($ano);
    $datas[$pascoaMD] = 31; // Páscoa

    $carnavalMD = calcularCarnaval($ano);
    $datas[$carnavalMD] = 30; // Carnaval

    // Recalcular Dia das Mães e Dia dos Pais para o ano corrente
    $maesMD = calcularDiaMaes($ano);
    $datas[$maesMD] = 37; // Dia das Mães

    $paisMD = calcularDiaPais($ano);
    $datas[$paisMD] = 38; // Dia dos Pais

    // Verificar cada data comemorativa
    foreach ($datas as $mmdd => $temaId) {
        $dataComemo = new DateTime("$ano-$mmdd");
        $dataInicio = (clone $dataComemo)->modify('-7 days');
        $dataFim    = (clone $dataComemo)->modify('+2 days');

        // Se hoje está dentro do período de ativação
        if ($hoje >= $dataInicio && $hoje <= $dataFim) {
            return $temaId;
        }

        // Tratamento especial para virada de ano (ex: Ano Novo em 01-01)
        // Se a data comemorativa é em janeiro, verificar se dezembro do ano anterior se encaixa
        if ((int)$dataComemo->format('m') <= 1) {
            $dataComemoAnt = new DateTime(($ano - 1) . "-12-" . $dataComemo->format('d'));
            // Recalcular para caso de 01-01: o início seria 25/dez do ano anterior
            $dataComemo2 = new DateTime("$ano-$mmdd");
            $dataInicio2 = (clone $dataComemo2)->modify('-7 days');
            if ($dataInicio2->format('Y') < $ano) {
                // O período de ativação começa no ano anterior
                if ($hoje >= $dataInicio2 && $hoje <= $dataFim) {
                    return $temaId;
                }
            }
        }
    }

    return null;
}

/**
 * Retorna os IDs dos temas elegíveis para rotação automática.
 * Exclui todos os temas de categoria 'datas'.
 */
function getTemasParaRotacao() {
    $temas = getTemasDisponiveis();
    $comemorativoIDs = getTemasComemoIDs();
    $elegiveis = [];

    foreach ($temas as $id => $t) {
        if (!in_array($id, $comemorativoIDs)) {
            $elegiveis[] = $id;
        }
    }
    return $elegiveis;
}

/**
 * Inicializa as tabelas extras para o sistema automático de temas.
 */
function initTemasAutomatico($conn) {
    // Tabela de configuração global de tema
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tema_config (
        id INT PRIMARY KEY DEFAULT 1,
        tema_global_id INT DEFAULT 1 COMMENT 'Tema ativo globalmente (manual ou auto)',
        modo ENUM('auto','manual') DEFAULT 'auto' COMMENT 'auto=sistema decide, manual=admin fixou',
        ultimo_rotacao DATE DEFAULT NULL COMMENT 'Data da última rotação automática',
        rotacao_ativa TINYINT(1) DEFAULT 1 COMMENT 'Se a rotação do dia 3 está ativa',
        comemo_ativa TINYINT(1) DEFAULT 1 COMMENT 'Se temas comemorativos automáticos estão ativos',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Tabela de histórico de rotação (para não repetir)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tema_rotacao_historico (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tema_id INT NOT NULL,
        data_ativacao DATE NOT NULL,
        tipo ENUM('rotacao','comemorativo','manual') DEFAULT 'rotacao',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tema (tema_id),
        INDEX idx_data (data_ativacao)
    )");

    // Tabela de fundos personalizados
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tema_fundos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tema_id INT NOT NULL,
        arquivo VARCHAR(255) NOT NULL COMMENT 'Caminho relativo do arquivo de imagem',
        original_name VARCHAR(255) DEFAULT '' COMMENT 'Nome original do arquivo',
        ativo TINYINT(1) DEFAULT 1,
        uploaded_by INT DEFAULT NULL COMMENT 'ID do admin que fez upload',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_tema (tema_id)
    )");

    // Inserir configuração inicial se não existe
    $r_check = mysqli_query($conn, "SELECT COUNT(*) as c FROM tema_config");
    $check = $r_check ? mysqli_fetch_assoc($r_check) : null;
    if (!$check || $check['c'] == 0) {
        mysqli_query($conn, "INSERT INTO tema_config (id, tema_global_id, modo, rotacao_ativa, comemo_ativa)
            VALUES (1, 1, 'auto', 1, 1)");
    }
}

/**
 * Obtém a configuração global de temas.
 */
function getTemaConfig($conn) {
    $r = mysqli_query($conn, "SELECT * FROM tema_config WHERE id=1 LIMIT 1");
    if ($r && $row = mysqli_fetch_assoc($r)) {
        return $row;
    }
    return [
        'tema_global_id' => 1,
        'modo' => 'auto',
        'ultimo_rotacao' => null,
        'rotacao_ativa' => 1,
        'comemo_ativa' => 1,
    ];
}

/**
 * Salva a configuração global de temas. Somente admin.
 */
function setTemaConfig($conn, $dados) {
    if (!isAdmin()) return false;

    $campos = [];
    if (isset($dados['tema_global_id'])) {
        $campos[] = "tema_global_id=" . intval($dados['tema_global_id']);
    }
    if (isset($dados['modo'])) {
        $modo = mysqli_real_escape_string($conn, $dados['modo']);
        $campos[] = "modo='$modo'";
    }
    if (isset($dados['rotacao_ativa'])) {
        $campos[] = "rotacao_ativa=" . (intval($dados['rotacao_ativa']) ? 1 : 0);
    }
    if (isset($dados['comemo_ativa'])) {
        $campos[] = "comemo_ativa=" . (intval($dados['comemo_ativa']) ? 1 : 0);
    }
    if (isset($dados['ultimo_rotacao'])) {
        $data = mysqli_real_escape_string($conn, $dados['ultimo_rotacao']);
        $campos[] = "ultimo_rotacao='$data'";
    }

    if (empty($campos)) return false;

    return mysqli_query($conn, "UPDATE tema_config SET " . implode(',', $campos) . " WHERE id=1");
}

/**
 * Verifica se o usuário atual é admin.
 */
function isAdmin() {
    if (!empty($_SESSION['login'])  && $_SESSION['login']  === 'admin') return true;
    if (!empty($_SESSION['nivel'])  && $_SESSION['nivel']  === 'admin') return true;
    if (!empty($_SESSION['tipo'])   && $_SESSION['tipo']   === 'admin') return true;
    if (!empty($_SESSION['admin'])  && $_SESSION['admin']  == 1)        return true;
    if (!empty($_SESSION['iduser']) && $_SESSION['iduser'] == 1)        return true;
    if (!empty($_SESSION['id'])     && $_SESSION['id']     == 1)        return true;
    return false;
}

/**
 * Verifica se o usuário atual é revendedor.
 */
function isRevendedor() {
    return (!empty($_SESSION['login']) && $_SESSION['login'] !== 'admin')
        || (!empty($_SESSION['nivel']) && $_SESSION['nivel'] === 'revenda');
}

/**
 * Executa a rotação automática do dia 3 se necessário.
 * Chamada automaticamente no carregamento de qualquer página.
 * 
 * Regras:
 * - Só executa no dia 3 do mês
 * - Só executa se modo='auto' e rotacao_ativa=1
 * - Nunca usa temas comemorativos
 * - Nunca repete um tema já usado (até esgotar todos)
 * - Registra no histórico
 */
function executarRotacaoAutomatica($conn) {
    $hoje = date('Y-m-d');
    $dia  = (int)date('j');

    // Só executa no dia 3
    if ($dia !== 3) return null;

    $config = getTemaConfig($conn);

    // Verificar se rotação está ativa e modo auto
    if ($config['modo'] !== 'auto' || !$config['rotacao_ativa']) return null;

    // Verificar se já rotacionou hoje
    if ($config['ultimo_rotacao'] === $hoje) return null;

    // Pegar temas elegíveis (sem comemorativos)
    $elegiveis = getTemasParaRotacao();
    if (empty($elegiveis)) return null;

    // Pegar histórico de temas já usados
    $result = mysqli_query($conn, "SELECT tema_id FROM tema_rotacao_historico WHERE tipo='rotacao' ORDER BY data_ativacao DESC");
    $usados = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $usados[] = (int)$row['tema_id'];
        }
    }

    // Filtrar temas ainda não usados
    $disponiveis = array_diff($elegiveis, $usados);

    // Se todos já foram usados, resetar o histórico e começar de novo
    if (empty($disponiveis)) {
        mysqli_query($conn, "DELETE FROM tema_rotacao_historico WHERE tipo='rotacao'");
        $disponiveis = $elegiveis;
    }

    // Remover o tema atual para não repetir imediatamente
    $temaAtualId = (int)$config['tema_global_id'];
    $disponiveis = array_diff($disponiveis, [$temaAtualId]);
    if (empty($disponiveis)) {
        $disponiveis = $elegiveis;
    }

    // Escolher aleatoriamente
    $disponiveis = array_values($disponiveis);
    $novoTemaId  = $disponiveis[array_rand($disponiveis)];

    // Aplicar o novo tema
    mysqli_query($conn, "UPDATE tema_config SET tema_global_id=$novoTemaId, ultimo_rotacao='$hoje' WHERE id=1");

    // Registrar no histórico
    mysqli_query($conn, "INSERT INTO tema_rotacao_historico (tema_id, data_ativacao, tipo) VALUES ($novoTemaId, '$hoje', 'rotacao')");

    return $novoTemaId;
}

/**
 * FUNÇÃO PRINCIPAL: Determina qual tema deve estar ativo AGORA.
 * 
 * Prioridade:
 * 1. Tema comemorativo ativo (se comemo_ativa=1)
 * 2. Tema manual definido pelo admin (se modo='manual')
 * 3. Tema da rotação automática (dia 3) / tema global atual
 * 
 * Esta função é chamada em TODA página para determinar o tema.
 */
function getTemaGlobalAtivo($conn) {
    // Garantir que as tabelas existem
    static $initialized = false;
    if (!$initialized) {
        initTemasAutomatico($conn);
        $initialized = true;
    }

    $config = getTemaConfig($conn);
    $temas  = getTemasDisponiveis();

    // 1. Verificar tema comemorativo
    if ($config['comemo_ativa']) {
        $temaComemo = getTemaComemorativoAtivo();
        if ($temaComemo !== null && isset($temas[$temaComemo])) {
            $tema = $temas[$temaComemo];
            $tema['id'] = $temaComemo;
            $tema['origem'] = 'comemorativo';
            return $tema;
        }
    }

    // 2. Se modo desativado, retornar tema vazio (sem visual)
    if ($config['modo'] === 'desativado' || (int)$config['tema_global_id'] === 0) {
        return [
            'id' => 0,
            'nome' => 'Sem Tema',
            'classe' => '',
            'desc' => 'Visual padrão sem tema',
            'categoria' => 'padrao',
            'preview' => '#475569',
            'origem' => 'desativado',
        ];
    }

    // 3. Se modo manual, usar o tema definido pelo admin
    if ($config['modo'] === 'manual') {
        $tid = (int)$config['tema_global_id'];
        $tema = $temas[$tid] ?? $temas[1];
        $tema['id'] = $tid;
        $tema['origem'] = 'manual';
        return $tema;
    }

    // 3. Modo auto: tentar executar rotação (se for dia 3)
    $novoTema = executarRotacaoAutomatica($conn);

    // Recarregar config após possível rotação
    if ($novoTema !== null) {
        $config = getTemaConfig($conn);
    }

    $tid = (int)$config['tema_global_id'];
    $tema = $temas[$tid] ?? $temas[1];
    $tema['id'] = $tid;
    $tema['origem'] = 'auto';
    return $tema;
}

/**
 * Admin define um tema manualmente (muda para modo manual).
 */
function adminSetTemaGlobal($conn, $temaId) {
    if (!isAdmin()) return false;

    $tid = intval($temaId);
    $temas = getTemasDisponiveis();
    if (!isset($temas[$tid])) return false;

    // Mudar para modo manual e definir o tema global
    setTemaConfig($conn, [
        'tema_global_id' => $tid,
        'modo' => 'manual',
    ]);

    // Aplicar o mesmo tema na página de login automaticamente
    setarTemaLogin($conn, $tid);

    // Registrar no histórico
    $hoje = date('Y-m-d');
    mysqli_query($conn, "INSERT INTO tema_rotacao_historico (tema_id, data_ativacao, tipo)
        VALUES ($tid, '$hoje', 'manual')");

    return true;
}

/**
 * Admin volta para o modo automático.
 */
function adminSetModoAuto($conn) {
    if (!isAdmin()) return false;
    return setTemaConfig($conn, ['modo' => 'auto']);
}

/**
 * Admin desativa todos os temas visuais (volta ao visual padrão sem tema).
 */
function adminDesativarTemas($conn) {
    if (!isAdmin()) return false;
    setTemaConfig($conn, [
        'tema_global_id' => 0,
        'modo' => 'desativado',
    ]);
    // Limpar tema de login também
    @mysqli_query($conn, "UPDATE configs SET tema_login=0 WHERE 1 LIMIT 1");
    return true;
}

/**
 * Retorna o histórico de rotação de temas.
 */
function getHistoricoRotacao($conn, $limite = 50) {
    $limite = intval($limite);
    $result = mysqli_query($conn, "SELECT h.*, t.nome, t.classe
        FROM tema_rotacao_historico h
        LEFT JOIN temas t ON t.id = h.tema_id
        ORDER BY h.data_ativacao DESC, h.id DESC
        LIMIT $limite");
    $historico = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $historico[] = $row;
        }
    }
    return $historico;
}

/**
 * Obtém o fundo personalizado de um tema (se existir).
 */
function getFundoPersonalizado($conn, $temaId) {
    $tid = intval($temaId);
    $r = mysqli_query($conn, "SELECT * FROM tema_fundos WHERE tema_id=$tid AND ativo=1 LIMIT 1");
    if ($r && $row = mysqli_fetch_assoc($r)) {
        return $row;
    }
    return null;
}

/**
 * Salva um fundo personalizado para um tema. Somente admin.
 */
function salvarFundoPersonalizado($conn, $temaId, $arquivo, $nomeOriginal) {
    if (!isAdmin()) return false;

    $tid  = intval($temaId);
    $arq  = mysqli_real_escape_string($conn, $arquivo);
    $nome = mysqli_real_escape_string($conn, $nomeOriginal);
    $uid  = intval($_SESSION['iduser'] ?? 1);

    // Upsert: se já existe fundo para este tema, atualiza
    return mysqli_query($conn, "INSERT INTO tema_fundos (tema_id, arquivo, original_name, ativo, uploaded_by)
        VALUES ($tid, '$arq', '$nome', 1, $uid)
        ON DUPLICATE KEY UPDATE arquivo='$arq', original_name='$nome', ativo=1, uploaded_by=$uid");
}

/**
 * Remove o fundo personalizado de um tema. Somente admin.
 */
function removerFundoPersonalizado($conn, $temaId) {
    if (!isAdmin()) return false;

    $tid = intval($temaId);
    // Pegar o arquivo antes de remover para apagar do disco
    $fundo = getFundoPersonalizado($conn, $tid);
    if ($fundo && !empty($fundo['arquivo'])) {
        $caminho = __DIR__ . '/../' . $fundo['arquivo'];
        if (file_exists($caminho)) {
            @unlink($caminho);
        }
    }
    return mysqli_query($conn, "DELETE FROM tema_fundos WHERE tema_id=$tid");
}

/**
 * Lista todos os fundos personalizados.
 */
function listarFundosPersonalizados($conn) {
    $result = mysqli_query($conn, "SELECT * FROM tema_fundos WHERE ativo=1 ORDER BY tema_id");
    $fundos = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $fundos[$row['tema_id']] = $row;
        }
    }
    return $fundos;
}

/**
 * Retorna informações sobre o próximo tema comemorativo.
 */
function getProximoTemaComemorativo() {
    $hoje = new DateTime();
    $ano  = (int)$hoje->format('Y');
    $temas = getTemasDisponiveis();
    $datas = getDatasComemoTemas();

    // Adicionar datas móveis
    $datas[calcularPascoa($ano)] = 31;
    $datas[calcularCarnaval($ano)] = 30;
    $datas[calcularDiaMaes($ano)] = 37;
    $datas[calcularDiaPais($ano)] = 38;

    $proximo = null;
    $menorDiff = PHP_INT_MAX;

    foreach ($datas as $mmdd => $temaId) {
        $dataComemo = new DateTime("$ano-$mmdd");
        if ($dataComemo < $hoje) {
            // Tentar no próximo ano
            $dataComemo = new DateTime(($ano + 1) . "-$mmdd");
        }
        $diff = $hoje->diff($dataComemo)->days;
        if ($diff < $menorDiff) {
            $menorDiff = $diff;
            $tema = $temas[$temaId] ?? null;
            $proximo = [
                'tema_id' => $temaId,
                'nome' => $tema ? $tema['nome'] : 'Desconhecido',
                'data' => $dataComemo->format('d/m/Y'),
                'dias_restantes' => $diff,
                'ativa_em' => (clone $dataComemo)->modify('-7 days')->format('d/m/Y'),
            ];
        }
    }

    return $proximo;
}

// ──────────────────────────────────────────────────────────────────────────
// FUNÇÕES AUXILIARES DE SHAPE/SVG/VISUAL POR TEMA
// ──────────────────────────────────────────────────────────────────────────

function getSVGPorTema($classe) {
    $svgs = [
        'theme-dark'        => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="15" cy="12" r="1.5" fill="#6366f1" fill-opacity="0.7"/><circle cx="35" cy="7" r="1" fill="#818cf8" fill-opacity="0.6"/><circle cx="55" cy="15" r="2" fill="#a855f7" fill-opacity="0.5"/><circle cx="70" cy="8" r="1.5" fill="#6366f1" fill-opacity="0.6"/><circle cx="25" cy="32" r="1" fill="#818cf8" fill-opacity="0.5"/><circle cx="60" cy="38" r="1.5" fill="#a855f7" fill-opacity="0.4"/><line x1="15" y1="12" x2="35" y2="7" stroke="#6366f1" stroke-width="0.8" stroke-opacity="0.3"/><line x1="35" y1="7" x2="55" y2="15" stroke="#818cf8" stroke-width="0.8" stroke-opacity="0.25"/><line x1="55" y1="15" x2="70" y2="8" stroke="#a855f7" stroke-width="0.8" stroke-opacity="0.2"/></svg>',
        'theme-neon-roxo'   => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="25" r="12" fill="none" stroke="#a855f7" stroke-width="1.5" stroke-opacity="0.4"/><circle cx="40" cy="25" r="6" fill="#06b6d4" fill-opacity="0.15"/><line x1="28" y1="25" x2="52" y2="25" stroke="#a855f7" stroke-width="0.8" stroke-opacity="0.3"/><line x1="40" y1="13" x2="40" y2="37" stroke="#06b6d4" stroke-width="0.8" stroke-opacity="0.3"/></svg>',
        'theme-cyber'       => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><rect x="32" y="18" width="16" height="14" rx="2" fill="none" stroke="#facc15" stroke-width="1.5" stroke-opacity="0.35"/><line x1="40" y1="18" x2="40" y2="5" stroke="#facc15" stroke-width="1" stroke-opacity="0.3"/><line x1="48" y1="25" x2="70" y2="25" stroke="#fde047" stroke-width="1" stroke-opacity="0.25"/><line x1="32" y1="25" x2="10" y2="25" stroke="#facc15" stroke-width="1" stroke-opacity="0.2"/><rect x="34" y="2" width="12" height="6" rx="2" fill="#facc15" fill-opacity="0.2"/><circle cx="40" cy="25" r="3" fill="#facc15" fill-opacity="0.4"/></svg>',
        'theme-ocean'       => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M5 30 Q20 20 35 26 Q50 20 65 28 Q72 31 78 26" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-opacity="0.3"/><path d="M5 38 Q20 28 35 34 Q50 28 65 36 Q72 39 78 34" fill="none" stroke="#38bdf8" stroke-width="1.5" stroke-opacity="0.22"/><path d="M5 45 Q20 36 35 42 Q50 36 65 43" fill="none" stroke="#0ea5e9" stroke-width="1" stroke-opacity="0.15"/><circle cx="62" cy="14" r="8" fill="none" stroke="#38bdf8" stroke-width="1" stroke-opacity="0.2"/><circle cx="62" cy="14" r="4" fill="#0ea5e9" fill-opacity="0.12"/></svg>',
        'theme-sunset'      => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="40" r="18" fill="#f97316" fill-opacity="0.15"/><circle cx="40" cy="40" r="10" fill="#fbbf24" fill-opacity="0.12"/><path d="M5 35 Q20 28 40 32 Q60 28 78 32" fill="none" stroke="#f97316" stroke-width="1.5" stroke-opacity="0.25"/><path d="M5 42 Q20 36 40 39 Q60 36 78 39" fill="none" stroke="#fb923c" stroke-width="1" stroke-opacity="0.18"/><line x1="40" y1="22" x2="40" y2="5" stroke="#fbbf24" stroke-width="1.5" stroke-opacity="0.25"/><line x1="55" y1="26" x2="68" y2="13" stroke="#fbbf24" stroke-width="1" stroke-opacity="0.2"/><line x1="25" y1="26" x2="12" y2="13" stroke="#fbbf24" stroke-width="1" stroke-opacity="0.2"/></svg>',
        'theme-emerald'     => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><polygon points="40,5 58,20 58,38 40,48 22,38 22,20" fill="none" stroke="#10b981" stroke-width="1.5" stroke-opacity="0.25"/><polygon points="40,12 52,22 52,35 40,42 28,35 28,22" fill="#10b981" fill-opacity="0.06"/><circle cx="40" cy="25" r="5" fill="#34d399" fill-opacity="0.2"/><path d="M40 42 Q50 50 58 55" stroke="#10b981" stroke-width="1" stroke-opacity="0.15" fill="none"/></svg>',
        'theme-sakura'      => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><ellipse cx="40" cy="18" rx="6" ry="11" fill="#ec4899" fill-opacity="0.2" transform="rotate(0 40 28)"/><ellipse cx="40" cy="18" rx="6" ry="11" fill="#f472b6" fill-opacity="0.18" transform="rotate(72 40 28)"/><ellipse cx="40" cy="18" rx="6" ry="11" fill="#ec4899" fill-opacity="0.18" transform="rotate(144 40 28)"/><ellipse cx="40" cy="18" rx="6" ry="11" fill="#f472b6" fill-opacity="0.16" transform="rotate(216 40 28)"/><ellipse cx="40" cy="18" rx="6" ry="11" fill="#ec4899" fill-opacity="0.18" transform="rotate(288 40 28)"/><circle cx="40" cy="28" r="5" fill="#fbbf24" fill-opacity="0.25"/></svg>',
        'theme-galaxy'      => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><ellipse cx="40" cy="25" r="22" ry="9" fill="none" stroke="#8b5cf6" stroke-width="1" stroke-opacity="0.2" transform="rotate(-30 40 25)"/><circle cx="40" cy="25" r="5" fill="#c084fc" fill-opacity="0.15"/><circle cx="40" cy="25" r="2" fill="#8b5cf6" fill-opacity="0.4"/><circle cx="15" cy="10" r="1.5" fill="#fff" fill-opacity="0.5"/><circle cx="65" cy="8" r="1" fill="#fff" fill-opacity="0.4"/><circle cx="10" cy="38" r="1.5" fill="#fff" fill-opacity="0.45"/><circle cx="68" cy="42" r="1" fill="#fff" fill-opacity="0.35"/></svg>',
        'theme-rose'        => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M40 46 L18 30 C10 22 14 10 24 10 C31 10 35 18 40 25 C45 18 49 10 56 10 C66 10 70 22 62 30 L40 46Z" fill="#f43f5e" fill-opacity="0.18"/><path d="M40 38 L22 26 C16 20 19 11 27 11 C33 11 37 18 40 24 C43 18 47 11 53 11 C61 11 64 20 58 26 L40 38Z" fill="#fb7185" fill-opacity="0.1"/><circle cx="40" cy="24" r="3" fill="#f43f5e" fill-opacity="0.3"/></svg>',
        'theme-violet'      => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M40 5 L65 18 L65 38 Q65 50 40 50 Q15 50 15 38 L15 18 Z" fill="none" stroke="#7c3aed" stroke-width="1.5" stroke-opacity="0.22"/><path d="M40 12 L58 22 L58 36 Q58 45 40 45 Q22 45 22 36 L22 22 Z" fill="#7c3aed" fill-opacity="0.06"/><circle cx="40" cy="28" r="6" fill="#a78bfa" fill-opacity="0.15"/><circle cx="40" cy="28" r="2.5" fill="#7c3aed" fill-opacity="0.35"/></svg>',
        'theme-mint'        => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="25" r="18" fill="none" stroke="#14b8a6" stroke-width="1" stroke-opacity="0.18"/><circle cx="40" cy="25" r="11" fill="none" stroke="#2dd4bf" stroke-width="1" stroke-opacity="0.14"/><circle cx="40" cy="25" r="5" fill="#14b8a6" fill-opacity="0.1"/><path d="M22 16 Q31 8 40 14" fill="none" stroke="#2dd4bf" stroke-width="1.5" stroke-opacity="0.25"/><path d="M22 34 Q31 42 40 36" fill="none" stroke="#14b8a6" stroke-width="1.5" stroke-opacity="0.22"/></svg>',
        'theme-lavender'    => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><ellipse cx="40" cy="22" rx="18" ry="12" fill="#9333ea" fill-opacity="0.12"/><ellipse cx="25" cy="18" rx="11" ry="8" fill="#e879f9" fill-opacity="0.1"/><ellipse cx="55" cy="20" rx="10" ry="7" fill="#9333ea" fill-opacity="0.1"/><circle cx="40" cy="36" r="7" fill="#c084fc" fill-opacity="0.08"/></svg>',
        'theme-aqua'        => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M8 28 Q24 18 40 24 Q56 18 72 26" fill="none" stroke="#0891b2" stroke-width="2" stroke-opacity="0.28"/><path d="M5 36 Q22 26 38 32 Q55 26 72 34" fill="none" stroke="#67e8f9" stroke-width="1.5" stroke-opacity="0.22"/><circle cx="62" cy="14" r="9" fill="none" stroke="#67e8f9" stroke-width="1" stroke-opacity="0.2"/><circle cx="62" cy="14" r="5" fill="#0891b2" fill-opacity="0.1"/></svg>',
        'theme-gold'        => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><polygon points="40,5 58,16 65,35 52,48 28,48 15,35 22,16" fill="none" stroke="#d97706" stroke-width="1.5" stroke-opacity="0.25"/><polygon points="40,11 54,20 60,35 49,44 31,44 20,35 26,20" fill="none" stroke="#fde68a" stroke-width="1" stroke-opacity="0.18"/><circle cx="40" cy="25" r="6" fill="#d97706" fill-opacity="0.12"/><circle cx="40" cy="25" r="2.5" fill="#fde68a" fill-opacity="0.35"/></svg>',
        'theme-copper'      => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="25" r="16" fill="none" stroke="#b45309" stroke-width="2" stroke-opacity="0.22" stroke-dasharray="4 3"/><circle cx="40" cy="25" r="8" fill="none" stroke="#fb923c" stroke-width="1" stroke-opacity="0.18"/><rect x="34" y="19" width="12" height="12" rx="2" fill="none" stroke="#b45309" stroke-width="1" stroke-opacity="0.2"/><circle cx="40" cy="25" r="3" fill="#fb923c" fill-opacity="0.3"/></svg>',
        'theme-matrix'      => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><text x="5" y="15" fill="#00ff41" fill-opacity="0.3" font-size="8" font-family="monospace">010</text><text x="35" y="22" fill="#00ff41" fill-opacity="0.25" font-size="7" font-family="monospace">110</text><text x="55" y="12" fill="#00ff41" fill-opacity="0.2" font-size="6" font-family="monospace">01</text><text x="10" y="35" fill="#00ff41" fill-opacity="0.2" font-size="7" font-family="monospace">1001</text><text x="45" y="40" fill="#00ff41" fill-opacity="0.25" font-size="8" font-family="monospace">10</text><line x1="0" y1="20" x2="80" y2="20" stroke="#00ff41" stroke-width="0.5" stroke-opacity="0.15"/><line x1="0" y1="35" x2="80" y2="35" stroke="#00ff41" stroke-width="0.5" stroke-opacity="0.12"/></svg>',
        'theme-naruto'      => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><rect x="25" y="10" width="30" height="30" rx="3" fill="#f97316" fill-opacity="0.12"/><circle cx="40" cy="25" r="8" fill="#fbbf24" fill-opacity="0.12"/><path d="M28 18 L52 32 M52 18 L28 32" stroke="#fbbf24" stroke-width="1" stroke-opacity="0.15"/><polygon points="40,5 44,15 55,15 46,22 50,33 40,26 30,33 34,22 25,15 36,15" fill="#f97316" fill-opacity="0.18"/></svg>',
        'theme-dbz'         => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="25" r="16" fill="#f97316" fill-opacity="0.18"/><circle cx="40" cy="25" r="10" fill="#fbbf24" fill-opacity="0.15"/><circle cx="40" cy="25" r="4" fill="#ef4444" fill-opacity="0.22"/><text x="36" y="28" fill="#ef4444" fill-opacity="0.3" font-size="12" font-weight="bold">★</text><circle cx="35" cy="20" r="2" fill="#ef4444" fill-opacity="0.25"/></svg>',
        'theme-vampire'     => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M40 8 L32 22 L24 14 L28 28 L20 42 L40 35 L60 42 L52 28 L56 14 L48 22 Z" fill="#dc2626" fill-opacity="0.18"/><circle cx="34" cy="22" r="3" fill="#fff" fill-opacity="0.15"/><circle cx="46" cy="22" r="3" fill="#fff" fill-opacity="0.15"/><path d="M35 30 Q40 36 45 30" fill="none" stroke="#dc2626" stroke-width="1" stroke-opacity="0.3"/><circle cx="38" cy="32" r="1" fill="#dc2626" fill-opacity="0.5"/><circle cx="42" cy="32" r="1" fill="#dc2626" fill-opacity="0.5"/></svg>',
        'theme-onepiece'    => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><ellipse cx="40" cy="28" rx="18" ry="7" fill="#fbbf24" fill-opacity="0.18"/><ellipse cx="40" cy="22" rx="12" ry="5" fill="#dc2626" fill-opacity="0.15"/><path d="M28 28 L52 28" stroke="#dc2626" stroke-width="1.5" stroke-opacity="0.2"/><circle cx="40" cy="32" r="3" fill="#fbbf24" fill-opacity="0.25"/></svg>',
        'theme-cyberpunk'   => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><polygon points="40,5 75,22 75,42 40,50 5,42 5,22" fill="none" stroke="#ff00ff" stroke-width="1.5" stroke-opacity="0.3"/><circle cx="40" cy="25" r="8" fill="none" stroke="#00ffff" stroke-width="1" stroke-opacity="0.25"/><circle cx="40" cy="25" r="4" fill="#ff00ff" fill-opacity="0.15"/><line x1="5" y1="22" x2="75" y2="22" stroke="#00ffff" stroke-width="0.5" stroke-opacity="0.2"/></svg>',
        'theme-retrogames'  => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><rect x="22" y="18" width="36" height="20" fill="#e94560" fill-opacity="0.2"/><rect x="26" y="22" width="8" height="8" fill="#e94560" fill-opacity="0.25"/><rect x="46" y="22" width="8" height="8" fill="#e94560" fill-opacity="0.25"/><rect x="36" y="28" width="8" height="4" fill="#fff" fill-opacity="0.1"/><rect x="8" y="8" width="4" height="4" fill="#e94560" fill-opacity="0.4"/><rect x="18" y="4" width="4" height="4" fill="#fbbf24" fill-opacity="0.4"/><rect x="58" y="8" width="4" height="4" fill="#e94560" fill-opacity="0.35"/></svg>',
        'theme-pokemon'     => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="25" r="16" fill="#dc2626" fill-opacity="0.18"/><circle cx="40" cy="25" r="10" fill="#f8fafc" fill-opacity="0.12"/><circle cx="40" cy="25" r="4" fill="#333" fill-opacity="0.18"/><line x1="24" y1="25" x2="56" y2="25" stroke="#333" stroke-width="1.5" stroke-opacity="0.2"/></svg>',
        'theme-halloween'   => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path fill="#f97316" fill-opacity="0.18" d="M40 25 L72 8 L76 14 L62 24 L76 32 L68 38 L52 30 L44 44 L52 50 L40 46 L28 50 L36 44 L28 30 L12 38 L4 32 L18 24 L4 14 L8 8 Z"/><circle cx="40" cy="25" r="4" fill="#f97316" fill-opacity="0.3"/></svg>',
        'theme-natal'       => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><polygon points="40,5 58,28 50,28 60,45 20,45 30,28 22,28" fill="#22c55e" fill-opacity="0.2"/><rect x="35" y="45" width="10" height="8" fill="#b45309" fill-opacity="0.2"/><circle cx="40" cy="7" r="3" fill="#fbbf24" fill-opacity="0.5"/><circle cx="32" cy="30" r="2" fill="#ef4444" fill-opacity="0.4"/><circle cx="50" cy="32" r="2" fill="#fbbf24" fill-opacity="0.4"/><circle cx="40" cy="38" r="2" fill="#ef4444" fill-opacity="0.35"/></svg>',
        'theme-valentine'   => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M40 46 L14 28 C4 18 10 4 22 4 C30 4 35 14 40 22 C45 14 50 4 58 4 C70 4 76 18 66 28 L40 46Z" fill="#ef4444" fill-opacity="0.18"/><path d="M40 38 L20 24 C13 16 18 6 27 6 C33 6 38 14 40 20 C42 14 47 6 53 6 C62 6 67 16 60 24 L40 38Z" fill="#fb7185" fill-opacity="0.1"/></svg>',
        'theme-primavera'   => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M40 8 L44 18 L56 18 L47 25 L50 36 L40 30 L30 36 L33 25 L24 18 L36 18 Z" fill="#f472b6" fill-opacity="0.2"/><circle cx="40" cy="24" r="5" fill="#fbbf24" fill-opacity="0.2"/><circle cx="18" cy="35" r="4" fill="#34d399" fill-opacity="0.2"/><circle cx="62" cy="32" r="3" fill="#f472b6" fill-opacity="0.2"/></svg>',
        'theme-anonovo'     => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="28" r="4" fill="#fbbf24" fill-opacity="0.4"/><line x1="40" y1="28" x2="18" y2="8" stroke="#ef4444" stroke-width="1.5" stroke-opacity="0.3"/><line x1="40" y1="28" x2="62" y2="6" stroke="#fbbf24" stroke-width="1.5" stroke-opacity="0.3"/><line x1="40" y1="28" x2="72" y2="22" stroke="#3b82f6" stroke-width="1.5" stroke-opacity="0.25"/><line x1="40" y1="28" x2="8" y2="20" stroke="#fbbf24" stroke-width="1" stroke-opacity="0.25"/><circle cx="18" cy="8" r="2.5" fill="#ef4444" fill-opacity="0.4"/><circle cx="62" cy="6" r="2.5" fill="#fbbf24" fill-opacity="0.4"/><circle cx="72" cy="22" r="2" fill="#3b82f6" fill-opacity="0.35"/></svg>',
        'theme-pascoa'      => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><ellipse cx="40" cy="32" rx="14" ry="16" fill="#f472b6" fill-opacity="0.18"/><circle cx="27" cy="18" rx="8" ry="10" r="8" fill="#f472b6" fill-opacity="0.12"/><circle cx="53" cy="18" r="7" fill="#60a5fa" fill-opacity="0.12"/><ellipse cx="40" cy="12" rx="5" ry="7" fill="#f472b6" fill-opacity="0.15"/></svg>',
        'theme-festajunina' => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><line x1="5" y1="12" x2="75" y2="8" stroke="#b45309" stroke-width="0.8" stroke-opacity="0.3"/><polygon points="14,12 22,22 6,22" fill="#dc2626" fill-opacity="0.3"/><polygon points="30,11 38,21 22,21" fill="#fbbf24" fill-opacity="0.3"/><polygon points="46,10 54,20 38,20" fill="#3b82f6" fill-opacity="0.3"/><polygon points="62,9 70,19 54,19" fill="#dc2626" fill-opacity="0.25"/><rect x="32" y="28" width="16" height="14" rx="2" fill="#b45309" fill-opacity="0.12"/><path d="M32 28 L26 18 L40 22 L54 18 L48 28" fill="#dc2626" fill-opacity="0.1"/></svg>',
        'theme-dcriancas'   => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><ellipse cx="28" cy="24" rx="7" ry="10" fill="#fbbf24" fill-opacity="0.25"/><ellipse cx="42" cy="20" rx="6" ry="9" fill="#60a5fa" fill-opacity="0.25"/><ellipse cx="56" cy="26" rx="7" ry="10" fill="#f472b6" fill-opacity="0.2"/><line x1="28" y1="34" x2="28" y2="44" stroke="#b45309" stroke-width="1" stroke-opacity="0.2"/><line x1="42" y1="29" x2="42" y2="44" stroke="#b45309" stroke-width="1" stroke-opacity="0.2"/><line x1="56" y1="36" x2="56" y2="44" stroke="#b45309" stroke-width="1" stroke-opacity="0.2"/></svg>',
        'theme-mulheres'    => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="22" r="10" fill="#ec4899" fill-opacity="0.18"/><circle cx="22" cy="18" r="7" fill="#fbbf24" fill-opacity="0.15"/><circle cx="58" cy="18" r="7" fill="#fbbf24" fill-opacity="0.15"/><circle cx="40" cy="38" r="6" fill="#a855f7" fill-opacity="0.15"/><circle cx="40" cy="22" r="3" fill="#ec4899" fill-opacity="0.35"/></svg>',
        'theme-maes'        => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M40 44 L16 26 C7 17 12 4 24 4 C31 4 36 12 40 20 C44 12 49 4 56 4 C68 4 73 17 64 26 L40 44Z" fill="#f472b6" fill-opacity="0.2"/><circle cx="40" cy="22" r="7" fill="#fbbf24" fill-opacity="0.14"/><circle cx="26" cy="18" r="4" fill="#f472b6" fill-opacity="0.2"/><circle cx="54" cy="18" r="4" fill="#ef4444" fill-opacity="0.2"/></svg>',
        'theme-pais'        => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="15" r="6" fill="#fbbf24" fill-opacity="0.2"/><polygon points="40,10 42,14 47,14 43,17 44,22 40,19 36,22 37,17 33,14 38,14" fill="#fbbf24" fill-opacity="0.25"/><rect x="25" y="28" width="30" height="16" rx="2" fill="#3b82f6" fill-opacity="0.15"/><rect x="29" y="32" width="6" height="6" rx="1" fill="#3b82f6" fill-opacity="0.25"/></svg>',
        'theme-trabalhador' => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="25" r="12" fill="none" stroke="#dc2626" stroke-width="2" stroke-opacity="0.25"/><circle cx="40" cy="25" r="6" fill="#dc2626" fill-opacity="0.12"/><circle cx="52" cy="25" r="3" fill="none" stroke="#dc2626" stroke-width="1" stroke-opacity="0.2"/><circle cx="28" cy="25" r="3" fill="none" stroke="#dc2626" stroke-width="1" stroke-opacity="0.2"/><circle cx="40" cy="37" r="3" fill="none" stroke="#dc2626" stroke-width="1" stroke-opacity="0.2"/><circle cx="40" cy="13" r="3" fill="none" stroke="#dc2626" stroke-width="1" stroke-opacity="0.2"/></svg>',
        'theme-dentista'    => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><rect x="32" y="12" width="16" height="28" rx="8" fill="#f8fafc" fill-opacity="0.12"/><circle cx="40" cy="26" r="3" fill="#3b82f6" fill-opacity="0.2"/><path d="M36 34 Q40 38 44 34" fill="none" stroke="#3b82f6" stroke-width="1.5" stroke-opacity="0.3"/><circle cx="24" cy="22" r="5" fill="#22d3ee" fill-opacity="0.15"/><circle cx="56" cy="22" r="5" fill="#22d3ee" fill-opacity="0.15"/></svg>',
        'theme-arctic'      => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><polygon points="40,5 46,18 60,20 50,30 52,44 40,38 28,44 30,30 20,20 34,18" fill="none" stroke="#67e8f9" stroke-width="1" stroke-opacity="0.3"/><circle cx="18" cy="12" r="2" fill="#fff" fill-opacity="0.3"/><circle cx="62" cy="10" r="1.5" fill="#e0f2fe" fill-opacity="0.35"/><circle cx="10" cy="38" r="1" fill="#fff" fill-opacity="0.25"/><circle cx="70" cy="40" r="1.5" fill="#e0f2fe" fill-opacity="0.3"/><path d="M5 42 Q20 35 35 40 Q50 35 65 40 Q72 42 78 38" fill="none" stroke="#bae6fd" stroke-width="1.5" stroke-opacity="0.2"/></svg>',
        'theme-inferno'     => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M30 45 Q28 30 35 22 Q32 18 38 10 Q42 18 40 22 Q46 15 50 8 Q52 20 48 26 Q55 20 52 30 Q58 28 50 45 Z" fill="#ef4444" fill-opacity="0.2"/><path d="M35 45 Q34 35 38 28 Q40 32 42 28 Q46 35 45 45 Z" fill="#f97316" fill-opacity="0.18"/><circle cx="40" cy="38" r="3" fill="#fbbf24" fill-opacity="0.25"/></svg>',
        'theme-caramel'     => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><ellipse cx="40" cy="25" rx="22" ry="14" fill="none" stroke="#d97706" stroke-width="1.5" stroke-opacity="0.22"/><ellipse cx="40" cy="25" rx="12" ry="8" fill="#92400e" fill-opacity="0.12"/><circle cx="30" cy="20" r="4" fill="#d97706" fill-opacity="0.15"/><circle cx="50" cy="20" r="4" fill="#fbbf24" fill-opacity="0.12"/><path d="M25 35 Q33 42 40 38 Q47 42 55 35" fill="none" stroke="#d97706" stroke-width="1" stroke-opacity="0.2"/></svg>',
        'theme-steampunk'   => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="30" cy="25" r="10" fill="none" stroke="#d97706" stroke-width="1.5" stroke-opacity="0.25"/><circle cx="30" cy="25" r="5" fill="none" stroke="#fbbf24" stroke-width="1" stroke-opacity="0.2"/><circle cx="52" cy="20" r="7" fill="none" stroke="#d97706" stroke-width="1.5" stroke-opacity="0.22"/><circle cx="52" cy="20" r="3" fill="none" stroke="#fbbf24" stroke-width="1" stroke-opacity="0.18"/><line x1="37" y1="20" x2="45" y2="20" stroke="#d97706" stroke-width="1" stroke-opacity="0.2"/><circle cx="55" cy="35" r="5" fill="none" stroke="#d97706" stroke-width="1" stroke-opacity="0.2"/><rect x="22" y="38" width="16" height="6" rx="2" fill="#92400e" fill-opacity="0.12"/></svg>',
        'theme-natalneve'   => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><polygon points="40,8 52,28 46,28 54,42 26,42 34,28 28,28" fill="#22c55e" fill-opacity="0.18"/><circle cx="15" cy="10" r="2" fill="#fff" fill-opacity="0.4"/><circle cx="65" cy="8" r="1.5" fill="#fff" fill-opacity="0.35"/><circle cx="10" cy="30" r="1" fill="#e0f2fe" fill-opacity="0.3"/><circle cx="72" cy="25" r="2" fill="#fff" fill-opacity="0.3"/><circle cx="25" cy="15" r="1.5" fill="#e0f2fe" fill-opacity="0.35"/><circle cx="58" cy="38" r="1" fill="#fff" fill-opacity="0.25"/><circle cx="40" cy="10" r="2.5" fill="#fbbf24" fill-opacity="0.45"/></svg>',
        'theme-carnaval'    => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><ellipse cx="40" cy="20" rx="15" ry="10" fill="#8b5cf6" fill-opacity="0.15"/><ellipse cx="28" cy="18" rx="8" ry="6" fill="#ec4899" fill-opacity="0.15"/><ellipse cx="52" cy="18" rx="8" ry="6" fill="#22c55e" fill-opacity="0.15"/><circle cx="20" cy="35" r="3" fill="#fbbf24" fill-opacity="0.25"/><circle cx="40" cy="38" r="3" fill="#ec4899" fill-opacity="0.2"/><circle cx="60" cy="35" r="3" fill="#3b82f6" fill-opacity="0.25"/><path d="M15 28 Q28 22 40 26 Q52 22 65 28" fill="none" stroke="#fbbf24" stroke-width="1" stroke-opacity="0.25"/></svg>',
        'theme-anonovo-fogo'=> '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="30" r="5" fill="#fbbf24" fill-opacity="0.35"/><line x1="40" y1="30" x2="20" y2="8" stroke="#ef4444" stroke-width="1.5" stroke-opacity="0.35"/><line x1="40" y1="30" x2="60" y2="6" stroke="#f97316" stroke-width="1.5" stroke-opacity="0.35"/><line x1="40" y1="30" x2="72" y2="18" stroke="#fbbf24" stroke-width="1.5" stroke-opacity="0.3"/><line x1="40" y1="30" x2="8" y2="16" stroke="#ef4444" stroke-width="1.5" stroke-opacity="0.3"/><line x1="40" y1="30" x2="30" y2="5" stroke="#f97316" stroke-width="1" stroke-opacity="0.25"/><line x1="40" y1="30" x2="55" y2="5" stroke="#fbbf24" stroke-width="1" stroke-opacity="0.25"/><circle cx="20" cy="8" r="3" fill="#ef4444" fill-opacity="0.4"/><circle cx="60" cy="6" r="3" fill="#f97316" fill-opacity="0.4"/><circle cx="72" cy="18" r="2.5" fill="#fbbf24" fill-opacity="0.35"/></svg>',
    ];
    return $svgs[$classe] ?? '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="25" r="15" fill="rgba(255,255,255,0.1)"/></svg>';
}

function getShapeEstilos($classe) {
    $shapes = [
        'theme-dark'        => ['card'=>'border-radius:20px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'20px'],
        'theme-neon-roxo'   => ['card'=>'border-radius:20px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'20px'],
        'theme-cyber'       => ['card'=>'clip-path:polygon(12px 0,100% 0,100% calc(100% - 12px),calc(100% - 12px) 100%,0 100%,0 12px);border-radius:2px;', 'avatar'=>'clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);border-radius:0;', 'badge_r'=>'3px'],
        'theme-ocean'       => ['card'=>'border-radius:22px 6px 22px 6px;', 'avatar'=>'border-radius:22px 6px 22px 6px;', 'badge_r'=>'22px 6px'],
        'theme-sunset'      => ['card'=>'border-radius:4px 18px 4px 18px;', 'avatar'=>'border-radius:4px 18px 4px 18px;', 'badge_r'=>'4px 18px'],
        'theme-emerald'     => ['card'=>'clip-path:polygon(0 10px,10px 0,100% 0,100% calc(100% - 10px),calc(100% - 10px) 100%,0 100%);border-radius:0;', 'avatar'=>'clip-path:polygon(0 10px,10px 0,100% 0,100% calc(100% - 10px),calc(100% - 10px) 100%,0 100%);border-radius:0;', 'badge_r'=>'4px'],
        'theme-sakura'      => ['card'=>'border-radius:28px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'28px'],
        'theme-galaxy'      => ['card'=>'border-radius:32px 10px 32px 10px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'32px 10px'],
        'theme-rose'        => ['card'=>'border-radius:0 24px 0 24px;', 'avatar'=>'border-radius:0 18px 0 18px;', 'badge_r'=>'0 18px'],
        'theme-violet'      => ['card'=>'clip-path:polygon(0 16px,50% 0,100% 16px,100% 100%,0 100%);border-radius:0;', 'avatar'=>'clip-path:polygon(50% 0%,80% 10%,100% 35%,100% 70%,80% 90%,50% 100%,20% 90%,0% 70%,0% 35%,20% 10%);border-radius:0;', 'badge_r'=>'4px'],
        'theme-mint'        => ['card'=>'border-radius:38px 10px 38px 10px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'38px 10px'],
        'theme-lavender'    => ['card'=>'border-radius:28px 28px 16px 16px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'28px'],
        'theme-aqua'        => ['card'=>'border-radius:6px 32px 6px 32px;', 'avatar'=>'border-radius:6px 32px 6px 32px;', 'badge_r'=>'6px 32px'],
        'theme-gold'        => ['card'=>'border-radius:10px;', 'avatar'=>'clip-path:polygon(50% 0%,90% 20%,100% 60%,75% 100%,25% 100%,0% 60%,10% 20%);border-radius:0;', 'badge_r'=>'10px'],
        'theme-copper'      => ['card'=>'clip-path:polygon(16px 0,100% 0,100% calc(100% - 16px),calc(100% - 16px) 100%,0 100%,0 16px);border-radius:0;', 'avatar'=>'clip-path:polygon(16px 0,100% 0,100% calc(100% - 16px),calc(100% - 16px) 100%,0 100%,0 16px);border-radius:0;', 'badge_r'=>'3px'],
        'theme-matrix'      => ['card'=>'border-radius:2px;', 'avatar'=>'border-radius:0;', 'badge_r'=>'2px'],
        'theme-naruto'      => ['card'=>'border-radius:8px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'8px'],
        'theme-dbz'         => ['card'=>'border-radius:16px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'16px'],
        'theme-vampire'     => ['card'=>'border-radius:0 16px 0 16px;', 'avatar'=>'clip-path:polygon(50% 0%,100% 50%,50% 100%,0% 50%);border-radius:0;', 'badge_r'=>'0 16px'],
        'theme-onepiece'    => ['card'=>'border-radius:10px 10px 16px 16px;', 'avatar'=>'border-radius:40% 60% 70% 30%/40% 50% 60% 50%;', 'badge_r'=>'10px 16px'],
        'theme-cyberpunk'   => ['card'=>'clip-path:polygon(8px 0,100% 0,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px);border-radius:0;', 'avatar'=>'clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);border-radius:0;', 'badge_r'=>'3px'],
        'theme-retrogames'  => ['card'=>'border-radius:0;', 'avatar'=>'border-radius:0;', 'badge_r'=>'0'],
        'theme-pokemon'     => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-halloween'   => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-natal'       => ['card'=>'border-radius:20px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'20px'],
        'theme-anonovo'     => ['card'=>'border-radius:4px 18px 4px 18px;', 'avatar'=>'border-radius:4px 18px 4px 18px;', 'badge_r'=>'4px 18px'],
        'theme-valentine'   => ['card'=>'border-radius:22px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'22px'],
        'theme-primavera'   => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-pascoa'      => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-festajunina' => ['card'=>'border-radius:14px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'14px'],
        'theme-dcriancas'   => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-mulheres'    => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-maes'        => ['card'=>'border-radius:22px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'22px'],
        'theme-pais'        => ['card'=>'border-radius:14px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'14px'],
        'theme-trabalhador' => ['card'=>'border-radius:10px;', 'avatar'=>'border-radius:0;', 'badge_r'=>'10px'],
        'theme-dentista'    => ['card'=>'border-radius:14px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'14px'],
        'theme-arctic'      => ['card'=>'border-radius:24px 8px 24px 8px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'24px 8px'],
        'theme-inferno'     => ['card'=>'border-radius:6px 22px 6px 22px;', 'avatar'=>'border-radius:6px 22px 6px 22px;', 'badge_r'=>'6px 22px'],
        'theme-caramel'     => ['card'=>'border-radius:20px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'20px'],
        'theme-steampunk'   => ['card'=>'clip-path:polygon(10px 0,100% 0,100% calc(100% - 10px),calc(100% - 10px) 100%,0 100%,0 10px);border-radius:0;', 'avatar'=>'clip-path:polygon(50% 0%,90% 20%,100% 60%,75% 100%,25% 100%,0% 60%,10% 20%);border-radius:0;', 'badge_r'=>'4px'],
        'theme-natalneve'   => ['card'=>'border-radius:22px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'22px'],
        'theme-carnaval'    => ['card'=>'border-radius:24px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'24px'],
        'theme-anonovo-fogo'=> ['card'=>'border-radius:4px 18px 4px 18px;', 'avatar'=>'border-radius:4px 18px 4px 18px;', 'badge_r'=>'4px 18px'],
    ];
    return $shapes[$classe] ?? ['card'=>'border-radius:16px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'16px'];
}

function getFundoTema($classe, $cor) {
    $fundos = [
        'theme-dark'        => 'linear-gradient(145deg,#1e293b,#0f172a)',
        'theme-neon-roxo'   => 'linear-gradient(145deg,#150025,#0a0015)',
        'theme-cyber'       => 'linear-gradient(145deg,#14100a,#0a0800)',
        'theme-ocean'       => 'linear-gradient(145deg,#041525,#020d1a)',
        'theme-sunset'      => 'linear-gradient(160deg,#1a0f00,#0f0800)',
        'theme-emerald'     => 'linear-gradient(135deg,#04150e,#020f0a)',
        'theme-sakura'      => 'linear-gradient(135deg,#1a0020,#0f0015)',
        'theme-galaxy'      => 'linear-gradient(135deg,#0a0019,#04000f)',
        'theme-rose'        => 'linear-gradient(135deg,#200015,#1a0010)',
        'theme-violet'      => 'linear-gradient(135deg,#150030,#0d0020)',
        'theme-mint'        => 'linear-gradient(135deg,#00251f,#001a18)',
        'theme-lavender'    => 'linear-gradient(135deg,#200030,#150020)',
        'theme-aqua'        => 'linear-gradient(135deg,#002530,#001820)',
        'theme-gold'        => 'linear-gradient(135deg,#1a1000,#0f0900)',
        'theme-copper'      => 'linear-gradient(135deg,#1a1000,#0f0800)',
        'theme-matrix'      => '#0a0a0a',
        'theme-naruto'      => 'linear-gradient(145deg,#1f1a15,#16120e)',
        'theme-dbz'         => 'linear-gradient(145deg,#1a1510,#0f0a08)',
        'theme-vampire'     => 'linear-gradient(145deg,#1a0a0a,#0f0505)',
        'theme-onepiece'    => 'linear-gradient(145deg,#1f1515,#161010)',
        'theme-cyberpunk'   => 'linear-gradient(145deg,#11111a,#0d0d15)',
        'theme-retrogames'  => '#16213e',
        'theme-pokemon'     => 'linear-gradient(145deg,#1a1515,#0f0a0a)',
        'theme-halloween'   => 'linear-gradient(145deg,#1a1010,#0f0a0a)',
        'theme-natal'       => 'linear-gradient(145deg,#04150e,#020f08)',
        'theme-anonovo'     => 'linear-gradient(145deg,#18150a,#0a0800)',
        'theme-valentine'   => 'linear-gradient(145deg,#1a0f1a,#100a10)',
        'theme-primavera'   => 'linear-gradient(145deg,#1a1520,#0f0a15)',
        'theme-pascoa'      => 'linear-gradient(145deg,#1a1520,#0f0a15)',
        'theme-arctic'      => 'linear-gradient(145deg,#0a1a2a,#051220)',
        'theme-inferno'     => 'linear-gradient(145deg,#1a0800,#0f0500)',
        'theme-caramel'     => 'linear-gradient(145deg,#1a1208,#0f0a04)',
        'theme-steampunk'   => 'linear-gradient(145deg,#1a1408,#0f0c04)',
        'theme-natalneve'   => 'linear-gradient(145deg,#0a1a2a,#051525)',
        'theme-carnaval'    => 'linear-gradient(145deg,#1a0a20,#0f0515)',
        'theme-anonovo-fogo'=> 'linear-gradient(145deg,#1a1005,#0f0a02)',
        'theme-festajunina' => 'linear-gradient(145deg,#1a1208,#0f0a04)',
        'theme-dcriancas'   => 'linear-gradient(145deg,#1a1820,#0f1015)',
        'theme-mulheres'    => 'linear-gradient(145deg,#1a0a20,#100515)',
        'theme-maes'        => 'linear-gradient(145deg,#1a0f18,#0f0a10)',
        'theme-pais'        => 'linear-gradient(145deg,#0a1525,#050a1a)',
        'theme-trabalhador' => 'linear-gradient(145deg,#1a0a0a,#0f0505)',
        'theme-dentista'    => 'linear-gradient(145deg,#0a1525,#050a1a)',
    ];
    return $fundos[$classe] ?? 'linear-gradient(145deg,#1e293b,#0f172a)';
}
// (continuação — após getSVGPorTema e getShapeEstilos e getFundoTema e getFaixaGradiente)

function getFaixaGradiente($classe, $cor) {
    $faixas = [
        'theme-dark'        => 'linear-gradient(90deg,#6366f1,#818cf8,#a855f7)',
        'theme-neon-roxo'   => 'linear-gradient(90deg,#a855f7,#06b6d4,#a855f7)',
        'theme-cyber'       => 'linear-gradient(90deg,#facc15,#fde047,#eab308)',
        'theme-ocean'       => 'linear-gradient(90deg,#0ea5e9,#38bdf8,#0ea5e9)',
        'theme-sunset'      => 'linear-gradient(90deg,#f97316,#fbbf24,#ef4444,#f97316)',
        'theme-emerald'     => 'linear-gradient(90deg,#10b981,#34d399,#059669)',
        'theme-sakura'      => 'linear-gradient(90deg,#ec4899,#f472b6,#db2777,#ec4899)',
        'theme-galaxy'      => 'linear-gradient(90deg,#8b5cf6,#c084fc,#a855f7,#8b5cf6)',
        'theme-rose'        => 'linear-gradient(90deg,#f43f5e,#fb7185,#e11d48)',
        'theme-violet'      => 'linear-gradient(90deg,#7c3aed,#a78bfa,#6d28d9)',
        'theme-mint'        => 'linear-gradient(90deg,#14b8a6,#2dd4bf,#0d9488)',
        'theme-lavender'    => 'linear-gradient(90deg,#9333ea,#c084fc,#e879f9)',
        'theme-aqua'        => 'linear-gradient(90deg,#0891b2,#67e8f9,#06b6d4)',
        'theme-gold'        => 'linear-gradient(90deg,#d97706,#fde68a,#f59e0b,#fbbf24,#d97706)',
        'theme-copper'      => 'linear-gradient(90deg,#b45309,#fb923c,#ea580c)',
        'theme-matrix'      => '#00ff41',
        'theme-naruto'      => 'linear-gradient(90deg,#f97316,#fbbf24,#f97316)',
        'theme-dbz'         => 'linear-gradient(90deg,#f97316,#fbbf24,#3b82f6)',
        'theme-vampire'     => 'linear-gradient(90deg,#dc2626,#7f1d1d,#dc2626)',
        'theme-onepiece'    => 'linear-gradient(90deg,#dc2626,#fbbf24,#dc2626)',
        'theme-cyberpunk'   => 'linear-gradient(90deg,#ff00ff,#00ffff,#ff00ff)',
        'theme-retrogames'  => '#e94560',
        'theme-pokemon'     => 'linear-gradient(90deg,#dc2626,#fbbf24,#3b82f6)',
        'theme-halloween'   => 'linear-gradient(90deg,#f97316,#8b5cf6,#dc2626)',
        'theme-natal'       => 'linear-gradient(90deg,#22c55e,#dc2626,#22c55e)',
        'theme-anonovo'     => 'linear-gradient(90deg,#f59e0b,#ef4444,#3b82f6,#f59e0b)',
        'theme-valentine'   => 'linear-gradient(90deg,#ef4444,#ec4899,#fbbf24)',
        'theme-primavera'   => 'linear-gradient(90deg,#f472b6,#34d399,#f472b6)',
        'theme-pascoa'      => 'linear-gradient(90deg,#f472b6,#60a5fa,#fbbf24)',
        'theme-festajunina' => 'linear-gradient(90deg,#dc2626,#fbbf24,#3b82f6)',
        'theme-dcriancas'   => 'linear-gradient(90deg,#fbbf24,#60a5fa,#f472b6)',
        'theme-mulheres'    => 'linear-gradient(90deg,#ec4899,#a855f7,#fbbf24)',
        'theme-maes'        => 'linear-gradient(90deg,#f472b6,#ef4444,#fbbf24)',
        'theme-pais'        => 'linear-gradient(90deg,#3b82f6,#1e293b,#3b82f6)',
        'theme-trabalhador' => '#dc2626',
        'theme-dentista'    => 'linear-gradient(90deg,#3b82f6,#22d3ee,#3b82f6)',
        'theme-inferno'     => 'linear-gradient(90deg,#dc2626,#f97316)',
        'theme-arctic'      => 'linear-gradient(90deg,#67e8f9,#bae6fd,#67e8f9)',
        'theme-caramel'     => 'linear-gradient(90deg,#d97706,#92400e,#d97706)',
        'theme-steampunk'   => 'linear-gradient(90deg,#d97706,#fbbf24,#92400e)',
        'theme-natalneve'   => 'linear-gradient(90deg,#22c55e,#e0f2fe,#dc2626)',
        'theme-carnaval'    => 'linear-gradient(90deg,#8b5cf6,#ec4899,#fbbf24,#22c55e)',
        'theme-anonovo-fogo'=> 'linear-gradient(90deg,#ef4444,#f97316,#fbbf24,#ef4444)',
    ];
    return $faixas[$classe] ?? "linear-gradient(90deg,{$cor},{$cor}aa)";
}

function getGlowColor($classe) {
    $glows = [
        'theme-dark'        => 'rgba(99,102,241,0.35)',
        'theme-neon-roxo'   => 'rgba(168,85,247,0.4)',
        'theme-cyber'       => 'rgba(250,204,21,0.4)',
        'theme-ocean'       => 'rgba(14,165,233,0.35)',
        'theme-sunset'      => 'rgba(249,115,22,0.35)',
        'theme-emerald'     => 'rgba(16,185,129,0.35)',
        'theme-sakura'      => 'rgba(236,72,153,0.4)',
        'theme-galaxy'      => 'rgba(139,92,246,0.45)',
        'theme-rose'        => 'rgba(244,63,94,0.35)',
        'theme-violet'      => 'rgba(124,58,237,0.4)',
        'theme-mint'        => 'rgba(20,184,166,0.35)',
        'theme-lavender'    => 'rgba(147,51,234,0.4)',
        'theme-aqua'        => 'rgba(8,145,178,0.35)',
        'theme-gold'        => 'rgba(217,119,6,0.45)',
        'theme-copper'      => 'rgba(180,83,9,0.35)',
        'theme-matrix'      => 'rgba(0,255,65,0.5)',
        'theme-naruto'      => 'rgba(249,115,22,0.4)',
        'theme-dbz'         => 'rgba(249,115,22,0.4)',
        'theme-vampire'     => 'rgba(220,38,38,0.45)',
        'theme-onepiece'    => 'rgba(220,38,38,0.4)',
        'theme-cyberpunk'   => 'rgba(255,0,255,0.5)',
        'theme-retrogames'  => 'rgba(233,69,96,0.4)',
        'theme-pokemon'     => 'rgba(220,38,38,0.35)',
        'theme-halloween'   => 'rgba(249,115,22,0.4)',
        'theme-natal'       => 'rgba(34,197,94,0.35)',
        'theme-valentine'   => 'rgba(239,68,68,0.4)',
        'theme-primavera'   => 'rgba(244,114,182,0.4)',
        'theme-anonovo'     => 'rgba(251,191,36,0.4)',
        'theme-inferno'     => 'rgba(220,38,38,0.4)',
        'theme-arctic'      => 'rgba(103,232,249,0.35)',
        'theme-caramel'     => 'rgba(217,119,6,0.4)',
        'theme-steampunk'   => 'rgba(217,119,6,0.4)',
        'theme-natalneve'   => 'rgba(34,197,94,0.35)',
        'theme-carnaval'    => 'rgba(139,92,246,0.4)',
        'theme-anonovo-fogo'=> 'rgba(249,115,22,0.45)',
        'theme-pascoa'      => 'rgba(244,114,182,0.4)',
        'theme-festajunina' => 'rgba(220,38,38,0.4)',
        'theme-dcriancas'   => 'rgba(251,191,36,0.4)',
        'theme-mulheres'    => 'rgba(236,72,153,0.4)',
        'theme-maes'        => 'rgba(244,114,182,0.4)',
        'theme-pais'        => 'rgba(59,130,246,0.4)',
        'theme-trabalhador' => 'rgba(220,38,38,0.4)',
        'theme-dentista'    => 'rgba(59,130,246,0.35)',
    ];
    return $glows[$classe] ?? 'rgba(255,255,255,0.2)';
}

function getBorderColorTema($classe) {
    $borders = [
        'theme-dark'        => 'rgba(99,102,241,0.5)',
        'theme-neon-roxo'   => 'rgba(168,85,247,0.5)',
        'theme-cyber'       => 'rgba(250,204,21,0.6)',
        'theme-ocean'       => 'rgba(14,165,233,0.45)',
        'theme-sunset'      => 'rgba(249,115,22,0.5)',
        'theme-emerald'     => 'rgba(16,185,129,0.5)',
        'theme-sakura'      => 'rgba(236,72,153,0.5)',
        'theme-galaxy'      => 'rgba(139,92,246,0.5)',
        'theme-rose'        => 'rgba(244,63,94,0.5)',
        'theme-violet'      => 'rgba(124,58,237,0.5)',
        'theme-mint'        => 'rgba(20,184,166,0.5)',
        'theme-lavender'    => 'rgba(147,51,234,0.5)',
        'theme-aqua'        => 'rgba(8,145,178,0.5)',
        'theme-gold'        => 'rgba(217,119,6,0.6)',
        'theme-copper'      => 'rgba(180,83,9,0.5)',
        'theme-matrix'      => '#00ff41',
        'theme-naruto'      => 'rgba(249,115,22,0.6)',
        'theme-dbz'         => 'rgba(249,115,22,0.5)',
        'theme-vampire'     => 'rgba(220,38,38,0.55)',
        'theme-onepiece'    => 'rgba(220,38,38,0.5)',
        'theme-cyberpunk'   => '#ff00ff',
        'theme-retrogames'  => '#e94560',
        'theme-pokemon'     => 'rgba(220,38,38,0.5)',
        'theme-halloween'   => 'rgba(249,115,22,0.5)',
        'theme-natal'       => 'rgba(34,197,94,0.5)',
        'theme-valentine'   => 'rgba(239,68,68,0.5)',
        'theme-primavera'   => 'rgba(244,114,182,0.5)',
        'theme-anonovo'     => 'rgba(251,191,36,0.5)',
        'theme-inferno'     => 'rgba(220,38,38,0.5)',
        'theme-arctic'      => 'rgba(103,232,249,0.45)',
        'theme-caramel'     => 'rgba(217,119,6,0.5)',
        'theme-steampunk'   => 'rgba(217,119,6,0.5)',
        'theme-natalneve'   => 'rgba(34,197,94,0.45)',
        'theme-carnaval'    => 'rgba(139,92,246,0.5)',
        'theme-anonovo-fogo'=> 'rgba(249,115,22,0.5)',
        'theme-pascoa'      => 'rgba(244,114,182,0.5)',
        'theme-festajunina' => 'rgba(220,38,38,0.5)',
        'theme-dcriancas'   => 'rgba(251,191,36,0.5)',
        'theme-mulheres'    => 'rgba(236,72,153,0.5)',
        'theme-maes'        => 'rgba(244,114,182,0.5)',
        'theme-pais'        => 'rgba(59,130,246,0.5)',
        'theme-trabalhador' => 'rgba(220,38,38,0.5)',
        'theme-dentista'    => 'rgba(59,130,246,0.45)',
    ];
    return $borders[$classe] ?? "rgba(255,255,255,0.3)";
}

// ────────────��─────────────────────────────────────────────────────────────
// MODAL DE TEMAS ATUALIZADO — SHAPE EDITION
// ──────────────────────────────────────────────────────────────────────────

function getModalTemasHTML($conn) {
    // Revendedores NÃO têm acesso ao modal de troca de temas
    if (!isAdmin()) {
        return '<!-- Troca de tema disponível apenas para administradores -->
<script>window.openThemeModal=function(){};window.fecharThemeModal=function(){};</script>';
    }

    try {
    $categorias   = getTemasPorCategoria($conn);
    $temaAtual    = getTemaSessao($conn);
    $temaLogin    = getTemaLogin($conn);
    $config       = getTemaConfig($conn);
    $proximoComemo = getProximoTemaComemorativo();

    $labels = [
        'padrao'   => 'Padrao',
        'moderno'  => 'Moderno',
        'premium'  => 'Premium',
        'natureza' => 'Natureza',
        'anime'    => 'Animes',
        'games'    => 'Games',
        'datas'    => 'Comemorativos',
    ];

    ob_start(); ?>
<div id="themeModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:9999;backdrop-filter:blur(6px);align-items:center;justify-content:center;">
  <div style="max-width:960px;width:96%;max-height:92vh;display:flex;flex-direction:column;background:linear-gradient(145deg,#0d1117,#020617);border-radius:24px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);box-shadow:0 30px 80px rgba(0,0,0,0.7);">

    <!-- HEADER -->
    <div style="background:linear-gradient(135deg,#4158D0,#C850C0,#FFCC70);padding:18px 24px;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
      <h5 style="margin:0;color:#fff;font-size:16px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;display:flex;align-items:center;gap:10px;">
        <span style="font-size:22px;">&#9734;</span> Galeria de Temas — AegisCore
      </h5>
      <button onclick="fecharThemeModal()" style="background:rgba(0,0,0,0.25);border:none;color:#fff;width:34px;height:34px;border-radius:50%;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.5)'" onmouseout="this.style.background='rgba(0,0,0,0.25)'">&times;</button>
    </div>

    <!-- FILTROS -->
    <div style="padding:14px 24px;background:rgba(255,255,255,0.02);border-bottom:1px solid rgba(255,255,255,0.06);flex-shrink:0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <span class="tf-btn tf-active" data-filter="all" onclick="filtrarTemas(this,'all')" style="padding:5px 14px;border-radius:20px;cursor:pointer;font-size:11px;font-weight:600;background:linear-gradient(135deg,#4158D0,#C850C0);color:#fff;border:none;transition:0.2s;">&#9733; Todos</span>
      <?php foreach ($labels as $key => $label): ?>
      <span class="tf-btn" data-filter="<?= $key ?>" onclick="filtrarTemas(this,'<?= $key ?>')" style="padding:5px 14px;border-radius:20px;cursor:pointer;font-size:11px;font-weight:600;background:rgba(255,255,255,0.06);color:#cbd5e1;border:1px solid rgba(255,255,255,0.08);transition:0.2s;"><?= $label ?></span>
      <?php endforeach; ?>
      <span style="margin-left:auto;font-size:10px;color:#475569;display:flex;align-items:center;gap:5px;">
        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;"></span>Painel ativo
        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;margin-left:6px;"></span>Login ativo
      </span>
    </div>

    <!-- GRID DE TEMAS -->
    <div style="flex:1;overflow-y:auto;padding:24px;" id="themeGrid">
      <?php foreach ($categorias as $cat => $temas):
        $catLabel = $labels[$cat] ?? ucfirst($cat);
        $count    = count($temas);
      ?>
      <div class="tc-section" data-category="<?= $cat ?>" style="margin-bottom:32px;">
        <div style="font-size:13px;font-weight:700;margin-bottom:14px;padding-left:12px;border-left:3px solid #C850C0;color:#e2e8f0;text-transform:uppercase;letter-spacing:0.8px;display:flex;align-items:center;gap:8px;">
          <?= $catLabel ?>
          <span style="font-size:10px;color:#475569;font-weight:500;text-transform:none;">(<?= $count ?>)</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;">

          <?php foreach ($temas as $id => $tema):
            $isActivePainel = ($temaAtual['id'] == $id);
            $isActiveLogin  = ($temaLogin['id']  == $id);
            $cor      = $tema['preview_cor'] ?? '#6366f1';
            $classe   = $tema['classe'];
            $svg      = getSVGPorTema($classe);
            $shape    = getShapeEstilos($classe);
            $fundo    = getFundoTema($classe, $cor);
            $faixa    = getFaixaGradiente($classe, $cor);
            $glow     = getGlowColor($classe);
            $bdrClr   = getBorderColorTema($classe);

            // Borda especial para retrogames
            $borderW  = ($classe === 'theme-retrogames') ? '3px' : '1px';
            $extraShadow = ($classe === 'theme-retrogames')
                          ? "8px 8px 0 #0f0f23"
                          : "0 4px 20px rgba(0,0,0,0.5)";
          ?>
          <div
            class="tc-card <?= $isActivePainel ? 'tc-active' : '' ?>"
            data-id="<?= $id ?>"
            data-category="<?= $cat ?>"
            data-cor="<?= htmlspecialchars($cor) ?>"
            data-glow="<?= htmlspecialchars($glow) ?>"
            data-bdr="<?= htmlspecialchars($bdrClr) ?>"
            onclick="selecionarTema(<?= $id ?>)"
            oncontextmenu="aplicarLogin(<?= $id ?>);return false;"
            style="
              position:relative;
              cursor:pointer;
              border-radius:14px;
              overflow:hidden;
              border:<?= $borderW ?> solid <?= $bdrClr ?>;
              box-shadow:<?= $extraShadow ?>;
              transition:transform 0.25s ease,box-shadow 0.25s ease,border-color 0.25s ease;
              background:#0d1117;
            "
            onmouseover="hoverCard(this)"
            onmouseout="unhoverCard(this)"
          >
            <!-- PREVIEW ÁREA -->
            <div style="
              height:110px;
              background:<?= $fundo ?>;
              position:relative;
              overflow:hidden;
              display:flex;
              flex-direction:column;
              align-items:center;
              justify-content:center;
            ">
              <!-- FAIXA SUPERIOR -->
              <div style="
                position:absolute;top:0;left:0;right:0;height:4px;
                background:<?= $faixa ?>;
                z-index:3;
              "></div>

              <!-- SVG DECORATIVO DE FUNDO -->
              <div style="
                position:absolute;bottom:0;right:0;width:90px;height:90px;
                opacity:0.9;pointer-events:none;z-index:1;
              "><?= $svg ?></div>

              <!-- MINI CARD SIMULADO -->
              <div style="
                position:relative;z-index:2;
                width:72%;
                margin-top:10px;
                background:rgba(255,255,255,0.06);
                backdrop-filter:blur(4px);
                border:1px solid rgba(255,255,255,0.1);
                <?= $shape['card'] ?>
                padding:8px 10px;
                display:flex;
                flex-direction:column;
                gap:4px;
              ">
                <div style="height:4px;border-radius:4px;background:<?= $cor ?>;opacity:0.7;width:75%;"></div>
                <div style="height:3px;border-radius:4px;background:<?= $cor ?>;opacity:0.4;width:45%;"></div>
                <div style="height:3px;border-radius:4px;background:rgba(255,255,255,0.15);width:60%;"></div>
              </div>

              <!-- MINI AVATAR -->
              <div style="
                position:absolute;
                top:8px;left:10px;
                width:22px;height:22px;
                background:<?= $cor ?>;
                opacity:0.85;
                <?= $shape['avatar'] ?>
                z-index:3;
              "></div>

              <!-- MINI ÍCONE ACENTO -->
              <div style="
                position:absolute;
                top:8px;right:10px;
                width:18px;height:18px;
                background:linear-gradient(135deg,<?= $cor ?>,<?= $cor ?>aa);
                border-radius:5px;
                z-index:3;
                display:flex;align-items:center;justify-content:center;
                font-size:9px;
              ">●</div>

              <?php if ($classe === 'theme-matrix'): ?>
              <!-- Texto especial Matrix -->
              <div style="position:absolute;bottom:8px;left:8px;font-family:monospace;font-size:9px;color:#00ff41;opacity:0.4;letter-spacing:2px;z-index:2;">010010</div>
              <?php endif; ?>

              <?php if ($classe === 'theme-retrogames'): ?>
              <!-- Pixel extra Retro -->
              <div style="position:absolute;top:8px;left:35px;width:6px;height:6px;background:#e94560;z-index:3;"></div>
              <div style="position:absolute;top:8px;left:45px;width:6px;height:6px;background:#fbbf24;z-index:3;"></div>
              <?php endif; ?>
            </div>

            <!-- INFO DO TEMA -->
            <div style="padding:10px 12px;background:#0d1117;">
              <div style="
                font-size:12px;font-weight:700;
                color:<?= $cor ?>;
                margin-bottom:3px;
                white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
              "><?= htmlspecialchars(stripEmoji($tema['nome'])) ?></div>
              <div style="font-size:10px;color:#475569;line-height:1.3;"><?= htmlspecialchars(mb_substr($tema['descricao'],0,35)) ?></div>
            </div>

            <!-- BADGE ATIVO PAINEL -->
            <?php if ($isActivePainel): ?>
            <div style="
              position:absolute;top:8px;
              <?= ($classe === 'theme-matrix') ? 'left' : 'right' ?>:8px;
              background:#10b981;color:#000;
              font-weight:800;border-radius:<?= $shape['badge_r'] ?>;
              padding:2px 7px;font-size:9px;
              box-shadow:0 2px 8px rgba(16,185,129,0.4);
              z-index:10;
            ">✓ ATIVO</div>
            <?php endif; ?>

            <!-- BADGE LOGIN -->
            <?php if ($isActiveLogin): ?>
            <div style="
              position:absolute;bottom:48px;right:8px;
              background:#f59e0b;color:#000;
              font-weight:700;border-radius:6px;
              padding:2px 7px;font-size:9px;
              box-shadow:0 2px 8px rgba(245,158,11,0.4);
              z-index:10;
            ">LOGIN</div>
            <?php endif; ?>

            <!-- BORDA BRILHANTE QUANDO ATIVO -->
            <?php if ($isActivePainel): ?>
            <div style="
              position:absolute;inset:0;
              border-radius:inherit;
              border:2px solid #10b981;
              pointer-events:none;
              box-shadow:inset 0 0 12px rgba(16,185,129,0.15);
              z-index:5;
            "></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>

        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- FOOTER -->
    <div style="padding:14px 24px;background:#020617;border-top:1px solid rgba(255,255,255,0.08);flex-shrink:0;">
      <!-- PAINEL DE CONTROLE AUTO-TEMA (Admin) -->
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:12px;padding:10px 14px;background:rgba(255,255,255,0.03);border-radius:10px;border:1px solid rgba(255,255,255,0.06);">
        <div style="font-size:11px;font-weight:700;color:#e2e8f0;display:flex;align-items:center;gap:6px;">
          <span style="font-size:14px;">&#9881;</span> Motor de Temas
        </div>
        <div style="font-size:10px;color:#94a3b8;display:flex;align-items:center;gap:6px;">
          Modo: <span style="color:<?= $config['modo']==='auto' ? '#10b981' : '#f59e0b' ?>;font-weight:700;"><?= strtoupper($config['modo']) ?></span>
        </div>
        <div style="font-size:10px;color:#94a3b8;display:flex;align-items:center;gap:6px;">
          Rotacao dia 3: <span style="color:<?= $config['rotacao_ativa'] ? '#10b981' : '#ef4444' ?>;font-weight:600;"><?= $config['rotacao_ativa'] ? 'ON' : 'OFF' ?></span>
        </div>
        <div style="font-size:10px;color:#94a3b8;display:flex;align-items:center;gap:6px;">
          Comemorativos: <span style="color:<?= $config['comemo_ativa'] ? '#10b981' : '#ef4444' ?>;font-weight:600;"><?= $config['comemo_ativa'] ? 'ON' : 'OFF' ?></span>
        </div>
        <?php if ($proximoComemo): ?>
        <div style="font-size:10px;color:#94a3b8;display:flex;align-items:center;gap:6px;">
          Proximo: <span style="color:#c084fc;font-weight:600;"><?= htmlspecialchars(stripEmoji($proximoComemo['nome'])) ?></span>
          <span style="color:#64748b;">(<?= $proximoComemo['dias_restantes'] ?>d)</span>
        </div>
        <?php endif; ?>
        <?php if (!empty($temaAtual['origem'])): ?>
        <div style="font-size:10px;color:#94a3b8;display:flex;align-items:center;gap:6px;">
          Origem: <span style="color:#38bdf8;font-weight:600;"><?= $temaAtual['origem'] ?></span>
        </div>
        <?php endif; ?>
        <div style="margin-left:auto;display:flex;gap:6px;">
          <form method="POST" style="margin:0;">
            <button type="submit" name="__toggleRotacao" value="1"
              style="background:rgba(<?= $config['rotacao_ativa'] ? '239,68,68' : '16,185,129' ?>,0.15);border:1px solid rgba(<?= $config['rotacao_ativa'] ? '239,68,68' : '16,185,129' ?>,0.3);color:<?= $config['rotacao_ativa'] ? '#fca5a5' : '#6ee7b7' ?>;padding:4px 10px;border-radius:6px;font-size:10px;cursor:pointer;transition:0.2s;">
              <?= $config['rotacao_ativa'] ? 'Pausar Rotacao' : 'Ativar Rotacao' ?>
            </button>
          </form>
          <form method="POST" style="margin:0;">
            <button type="submit" name="__toggleComemo" value="1"
              style="background:rgba(<?= $config['comemo_ativa'] ? '239,68,68' : '16,185,129' ?>,0.15);border:1px solid rgba(<?= $config['comemo_ativa'] ? '239,68,68' : '16,185,129' ?>,0.3);color:<?= $config['comemo_ativa'] ? '#fca5a5' : '#6ee7b7' ?>;padding:4px 10px;border-radius:6px;font-size:10px;cursor:pointer;transition:0.2s;">
              <?= $config['comemo_ativa'] ? 'Pausar Comemo' : 'Ativar Comemo' ?>
            </button>
          </form>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div style="display:flex;gap:8px;align-items:center;">
          <form method="POST" style="margin:0;">
            <button type="submit" name="__resetTema" value="1"
              style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#64748b;padding:8px 16px;border-radius:10px;font-size:12px;cursor:pointer;transition:0.2s;"
              onmouseover="this.style.background='rgba(255,255,255,0.08)'"
              onmouseout="this.style.background='rgba(255,255,255,0.04)'"
              title="Volta para modo automatico">
              &#8634; Modo Auto
            </button>
          </form>
          <span style="font-size:10px;color:#334155;">Clique = tema global | Direito = login | Resetar = modo auto</span>
        </div>
        <div style="display:flex;gap:10px;">
          <form method="POST" style="margin:0;" id="formLoginTema">
            <input type="hidden" name="__setLoginTema" id="loginTemaId" value="">
            <button type="button" id="btnLoginTema"
              style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);color:#fcd34d;padding:8px 16px;border-radius:10px;font-size:12px;cursor:pointer;transition:0.2s;"
              onmouseover="this.style.background='rgba(245,158,11,0.18)'"
              onmouseout="this.style.background='rgba(245,158,11,0.1)'">
              &#8658; Aplicar ao Login
            </button>
          </form>
          <button onclick="fecharThemeModal()"
            style="background:linear-gradient(135deg,#4158D0,#C850C0);border:none;color:#fff;padding:8px 22px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 15px rgba(199,80,192,0.3);transition:0.2s;"
            onmouseover="this.style.opacity='0.85'"
            onmouseout="this.style.opacity='1'">
            &#10003; Fechar
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- FORMS OCULTOS PARA SUBMIT -->
<form method="POST" id="formSetTema" style="display:none;">
  <input type="hidden" name="__setMeuTema" id="setTemaId" value="">
</form>

<script>
(function() {
  // Abrir / fechar
  window.openThemeModal = function() {
    var m = document.getElementById('themeModal');
    m.style.display = 'flex';
    requestAnimationFrame(function() { m.style.opacity = '1'; });
  };
  window.fecharThemeModal = function() {
    document.getElementById('themeModal').style.display = 'none';
  };

  // Hover com glow dinâmico
  window.hoverCard = function(el) {
    var glow = el.dataset.glow || 'rgba(255,255,255,0.2)';
    var bdr  = el.dataset.bdr  || 'rgba(255,255,255,0.3)';
    if (el.classList.contains('tc-active')) return;
    el.style.transform    = 'translateY(-5px)';
    el.style.boxShadow    = '0 12px 30px ' + glow;
    el.style.borderColor  = bdr;
  };
  window.unhoverCard = function(el) {
    if (el.classList.contains('tc-active')) return;
    el.style.transform   = '';
    el.style.boxShadow   = '';
    el.style.borderColor = '';
  };

  // Selecionar tema (click esquerdo)
  window.selecionarTema = function(id) {
    document.getElementById('setTemaId').value = id;
    document.getElementById('formSetTema').submit();
  };

  // Aplicar ao login (click direito)
  window.aplicarLogin = function(id) {
    document.getElementById('loginTemaId').value = id;
    document.getElementById('formLoginTema').submit();
  };

  // Botão "Aplicar ao Login" — usa o tema atualmente ativo no painel
  document.getElementById('btnLoginTema').addEventListener('click', function() {
    var active = document.querySelector('.tc-card.tc-active');
    if (active) {
      document.getElementById('loginTemaId').value = active.dataset.id;
      document.getElementById('formLoginTema').submit();
    } else {
      alert('Selecione um tema primeiro.');
    }
  });

  // Filtro de categorias
  window.filtrarTemas = function(btn, filter) {
    document.querySelectorAll('.tf-btn').forEach(function(b) {
      b.style.background = 'rgba(255,255,255,0.06)';
      b.style.color      = '#cbd5e1';
      b.style.border     = '1px solid rgba(255,255,255,0.08)';
    });
    btn.style.background = 'linear-gradient(135deg,#4158D0,#C850C0)';
    btn.style.color      = '#fff';
    btn.style.border     = 'none';

    document.querySelectorAll('.tc-section').forEach(function(sec) {
      sec.style.display = (filter === 'all' || sec.dataset.category === filter) ? 'block' : 'none';
    });
  };

  // Fechar com Esc ou clique fora
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharThemeModal();
  });
  document.getElementById('themeModal').addEventListener('click', function(e) {
    if (e.target === this) fecharThemeModal();
  });
})();
</script>
<?php
    return ob_get_clean();
    } catch (\Throwable $e) {
        // Limpar buffer em caso de erro
        if (ob_get_level() > 0) ob_end_clean();
        $errMsg = htmlspecialchars($e->getMessage());
        return '<div style="position:fixed;bottom:10px;left:10px;background:#dc2626;color:#fff;padding:10px 16px;border-radius:10px;z-index:99999;font-size:12px;font-family:monospace;max-width:500px;box-shadow:0 4px 20px rgba(0,0,0,.5);">ERRO Modal Temas: ' . $errMsg . '</div>
<script>window.openThemeModal=function(){alert("Erro ao carregar modal de temas: ' . addslashes($errMsg) . '");};window.fecharThemeModal=function(){};</script>';
    }
}

// ──────────────────────────────────────────────────────────────────────────
// PROCESSAMENTO POST — SOMENTE ADMIN PODE TROCAR TEMA
// ──────────────────────────────────────────────────────────────────────────

function processarTemaPOST($conn) {
    // Helper: redirecionar de volta para a mesma página (PRG pattern)
    // Usa header() redirect — funciona mesmo antes de qualquer HTML ser outputado
    $redirect = function($msg = null) {
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        if ($msg) $url .= '?tema_msg=' . urlencode($msg);
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }
        // Fallback se headers já foram enviados (não deveria acontecer aqui)
        echo '<script>window.location=' . json_encode($url) . ';</script>';
        exit;
    };

    // Trocar tema global (somente admin)
    if (isset($_POST['__setMeuTema'])) {
        if (!isAdmin()) {
            $redirect();
            return;
        }
        $tid = intval($_POST['__setMeuTema']);
        adminSetTemaGlobal($conn, $tid);
        $redirect('aplicado');
    }

    // Trocar tema do login (somente admin)
    if (isset($_POST['__setLoginTema'])) {
        if (!isAdmin()) {
            $redirect();
            return;
        }
        setarTemaLogin($conn, intval($_POST['__setLoginTema']));
        $redirect('login_ok');
    }

    // Resetar para modo automático (somente admin)
    if (isset($_POST['__resetTema'])) {
        if (!isAdmin()) {
            $redirect();
            return;
        }
        adminSetModoAuto($conn);
        $redirect('auto');
    }

    // Desativar temas visuais (somente admin)
    if (isset($_POST['__desativarTemas'])) {
        if (!isAdmin()) {
            $redirect();
            return;
        }
        adminDesativarTemas($conn);
        $redirect('desativado');
    }

    // Alternar rotação automática (somente admin)
    if (isset($_POST['__toggleRotacao'])) {
        if (!isAdmin()) { $redirect(); return; }
        $config = getTemaConfig($conn);
        $novo = $config['rotacao_ativa'] ? 0 : 1;
        setTemaConfig($conn, ['rotacao_ativa' => $novo]);
        $redirect();
    }

    // Alternar temas comemorativos (somente admin)
    if (isset($_POST['__toggleComemo'])) {
        if (!isAdmin()) { $redirect(); return; }
        $config = getTemaConfig($conn);
        $novo = $config['comemo_ativa'] ? 0 : 1;
        setTemaConfig($conn, ['comemo_ativa' => $novo]);
        $redirect();
    }
}

// ──────────────────────────────────────────────────────────────────────────
// FUNÇÕES DE BANCO / SESSÃO (mantidas do original)
// ──────────────────────────────────────────────────────────────────────────

function initTemas($conn) {
    try {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS temas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        classe VARCHAR(80) NOT NULL DEFAULT 'theme-dark',
        descricao VARCHAR(255) DEFAULT '',
        categoria VARCHAR(50) DEFAULT 'padrao',
        preview_cor VARCHAR(10) DEFAULT '#6366f1',
        ativo TINYINT(1) DEFAULT 1,
        tipo ENUM('sistema','custom') DEFAULT 'sistema',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    @mysqli_query($conn, "ALTER TABLE accounts ADD COLUMN tema_id INT DEFAULT 1");
    @mysqli_query($conn, "ALTER TABLE configs ADD COLUMN tema_login INT DEFAULT 1");
    @mysqli_query($conn, "ALTER TABLE temas ADD COLUMN preview_cor VARCHAR(10) DEFAULT '#6366f1'");
    @mysqli_query($conn, "ALTER TABLE temas ADD COLUMN categoria VARCHAR(50) DEFAULT 'padrao'");

    // Inicializar tabelas do sistema automático
    initTemasAutomatico($conn);

    // Garantir charset utf8mb4 para suportar emojis nos nomes (se houver)
    @mysqli_set_charset($conn, 'utf8mb4');

    // Sempre sincronizar: INSERT todos os 43 temas com ON DUPLICATE KEY UPDATE
    // Isso garante que temas novos sejam adicionados e dados antigos sejam corrigidos
    $temasBase = getTemasDisponiveis();
    $r_temas = mysqli_query($conn, "SELECT COUNT(*) as t FROM temas");
    $check = $r_temas ? mysqli_fetch_assoc($r_temas) : null;
    $totalAtual = $check ? intval($check['t']) : 0;
    $totalEsperado = count($temasBase);

    // Se faltam temas OU tabela vazia, forçar sincronização
    if ($totalAtual < $totalEsperado || $totalAtual == 0) {
        foreach ($temasBase as $id => $t) {
            $n  = mysqli_real_escape_string($conn, stripEmoji($t['nome']));
            $c  = mysqli_real_escape_string($conn, $t['classe']);
            $d  = mysqli_real_escape_string($conn, stripEmoji($t['desc']));
            $ca = mysqli_real_escape_string($conn, $t['categoria']);
            $p  = mysqli_real_escape_string($conn, $t['preview']);
            mysqli_query($conn, "INSERT INTO temas (id,nome,classe,descricao,categoria,preview_cor,ativo,tipo)
                VALUES ($id,'$n','$c','$d','$ca','$p',1,'sistema')
                ON DUPLICATE KEY UPDATE nome='$n',classe='$c',descricao='$d',categoria='$ca',preview_cor='$p'");
        }
    }
    return getTemaSessao($conn);
    } catch (\Throwable $e) {
        // Se qualquer erro ocorrer, retornar tema padrão para não quebrar a página
        $temas = getTemasDisponiveis();
        $default = $temas[1];
        $default['id'] = 1;
        $default['origem'] = 'fallback';
        $_SESSION['tema_atual'] = $default;
        return $default;
    }
}

function getTemaSessao($conn) {
    // O tema agora é GLOBAL — determinado pelo sistema automático ou pelo admin
    // Revendedores e usuários NÃO escolhem tema individualmente
    try {
        $tema = getTemaGlobalAtivo($conn);
    } catch (\Throwable $e) {
        $temas = getTemasDisponiveis();
        $tema = $temas[1];
        $tema['id'] = 1;
        $tema['origem'] = 'fallback';
    }

    // Verificar fundo personalizado
    $fundo = getFundoPersonalizado($conn, $tema['id']);
    if ($fundo) {
        $tema['fundo_personalizado'] = $fundo['arquivo'];
    }

    $_SESSION['tema_atual'] = $tema;
    return $tema;
}

function getTemaLogin($conn) {
    $temas = getTemasDisponiveis();
    $r_login = mysqli_query($conn, "SELECT tema_login FROM configs LIMIT 1");
    $r     = $r_login ? mysqli_fetch_assoc($r_login) : null;
    $tid   = $r['tema_login'] ?? 1;
    $tema  = $temas[$tid] ?? $temas[1];
    $tema['id'] = $tid;
    return $tema;
}

function setarTemaUsuario($conn, $userId, $temaId) {
    // Agora o tema é global. Esta função redireciona para adminSetTemaGlobal.
    // Mantida para compatibilidade, mas só funciona para admin.
    if (!isAdmin()) return false;
    return adminSetTemaGlobal($conn, $temaId);
}

function setarTemaLogin($conn, $temaId) {
    mysqli_query($conn, "UPDATE configs SET tema_login=" . intval($temaId));
}

function getListaTemas($conn) {
    $result = mysqli_query($conn, "SELECT * FROM temas ORDER BY FIELD(categoria,'padrao','moderno','premium','natureza','anime','games','datas'), id ASC");
    if (!$result) {
        // Fallback: tentar sem ORDER BY complexo
        $result = mysqli_query($conn, "SELECT * FROM temas ORDER BY id ASC");
    }
    $temas  = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $temas[$row['id']] = [
                'nome'      => $row['nome'] ?? '',
                'classe'    => $row['classe'] ?? 'theme-dark',
                'descricao' => $row['descricao'] ?? '',
                'categoria' => $row['categoria'] ?? 'padrao',
                'preview'   => $row['preview_cor'] ?? '#6366f1',
                'preview_cor' => $row['preview_cor'] ?? '#6366f1',
                'tipo'      => $row['tipo'] ?? 'sistema',
            ];
        }
    }
    return $temas;
}

function getTemasPorCategoria($conn) {
    $temas = getListaTemas($conn);

    // Fallback: se o banco retornou vazio, usar getTemasDisponiveis() direto
    if (empty($temas)) {
        $temasBase = getTemasDisponiveis();
        foreach ($temasBase as $id => $t) {
            $temas[$id] = [
                'nome'        => stripEmoji($t['nome']),
                'classe'      => $t['classe'],
                'descricao'   => stripEmoji($t['desc']),
                'categoria'   => $t['categoria'],
                'preview'     => $t['preview'],
                'preview_cor' => $t['preview'],
                'tipo'        => 'sistema',
            ];
        }
    }

    $categorias = [];
    foreach ($temas as $id => $tema) {
        $cat = $tema['categoria'];
        if (!isset($categorias[$cat])) $categorias[$cat] = [];
        $categorias[$cat][$id] = $tema;
    }
    return $categorias;
}

function getBodyClass($tema = null) {
    if ($tema === null) $tema = $_SESSION['tema_atual'] ?? ['classe' => 'theme-dark'];
    return $tema['classe'] ?? 'theme-dark';
}

function getTemasCSS() {
    return '<link rel="stylesheet" href="../AegisCore/temas_visual.css">' . "\n";
}

function gettemasCSSSelf() {
    return '<link rel="stylesheet" href="AegisCore/temas_visual.css">' . "\n";
}

function adjustBrightness($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) < 6) return '#' . $hex;
    $r = max(0, min(255, hexdec(substr($hex,0,2)) + $percent * 2.55));
    $g = max(0, min(255, hexdec(substr($hex,2,2)) + $percent * 2.55));
    $b = max(0, min(255, hexdec(substr($hex,4,2)) + $percent * 2.55));
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

/**
 * Retorna CSS inline para fundo personalizado (se existir).
 * Usar no <body> style ou em <style> tag.
 */
function getFundoPersonalizadoCSS($conn, $temaId = null) {
    try {
    if ($temaId === null) {
        $tema = $_SESSION['tema_atual'] ?? null;
        $temaId = $tema ? ($tema['id'] ?? 1) : 1;
    }
    // Se recebeu array (tema completo), extrair o id e a classe
    $classeBody = 'theme-dark';
    if (is_array($temaId)) {
        $classeBody = $temaId['classe'] ?? 'theme-dark';
        $temaId = $temaId['id'] ?? 1;
    } else {
        // Tentar obter a classe do tema a partir da sessão ou do banco
        $temaSessao = $_SESSION['tema_atual'] ?? null;
        if ($temaSessao && isset($temaSessao['classe'])) {
            $classeBody = $temaSessao['classe'];
        } else {
            $temas = getTemasDisponiveis();
            $classeBody = $temas[$temaId]['classe'] ?? 'theme-dark';
        }
    }

    $fundo = getFundoPersonalizado($conn, $temaId);
    if (!$fundo || empty($fundo['arquivo'])) return '';

    // Calcular caminho relativo correto: o arquivo é salvo como "uploads/fundos/xxx"
    // Precisamos prefixar com ../ se estamos em subdiretório (admin/, usuario/, etc.)
    $arquivo = $fundo['arquivo'];
    $scriptDir = basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if (in_array($scriptDir, ['admin', 'usuario', 'revenda', 'AegisCore', 'public'])) {
        $url = '../' . $arquivo;
    } else {
        $url = $arquivo;
    }
    $url = htmlspecialchars($url);
    $classe = htmlspecialchars($classeBody);

    // Usar seletor html body.theme-* para ter especificidade MAIOR que body.theme-*
    // usado em temas_visual.css, garantindo que a imagem de fundo sempre sobreponha
    // o gradiente padrão do tema, independente da ordem de carregamento do CSS.
    return "<style>
html body.{$classe} {
  background: url('{$url}') center center / cover fixed no-repeat !important;
}
html body.{$classe}::before {
  display: none !important;
}
</style>\n";
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * Retorna informações completas do estado do sistema de temas.
 * Útil para debug e para o painel admin.
 */
function getTemasStatus($conn) {
    $config = getTemaConfig($conn);
    $temaAtual = getTemaGlobalAtivo($conn);
    $proximoComemo = getProximoTemaComemorativo();
    $historico = getHistoricoRotacao($conn, 10);
    $fundos = listarFundosPersonalizados($conn);
    $comemorativoAtivo = getTemaComemorativoAtivo();

    return [
        'config' => $config,
        'tema_atual' => $temaAtual,
        'proximo_comemorativo' => $proximoComemo,
        'comemorativo_ativo_id' => $comemorativoAtivo,
        'historico_recente' => $historico,
        'fundos_personalizados' => $fundos,
        'total_temas' => count(getTemasDisponiveis()),
        'total_comemorativos' => count(getTemasComemoIDs()),
        'total_rotacao' => count(getTemasParaRotacao()),
    ];
}

// ──────────────────────────────────────────────────────────────────────────
// COMPATIBILIDADE — Funções legadas (sistema antigo)
// Estas funções existem apenas para evitar fatal error em páginas que ainda
// chamam o sistema antigo. O CSS agora vem de temas_visual.css.
// ──────────────────────────────────────────────────────────────────────────

/**
 * STUB: getCSSVariables era do sistema antigo.
 * Agora retorna string vazia — todo o CSS vem de temas_visual.css via classes no body.
 */
function getCSSVariables($tema = null) {
    return '/* CSS gerenciado por temas_visual.css */';
}
?>
