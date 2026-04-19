<?php
// ============================================
// RSVP - Dashboard Admin
// ============================================

require_once 'config.php';

session_start();

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Verifica login
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $login_error = 'Password errata.';
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin RSVP — Login</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/admin.css">
        <style>
            .login-wrap { display:flex; align-items:center; justify-content:center; min-height:100vh; }
            .login-box { background:#fff; padding:2.5rem 2rem; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,.08); width:100%; max-width:360px; text-align:center; }
            .login-box h1 { font-family:'Cormorant Garamond',serif; font-size:1.8rem; margin-bottom:.25rem; }
            .login-box p { color:#888; font-size:.9rem; margin-bottom:1.5rem; }
            .login-box input[type=password] { width:100%; padding:.75rem 1rem; border:1px solid #ddd; border-radius:8px; font-size:1rem; box-sizing:border-box; margin-bottom:1rem; }
            .login-box button { width:100%; padding:.75rem; background:#8b7355; color:#fff; border:none; border-radius:8px; font-size:1rem; cursor:pointer; }
            .login-box button:hover { background:#7a6448; }
            .login-error { color:#c0392b; font-size:.875rem; margin-bottom:.75rem; }
        </style>
    </head>
    <body>
    <div class="login-wrap">
        <div class="login-box">
            <h1>Admin RSVP</h1>
            <p>Francesco &amp; Serena — 27 Settembre 2026</p>
            <?php if (!empty($login_error)): ?>
                <div class="login-error"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Password" autofocus>
                <button type="submit">Accedi</button>
            </form>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$sitoweb = SITOWEB;
$prefisso = PREFISSO_INTERNAZIONALE;

function generaTokenUnivoco($db): string {
    do {
        $token = str_pad((string)random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        
        // Verifica se esiste già nel database
        $check = $db->prepare("SELECT COUNT(*) FROM famiglie WHERE token = ?");
        $check->execute([$token]);
        $exists = $check->fetchColumn();
        
    } while ($exists > 0);
    return $token;
}

// ---- CONFIGURAZIONE LINK ----
define('RSVP_BASE_URL', $sitoweb.'index.php');
define('WA_MESSAGE', "Ciao! \nSiete invitati al matrimonio di *Francesco & Serena* il 27 Settembre 2026.\n\nConfermate la vostra presenza qui:\n{link}\n\nVi aspettiamo con gioia!");
define('WA_MESSAGE_SINGLE', "Ciao! \nSei invitato/a al matrimonio di *Francesco & Serena* il 27 Settembre 2026.\n\nConferma la tua presenza qui:\n{link}\n\nTi aspettiamo con gioia!");

$db = getDB();

// ---- Migrazione: aggiunge colonna sended se non esiste ----
try {
    $db->exec("ALTER TABLE famiglie ADD COLUMN sended TIMESTAMP NULL DEFAULT NULL");
} catch (PDOException $e) {
    if ($e->errorInfo[1] !== 1060) throw $e; // 1060 = Duplicate column, ignorato
}

// ---- Azione AJAX: segna invito come inviato ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'segna_inviato') {
    header('Content-Type: application/json');
    $token_fam = $_POST['token'] ?? '';
    if (!$token_fam) {
        echo json_encode(['ok' => false, 'error' => 'Token mancante']);
        exit;
    }
    $upd = $db->prepare("UPDATE famiglie SET sended = NOW() WHERE token = :token");
    $upd->execute(['token' => $token_fam]);
    $row = $db->prepare("SELECT sended FROM famiglie WHERE token = :token");
    $row->execute(['token' => $token_fam]);
    $sended = $row->fetchColumn();
    echo json_encode(['ok' => true, 'sended' => $sended]);
    exit;
}

// ---- Azione: genera token per famiglie senza token ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'genera_token') {
    $famiglie_senza_token = $db->query("SELECT id FROM famiglie WHERE token IS NULL OR token = ''")->fetchAll();
    $generati = 0;
    foreach ($famiglie_senza_token as $f) {
        $nuovo_token = generaTokenUnivoco($db);
        $upd = $db->prepare("UPDATE famiglie SET token = :token WHERE id = :id");
        $upd->execute(['token' => $nuovo_token, 'id' => $f['id']]);
        $generati++;
    }
    header('Location: admin.php?filtro=' . urlencode($_GET['filtro'] ?? 'tutti') . '&token_generati=' . $generati);
    exit;
}

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
    case 'inviati':
        $where .= ' AND f.sended IS NOT NULL';
        break;
    case 'non_inviati':
        $where .= ' AND f.sended IS NULL';
        break;
}

if ($ricerca !== '') {
    $where .= ' AND (i.nome LIKE :q1 OR i.cognome LIKE :q2 OR f.nome_famiglia LIKE :q3)';
    $params['q1'] = '%' . $ricerca . '%';
    $params['q2'] = '%' . $ricerca . '%';
    $params['q3'] = '%' . $ricerca . '%';
}

$stmt = $db->prepare("
    SELECT f.nome_famiglia, f.token, f.telefono, f.sended,
           i.id, i.nome, i.cognome, i.confermato, i.note, i.risposto_at, i.famiglia_id
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
    $nomeFamiglia = $inv['nome_famiglia'];
    $tokenAttuale = $inv['token'];

    if (empty($tokenAttuale)) {
        $tokenAttuale = generaTokenUnivoco($db);
        
        $update = $db->prepare("UPDATE famiglie SET token = :token WHERE id = :id");
        $update->execute([
            'token' => $tokenAttuale,
            'id'    => $inv['famiglia_id']
        ]);
        $inv['token'] = $tokenAttuale;
    }
    $famiglie[$nomeFamiglia]['token'] = $tokenAttuale;
    $famiglie[$nomeFamiglia]['telefono'] = $inv['telefono'];
    $famiglie[$nomeFamiglia]['sended'] = $inv['sended'];
    $famiglie[$nomeFamiglia]['membri'][] = $inv;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin RSVP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="dashboard">

    <div class="dash-header">
        <h1>Dashboard RSVP</h1>
        <div class="subtitle"><?php echo TITOLO; ?></div>
        <a href="?logout=1" class="logout-btn" style="position:absolute;top:1.25rem;right:1.5rem;font-size:.85rem;color:#888;text-decoration:none;padding:.4rem .8rem;border:1px solid #ddd;border-radius:6px;">Esci</a>
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

    <!-- Azioni globali -->
    <?php if (isset($_GET['token_generati'])): ?>
        <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;color:#2e7d32;font-size:.9rem;">
            ✓ Token generati: <?= (int)$_GET['token_generati'] ?> famiglie aggiornate.
        </div>
    <?php endif; ?>
    <div style="margin-bottom:1rem;display:flex;gap:.75rem;align-items:center;">
        <form method="POST">
            <input type="hidden" name="action" value="genera_token">
            <button type="submit" class="action-btn" style="background:#6d8b74;color:#fff;border:none;padding:.55rem 1.1rem;border-radius:8px;cursor:pointer;font-size:.875rem;">
                🔑 Genera token mancanti
            </button>
        </form>
        <a href="insert.php" style="display:inline-block;padding:.55rem 1.1rem;border:1px solid #ddd;border-radius:8px;text-decoration:none;color:#555;font-size:.875rem;">+ Inserisci invitati</a>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <a href="?filtro=tutti" class="filter-btn <?= $filtro === 'tutti' ? 'active' : '' ?>">Tutti</a>
        <a href="?filtro=confermati" class="filter-btn <?= $filtro === 'confermati' ? 'active' : '' ?>">✓ Confermati</a>
        <a href="?filtro=declinati" class="filter-btn <?= $filtro === 'declinati' ? 'active' : '' ?>">✗ Declinati</a>
        <a href="?filtro=attesa" class="filter-btn <?= $filtro === 'attesa' ? 'active' : '' ?>">⏳ In attesa</a>
        <a href="?filtro=note" class="filter-btn <?= $filtro === 'note' ? 'active' : '' ?>">📝 Con note</a>
        <a href="?filtro=inviati" class="filter-btn <?= $filtro === 'inviati' ? 'active' : '' ?>">✉️ Inviati</a>
        <a href="?filtro=non_inviati" class="filter-btn <?= $filtro === 'non_inviati' ? 'active' : '' ?>">📭 Non inviati</a>

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
    <div class="famiglia-card" onclick="this.classList.toggle('open')" id="famiglia-<?= htmlspecialchars($fam['token']) ?>">
        <div class="famiglia-header">
            <div>
                <span class="famiglia-nome"><?= htmlspecialchars($nome_fam) ?> - <small class="numero-tel"><?= $fam['telefono'] ?></small></span>
                <?php if ($fam['sended']): ?>
                    <span class="badge inviato" style="font-size:.75rem;background:#e3f2fd;color:#1565c0;border-radius:4px;padding:.15rem .45rem;margin-left:.5rem;">
                        ✉️ <?= date('d/m/Y H:i', strtotime($fam['sended'])) ?>
                    </span>
                <?php endif; ?>
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

            <?php
            $rsvp_link = RSVP_BASE_URL . '?token=' . urlencode($fam['token']) . '&famiglia=' . urlencode($nome_fam);
            $template = count($fam['membri']) === 1 ? WA_MESSAGE_SINGLE : WA_MESSAGE;
            $wa_text = str_replace('{link}', $rsvp_link, $template);
            $telefono = preg_replace('/[^0-9]/', '', $fam['telefono'] ?? '');
            $wa_url = $telefono
                ? 'https://wa.me/' . $prefisso . $telefono . '?text=' . rawurlencode($wa_text)
                : '';
            ?>
            <div class="famiglia-actions">
                <button class="action-btn copia" onclick="copiaLink(this, '<?= htmlspecialchars($rsvp_link, ENT_QUOTES) ?>')">
                    📋 Copia link
                </button>
                <?php if ($wa_url): ?>
                <button class="action-btn whatsapp"
                    onclick="inviaWhatsApp(this, '<?= htmlspecialchars($wa_url, ENT_QUOTES) ?>', '<?= htmlspecialchars($fam['token'], ENT_QUOTES) ?>')">
                    💬 Invia su WhatsApp
                </button>
                <?php else: ?>
                <span class="action-btn whatsapp" style="opacity:0.4; cursor:default;" title="Numero di telefono mancante">
                    💬 WhatsApp (no telefono)
                </span>
                <?php endif; ?>
                <div class="link-preview"><?= htmlspecialchars($rsvp_link) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<script>
function inviaWhatsApp(btn, waUrl, token) {
    // Apre WhatsApp
    window.open(waUrl, '_blank', 'noopener');

    // Segna come inviato via AJAX
    const form = new FormData();
    form.append('action', 'segna_inviato');
    form.append('token', token);

    fetch('admin.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;
            // Aggiorna il badge nel card
            const card = document.getElementById('famiglia-' + token);
            if (!card) return;
            const nomeSpan = card.querySelector('.famiglia-nome');
            let badge = card.querySelector('.badge.inviato');
            const dataFmt = new Date(data.sended.replace(' ', 'T'))
                .toLocaleString('it-IT', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
            if (badge) {
                badge.textContent = '✉️ ' + dataFmt;
            } else {
                badge = document.createElement('span');
                badge.className = 'badge inviato';
                badge.style.cssText = 'font-size:.75rem;background:#e3f2fd;color:#1565c0;border-radius:4px;padding:.15rem .45rem;margin-left:.5rem;';
                badge.textContent = '✉️ ' + dataFmt;
                nomeSpan.appendChild(badge);
            }
        })
        .catch(() => {});
}

function copiaLink(btn, link) {
    navigator.clipboard.writeText(link).then(() => {
        btn.classList.add('copiato');
        btn.innerHTML = '✓ Copiato!';
        setTimeout(() => {
            btn.classList.remove('copiato');
            btn.innerHTML = '📋 Copia link';
        }, 2000);
    }).catch(() => {
        // Fallback per browser senza clipboard API
        const tmp = document.createElement('textarea');
        tmp.value = link;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
        btn.classList.add('copiato');
        btn.innerHTML = '✓ Copiato!';
        setTimeout(() => {
            btn.classList.remove('copiato');
            btn.innerHTML = '📋 Copia link';
        }, 2000);
    });
}
</script>
</body>
</html>
