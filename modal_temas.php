<?php
if (session_status() == PHP_SESSION_NONE)
  session_start();
if (!isset($conn)) {
  include_once("conexao.php");
  $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
}
if (!function_exists('getTemasDisponiveis'))
  include_once("temas.php");
$temaAtual = $_SESSION['tema_atual'] ?? ['id' => 1, 'classe' => 'theme-dark'];
$temaAtualId = $temaAtual['id'] ?? 1;
$isAdmin = ($_SESSION['login'] ?? '') === 'admin';
$temas = getTemasDisponiveis();

// Pré-visualização de cores por tema (fundo, card, acento)
  $temasPrev = [
  1  => ['#0f172a', '#1e293b', '#4158D0'],
  2  => ['#070f09', '#0f2213', '#dc2626'],
  3  => ['#0a0800', '#161200', '#eab308'],
  4  => ['#06001a', '#0f0028', '#a855f7'],
  5  => ['#020d1f', '#051e3e', '#0096c7'],
  6  => ['#130400', '#220900', '#f97316'],
  7  => ['#011208', '#042012', '#10b981'],
  8  => ['#12000c', '#220018', '#ec4899'],
  9  => ['#060600', '#0e0e00', '#eab308'],
  10 => ['#120a00', '#221400', '#b45309'],
  11 => ['#020c16', '#061826', '#38bdf8'],
  12 => ['#04000c', '#0c0020', '#8b5cf6'],
  13 => ['#1a060a', '#2d0a10', '#e11d48'],
  14 => ['#0e0415', '#1e0a2e', '#a855f7'],
  15 => ['#0a1204', '#0f1f04', '#84cc16'],
  16 => ['#170404', '#2d0a0a', '#dc2626'],
  17 => ['#100800', '#1e1000', '#b45309'],
  18 => ['#001210', '#001e1a', '#0d9488'],
  19 => ['#0f0000', '#200000', '#b91c1c'],
  20 => ['#000d14', '#001828', '#0e7490'],
  21 => ['#0a0014', '#160028', '#9333ea'],
  22 => ['#120200', '#220500', '#ea580c'],
  23 => ['#001416', '#002028', '#06b6d4'],
  24 => ['#0f0a00', '#1e1400', '#ca8a04'],
];
?>
<div id="themeModalOverlay"
  style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.88);z-index:999999;align-items:center;justify-content:center;backdrop-filter:blur(10px);animation:fadeIn .2s ease;">
  <div
    style="background:linear-gradient(135deg,#1a2235,#0f172a);border-radius:28px;width:95%;max-width:820px;max-height:88vh;overflow-y:auto;border:1px solid rgba(255,255,255,0.12);box-shadow:0 40px 80px rgba(0,0,0,0.7);animation:slideUp .28s ease;font-family:'Inter',sans-serif;position:relative;">

    <!-- Cabeçalho -->
    <div
      style="background:linear-gradient(135deg,rgba(65,88,208,0.4),rgba(200,80,192,0.3));border-bottom:1px solid rgba(255,255,255,0.1);padding:20px 26px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:2;backdrop-filter:blur(20px);border-radius:28px 28px 0 0;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div
          style="width:42px;height:42px;border-radius:14px;background:linear-gradient(135deg,#4158D0,#C850C0);display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;">
          <i class='bx bx-palette'></i></div>
        <div>
          <div style="font-size:17px;font-weight:800;color:#fff;">Meu Tema</div>
          <div style="font-size:11px;color:rgba(255,255,255,0.4);">
            <?php echo $isAdmin ? 'Admin: você também controla o tema da tela de login' : 'Escolha o visual do seu painel (só afeta você)'; ?>
          </div>
        </div>
      </div>
      <button
        onclick="document.getElementById('themeModalOverlay').style.display='none';document.body.style.overflow='';"
        style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);color:rgba(255,255,255,0.7);width:36px;height:36px;border-radius:10px;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;"><i
          class='bx bx-x'></i></button>
    </div>

    <div style="padding:22px 26px;">

      <!-- Botão restaurar padrão -->
      <div
        style="background:rgba(65,88,208,0.08);border:1px solid rgba(65,88,208,0.2);border-radius:14px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:22px;">
        <div style="display:flex;align-items:center;gap:10px;">
          <div
            style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#4158D0,#6366f1);display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff;">
            <i class='bx bx-reset'></i></div>
          <div>
            <div style="color:#fff;font-weight:700;font-size:13px;">Voltar ao Tema Padrão</div>
            <div style="color:rgba(255,255,255,0.4);font-size:11px;">Restaura o visual Dark Original</div>
          </div>
        </div>
        <form method="POST"><input type="hidden" name="__setMeuTema" value="1">
          <button type="submit"
            style="background:linear-gradient(135deg,#4158D0,#6366f1);border:none;color:#fff;padding:8px 18px;border-radius:10px;font-weight:700;font-size:12px;cursor:pointer;display:flex;align-items:center;gap:6px;"><i
              class='bx bx-undo'></i> Restaurar</button>
        </form>
      </div>

      <div
        style="font-size:11px;font-weight:700;color:rgba(255,255,255,0.3);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
        <span style="flex:1;height:1px;background:rgba(255,255,255,0.07);"></span>
        <span>Escolher Tema</span>
        <span style="flex:1;height:1px;background:rgba(255,255,255,0.07);"></span>
      </div>

      <!-- Grid de temas -->
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:13px;margin-bottom:22px;">
        <?php foreach ($temas as $id => $t):
          $ativo = ($id == $temaAtualId);
          $prev = $temasPrev[$id] ?? ['#0f172a', '#1e293b', '#4158D0'];
          ?>
          <form method="POST" style="margin:0;">
            <input type="hidden" name="__setMeuTema" value="<?php echo $id; ?>">
            <button type="submit"
              style="width:100%;background:<?php echo $ativo ? 'rgba(255,255,255,0.1)' : 'rgba(255,255,255,0.04)'; ?>;border:2px solid <?php echo $ativo ? $prev[2] : 'rgba(255,255,255,0.07)'; ?>;border-radius:16px;padding:14px;cursor:pointer;transition:all .2s;text-align:left;font-family:'Inter',sans-serif;"
              onmouseover="this.style.borderColor='<?php echo $prev[2]; ?>';this.style.background='rgba(255,255,255,0.08)'"
              onmouseout="this.style.borderColor='<?php echo $ativo ? $prev[2] : 'rgba(255,255,255,0.07)'; ?>';this.style.background='<?php echo $ativo ? 'rgba(255,255,255,0.1)' : 'rgba(255,255,255,0.04)'; ?>'">
              <!-- Preview visual do tema -->
              <div
                style="height:48px;border-radius:10px;background:<?php echo $prev[0]; ?>;border:1px solid rgba(255,255,255,0.1);margin-bottom:11px;display:flex;overflow:hidden;position:relative;">
                <div
                  style="width:28%;background:<?php echo $prev[0]; ?>;border-right:1px solid rgba(255,255,255,0.08);display:flex;flex-direction:column;gap:4px;padding:6px 5px;">
                  <div style="height:4px;border-radius:2px;background:<?php echo $prev[2]; ?>;opacity:0.8;"></div>
                  <div style="height:4px;border-radius:2px;background:rgba(255,255,255,0.15);"></div>
                  <div style="height:4px;border-radius:2px;background:rgba(255,255,255,0.1);"></div>
                </div>
                <div style="flex:1;padding:5px;display:flex;flex-direction:column;gap:3px;">
                  <div
                    style="height:12px;border-radius:4px;background:<?php echo $prev[1]; ?>;border:1px solid rgba(255,255,255,0.1);">
                  </div>
                  <div style="display:flex;gap:3px;">
                    <div
                      style="flex:1;height:10px;border-radius:3px;background:<?php echo $prev[1]; ?>;border-top:2px solid <?php echo $prev[2]; ?>;">
                    </div>
                    <div
                      style="flex:1;height:10px;border-radius:3px;background:<?php echo $prev[1]; ?>;border-top:2px solid rgba(255,255,255,0.2);">
                    </div>
                  </div>
                </div>
              </div>
              <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;">
                <span
                  style="color:#fff;font-size:12px;font-weight:700;"><?php echo htmlspecialchars($t['nome']); ?></span>
                <?php if ($ativo): ?>
                  <span
                    style="background:<?php echo $prev[2]; ?>;color:#fff;font-size:9px;font-weight:700;padding:2px 7px;border-radius:20px;">✓
                    ATIVO</span>
                <?php endif; ?>
              </div>
              <div style="font-size:10px;color:rgba(255,255,255,0.35);margin-top:3px;">
                <?php echo htmlspecialchars($t['desc']); ?></div>
              <div style="display:flex;gap:4px;margin-top:8px;">
                <div style="width:10px;height:10px;border-radius:50%;background:<?php echo $prev[2]; ?>;"></div>
                <div
                  style="width:10px;height:10px;border-radius:50%;background:<?php echo $prev[1]; ?>;border:1px solid rgba(255,255,255,0.15);">
                </div>
                <div
                  style="width:10px;height:10px;border-radius:50%;background:<?php echo $prev[0]; ?>;border:1px solid rgba(255,255,255,0.15);">
                </div>
              </div>
            </button>
          </form>
        <?php endforeach; ?>
      </div>

      <?php if ($isAdmin): ?>
        <!-- Seção Admin: tema da tela de login -->
        <div
          style="background:rgba(234,179,8,0.05);border:1px solid rgba(234,179,8,0.2);border-radius:14px;padding:16px 18px;margin-top:4px;">
          <div
            style="font-size:12px;font-weight:700;color:#fbbf24;margin-bottom:12px;display:flex;align-items:center;gap:8px;">
            <i class='bx bx-globe'></i> Tema da Tela de Login (visível para todos)
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($temas as $id => $t):
              $prev = $temasPrev[$id] ?? ['#0f172a', '#1e293b', '#4158D0'];
              ?>
              <form method="POST" style="margin:0;">
                <input type="hidden" name="__setLoginTema" value="<?php echo $id; ?>">
                <button type="submit"
                  style="background:<?php echo $prev[1]; ?>;border:1.5px solid <?php echo $prev[2]; ?>;border-radius:10px;padding:6px 12px;cursor:pointer;color:#fff;font-size:11px;font-weight:600;transition:all .2s;display:flex;align-items:center;gap:6px;font-family:'Inter',sans-serif;"
                  onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                  <span
                    style="width:8px;height:8px;border-radius:50%;background:<?php echo $prev[2]; ?>;display:inline-block;"></span>
                  <?php echo htmlspecialchars($t['nome']); ?>
                </button>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div
        style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:11px 14px;margin-top:16px;display:flex;align-items:center;gap:8px;color:rgba(255,255,255,0.35);font-size:11px;">
        <i class='bx bx-info-circle' style="font-size:15px;color:#a78bfa;flex-shrink:0;"></i>
        <?php echo $isAdmin ? 'Seu tema afeta seu painel e a tela de login. Outros revendedores têm temas independentes.' : 'Apenas você vê este tema. Outros revendedores não são afetados.'; ?>
      </div>
    </div>
  </div>
</div>

<style>
  @keyframes fadeIn {
    from {
      opacity: 0
    }

    to {
      opacity: 1
    }
  }

  @keyframes slideUp {
    from {
      opacity: 0;
      transform: translateY(30px)
    }

    to {
      opacity: 1;
      transform: translateY(0)
    }
  }

  #themeModalOverlay {
    animation: fadeIn .2s ease
  }
</style>
<script>
  function openThemeModal() {
    document.getElementById('themeModalOverlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
  document.getElementById('themeModalOverlay').addEventListener('click', function (e) {
    if (e.target === this) { this.style.display = 'none'; document.body.style.overflow = ''; }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.getElementById('themeModalOverlay').style.display = 'none';
      document.body.style.overflow = '';
    }
  });
</script>