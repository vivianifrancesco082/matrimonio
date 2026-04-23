<?php
// ============================================
// RSVP - Inserimento Famiglie e Invitati
// ============================================

require_once 'config.php';

session_start();

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: insert.php');
    exit;
}

// Verifica login
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: insert.php');
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
        <title>Inserimento RSVP — Login</title>
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
            <h1>Inserimento RSVP</h1>
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

$db = getDB();

function generaTokenUnivoco($db): string {
    do {
        $token = str_pad((string)random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        $check = $db->prepare("SELECT COUNT(*) FROM famiglie WHERE token = ?");
        $check->execute([$token]);
        $exists = $check->fetchColumn();
    } while ($exists > 0);
    return $token;
}

$messaggi = [];
$errori = [];

// ---- Azione: aggiungi famiglia ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'aggiungi_famiglia') {
    $nome_famiglia = trim($_POST['nome_famiglia'] ?? '');
    $telefono      = trim($_POST['telefono'] ?? '');
    $lato          = $_POST['lato'] ?? '';
    $lato          = in_array($lato, ['sposo', 'sposa']) ? $lato : null;

    if ($nome_famiglia === '') {
        $errori[] = 'Il nome della famiglia è obbligatorio.';
    } else {
        // Controlla se esiste già
        $check = $db->prepare("SELECT COUNT(*) FROM famiglie WHERE nome_famiglia = ?");
        $check->execute([$nome_famiglia]);
        if ($check->fetchColumn() > 0) {
            $errori[] = 'Esiste già una famiglia con questo nome.';
        } else {
            $token = generaTokenUnivoco($db);
            $ins = $db->prepare("INSERT INTO famiglie (nome_famiglia, token, telefono, lato) VALUES (:nome, :token, :tel, :lato)");
            $ins->execute([
                'nome'  => $nome_famiglia,
                'token' => $token,
                'tel'   => $telefono !== '' ? $telefono : null,
                'lato'  => $lato,
            ]);
            $messaggi[] = 'Famiglia "' . htmlspecialchars($nome_famiglia) . '" aggiunta con successo (token: ' . $token . ').';
        }
    }
}

// ---- Azione: aggiungi invitato a una famiglia ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'aggiungi_invitato') {
    $famiglia_id = (int)($_POST['famiglia_id'] ?? 0);
    $nome        = trim($_POST['nome'] ?? '');
    $cognome     = trim($_POST['cognome'] ?? '');

    if (!$famiglia_id) {
        $errori[] = 'Seleziona una famiglia.';
    } elseif ($nome === '') {
        $errori[] = 'Nome è obbligatorio.';
    } else {
        $ins = $db->prepare("INSERT INTO invitati (famiglia_id, nome, cognome) VALUES (:fid, :nome, :cognome)");
        $ins->execute(['fid' => $famiglia_id, 'nome' => $nome, 'cognome' => $cognome]);
        $messaggi[] = 'Invitato "' . htmlspecialchars($nome . ' ' . $cognome) . '" aggiunto con successo.';
    }
}

// ---- Azione: elimina invitato ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'elimina_invitato') {
    $inv_id = (int)($_POST['invitato_id'] ?? 0);
    if ($inv_id) {
        $del = $db->prepare("DELETE FROM invitati WHERE id = ?");
        $del->execute([$inv_id]);
        $messaggi[] = 'Invitato eliminato.';
    }
}

// ---- Azione: elimina famiglia (e tutti i suoi invitati via CASCADE) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'elimina_famiglia') {
    $fam_id = (int)($_POST['famiglia_id'] ?? 0);
    if ($fam_id) {
        $del = $db->prepare("DELETE FROM famiglie WHERE id = ?");
        $del->execute([$fam_id]);
        $messaggi[] = 'Famiglia e relativi invitati eliminati.';
    }
}

