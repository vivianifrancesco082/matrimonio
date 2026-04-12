<?php
// ============================================
// RSVP - Pagina invitato (scansione QR)
// URL: rsvp.php?token=XXXX
// ============================================

require_once 'config.php';

$token = $_GET['token'] ?? '';
$messaggio = '';
$errore = '';

if (empty($token)) {
    $errore = 'Link non valido. Verifica il tuo invito.';
} else {
    $db = getDB();

    // Recupera famiglia e invitati
    $stmt = $db->prepare('
        SELECT f.id AS famiglia_id, f.nome_famiglia, 
               i.id AS invitato_id, i.nome, i.cognome, 
               i.confermato, i.note, i.risposto_at
        FROM famiglie f
        JOIN invitati i ON i.famiglia_id = f.id
        WHERE f.token = :token
        ORDER BY i.id ASC
    ');
    $stmt->execute(['token' => $token]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        $errore = 'Invito non trovato. Verifica il link ricevuto.';
    } else {
        $nome_famiglia = $rows[0]['nome_famiglia'];
        
        // Controlla se qualcuno ha già risposto (form bloccato)
        $gia_risposto = false;
        foreach ($rows as $r) {
            if ($r['risposto_at'] !== null) {
                $gia_risposto = true;
                break;
            }
        }

        // Gestione POST (salvataggio risposte)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$gia_risposto) {
            try {
                $db->beginTransaction();
                $update = $db->prepare('
                    UPDATE invitati 
                    SET confermato = :confermato, note = :note, risposto_at = NOW()
                    WHERE id = :id AND famiglia_id = :famiglia_id
                ');

                foreach ($rows as $r) {
                    $id = $r['invitato_id'];
                    $confermato = isset($_POST['conferma'][$id]) ? (int) $_POST['conferma'][$id] : 0;
                    $note = trim($_POST['note'][$id] ?? '');

                    $update->execute([
                        'confermato'  => $confermato,
                        'note'        => $note ?: null,
                        'id'          => $id,
                        'famiglia_id' => $rows[0]['famiglia_id'],
                    ]);
                }

                $db->commit();
                $messaggio = 'Grazie! La vostra risposta è stata registrata.';
                $gia_risposto = true;

                // Ricarica i dati aggiornati
                $stmt->execute(['token' => $token]);
                $rows = $stmt->fetchAll();
            } catch (Exception $e) {
                $db->rollBack();
                $errore = 'Si è verificato un errore. Riprova più tardi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSVP - Francesco &amp; Serena</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Great+Vibes&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-bg: #FDF8F4;
            --color-cream: #F5EDE4;
            --color-mauve: #B8909A;
            --color-mauve-dark: #8C6B73;
            --color-gold: #C4A265;
            --color-gold-light: #D4B87A;
            --color-text: #3D2E33;
            --color-text-light: #6B5A60;
            --color-white: #FFFFFF;
            --color-success: #7A9E7E;
            --color-decline: #C4837A;
            --font-display: 'Great Vibes', cursive;
            --font-body: 'Cormorant Garamond', Georgia, serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-body);
            background: var(--color-bg);
            color: var(--color-text);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 2rem 1rem;
        }

        .container {
            width: 100%;
            max-width: 520px;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .header h1 {
            font-family: var(--font-display);
            font-size: 2.8rem;
            font-weight: 400;
            color: var(--color-mauve-dark);
            line-height: 1.2;
        }

        .header .data {
            font-size: 1.1rem;
            color: var(--color-gold);
            margin-top: 0.5rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            font-weight: 600;
        }

        .header .famiglia {
            font-size: 1.3rem;
            color: var(--color-text-light);
            margin-top: 1.2rem;
            font-style: italic;
        }

        .divider {
            width: 60px;
            height: 1px;
            background: var(--color-gold);
            margin: 1.5rem auto;
        }

        /* Cards invitato */
        .invitato-card {
            background: var(--color-white);
            border: 1px solid var(--color-cream);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 2px 12px rgba(60, 46, 51, 0.06);
        }

        .invitato-nome {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--color-mauve-dark);
            margin-bottom: 1rem;
        }

        /* Radio buttons */
        .radio-group {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }

        .radio-option {
            flex: 1;
        }

        .radio-option input[type="radio"] {
            display: none;
        }

        .radio-option label {
            display: block;
            text-align: center;
            padding: 0.7rem 1rem;
            border: 2px solid var(--color-cream);
            border-radius: 8px;
            cursor: pointer;
            font-family: var(--font-body);
            font-size: 1.05rem;
            font-weight: 600;
            transition: all 0.25s ease;
            color: var(--color-text-light);
        }

        .radio-option input[type="radio"]:checked + label.conferma {
            border-color: var(--color-success);
            background: rgba(122, 158, 126, 0.1);
            color: var(--color-success);
        }

        .radio-option input[type="radio"]:checked + label.declina {
            border-color: var(--color-decline);
            background: rgba(196, 131, 122, 0.1);
            color: var(--color-decline);
        }

        .radio-option label:hover {
            border-color: var(--color-mauve);
        }

        /* Textarea */
        .note-field textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--color-cream);
            border-radius: 8px;
            font-family: var(--font-body);
            font-size: 1rem;
            color: var(--color-text);
            background: var(--color-bg);
            resize: vertical;
            min-height: 70px;
            transition: border-color 0.2s;
        }

        .note-field textarea:focus {
            outline: none;
            border-color: var(--color-mauve);
        }

        .note-field textarea::placeholder {
            color: var(--color-text-light);
            opacity: 0.6;
        }

        .note-label {
            font-size: 0.9rem;
            color: var(--color-text-light);
            margin-bottom: 0.4rem;
        }

        /* Submit */
        .submit-btn {
            display: block;
            width: 100%;
            padding: 1rem;
            margin-top: 1.5rem;
            background: var(--color-gold);
            color: var(--color-white);
            border: none;
            border-radius: 10px;
            font-family: var(--font-body);
            font-size: 1.15rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: background 0.25s, transform 0.15s;
        }

        .submit-btn:hover {
            background: var(--color-gold-light);
            transform: translateY(-1px);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        /* Messaggi */
        .messaggio {
            text-align: center;
            padding: 1.2rem;
            border-radius: 10px;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .messaggio.successo {
            background: rgba(122, 158, 126, 0.12);
            color: var(--color-success);
            border: 1px solid rgba(122, 158, 126, 0.25);
        }

        .messaggio.errore {
            background: rgba(196, 131, 122, 0.12);
            color: var(--color-decline);
            border: 1px solid rgba(196, 131, 122, 0.25);
        }

        /* Stato bloccato */
        .stato-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }

        .stato-badge.confermato {
            background: rgba(122, 158, 126, 0.15);
            color: var(--color-success);
        }

        .stato-badge.declinato {
            background: rgba(196, 131, 122, 0.15);
            color: var(--color-decline);
        }

        .note-readonly {
            font-style: italic;
            color: var(--color-text-light);
            font-size: 0.95rem;
            margin-top: 0.5rem;
        }

        .lock-notice {
            text-align: center;
            color: var(--color-text-light);
            font-size: 0.9rem;
            margin-top: 1rem;
            font-style: italic;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 2.5rem;
            color: var(--color-text-light);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <h1>Francesco &amp; Serena</h1>
        <div class="data">27 Settembre 2026</div>
        <?php if (!empty($nome_famiglia)): ?>
            <div class="divider"></div>
            <div class="famiglia"><?= htmlspecialchars($nome_famiglia) ?></div>
        <?php endif; ?>
    </div>

    <?php if ($errore): ?>
        <div class="messaggio errore"><?= htmlspecialchars($errore) ?></div>

    <?php elseif ($messaggio): ?>
        <div class="messaggio successo"><?= htmlspecialchars($messaggio) ?></div>

        <?php foreach ($rows as $r): ?>
        <div class="invitato-card">
            <div class="invitato-nome"><?= htmlspecialchars($r['nome'] . ' ' . $r['cognome']) ?></div>
            <span class="stato-badge <?= $r['confermato'] ? 'confermato' : 'declinato' ?>">
                <?= $r['confermato'] ? '✓ Confermato' : '✗ Non parteciperà' ?>
            </span>
            <?php if (!empty($r['note'])): ?>
                <div class="note-readonly"><?= htmlspecialchars($r['note']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="lock-notice">La risposta è stata registrata e non può essere modificata.</div>

    <?php elseif ($gia_risposto): ?>
        <div class="messaggio successo">Avete già confermato la vostra partecipazione.</div>

        <?php foreach ($rows as $r): ?>
        <div class="invitato-card">
            <div class="invitato-nome"><?= htmlspecialchars($r['nome'] . ' ' . $r['cognome']) ?></div>
            <span class="stato-badge <?= $r['confermato'] ? 'confermato' : 'declinato' ?>">
                <?= $r['confermato'] ? '✓ Confermato' : '✗ Non parteciperà' ?>
            </span>
            <?php if (!empty($r['note'])): ?>
                <div class="note-readonly"><?= htmlspecialchars($r['note']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="lock-notice">La risposta è stata registrata e non può essere modificata.</div>

    <?php else: ?>
        <form method="POST" id="rsvpForm">
            <?php foreach ($rows as $r): ?>
            <div class="invitato-card">
                <div class="invitato-nome"><?= htmlspecialchars($r['nome'] . ' ' . $r['cognome']) ?></div>

                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" name="conferma[<?= $r['invitato_id'] ?>]" 
                               id="si_<?= $r['invitato_id'] ?>" value="1" required>
                        <label for="si_<?= $r['invitato_id'] ?>" class="conferma">Parteciperò ♥</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" name="conferma[<?= $r['invitato_id'] ?>]" 
                               id="no_<?= $r['invitato_id'] ?>" value="0">
                        <label for="no_<?= $r['invitato_id'] ?>" class="declina">Non potrò</label>
                    </div>
                </div>

                <div class="note-field">
                    <div class="note-label">Allergie, intolleranze o note:</div>
                    <textarea name="note[<?= $r['invitato_id'] ?>]" 
                              placeholder="Es: intolleranza al glutine, allergia alle noci..."
                              maxlength="500"></textarea>
                </div>
            </div>
            <?php endforeach; ?>

            <button type="submit" class="submit-btn">Conferma la risposta</button>
        </form>
    <?php endif; ?>

    <div class="footer">
        Vi aspettiamo con gioia!
    </div>

</div>
</body>
</html>
