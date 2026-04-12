<?php
// ============================================
// RSVP - Dashboard Admin
// ============================================

session_start();
require_once 'config.php';

// ---- Logout ----
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ---- Login ----
$login_errore = '';
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
        if ($_POST['admin_password'] === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $login_errore = 'Password errata.';
        }
    }

    if (!isset($_SESSION['admin_logged_in'])) {
        ?><!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Accesso</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #1A1520; --bg-card: #231E29; --border: #3A3340;
            --text: #E8E2ED; --text-muted: #9B8FA3;
            --mauve: #C49AAA; --gold: #C4A265;
            --decline: #C4837A; --decline-dim: rgba(196,131,122,0.12);
            --font-display: 'Cormorant Garamond', Georgia, serif;
            --font-ui: 'DM Sans', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-ui);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 360px;
            text-align: center;
        }
        h1 {
            font-family: var(--font-display);
            font-size: 1.8rem;
            color: var(--mauve);
            margin-bottom: 0.3rem;
        }
        .subtitle { font-size: 0.82rem; color: var(--text-muted); margin-bottom: 2rem; }
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-family: var(--font-ui);
            font-size: 0.95rem;
            margin-bottom: 1rem;
            transition: border-color 0.2s;
        }
        input[type="password"]:focus { outline: none; border-color: var(--mauve); }
        button {
            width: 100%;
            padding: 0.75rem;
            background: var(--gold);
            color: #1A1520;
            border: none;
            border-radius: 8px;
            font-family: var(--font-ui);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        button:hover { opacity: 0.85; }
        .errore {
            background: var(--decline-dim);
            color: var(--decline);
            border: 1px solid rgba(196,131,122,0.3);
            border-radius: 8px;
            padding: 0.6rem 1rem;
            font-size: 0.88rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<div class="login-box">
    <h1>Dashboard RSVP</h1>
    <div class="subtitle">Francesco &amp; Serena — area riservata</div>
    <?php if ($login_errore): ?>
        <div class="errore"><?= htmlspecialchars($login_errore) ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="password" name="admin_password" placeholder="Password" autofocus>
        <button type="submit">Accedi</button>
    </form>
</div>
</body>
</html><?php
        exit;
    }
}

$db = getDB();

// ---- Filtro attivo ----
$filtro = $_GET['filtro'] ?? 'tutti';
$ricerca = trim($_GET['q'] ?? '');