// ---- Carica famiglie con i loro invitati ----
$famiglie = $db->query("
    SELECT f.id, f.nome_famiglia, f.token, f.telefono, f.lato,
           COUNT(i.id) AS num_invitati
    FROM famiglie f
    LEFT JOIN invitati i ON i.famiglia_id = f.id
    GROUP BY f.id, f.nome_famiglia, f.token, f.telefono, f.lato
    ORDER BY f.nome_famiglia ASC
")->fetchAll();

$invitati_per_famiglia = [];
$tutti_invitati = $db->query("SELECT id, famiglia_id, nome, cognome FROM invitati ORDER BY id ASC")->fetchAll();
foreach ($tutti_invitati as $inv) {
    $invitati_per_famiglia[$inv['famiglia_id']][] = $inv;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inserimento RSVP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .insert-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .insert-section h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem;
            margin: 0 0 1rem;
            color: #3d3d3d;
            border-bottom: 1px solid #f0ece6;
            padding-bottom: .6rem;
        }
        .form-row {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: .3rem;
            flex: 1;
            min-width: 160px;
        }
        .form-group label {
            font-size: .8rem;
            color: #888;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .form-group input,
        .form-group select {
            padding: .6rem .9rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: .95rem;
            font-family: inherit;
            background: #fafafa;
            transition: border-color .15s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #8b7355;
            background: #fff;
        }
        .btn-submit {
            padding: .65rem 1.3rem;
            background: #8b7355;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .9rem;
            cursor: pointer;
            white-space: nowrap;
            font-family: inherit;
            transition: background .15s;
        }
        .btn-submit:hover { background: #7a6448; }
        .btn-danger {
            padding: .4rem .8rem;
            background: #fff;
            color: #c0392b;
            border: 1px solid #e8a0a0;
            border-radius: 6px;
            font-size: .8rem;
            cursor: pointer;
            font-family: inherit;
            transition: background .15s;
        }
        .btn-danger:hover { background: #fdf0f0; }
        .msg-ok {
            background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 8px;
            padding: .7rem 1rem; margin-bottom: 1rem; color: #2e7d32; font-size: .9rem;
        }
        .msg-err {
            background: #fdecea; border: 1px solid #f5c6c6; border-radius: 8px;
            padding: .7rem 1rem; margin-bottom: 1rem; color: #c0392b; font-size: .9rem;
        }
        .famiglia-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .9rem;
        }
        .famiglia-table th {
            text-align: left;
            padding: .5rem .75rem;
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #999;
            border-bottom: 1px solid #f0ece6;
        }
        .famiglia-table td {
            padding: .55rem .75rem;
            vertical-align: top;
            border-bottom: 1px solid #f8f5f1;
        }
        .famiglia-table tr:last-child td { border-bottom: none; }
        .invitati-list { margin: .25rem 0 0; padding: 0; list-style: none; }
        .invitati-list li {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .2rem 0;
            font-size: .875rem;
            color: #555;
        }
        .token-code {
            font-family: monospace;
            font-size: .8rem;
            color: #8b7355;
            background: #fdf8f2;
            padding: .15rem .4rem;
            border-radius: 4px;
        }
        .section-nav {
            display: flex;
            gap: .75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .nav-link {
            padding: .45rem .9rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            text-decoration: none;
            color: #555;
            font-size: .875rem;
            transition: background .15s;
        }
        .nav-link:hover { background: #f5f0ea; }
        .empty-famiglie { color: #aaa; font-size: .9rem; padding: .5rem 0; }
    </style>
</head>
<body>
<div class="dashboard">

    <div class="dash-header">
        <h1>Inserimento Invitati</h1>
        <div class="subtitle"><?php echo TITOLO; ?></div>
        <a href="?logout=1" class="logout-btn" style="position:absolute;top:1.25rem;right:1.5rem;font-size:.85rem;color:#888;text-decoration:none;padding:.4rem .8rem;border:1px solid #ddd;border-radius:6px;">Esci</a>
    </div>

    <div class="section-nav">
        <a href="admin.php" class="nav-link">← Dashboard RSVP</a>
    </div>

    <?php foreach ($messaggi as $msg): ?>
        <div class="msg-ok">✓ <?= $msg ?></div>
    <?php endforeach; ?>
    <?php foreach ($errori as $err): ?>
        <div class="msg-err">✗ <?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <!-- Form: nuova famiglia -->
    <div class="insert-section">
        <h2>Aggiungi famiglia</h2>
        <form method="POST">
            <input type="hidden" name="action" value="aggiungi_famiglia">
            <div class="form-row">
                <div class="form-group">
                    <label>Nome famiglia *</label>
                    <input type="text" name="nome_famiglia" placeholder="es. Famiglia Rossi" required>
                </div>
                <div class="form-group">
                    <label>Telefono (WhatsApp)</label>
                    <input type="text" name="telefono" placeholder="es. 3331234567">
                </div>
                <div class="form-group" style="max-width:160px;">
                    <label>Lato</label>
                    <select name="lato">
                        <option value="">— nessuno —</option>
                        <option value="sposo">🤵 sposo</option>
                        <option value="sposa">👰 sposa</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit">+ Aggiungi famiglia</button>
            </div>
        </form>
    </div>

    <!-- Form: nuovo invitato -->
    <div class="insert-section">
        <h2>Aggiungi invitato</h2>
        <?php if (empty($famiglie)): ?>
            <p class="empty-famiglie">Nessuna famiglia presente. Aggiungi prima una famiglia.</p>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="aggiungi_invitato">
            <div class="form-row">
                <div class="form-group">
                    <label>Famiglia *</label>
                    <select name="famiglia_id" required>
                        <option value="">— Seleziona —</option>
                        <?php foreach ($famiglie as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nome_famiglia']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" placeholder="Nome" required>
                </div>
                <div class="form-group">
                    <label>Cognome</label>
                    <input type="text" name="cognome" placeholder="Cognome">
                </div>
                <button type="submit" class="btn-submit">+ Aggiungi invitato</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Lista famiglie e invitati -->
    <div class="insert-section">
        <h2>Famiglie e invitati (<?= count($famiglie) ?>)</h2>
        <?php if (empty($famiglie)): ?>
            <p class="empty-famiglie">Nessuna famiglia inserita.</p>
        <?php else: ?>
        <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
        <table class="famiglia-table" style="min-width:600px;">
            <thead>
                <tr>
                    <th>Famiglia</th>
                    <th>Telefono</th>
                    <th>Lato</th>
                    <th>Token</th>
                    <th>Invitati</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($famiglie as $f): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['nome_famiglia']) ?></strong></td>
                    <td><?= htmlspecialchars($f['telefono'] ?? '—') ?></td>
                    <td>
                        <?php if ($f['lato'] === 'sposo'): ?>
                            <span style="font-size:.8rem;background:#e3f2fd;color:#1565c0;border-radius:4px;padding:.15rem .45rem;">🤵</span>
                        <?php elseif ($f['lato'] === 'sposa'): ?>
                            <span style="font-size:.8rem;background:#fce4ec;color:#880e4f;border-radius:4px;padding:.15rem .45rem;">👰</span>
                        <?php else: ?>
                            <span style="color:#bbb;font-size:.85rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="token-code"><?= htmlspecialchars($f['token']) ?></span></td>
                    <td>
                        <?php $membri = $invitati_per_famiglia[$f['id']] ?? []; ?>
                        <?php if (empty($membri)): ?>
                            <em style="color:#bbb;font-size:.85rem;">nessuno</em>
                        <?php else: ?>
                            <ul class="invitati-list">
                            <?php foreach ($membri as $inv): ?>
                                <li>
                                    <?= htmlspecialchars($inv['nome'] . ' ' . $inv['cognome']) ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Eliminare questo invitato?')">
                                        <input type="hidden" name="action" value="elimina_invitato">
                                        <input type="hidden" name="invitato_id" value="<?= $inv['id'] ?>">
                                        <button type="submit" class="btn-danger">✕</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Eliminare la famiglia e tutti i suoi invitati?')">
                            <input type="hidden" name="action" value="elimina_famiglia">
                            <input type="hidden" name="famiglia_id" value="<?= $f['id'] ?>">
                            <button type="submit" class="btn-danger">Elimina famiglia</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div><!-- /overflow wrapper -->
        <?php endif; ?>
    </div>

</div>
</body>
</html>
