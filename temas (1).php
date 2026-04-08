<?php
if (session_status() == PHP_SESSION_NONE) session_start();

// ──────────────────────────────────────────────────────────────────────────
// SISTEMA DE TEMAS COMPLETO — AegisCore v8.0 ULTIMATE (SHAPE EDITION)
// ──────────────────────────────────────────────────────────────────────────

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
        22 => ['nome'=>'🌸 Primavera',       'classe'=>'theme-spring',     'desc'=>'Flores de cerejeira', 'categoria'=>'natureza', 'preview'=>'#f472b6'],
        24 => ['nome'=>'🦇 Vampire Gothic',  'classe'=>'theme-vampire',    'desc'=>'Arcos e formato de caixão', 'categoria'=>'premium', 'preview'=>'#991b1b'],
        25 => ['nome'=>'🍥 Naruto',          'classe'=>'theme-naruto',     'desc'=>'Pergaminhos ninja laranja', 'categoria'=>'anime', 'preview'=>'#f97316'],
        26 => ['nome'=>'🐉 Dragon Ball Z',   'classe'=>'theme-dbz',        'desc'=>'Esfera do dragão dourada', 'categoria'=>'anime', 'preview'=>'#f97316'],
        27 => ['nome'=>'⚔️ Demon Slayer',    'classe'=>'theme-demonslayer','desc'=>'Katana e ondas d\'água', 'categoria'=>'anime', 'preview'=>'#0ea5e9'],
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
        29 => ['nome'=>'🎄 Natal Neve',      'classe'=>'theme-xmas',       'desc'=>'Árvore nevada festiva', 'categoria'=>'datas', 'preview'=>'#dc2626'],
        30 => ['nome'=>'🎭 Carnaval',        'classe'=>'theme-carnaval',   'desc'=>'Festa e alegria colorida', 'categoria'=>'datas', 'preview'=>'#fbbf24'],
        31 => ['nome'=>'🐣 Páscoa',          'classe'=>'theme-pascoa',     'desc'=>'Coelhinhos ovais coloridos', 'categoria'=>'datas', 'preview'=>'#f472b6'],
        32 => ['nome'=>'🌽 Festa Junina',    'classe'=>'theme-festajunina','desc'=>'Bandeirinhas e fogueira', 'categoria'=>'datas', 'preview'=>'#dc2626'],
        33 => ['nome'=>'🧸 Dia Crianças',    'classe'=>'theme-dcriancas',  'desc'=>'Balões e brinquedos', 'categoria'=>'datas', 'preview'=>'#fbbf24'],
        34 => ['nome'=>'🦷 Dia Dentista',    'classe'=>'theme-dentista',   'desc'=>'Saúde bucal e sorrisos', 'categoria'=>'datas', 'preview'=>'#3b82f6'],
        35 => ['nome'=>'👔 Trabalhador',     'classe'=>'theme-trabalhador','desc'=>'Engrenagens e construção', 'categoria'=>'datas', 'preview'=>'#dc2626'],
        36 => ['nome'=>'👩 Mulheres',        'classe'=>'theme-mulheres',   'desc'=>'Flores e empoderamento', 'categoria'=>'datas', 'preview'=>'#ec4899'],
        37 => ['nome'=>'💐 Dia das Mães',    'classe'=>'theme-maes',       'desc'=>'Coração com flores', 'categoria'=>'datas', 'preview'=>'#f472b6'],
        38 => ['nome'=>'👨 Dia dos Pais',    'classe'=>'theme-pais',       'desc'=>'Estrela paternidade robusta', 'categoria'=>'datas', 'preview'=>'#3b82f6'],
        39 => ['nome'=>'🎆 Ano Novo Fogo',   'classe'=>'theme-ny',         'desc'=>'Celebração e fogos dourados', 'categoria'=>'datas', 'preview'=>'#fbbf24'],
    ];
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
        'theme-demonslayer' => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M8 28 Q24 16 40 22 Q56 16 72 24" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-opacity="0.3"/><path d="M8 36 Q24 24 40 30 Q56 24 72 32" fill="none" stroke="#22d3ee" stroke-width="1.5" stroke-opacity="0.22"/><line x1="40" y1="5" x2="40" y2="45" stroke="#0ea5e9" stroke-width="1.5" stroke-opacity="0.15"/><polygon points="40,6 44,16 40,14 36,16" fill="#0ea5e9" fill-opacity="0.3"/></svg>',
        'theme-onepiece'    => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><ellipse cx="40" cy="28" rx="18" ry="7" fill="#fbbf24" fill-opacity="0.18"/><ellipse cx="40" cy="22" rx="12" ry="5" fill="#dc2626" fill-opacity="0.15"/><path d="M28 28 L52 28" stroke="#dc2626" stroke-width="1.5" stroke-opacity="0.2"/><circle cx="40" cy="32" r="3" fill="#fbbf24" fill-opacity="0.25"/></svg>',
        'theme-cyberpunk'   => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><polygon points="40,5 75,22 75,42 40,50 5,42 5,22" fill="none" stroke="#ff00ff" stroke-width="1.5" stroke-opacity="0.3"/><circle cx="40" cy="25" r="8" fill="none" stroke="#00ffff" stroke-width="1" stroke-opacity="0.25"/><circle cx="40" cy="25" r="4" fill="#ff00ff" fill-opacity="0.15"/><line x1="5" y1="22" x2="75" y2="22" stroke="#00ffff" stroke-width="0.5" stroke-opacity="0.2"/></svg>',
        'theme-retrogames'  => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><rect x="22" y="18" width="36" height="20" fill="#e94560" fill-opacity="0.2"/><rect x="26" y="22" width="8" height="8" fill="#e94560" fill-opacity="0.25"/><rect x="46" y="22" width="8" height="8" fill="#e94560" fill-opacity="0.25"/><rect x="36" y="28" width="8" height="4" fill="#fff" fill-opacity="0.1"/><rect x="8" y="8" width="4" height="4" fill="#e94560" fill-opacity="0.4"/><rect x="18" y="4" width="4" height="4" fill="#fbbf24" fill-opacity="0.4"/><rect x="58" y="8" width="4" height="4" fill="#e94560" fill-opacity="0.35"/></svg>',
        'theme-pokemon'     => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="25" r="16" fill="#dc2626" fill-opacity="0.18"/><circle cx="40" cy="25" r="10" fill="#f8fafc" fill-opacity="0.12"/><circle cx="40" cy="25" r="4" fill="#333" fill-opacity="0.18"/><line x1="24" y1="25" x2="56" y2="25" stroke="#333" stroke-width="1.5" stroke-opacity="0.2"/></svg>',
        'theme-halloween'   => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path fill="#f97316" fill-opacity="0.18" d="M40 25 L72 8 L76 14 L62 24 L76 32 L68 38 L52 30 L44 44 L52 50 L40 46 L28 50 L36 44 L28 30 L12 38 L4 32 L18 24 L4 14 L8 8 Z"/><circle cx="40" cy="25" r="4" fill="#f97316" fill-opacity="0.3"/></svg>',
        'theme-xmas'        => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><polygon points="40,5 58,28 50,28 60,45 20,45 30,28 22,28" fill="#22c55e" fill-opacity="0.2"/><rect x="35" y="45" width="10" height="8" fill="#b45309" fill-opacity="0.2"/><circle cx="40" cy="7" r="3" fill="#fbbf24" fill-opacity="0.5"/><circle cx="32" cy="30" r="2" fill="#ef4444" fill-opacity="0.4"/><circle cx="50" cy="32" r="2" fill="#fbbf24" fill-opacity="0.4"/><circle cx="40" cy="38" r="2" fill="#ef4444" fill-opacity="0.35"/></svg>',
        'theme-valentine'   => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M40 46 L14 28 C4 18 10 4 22 4 C30 4 35 14 40 22 C45 14 50 4 58 4 C70 4 76 18 66 28 L40 46Z" fill="#ef4444" fill-opacity="0.18"/><path d="M40 38 L20 24 C13 16 18 6 27 6 C33 6 38 14 40 20 C42 14 47 6 53 6 C62 6 67 16 60 24 L40 38Z" fill="#fb7185" fill-opacity="0.1"/></svg>',
        'theme-spring'      => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M40 8 L44 18 L56 18 L47 25 L50 36 L40 30 L30 36 L33 25 L24 18 L36 18 Z" fill="#f472b6" fill-opacity="0.2"/><circle cx="40" cy="24" r="5" fill="#fbbf24" fill-opacity="0.2"/><circle cx="18" cy="35" r="4" fill="#34d399" fill-opacity="0.2"/><circle cx="62" cy="32" r="3" fill="#f472b6" fill-opacity="0.2"/></svg>',
        'theme-ny'          => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="28" r="4" fill="#fbbf24" fill-opacity="0.4"/><line x1="40" y1="28" x2="18" y2="8" stroke="#ef4444" stroke-width="1.5" stroke-opacity="0.3"/><line x1="40" y1="28" x2="62" y2="6" stroke="#fbbf24" stroke-width="1.5" stroke-opacity="0.3"/><line x1="40" y1="28" x2="72" y2="22" stroke="#3b82f6" stroke-width="1.5" stroke-opacity="0.25"/><line x1="40" y1="28" x2="8" y2="20" stroke="#fbbf24" stroke-width="1" stroke-opacity="0.25"/><circle cx="18" cy="8" r="2.5" fill="#ef4444" fill-opacity="0.4"/><circle cx="62" cy="6" r="2.5" fill="#fbbf24" fill-opacity="0.4"/><circle cx="72" cy="22" r="2" fill="#3b82f6" fill-opacity="0.35"/></svg>',
        'theme-pascoa'      => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><ellipse cx="40" cy="32" rx="14" ry="16" fill="#f472b6" fill-opacity="0.18"/><circle cx="27" cy="18" rx="8" ry="10" r="8" fill="#f472b6" fill-opacity="0.12"/><circle cx="53" cy="18" r="7" fill="#60a5fa" fill-opacity="0.12"/><ellipse cx="40" cy="12" rx="5" ry="7" fill="#f472b6" fill-opacity="0.15"/></svg>',
        'theme-festajunina' => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><line x1="5" y1="12" x2="75" y2="8" stroke="#b45309" stroke-width="0.8" stroke-opacity="0.3"/><polygon points="14,12 22,22 6,22" fill="#dc2626" fill-opacity="0.3"/><polygon points="30,11 38,21 22,21" fill="#fbbf24" fill-opacity="0.3"/><polygon points="46,10 54,20 38,20" fill="#3b82f6" fill-opacity="0.3"/><polygon points="62,9 70,19 54,19" fill="#dc2626" fill-opacity="0.25"/><rect x="32" y="28" width="16" height="14" rx="2" fill="#b45309" fill-opacity="0.12"/><path d="M32 28 L26 18 L40 22 L54 18 L48 28" fill="#dc2626" fill-opacity="0.1"/></svg>',
        'theme-dcriancas'   => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><ellipse cx="28" cy="24" rx="7" ry="10" fill="#fbbf24" fill-opacity="0.25"/><ellipse cx="42" cy="20" rx="6" ry="9" fill="#60a5fa" fill-opacity="0.25"/><ellipse cx="56" cy="26" rx="7" ry="10" fill="#f472b6" fill-opacity="0.2"/><line x1="28" y1="34" x2="28" y2="44" stroke="#b45309" stroke-width="1" stroke-opacity="0.2"/><line x1="42" y1="29" x2="42" y2="44" stroke="#b45309" stroke-width="1" stroke-opacity="0.2"/><line x1="56" y1="36" x2="56" y2="44" stroke="#b45309" stroke-width="1" stroke-opacity="0.2"/></svg>',
        'theme-mulheres'    => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="22" r="10" fill="#ec4899" fill-opacity="0.18"/><circle cx="22" cy="18" r="7" fill="#fbbf24" fill-opacity="0.15"/><circle cx="58" cy="18" r="7" fill="#fbbf24" fill-opacity="0.15"/><circle cx="40" cy="38" r="6" fill="#a855f7" fill-opacity="0.15"/><circle cx="40" cy="22" r="3" fill="#ec4899" fill-opacity="0.35"/></svg>',
        'theme-maes'        => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><path d="M40 44 L16 26 C7 17 12 4 24 4 C31 4 36 12 40 20 C44 12 49 4 56 4 C68 4 73 17 64 26 L40 44Z" fill="#f472b6" fill-opacity="0.2"/><circle cx="40" cy="22" r="7" fill="#fbbf24" fill-opacity="0.14"/><circle cx="26" cy="18" r="4" fill="#f472b6" fill-opacity="0.2"/><circle cx="54" cy="18" r="4" fill="#ef4444" fill-opacity="0.2"/></svg>',
        'theme-pais'        => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="15" r="6" fill="#fbbf24" fill-opacity="0.2"/><polygon points="40,10 42,14 47,14 43,17 44,22 40,19 36,22 37,17 33,14 38,14" fill="#fbbf24" fill-opacity="0.25"/><rect x="25" y="28" width="30" height="16" rx="2" fill="#3b82f6" fill-opacity="0.15"/><rect x="29" y="32" width="6" height="6" rx="1" fill="#3b82f6" fill-opacity="0.25"/></svg>',
        'theme-trabalhador' => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="25" r="12" fill="none" stroke="#dc2626" stroke-width="2" stroke-opacity="0.25"/><circle cx="40" cy="25" r="6" fill="#dc2626" fill-opacity="0.12"/><circle cx="52" cy="25" r="3" fill="none" stroke="#dc2626" stroke-width="1" stroke-opacity="0.2"/><circle cx="28" cy="25" r="3" fill="none" stroke="#dc2626" stroke-width="1" stroke-opacity="0.2"/><circle cx="40" cy="37" r="3" fill="none" stroke="#dc2626" stroke-width="1" stroke-opacity="0.2"/><circle cx="40" cy="13" r="3" fill="none" stroke="#dc2626" stroke-width="1" stroke-opacity="0.2"/></svg>',
        'theme-dentista'    => '<svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg"><rect x="32" y="12" width="16" height="28" rx="8" fill="#f8fafc" fill-opacity="0.12"/><circle cx="40" cy="26" r="3" fill="#3b82f6" fill-opacity="0.2"/><path d="M36 34 Q40 38 44 34" fill="none" stroke="#3b82f6" stroke-width="1.5" stroke-opacity="0.3"/><circle cx="24" cy="22" r="5" fill="#22d3ee" fill-opacity="0.15"/><circle cx="56" cy="22" r="5" fill="#22d3ee" fill-opacity="0.15"/></svg>',
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
        'theme-demonslayer' => ['card'=>'border-radius:0 16px 0 16px;', 'avatar'=>'clip-path:polygon(50% 0%,100% 50%,50% 100%,0% 50%);border-radius:0;', 'badge_r'=>'0 16px'],
        'theme-onepiece'    => ['card'=>'border-radius:10px 10px 16px 16px;', 'avatar'=>'border-radius:40% 60% 70% 30%/40% 50% 60% 50%;', 'badge_r'=>'10px 16px'],
        'theme-cyberpunk'   => ['card'=>'clip-path:polygon(8px 0,100% 0,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px);border-radius:0;', 'avatar'=>'clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);border-radius:0;', 'badge_r'=>'3px'],
        'theme-retrogames'  => ['card'=>'border-radius:0;', 'avatar'=>'border-radius:0;', 'badge_r'=>'0'],
        'theme-pokemon'     => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-halloween'   => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-xmas'        => ['card'=>'border-radius:20px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'20px'],
        'theme-natal'       => ['card'=>'border-radius:20px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'20px'],
        'theme-anonovo'     => ['card'=>'border-radius:4px 18px 4px 18px;', 'avatar'=>'border-radius:4px 18px 4px 18px;', 'badge_r'=>'4px 18px'],
        'theme-valentine'   => ['card'=>'border-radius:22px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'22px'],
        'theme-spring'      => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-ny'          => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-pascoa'      => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-festajunina' => ['card'=>'border-radius:14px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'14px'],
        'theme-dcriancas'   => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-mulheres'    => ['card'=>'border-radius:18px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'18px'],
        'theme-maes'        => ['card'=>'border-radius:22px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'22px'],
        'theme-pais'        => ['card'=>'border-radius:14px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'14px'],
        'theme-trabalhador' => ['card'=>'border-radius:10px;', 'avatar'=>'border-radius:0;', 'badge_r'=>'10px'],
        'theme-dentista'    => ['card'=>'border-radius:14px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'14px'],
    ];
    return $shapes[$classe] ?? ['card'=>'border-radius:16px;', 'avatar'=>'border-radius:50%;', 'badge_r'=>'16px'];
}

function getFundoTema($classe, $cor) {
    $fundos = [
        'theme-dark'        => 'linear-gradient(145deg,#1e293b,#0f172a)',
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
        'theme-demonslayer' => 'linear-gradient(145deg,#111827,#0f172a)',
        'theme-onepiece'    => 'linear-gradient(145deg,#1f1515,#161010)',
        'theme-cyberpunk'   => 'linear-gradient(145deg,#11111a,#0d0d15)',
        'theme-retrogames'  => '#16213e',
        'theme-pokemon'     => 'linear-gradient(145deg,#1a1515,#0f0a0a)',
        'theme-halloween'   => 'linear-gradient(145deg,#1a1010,#0f0a0a)',
        'theme-xmas'        => 'linear-gradient(145deg,#1a2530,#0f1a25)',
        'theme-natal'       => 'linear-gradient(145deg,#04150e,#020f08)',
        'theme-anonovo'     => 'linear-gradient(145deg,#18150a,#0a0800)',
        'theme-valentine'   => 'linear-gradient(145deg,#1a0f1a,#100a10)',
        'theme-spring'      => 'linear-gradient(145deg,#1a1520,#0f0a15)',
        'theme-ny'          => 'linear-gradient(145deg,#1a1a2e,#0f0f1a)',
        'theme-pascoa'      => 'linear-gradient(145deg,#1a1520,#0f0a15)',
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
        'theme-demonslayer' => 'linear-gradient(90deg,#0ea5e9,#22d3ee,#0ea5e9)',
        'theme-onepiece'    => 'linear-gradient(90deg,#dc2626,#fbbf24,#dc2626)',
        'theme-cyberpunk'   => 'linear-gradient(90deg,#ff00ff,#00ffff,#ff00ff)',
        'theme-retrogames'  => '#e94560',
        'theme-pokemon'     => 'linear-gradient(90deg,#dc2626,#fbbf24,#3b82f6)',
        'theme-halloween'   => 'linear-gradient(90deg,#f97316,#8b5cf6,#dc2626)',
        'theme-xmas'        => 'linear-gradient(90deg,#dc2626,#22c55e,#fbbf24)',
        'theme-natal'       => 'linear-gradient(90deg,#22c55e,#dc2626,#22c55e)',
        'theme-anonovo'     => 'linear-gradient(90deg,#f59e0b,#ef4444,#3b82f6,#f59e0b)',
        'theme-valentine'   => 'linear-gradient(90deg,#ef4444,#ec4899,#fbbf24)',
        'theme-spring'      => 'linear-gradient(90deg,#f472b6,#34d399,#f472b6)',
        'theme-ny'          => 'linear-gradient(90deg,#fbbf24,#3b82f6,#ef4444)',
        'theme-pascoa'      => 'linear-gradient(90deg,#f472b6,#60a5fa,#fbbf24)',
        'theme-festajunina' => 'linear-gradient(90deg,#dc2626,#fbbf24,#3b82f6)',
        'theme-dcriancas'   => 'linear-gradient(90deg,#fbbf24,#60a5fa,#f472b6)',
        'theme-mulheres'    => 'linear-gradient(90deg,#ec4899,#a855f7,#fbbf24)',
        'theme-maes'        => 'linear-gradient(90deg,#f472b6,#ef4444,#fbbf24)',
        'theme-pais'        => 'linear-gradient(90deg,#3b82f6,#1e293b,#3b82f6)',
        'theme-trabalhador' => '#dc2626',
        'theme-dentista'    => 'linear-gradient(90deg,#3b82f6,#22d3ee,#3b82f6)',
        'theme-inferno'     => 'linear-gradient(90deg,#dc2626,#f97316)',
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
        'theme-demonslayer' => 'rgba(14,165,233,0.4)',
        'theme-onepiece'    => 'rgba(220,38,38,0.4)',
        'theme-cyberpunk'   => 'rgba(255,0,255,0.5)',
        'theme-retrogames'  => 'rgba(233,69,96,0.4)',
        'theme-pokemon'     => 'rgba(220,38,38,0.35)',
        'theme-halloween'   => 'rgba(249,115,22,0.4)',
        'theme-xmas'        => 'rgba(220,38,38,0.35)',
        'theme-valentine'   => 'rgba(239,68,68,0.4)',
        'theme-spring'      => 'rgba(244,114,182,0.4)',
        'theme-ny'          => 'rgba(251,191,36,0.4)',
        'theme-inferno'     => 'rgba(220,38,38,0.4)',
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
        'theme-demonslayer' => 'rgba(14,165,233,0.5)',
        'theme-onepiece'    => 'rgba(220,38,38,0.5)',
        'theme-cyberpunk'   => '#ff00ff',
        'theme-retrogames'  => '#e94560',
        'theme-halloween'   => 'rgba(249,115,22,0.5)',
        'theme-valentine'   => 'rgba(239,68,68,0.5)',
        'theme-spring'      => 'rgba(244,114,182,0.5)',
        'theme-ny'          => 'rgba(251,191,36,0.5)',
        'theme-inferno'     => 'rgba(220,38,38,0.5)',
    ];
    return $borders[$classe] ?? "rgba(255,255,255,0.3)";
}

// ────────────��─────────────────────────────────────────────────────────────
// MODAL DE TEMAS ATUALIZADO — SHAPE EDITION
// ──────────────────────────────────────────────────────────────────────────

function getModalTemasHTML($conn) {
    $categorias   = getTemasPorCategoria($conn);
    $temaAtual    = getTemaSessao($conn);
    $temaLogin    = getTemaLogin($conn);

    $labels = [
        'padrao'   => '🌟 Padrão',
        'moderno'  => '✨ Moderno',
        'premium'  => '💎 Premium',
        'natureza' => '🌿 Natureza',
        'anime'    => '🎌 Animes',
        'games'    => '🎮 Games',
        'datas'    => '🎊 Comemorativos',
    ];

    ob_start(); ?>
<div id="themeModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:9999;backdrop-filter:blur(6px);align-items:center;justify-content:center;">
  <div style="max-width:960px;width:96%;max-height:92vh;display:flex;flex-direction:column;background:linear-gradient(145deg,#0d1117,#020617);border-radius:24px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);box-shadow:0 30px 80px rgba(0,0,0,0.7);">

    <!-- HEADER -->
    <div style="background:linear-gradient(135deg,#4158D0,#C850C0,#FFCC70);padding:18px 24px;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
      <h5 style="margin:0;color:#fff;font-size:16px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;display:flex;align-items:center;gap:10px;">
        <span style="font-size:22px;">🎨</span> Galeria de Temas — AegisCore
      </h5>
      <button onclick="fecharThemeModal()" style="background:rgba(0,0,0,0.25);border:none;color:#fff;width:34px;height:34px;border-radius:50%;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.5)'" onmouseout="this.style.background='rgba(0,0,0,0.25)'">&times;</button>
    </div>

    <!-- FILTROS -->
    <div style="padding:14px 24px;background:rgba(255,255,255,0.02);border-bottom:1px solid rgba(255,255,255,0.06);flex-shrink:0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <span class="tf-btn tf-active" data-filter="all" onclick="filtrarTemas(this,'all')" style="padding:5px 14px;border-radius:20px;cursor:pointer;font-size:11px;font-weight:600;background:linear-gradient(135deg,#4158D0,#C850C0);color:#fff;border:none;transition:0.2s;">🎨 Todos</span>
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
              "><?= htmlspecialchars($tema['nome']) ?></div>
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
    <div style="padding:14px 24px;background:#020617;border-top:1px solid rgba(255,255,255,0.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;flex-shrink:0;">
      <div style="display:flex;gap:8px;align-items:center;">
        <form method="POST" style="margin:0;">
          <button type="submit" name="__resetTema" value="1"
            style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#64748b;padding:8px 16px;border-radius:10px;font-size:12px;cursor:pointer;transition:0.2s;"
            onmouseover="this.style.background='rgba(255,255,255,0.08)'"
            onmouseout="this.style.background='rgba(255,255,255,0.04)'">
            ↺ Resetar
          </button>
        </form>
        <span style="font-size:10px;color:#334155;">Clique para aplicar | Clique direito para login</span>
      </div>
      <div style="display:flex;gap:10px;">
        <form method="POST" style="margin:0;" id="formLoginTema">
          <input type="hidden" name="__setLoginTema" id="loginTemaId" value="">
          <button type="button" id="btnLoginTema"
            style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);color:#fcd34d;padding:8px 16px;border-radius:10px;font-size:12px;cursor:pointer;transition:0.2s;"
            onmouseover="this.style.background='rgba(245,158,11,0.18)'"
            onmouseout="this.style.background='rgba(245,158,11,0.1)'">
            ⇒ Aplicar ao Login
          </button>
        </form>
        <button onclick="fecharThemeModal()"
          style="background:linear-gradient(135deg,#4158D0,#C850C0);border:none;color:#fff;padding:8px 22px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 15px rgba(199,80,192,0.3);transition:0.2s;"
          onmouseover="this.style.opacity='0.85'"
          onmouseout="this.style.opacity='1'">
          ✓ Fechar
        </button>
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
}

// ──────────────────────────────────────────────────────────────────────────
// PROCESSAMENTO POST
// ──────────────────────────────────────────────────────────────────────────

function processarTemaPOST($conn) {
    if (isset($_POST['__setMeuTema'])) {
        $tid = intval($_POST['__setMeuTema']);
        if (!empty($_SESSION['iduser'])) {
            setarTemaUsuario($conn, $_SESSION['iduser'], $tid);
        }
        echo '<script>window.location.reload();</script>';
        exit;
    }
    if (isset($_POST['__setLoginTema'])) {
        setarTemaLogin($conn, intval($_POST['__setLoginTema']));
        echo '<script>alert("Tema do login atualizado!");window.location.reload();</script>';
        exit;
    }
    if (isset($_POST['__resetTema'])) {
        if (!empty($_SESSION['iduser'])) {
            setarTemaUsuario($conn, $_SESSION['iduser'], 1);
        }
        echo '<script>window.location.reload();</script>';
        exit;
    }
}

// ──────────────────────────────────────────────────────────────────────────
// FUNÇÕES DE BANCO / SESSÃO (mantidas do original)
// ──────────────────────────────────────────────────────────────────────────

function initTemas($conn) {
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

    $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM temas"));
    if ($check['t'] == 0) {
        foreach (getTemasDisponiveis() as $id => $t) {
            $n  = mysqli_real_escape_string($conn, $t['nome']);
            $c  = mysqli_real_escape_string($conn, $t['classe']);
            $d  = mysqli_real_escape_string($conn, $t['desc']);
            $ca = mysqli_real_escape_string($conn, $t['categoria']);
            $p  = mysqli_real_escape_string($conn, $t['preview']);
            mysqli_query($conn, "INSERT INTO temas (id,nome,classe,descricao,categoria,preview_cor,ativo,tipo)
                VALUES ($id,'$n','$c','$d','$ca','$p',1,'sistema')
                ON DUPLICATE KEY UPDATE nome='$n'");
        }
    }
    return getTemaSessao($conn);
}

function getTemaSessao($conn) {
    $temas = getTemasDisponiveis();
    $tid   = 1;
    if (!empty($_SESSION['iduser']) && $_SESSION['iduser'] > 0) {
        $uid = intval($_SESSION['iduser']);
        $r   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT tema_id FROM accounts WHERE id=$uid LIMIT 1"));
        $tid = $r['tema_id'] ?? 1;
    } elseif (!empty($_SESSION['login']) && $_SESSION['login'] === 'admin') {
        $r   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT tema_id FROM accounts WHERE id=1 LIMIT 1"));
        $tid = $r['tema_id'] ?? 1;
    }
    $tema       = $temas[$tid] ?? $temas[1];
    $tema['id'] = $tid;
    $_SESSION['tema_atual'] = $tema;
    return $tema;
}

function getTemaLogin($conn) {
    $temas = getTemasDisponiveis();
    $r     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT tema_login FROM configs LIMIT 1"));
    $tid   = $r['tema_login'] ?? 1;
    $tema  = $temas[$tid] ?? $temas[1];
    $tema['id'] = $tid;
    return $tema;
}

function setarTemaUsuario($conn, $userId, $temaId) {
    $uid = intval($userId);
    $tid = intval($temaId);
    mysqli_query($conn, "UPDATE accounts SET tema_id=$tid WHERE id=$uid");
    if (!empty($_SESSION['iduser']) && $_SESSION['iduser'] == $uid) {
        $temas = getTemasDisponiveis();
        $_SESSION['tema_atual']       = $temas[$tid] ?? $temas[1];
        $_SESSION['tema_atual']['id'] = $tid;
    }
}

function setarTemaLogin($conn, $temaId) {
    mysqli_query($conn, "UPDATE configs SET tema_login=" . intval($temaId));
}

function getListaTemas($conn) {
    $result = mysqli_query($conn, "SELECT * FROM temas ORDER BY FIELD(categoria,'padrao','moderno','premium','natureza','anime','games','datas'), id ASC");
    $temas  = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $temas[$row['id']] = [
                'nome'      => $row['nome'],
                'classe'    => $row['classe'],
                'descricao' => $row['descricao'],
                'categoria' => $row['categoria'],
                'preview'   => $row['preview_cor'],
                'preview_cor' => $row['preview_cor'],
                'tipo'      => $row['tipo'],
            ];
        }
    }
    return $temas;
}

function getTemasPorCategoria($conn) {
    $temas      = getListaTemas($conn);
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
?>
