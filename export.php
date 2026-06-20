<?php
require_once 'config.php';

session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit('Accesso negato.');
}

$db = getDB();

$rows = $db->query("
    SELECT
        f.lato,
        f.nome_famiglia,
        f.telefono,
        i.nome,
        i.cognome,
        i.note,
        i.risposto_at
    FROM invitati i
    JOIN famiglie f ON f.id = i.famiglia_id
    WHERE i.confermato = 1
    ORDER BY
        FIELD(f.lato, 'sposo', 'sposa', NULL),
        f.nome_famiglia ASC,
        i.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$filename = 'confermati_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// BOM UTF-8 per Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Lato', 'Famiglia', 'Telefono', 'Nome', 'Cognome', 'Note / Allergie', 'Data conferma'], ';');

$latoLabel = ['sposo' => 'Sposo', 'sposa' => 'Sposa'];

foreach ($rows as $r) {
    fputcsv($out, [
        $latoLabel[$r['lato']] ?? '',
        $r['nome_famiglia'],
        $r['telefono'],
        $r['nome'],
        $r['cognome'],
        $r['note'] ?? '',
        $r['risposto_at'] ? date('d/m/Y H:i', strtotime($r['risposto_at'])) : '',
    ], ';');
}

fclose($out);
exit;
