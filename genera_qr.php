<?php
// ============================================
// Generatore QR Code per inviti
// Uso: php genera_qr.php
// Richiede: composer require chillerlan/php-qrcode
// ============================================

require_once 'config.php';

// ---- CONFIGURAZIONE ----
$base_url    = 'https://tuosito.it/rsvp.php'; // <-- Modifica con il tuo dominio
$output_dir  = __DIR__ . '/qrcodes';
$wa_testo    = "Ciao! Vi invitiamo a confermare la vostra partecipazione al nostro matrimonio tramite questo link personalizzato: ";

if (!is_dir($output_dir)) {
    mkdir($output_dir, 0755, true);
}

$db = getDB();
$famiglie = $db->query('SELECT id, nome_famiglia, token FROM famiglie ORDER BY nome_famiglia')->fetchAll();

if (empty($famiglie)) {
    echo "Nessuna famiglia trovata nel database.\n";
    exit(1);
}

echo "=== Generazione QR Code ===\n\n";

foreach ($famiglie as $fam) {
    $url = $base_url . '?token=' . urlencode($fam['token']);
    
    // Genera con Google Charts API (zero dipendenze)
    $qr_api_url = 'https://chart.googleapis.com/chart?'
        . http_build_query([
            'cht'  => 'qr',
            'chs'  => '400x400',
            'chl'  => $url,
            'choe' => 'UTF-8',
        ]);

    // Oppure usa la libreria chillerlan/php-qrcode se installata:
    // use chillerlan\QRCode\{QRCode, QROptions};
    // $options = new QROptions(['outputType' => QRCode::OUTPUT_IMAGE_PNG, 'scale' => 10]);
    // (new QRCode($options))->render($url, $output_dir . '/' . $filename);
    
    $filename = preg_replace('/[^a-z0-9_-]/i', '_', $fam['nome_famiglia']) . '.png';
    $filepath = $output_dir . '/' . $filename;
    
    // Link WhatsApp
    $wa_messaggio = $wa_testo . $url;
    $wa_link = 'https://wa.me/?text=' . rawurlencode($wa_messaggio);

    $img = @file_get_contents($qr_api_url);
    if ($img !== false) {
        file_put_contents($filepath, $img);
        echo "✓ {$fam['nome_famiglia']} => {$filename}\n";
    } else {
        echo "✗ {$fam['nome_famiglia']} (QR non scaricato, genera manualmente)\n";
    }
    echo "  RSVP URL:   {$url}\n";
    echo "  WhatsApp:   {$wa_link}\n\n";
}

echo "=== Completato! QR salvati in: {$output_dir} ===\n";
