-- ============================================
-- RSVP System - Schema Database
-- Matrimonio Francesco & Serena - 27/09/2026
-- ============================================

-- Tabella famiglie (ogni famiglia ha un token QR unico)
CREATE TABLE IF NOT EXISTS famiglie (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_famiglia VARCHAR(100) NOT NULL COMMENT 'Es: Famiglia Rossi',
    token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Token univoco per QR code',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella invitati (ogni membro della famiglia)
CREATE TABLE IF NOT EXISTS invitati (
    id INT AUTO_INCREMENT PRIMARY KEY,
    famiglia_id INT NOT NULL,
    nome VARCHAR(50) NOT NULL,
    cognome VARCHAR(50) NOT NULL,
    confermato TINYINT(1) DEFAULT NULL COMMENT 'NULL=non risposto, 1=conferma, 0=declina',
    note TEXT DEFAULT NULL COMMENT 'Allergie, intolleranze, note varie',
    risposto_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Data/ora della risposta',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (famiglia_id) REFERENCES famiglie(id) ON DELETE CASCADE,
    INDEX idx_famiglia (famiglia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ESEMPIO: Inserimento famiglia di test
-- ============================================
-- INSERT INTO famiglie (nome_famiglia, token) VALUES ('Famiglia Rossi', 'abc123def456');
-- INSERT INTO invitati (famiglia_id, nome, cognome) VALUES (1, 'Marco', 'Rossi');
-- INSERT INTO invitati (famiglia_id, nome, cognome) VALUES (1, 'Laura', 'Rossi');