// ---- Statistiche generali ----
$stats = $db->query("
    SELECT 
        COUNT(*) AS totale,
        SUM(CASE WHEN confermato = 1 THEN 1 ELSE 0 END) AS confermati,
        SUM(CASE WHEN confermato = 0 AND risposto_at IS NOT NULL THEN 1 ELSE 0 END) AS declinati,
        SUM(CASE WHEN risposto_at IS NULL THEN 1 ELSE 0 END) AS in_attesa
    FROM invitati
")->fetch();

$stats_famiglie = $db->query("
    SELECT 
        COUNT(*) AS totale,
        SUM(CASE WHEN ha_risposto > 0 THEN 1 ELSE 0 END) AS risposte
    FROM (
        SELECT f.id, SUM(CASE WHEN i.risposto_at IS NOT NULL THEN 1 ELSE 0 END) AS ha_risposto
        FROM famiglie f
        LEFT JOIN invitati i ON i.famiglia_id = f.id
        GROUP BY f.id
    ) sub
")->fetch();

// ---- Query invitati con filtro ----
$where = '1=1';
$params = [];

switch ($filtro) {
    case 'confermati':
        $where .= ' AND i.confermato = 1';
        break;
    case 'declinati':
        $where .= ' AND i.confermato = 0 AND i.risposto_at IS NOT NULL';
        break;
    case 'attesa':
        $where .= ' AND i.risposto_at IS NULL';
        break;
    case 'note':
        $where .= " AND i.note IS NOT NULL AND i.note != ''";
        break;
}

if ($ricerca !== '') {
    $where .= ' AND (i.nome LIKE :q OR i.cognome LIKE :q OR f.nome_famiglia LIKE :q)';
    $params['q'] = '%' . $ricerca . '%';
}

$stmt = $db->prepare("
    SELECT f.nome_famiglia, f.token,
           i.id, i.nome, i.cognome, i.confermato, i.note, i.risposto_at
    FROM invitati i
    JOIN famiglie f ON f.id = i.famiglia_id
    WHERE {$where}
    ORDER BY f.nome_famiglia ASC, i.id ASC
");
$stmt->execute($params);
$invitati = $stmt->fetchAll();

// Raggruppa per famiglia
$famiglie = [];
foreach ($invitati as $inv) {
    $famiglie[$inv['nome_famiglia']]['token'] = $inv['token'];
    $famiglie[$inv['nome_famiglia']]['membri'][] = $inv;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin RSVP - Francesco &amp; Serena</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #1A1520;
            --bg-card: #231E29;
            --bg-card-hover: #2B2531;
            --border: #3A3340;
            --text: #E8E2ED;
            --text-muted: #9B8FA3;
            --mauve: #C49AAA;
            --gold: #C4A265;
            --gold-dim: rgba(196, 162, 101, 0.15);
            --success: #7EAF82;
            --success-dim: rgba(126, 175, 130, 0.12);
            --decline: #C4837A;
            --decline-dim: rgba(196, 131, 122, 0.12);
            --waiting: #A0A0B8;
            --waiting-dim: rgba(160, 160, 184, 0.10);
            --font-display: 'Cormorant Garamond', Georgia, serif;
            --font-ui: 'DM Sans', system-ui, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-ui);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 1.5rem;
        }

        .dashboard {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Header */
        .dash-header {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
        }
        .logout-btn {
            position: absolute;
            right: 0;
            top: 0;
            padding: 0.4rem 0.9rem;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-muted);
            font-family: var(--font-ui);
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .logout-btn:hover { border-color: var(--decline); color: var(--decline); }

        .dash-header h1 {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 600;
            color: var(--mauve);
        }

        .dash-header .subtitle {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 0.3rem;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.2rem 1rem;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            font-family: var(--font-display);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .stat-card.totale .stat-number { color: var(--text); }
        .stat-card.confermati .stat-number { color: var(--success); }
        .stat-card.declinati .stat-number { color: var(--decline); }
        .stat-card.attesa .stat-number { color: var(--waiting); }

        /* Toolbar */
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 1.2rem;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-muted);
            font-family: var(--font-ui);
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            border-color: var(--mauve);
            color: var(--text);
        }

        .filter-btn.active {
            background: var(--gold-dim);
            border-color: var(--gold);
            color: var(--gold);
        }

        .search-box {
            margin-left: auto;
        }

        .search-box input {
            padding: 0.5rem 0.8rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-family: var(--font-ui);
            font-size: 0.85rem;
            width: 200px;
            transition: border-color 0.2s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--mauve);
        }

        .search-box input::placeholder {
            color: var(--text-muted);
        }

        /* Famiglia card */
        .famiglia-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 0.8rem;
            overflow: hidden;
            transition: border-color 0.2s;
        }

        .famiglia-card:hover {
            border-color: var(--mauve);
        }

        .famiglia-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.2rem;
            cursor: pointer;
            user-select: none;
        }

        .famiglia-nome {
            font-family: var(--font-display);
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--mauve);
        }

        .famiglia-meta {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }

        .badge.confermato { background: var(--success-dim); color: var(--success); }
        .badge.declinato { background: var(--decline-dim); color: var(--decline); }
        .badge.attesa { background: var(--waiting-dim); color: var(--waiting); }

        .toggle-icon {
            color: var(--text-muted);
            font-size: 0.8rem;
            transition: transform 0.2s;
        }

        .famiglia-card.open .toggle-icon {
            transform: rotate(180deg);
        }

        .famiglia-body {
            display: none;
            border-top: 1px solid var(--border);
            padding: 0.8rem 1.2rem;
        }

        .famiglia-card.open .famiglia-body {
            display: block;
        }

        .membro-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(58, 51, 64, 0.5);
        }

        .membro-row:last-child {
            border-bottom: none;
        }

        .membro-nome {
            font-weight: 500;
            font-size: 0.95rem;
        }

        .membro-note {
            font-size: 0.82rem;
            color: var(--text-muted);
            font-style: italic;
            margin-top: 0.2rem;
        }

        .membro-data {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 0.2rem;
        }

        /* Famiglie info */
        .famiglie-info {
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }

        /* Responsive */
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-box { margin-left: 0; }
            .search-box input { width: 100%; }
            .filter-btn { text-align: center; }
        }
    </style>
