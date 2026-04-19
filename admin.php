<?php
// ============================================
// RSVP - Dashboard Admin
// URL: admin.php (proteggi con .htaccess o auth)
// ============================================

require_once 'config.php';
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
    SELECT f.nome_famiglia, f.token, f.telefono, f.nome_famiglia,
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
                <span class="famiglia-nome"><?= htmlspecialchars($nome_fam) ?> - <small class="numero-tel"><?= $fam['telefono'] ?></small></span>
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
                <a class="action-btn whatsapp" href="<?= htmlspecialchars($wa_url) ?>" target="_blank" rel="noopener">
                    💬 Invia su WhatsApp
                </a>
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
