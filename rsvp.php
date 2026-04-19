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
                $messaggio = 'Grazie! La risposta è stata registrata.';
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
<div>

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
                <div class="invitato-nome"><?= htmlspecialchars($r['nome'] . ' ' . $r['cognome']) ?></div>
            <div class="invitato-card">
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" name="conferma[<?= $r['invitato_id'] ?>]" 
                               id="si_<?= $r['invitato_id'] ?>" value="1" required>
                        <label for="si_<?= $r['invitato_id'] ?>" class="conferma"><?= $si_radio ?> 💜</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" name="conferma[<?= $r['invitato_id'] ?>]" 
                               id="no_<?= $r['invitato_id'] ?>" value="0">
                        <label for="no_<?= $r['invitato_id'] ?>" class="declina"><?= $no_radio ?> 😢</label>
                    </div>
                </div>

                <div class="note-field">
                    <div class="note-label">indica note, allergie o esigenze alimentari</div>
                    <textarea class="textarea-note" placeholder="" name="note[<?= $r['invitato_id'] ?>]" 
                              placeholder="Es: intolleranza al glutine, allergia alle noci..."
                              maxlength="500"></textarea>
                </div>
            </div>
            <?php endforeach; ?>

            <button type="submit" class="rsvp-btn reveal">
                Conferma la risposta
            </button>
        </form>
    <?php endif; ?>
</div>
