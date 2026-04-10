<?php
session_start();
include('../AegisCore/conexao.php');
include('header2.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'])) {
    echo "<script>location.href='../index.php';</script>";
    exit;
}

// Buscar todos os clientes online de revendedores com o nome do revendedor da tabela accounts
$sql_clientes = "SELECT s.login, s.senha, s.limite, s.expira, s.status, s.byid,
                        o.quantidade as conexoes,
                        a.userid as rev_id,
                        acc.login as rev_nome
                 FROM ssh_accounts s
                 INNER JOIN onlines o ON o.usuario = s.login
                 LEFT JOIN atribuidos a ON a.userid = s.byid
                 LEFT JOIN accounts acc ON acc.id = a.userid
                 WHERE s.byid != '0' AND s.byid IS NOT NULL
                 ORDER BY a.userid ASC, s.login ASC";
$result_clientes = mysqli_query($conn, $sql_clientes);

if (!$result_clientes) {
    echo "Erro na consulta: " . mysqli_error($conn);
    exit;
}

$total_clientes_online = mysqli_num_rows($result_clientes);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes Online de Revendedores</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes Online de Revendedores</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        :root {
            --primary: #4158D0;
            --secondary: #C850C0;
            --tertiary: #FFCC70;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #2c3e50;
            --light: #f8fafc;
            --border: #eef2f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }

        .app-content {
            margin-left: 240px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 1630px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 12px !important;
            padding: 10px 15px !important;
            margin-bottom: 15px !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
            color: white !important;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .filters-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 14px !important;
            padding: 14px !important;
            margin-bottom: 16px !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            position: relative !important;
            overflow: hidden !important;
        }

        .filters-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 30%, rgba(200,80,192,0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .filters-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 1;
        }

        .filters-title i {
            color: var(--tertiary);
            font-size: 16px;
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
            position: relative;
            z-index: 1;
        }

        .filter-item {
            flex: 1 1 200px;
            min-width: 160px;
        }

        .filter-label {
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-input {
            width: 100%;
            padding: 7px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            font-size: 13px;
            transition: all 0.3s;
            color: white;
        }

        .filter-input:focus {
            outline: none;
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .filter-input::placeholder {
            color: rgba(255,255,255,0.3);
        }

        .clientes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
            margin-top: 14px;
            width: 100%;
        }

        .cliente-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 16px !important;
            overflow: hidden !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            animation: fadeIn 0.4s ease !important;
            position: relative !important;
        }

        .cliente-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 80% 20%, rgba(65,88,208,0.1) 0%, transparent 60%);
            pointer-events: none;
        }

        .cliente-card:hover {
            transform: translateY(-3px) !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            background: linear-gradient(135deg, #C850C0, #4158D0) !important;
            color: white;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
            gap: 8px;
        }

        .cliente-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }

        .cliente-avatar {
            width: 36px;
            height: 36px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .cliente-text {
            flex: 1;
            min-width: 0;
        }

        .cliente-nome {
            font-size: 14px;
            font-weight: 700;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .revendedor-tag {
            font-size: 10px;
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-copy-card {
            background: rgba(255,255,255,0.15);
            border: none;
            border-radius: 10px;
            padding: 6px 12px;
            color: white;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
        }

        .btn-copy-card:hover {
            background: rgba(255,255,255,0.25);
            transform: scale(1.02);
        }

        .btn-copy-card.copied {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .card-body {
            padding: 12px;
            position: relative;
            z-index: 1;
        }

        .status-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        .status-card {
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            padding: 10px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
        }

        .status-card:hover {
            border-color: var(--primary);
            background: rgba(255,255,255,0.05);
        }

        .status-card .status-icon {
            font-size: 20px;
            margin-bottom: 6px;
        }

        .status-card .status-label {
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .status-card .status-value {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            margin-bottom: 8px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .info-label {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-value {
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .expiry-warning {
            color: #fbbf24;
        }

        .expiry-danger {
            color: #f87171;
        }

        .btn-delete-only {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-delete-only:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,38,38,0.4);
        }

        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.08);
            color: white;
        }

        .empty-state i {
            font-size: 48px;
            color: rgba(255,255,255,0.2);
            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: white;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .empty-state p {
            color: rgba(255,255,255,0.3);
            font-size: 13px;
        }

        .pagination-info {
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
            font-size: 13px;
        }

        .online-dot {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .pulse-dot {
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 50%;
            position: relative;
            animation: pulse 1.5s ease-in-out infinite;
            display: inline-block;
        }

        .pulse-dot::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: #10b981;
            border-radius: 50%;
            animation: pulse-ring 1.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
        }

        @keyframes pulse-ring {
            0% { transform: scale(1); opacity: 0.5; }
            100% { transform: scale(2); opacity: 0; }
        }

        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { margin: 0 auto !important; padding: 10px !important; }
            .clientes-grid { grid-template-columns: 1fr; gap: 12px; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .btn-copy-card { align-self: flex-end; }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">

            <div class="info-badge">
                <i class='bx bx-store'></i>
                <span>Clientes Online de Revendedores</span>
            </div>

            <div class="status-info">
                <div class="status-item">
                    <i class='bx bx-group'></i>
                    <span>Total Clientes Online: <?php echo $total_clientes_online; ?></span>
                </div>
                <div class="status-item">
                    <i class='bx bx-time'></i>
                    <span><?php echo date('d/m/Y H:i'); ?></span>
                </div>
            </div>

            <div class="filters-card">
                <div class="filters-title">
                    <i class='bx bx-filter-alt'></i>
                    Filtros
                </div>
                <div class="filter-group">
                    <div class="filter-item">
                        <div class="filter-label">BUSCAR CLIENTE</div>
                        <input type="text" class="filter-input" id="searchInput"
                               placeholder="Digite o nome do cliente..."
                               onkeyup="filtrarClientes(this.value)">
                    </div>
                </div>
            </div>

            <div class="clientes-grid" id="clientesGrid">
                <?php if ($result_clientes && mysqli_num_rows($result_clientes) > 0): ?>
                    <?php while ($cliente = mysqli_fetch_assoc($result_clientes)): 
                        $expira = strtotime($cliente['expira']);
                        $hoje = time();
                        $dias_restantes = floor(($expira - $hoje) / 86400);
                        $expiry_class = ($dias_restantes <= 5 && $dias_restantes > 0) ? 'expiry-warning' : (($dias_restantes <= 0) ? 'expiry-danger' : '');
                        $expira_formatada = date('d/m/Y', $expira);
                        
                        // Nome do revendedor - AGORA VEM DA TABELA accounts
                        $rev_nome = !empty($cliente['rev_nome']) ? $cliente['rev_nome'] : 'Revendedor #' . $cliente['rev_id'];
                    ?>
                    <div class="cliente-card" data-nome="<?php echo strtolower($cliente['login']); ?>">
                        <div class="card-header">
                            <div class="cliente-info">
                                <div class="cliente-avatar">
                                    <i class='bx bx-user'></i>
                                </div>
                                <div class="cliente-text">
                                    <div class="cliente-nome">
                                        <?php echo htmlspecialchars($cliente['login']); ?>
                                        <span class="online-dot">
                                            <span class="pulse-dot"></span>
                                        </span>
                                    </div>
                                    <div class="revendedor-tag">
                                        <i class='bx bx-store'></i>
                                       Revededor Dono: <?php echo htmlspecialchars($rev_nome); ?>
                                    </div>
                                </div>
                            </div>
                           
                        </div>
                        <div class="card-body">
                            <div class="status-row">
                                <div class="status-card">
                                    <div class="status-icon">
                                        <i class='bx bx-wifi' style="color: #10b981;"></i>
                                    </div>
                                    <div class="status-label">STATUS</div>
                                    <div class="status-value" style="color: #10b981;">Online</div>
                                </div>
                                <div class="status-card">
                                    <div class="status-icon">
                                        <i class='bx bx-group' style="color: #fbbf24;"></i>
                                    </div>
                                    <div class="status-label">LIMITE</div>
                                    <div class="status-value">
                                        <?php echo $cliente['conexoes']; ?>/<?php echo $cliente['limite']; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-label">
                                    <i class='bx bx-calendar'></i> Expira em
                                </div>
                                <div class="info-value <?php echo $expiry_class; ?>">
                                    <?php echo $expira_formatada; ?>
                                    <?php if ($dias_restantes > 0): ?>
                                        (<?php echo $dias_restantes; ?> dias)
                                    <?php elseif ($dias_restantes == 0): ?>
                                        (Hoje)
                                    <?php else: ?>
                                        (Expirado)
                                    <?php endif; ?>
                                </div>
                            </div>

                            <button class="btn-delete-only" onclick="excluirCliente('<?php echo $cliente['login']; ?>')">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class='bx bx-user-x'></i>
                        <h3>Nenhum cliente online de revendedores</h3>
                        <p>No momento não há clientes online de revendedores</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="pagination-info">
                Total de <?php echo $total_clientes_online; ?> cliente(s) online de revendedores
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>

    <script>
        function filtrarClientes(valor) {
            let search = valor.toLowerCase();
            let cards = document.querySelectorAll('.cliente-card');
            
            cards.forEach(card => {
                let nome = card.getAttribute('data-nome');
                if (nome.includes(search)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function copiarInfoCard(btn, usuario, senha, expira, limite, revendedor) {
            let texto = `📋 INFORMAÇÕES DO CLIENTE\n━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `👤 Login: ${usuario}\n`;
            texto += `🔑 Senha: ${senha}\n`;
            texto += `🔗 Limite: ${limite} conexões\n`;
            texto += `📅 Expira em: ${expira}\n`;
            texto += `✅ Status: Online\n`;
            texto += `🏪 Revendedor: ${revendedor}\n`;
            texto += `━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `📆 Data: ${new Date().toLocaleString('pt-BR')}`;
            
            navigator.clipboard.writeText(texto).then(function() {
                const originalText = btn.innerHTML;
                btn.classList.add('copied');
                btn.innerHTML = '<i class="bx bx-check"></i> <span class="copy-text">Copiado!</span>';
                setTimeout(function() {
                    btn.classList.remove('copied');
                    btn.innerHTML = originalText;
                }, 2000);
            });
        }

        function excluirCliente(usuario) {
            swal({
                title: "Tem certeza?",
                text: "Você deseja excluir o cliente " + usuario + "?",
                icon: "warning",
                buttons: true,
                dangerMode: true
            }).then((willDelete) => {
                if (willDelete) {
                    $.ajax({
                        url: 'excluiruser.php',
                        type: 'POST',
                        data: { usuario: usuario },
                        success: function(data) {
                            if (data == 'ok') {
                                swal("Sucesso!", "Cliente excluído com sucesso!", "success")
                                .then(() => { location.reload(); });
                            } else {
                                swal("Erro!", "Não foi possível excluir o cliente", "error");
                            }
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">

            <div class="info-badge">
                <i class='bx bx-store'></i>
                <span>Clientes Online de Revendedores</span>
            </div>

            <div class="status-info">
                <div class="status-item">
                    <i class='bx bx-group'></i>
                    <span>Total Clientes Online: <?php echo $total_clientes_online; ?></span>
                </div>
                <div class="status-item">
                    <i class='bx bx-time'></i>
                    <span><?php echo date('d/m/Y H:i'); ?></span>
                </div>
            </div>

            <div class="filters-card">
                <div class="filters-title">
                    <i class='bx bx-filter-alt'></i>
                    Filtros
                </div>
                <div class="filter-group">
                    <div class="filter-item">
                        <div class="filter-label">BUSCAR CLIENTE</div>
                        <input type="text" class="filter-input" id="searchInput"
                               placeholder="Digite o nome do cliente..."
                               onkeyup="filtrarClientes(this.value)">
                    </div>
                </div>
            </div>

            <div class="clientes-grid" id="clientesGrid">
                <?php if ($result_clientes && mysqli_num_rows($result_clientes) > 0): ?>
                    <?php while ($cliente = mysqli_fetch_assoc($result_clientes)): 
                        $expira = strtotime($cliente['expira']);
                        $hoje = time();
                        $dias_restantes = floor(($expira - $hoje) / 86400);
                        $expiry_class = ($dias_restantes <= 5 && $dias_restantes > 0) ? 'expiry-warning' : (($dias_restantes <= 0) ? 'expiry-danger' : '');
                        $expira_formatada = date('d/m/Y', $expira);
                        
                        // Nome do revendedor - AGORA VEM DA TABELA accounts
                        $rev_nome = !empty($cliente['rev_nome']) ? $cliente['rev_nome'] : 'Revendedor #' . $cliente['rev_id'];
                    ?>
                    <div class="cliente-card" data-nome="<?php echo strtolower($cliente['login']); ?>">
                        <div class="card-header">
                            <div class="cliente-info">
                                <div class="cliente-avatar">
                                    <i class='bx bx-user'></i>
                                </div>
                                <div class="cliente-text">
                                    <div class="cliente-nome">
                                        <?php echo htmlspecialchars($cliente['login']); ?>
                                        <span class="online-dot">
                                            <span class="pulse-dot"></span>
                                        </span>
                                    </div>
                                    <div class="revendedor-tag">
                                        <i class='bx bx-store'></i>
                                       Revededor Dono: <?php echo htmlspecialchars($rev_nome); ?>
                                    </div>
                                </div>
                            </div>
                           
                        </div>
                        <div class="card-body">
                            <div class="status-row">
                                <div class="status-card">
                                    <div class="status-icon">
                                        <i class='bx bx-wifi' style="color: #10b981;"></i>
                                    </div>
                                    <div class="status-label">STATUS</div>
                                    <div class="status-value" style="color: #10b981;">Online</div>
                                </div>
                                <div class="status-card">
                                    <div class="status-icon">
                                        <i class='bx bx-group' style="color: #fbbf24;"></i>
                                    </div>
                                    <div class="status-label">LIMITE</div>
                                    <div class="status-value">
                                        <?php echo $cliente['conexoes']; ?>/<?php echo $cliente['limite']; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-label">
                                    <i class='bx bx-calendar'></i> Expira em
                                </div>
                                <div class="info-value <?php echo $expiry_class; ?>">
                                    <?php echo $expira_formatada; ?>
                                    <?php if ($dias_restantes > 0): ?>
                                        (<?php echo $dias_restantes; ?> dias)
                                    <?php elseif ($dias_restantes == 0): ?>
                                        (Hoje)
                                    <?php else: ?>
                                        (Expirado)
                                    <?php endif; ?>
                                </div>
                            </div>

                            <button class="btn-delete-only" onclick="excluirCliente('<?php echo $cliente['login']; ?>')">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class='bx bx-user-x'></i>
                        <h3>Nenhum cliente online de revendedores</h3>
                        <p>No momento não há clientes online de revendedores</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="pagination-info">
                Total de <?php echo $total_clientes_online; ?> cliente(s) online de revendedores
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>

    <script>
        function filtrarClientes(valor) {
            let search = valor.toLowerCase();
            let cards = document.querySelectorAll('.cliente-card');
            
            cards.forEach(card => {
                let nome = card.getAttribute('data-nome');
                if (nome.includes(search)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function copiarInfoCard(btn, usuario, senha, expira, limite, revendedor) {
            let texto = `📋 INFORMAÇÕES DO CLIENTE\n━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `👤 Login: ${usuario}\n`;
            texto += `🔑 Senha: ${senha}\n`;
            texto += `🔗 Limite: ${limite} conexões\n`;
            texto += `📅 Expira em: ${expira}\n`;
            texto += `✅ Status: Online\n`;
            texto += `🏪 Revendedor: ${revendedor}\n`;
            texto += `━━━━━━━━━━━━━━━━━━━━━\n`;
            texto += `📆 Data: ${new Date().toLocaleString('pt-BR')}`;
            
            navigator.clipboard.writeText(texto).then(function() {
                const originalText = btn.innerHTML;
                btn.classList.add('copied');
                btn.innerHTML = '<i class="bx bx-check"></i> <span class="copy-text">Copiado!</span>';
                setTimeout(function() {
                    btn.classList.remove('copied');
                    btn.innerHTML = originalText;
                }, 2000);
            });
        }

        function excluirCliente(usuario) {
            swal({
                title: "Tem certeza?",
                text: "Você deseja excluir o cliente " + usuario + "?",
                icon: "warning",
                buttons: true,
                dangerMode: true
            }).then((willDelete) => {
                if (willDelete) {
                    $.ajax({
                        url: 'excluiruser.php',
                        type: 'POST',
                        data: { usuario: usuario },
                        success: function(data) {
                            if (data == 'ok') {
                                swal("Sucesso!", "Cliente excluído com sucesso!", "success")
                                .then(() => { location.reload(); });
                            } else {
                                swal("Erro!", "Não foi possível excluir o cliente", "error");
                            }
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>



