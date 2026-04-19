<?php
// ============================================
// RSVP Action — endpoint AJAX
// POST rsvp_action.php?token=XXXX
// Risponde con JSON { ok, invitati|error }
// ============================================

header('Content-Type: application/json');
require_once 'config.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    echo json_encode(['ok' => false, 'error' => 'Link non valido.']);
    exit;
}

$db = getDB();
$stmt = $db->prepare('
    SELECT f.id AS famiglia_id, i.id AS invitato_id, i.risposto_at
    FROM famiglie f
    JOIN invitati i ON i.famiglia_id = f.id
    WHERE f.token = :token
    ORDER BY i.id ASC
');
$stmt->execute(['token' => $token]);
$rows = $stmt->fetchAll();

if (empty($rows)) {
    echo json_encode(['ok' => false, 'error' => 'Invito non trovato.']);
    exit;
}

foreach ($rows as $r) {
    if ($r['risposto_at'] !== null) {
        echo json_encode(['ok' => false, 'error' => 'Avete già risposto.']);
        exit;
    }
}

try {
    $db->beginTransaction();
    $update = $db->prepare('
        UPDATE invitati
        SET confermato = :confermato, note = :note, risposto_at = NOW()
        WHERE id = :id AND famiglia_id = :famiglia_id
    ');

    foreach ($rows as $r) {
        $id        = $r['invitato_id'];
        $confermato = isset($_POST['conferma'][$id]) ? (int) $_POST['conferma'][$id] : 0;
        $note      = trim($_POST['note'][$id] ?? '');
        $update->execute([
            'confermato'  => $confermato,
            'note'        => $note ?: null,
            'id'          => $id,
            'famiglia_id' => $rows[0]['famiglia_id'],
        ]);
    }

    $db->commit();

    // Restituisce i dati aggiornati per aggiornare la UI
    $stmt2 = $db->prepare('
        SELECT i.id, i.nome, i.cognome, i.confermato, i.note
        FROM famiglie f
        JOIN invitati i ON i.famiglia_id = f.id
        WHERE f.token = :token
        ORDER BY i.id ASC
    ');
    $stmt2->execute(['token' => $token]);
    $invitati = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'invitati' => $invitati]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['ok' => false, 'error' => 'Errore del server. Riprova più tardi.']);
}