</head>
<body>
<div class="dashboard">

    <div class="dash-header">
        <a href="?logout=1" class="logout-btn">Esci</a>
        <h1>Dashboard RSVP</h1>
        <div class="subtitle">Francesco &amp; Serena — 27 Settembre 2026</div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card totale">
            <div class="stat-number"><?= $stats['totale'] ?></div>
            <div class="stat-label">Invitati</div>
        </div>
        <div class="stat-card confermati">
            <div class="stat-number"><?= $stats['confermati'] ?></div>
            <div class="stat-label">Confermati</div>
        </div>
        <div class="stat-card declinati">
            <div class="stat-number"><?= $stats['declinati'] ?></div>
            <div class="stat-label">Declinati</div>
        </div>
        <div class="stat-card attesa">
            <div class="stat-number"><?= $stats['in_attesa'] ?></div>
            <div class="stat-label">In attesa</div>
        </div>
    </div>

    <div class="famiglie-info">
        Famiglie: <?= $stats_famiglie['totale'] ?> totali, 
        <?= $stats_famiglie['risposte'] ?> hanno risposto
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <a href="?filtro=tutti" class="filter-btn <?= $filtro === 'tutti' ? 'active' : '' ?>">Tutti</a>
        <a href="?filtro=confermati" class="filter-btn <?= $filtro === 'confermati' ? 'active' : '' ?>">✓ Confermati</a>
        <a href="?filtro=declinati" class="filter-btn <?= $filtro === 'declinati' ? 'active' : '' ?>">✗ Declinati</a>
        <a href="?filtro=attesa" class="filter-btn <?= $filtro === 'attesa' ? 'active' : '' ?>">⏳ In attesa</a>
        <a href="?filtro=note" class="filter-btn <?= $filtro === 'note' ? 'active' : '' ?>">📝 Con note</a>

        <div class="search-box">
            <form method="GET">
                <input type="hidden" name="filtro" value="<?= htmlspecialchars($filtro) ?>">
                <input type="text" name="q" placeholder="Cerca nome o famiglia..." 
                       value="<?= htmlspecialchars($ricerca) ?>">
            </form>
        </div>
    </div>

    <!-- Lista famiglie -->
    <?php if (empty($famiglie)): ?>
        <div class="empty-state">Nessun risultato per i filtri selezionati.</div>
    <?php endif; ?>

    <?php foreach ($famiglie as $nome_fam => $fam): ?>
    <div class="famiglia-card" onclick="this.classList.toggle('open')">
        <div class="famiglia-header">
            <div>
                <span class="famiglia-nome"><?= htmlspecialchars($nome_fam) ?></span>
            </div>
            <div class="famiglia-meta">
                <?php
                $num_confermati = 0;
                $num_declinati = 0;
                $num_attesa = 0;
                foreach ($fam['membri'] as $m) {
                    if ($m['risposto_at'] === null) $num_attesa++;
                    elseif ($m['confermato']) $num_confermati++;
                    else $num_declinati++;
                }
                ?>
                <?php if ($num_confermati): ?>
                    <span class="badge confermato"><?= $num_confermati ?> ✓</span>
                <?php endif; ?>
                <?php if ($num_declinati): ?>
                    <span class="badge declinato"><?= $num_declinati ?> ✗</span>
                <?php endif; ?>
                <?php if ($num_attesa): ?>
                    <span class="badge attesa"><?= $num_attesa ?> ⏳</span>
                <?php endif; ?>
                <span class="toggle-icon">▼</span>
            </div>
        </div>
        <div class="famiglia-body" onclick="event.stopPropagation()">
            <?php foreach ($fam['membri'] as $m): ?>
            <div class="membro-row">
                <div>
                    <div class="membro-nome"><?= htmlspecialchars($m['nome'] . ' ' . $m['cognome']) ?></div>
                    <?php if (!empty($m['note'])): ?>
                        <div class="membro-note">📝 <?= htmlspecialchars($m['note']) ?></div>
                    <?php endif; ?>
                    <?php if ($m['risposto_at']): ?>
                        <div class="membro-data">Risposta: <?= date('d/m/Y H:i', strtotime($m['risposto_at'])) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($m['risposto_at'] === null): ?>
                        <span class="badge attesa">In attesa</span>
                    <?php elseif ($m['confermato']): ?>
                        <span class="badge confermato">Confermato</span>
                    <?php else: ?>
                        <span class="badge declinato">Declinato</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>
</body>
</html>
