# Matrimonio Francesco & Serena — 27 Settembre 2026

Sito web per la gestione degli RSVP del matrimonio. Ogni famiglia riceve un link personalizzato (via QR code o WhatsApp) per confermare la propria partecipazione.

## Stack

- **Frontend:** PHP con HTML/CSS/JS integrato
- **Backend:** PHP 8+
- **Database:** MySQL (schema in `database.sql`)
- **Configurazione:** `config.php`

## Struttura file

| File | Descrizione |
|------|-------------|
| `index.php` | Sito pubblico del matrimonio con il form RSVP integrato |
| `rsvp.php` | Pagina RSVP invocata dal token (inclusa in `index.php`) |
| `rsvp_action.php` | Endpoint AJAX per il salvataggio delle risposte |
| `admin.php` | Dashboard admin: statistiche, filtri, invio WhatsApp |
| `insert.php` | Pannello admin: inserimento/eliminazione di famiglie e invitati |
| `config.php` | Credenziali DB, password admin, URL sito |
| `database.sql` | Schema del database |

## Database

Due tabelle principali:

**`famiglie`** — ogni famiglia ha un token univoco (10 cifre) e un numero di telefono opzionale per WhatsApp.

**`invitati`** — ogni membro della famiglia con stato: `NULL` = in attesa, `1` = confermato, `0` = declinato. Può includere note (allergie, esigenze alimentari).

## Flusso RSVP

1. Dall'admin (`insert.php`) si aggiungono famiglie e relativi invitati.
2. Ogni famiglia riceve un token univoco generato automaticamente.
3. Dalla dashboard (`admin.php`) si copia il link o si invia direttamente via WhatsApp con un messaggio precompilato.
4. L'invitato apre il link (`index.php?token=XXXX`) e vede il form con i membri della famiglia.
5. Ogni membro può confermare o declinare, con campo note opzionale.
6. La risposta viene salvata via AJAX (`rsvp_action.php`) e bloccata: non è modificabile dopo l'invio.

## Admin

Accesso protetto da password (definita in `config.php` → `ADMIN_PASSWORD`).

### Dashboard (`admin.php`)
- Statistiche: totale invitati, confermati, declinati, in attesa
- Filtri: tutti / confermati / declinati / in attesa / con note / inviati / non inviati
- Ricerca per nome o famiglia
- Per ogni famiglia: copia link RSVP, invio WhatsApp con badge data/ora di invio
- Generazione token per famiglie che ne sono prive

### Inserimento (`insert.php`)
- Aggiunta e rimozione di famiglie (con telefono per WhatsApp)
- Aggiunta e rimozione di singoli invitati
- Visualizzazione tabellare di tutte le famiglie con i relativi invitati e token

## Configurazione

Editare `config.php`:

```php
define('DB_HOST', 'db');
define('DB_NAME', 'matrimonio');
define('DB_USER', 'matrimonio_user');
define('DB_PASS', 'password');

define('ADMIN_PASSWORD', '...');
define('SITOWEB', 'https://www.francescoeserena.it/');
define('PREFISSO_INTERNAZIONALE', '39'); // Italia
```

## Setup iniziale

```bash
# 1. Importa lo schema nel database MySQL
mysql -u matrimonio_user -p matrimonio < database.sql

# 2. Configura config.php con i parametri corretti

# 3. Accedi all'admin
# https://tuosito.it/insert.php  → aggiungi famiglie e invitati
# https://tuosito.it/admin.php   → gestisci RSVP e invia inviti
```
