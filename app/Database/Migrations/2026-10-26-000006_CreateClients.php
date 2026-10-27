<?php
declare(strict_types=1);
namespace App\Database\Migrations;
use App\Database\MySqlMigration;

final class CreateClients extends MySqlMigration
{
    public function up(): void
    {
        $this->executeSql(<<<'SQL'
CREATE TABLE {{clients}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
code VARCHAR(30) NOT NULL UNIQUE,
full_name VARCHAR(180) NOT NULL,
document_type VARCHAR(20) NULL,
document_country CHAR(2) NULL,
document_number VARCHAR(40) NULL,
document_key VARCHAR(100) NULL UNIQUE,
email VARCHAR(180) NULL,
phone VARCHAR(40) NULL,
address VARCHAR(500) NULL,
notes TEXT NULL,
is_active BOOLEAN NOT NULL DEFAULT TRUE,
lock_version INT UNSIGNED NOT NULL DEFAULT 0,
created_by INT UNSIGNED NOT NULL,
updated_by INT UNSIGNED NOT NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT chk_client_active CHECK(is_active IN (0,1)),
CONSTRAINT chk_client_document CHECK(document_type IS NULL OR document_type IN ('dui','passport','nit','other')),
CONSTRAINT fk_client_creator FOREIGN KEY(created_by) REFERENCES {{users}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_client_editor FOREIGN KEY(updated_by) REFERENCES {{users}}(id) ON DELETE RESTRICT,
INDEX idx_client_name(full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeSql(<<<'SQL'
CREATE TABLE {{client_property_links}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
client_id BIGINT UNSIGNED NOT NULL,
lot_id BIGINT UNSIGNED NOT NULL,
is_active BOOLEAN NOT NULL DEFAULT TRUE,
active_lot_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN is_active=1 THEN lot_id ELSE NULL END) STORED,
note VARCHAR(500) NOT NULL,
linked_by INT UNSIGNED NOT NULL,
linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
ended_by INT UNSIGNED NULL,
ended_at DATETIME NULL,
end_reason VARCHAR(500) NULL,
CONSTRAINT chk_client_link_active CHECK(is_active IN (0,1)),
CONSTRAINT fk_link_client FOREIGN KEY(client_id) REFERENCES {{clients}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_link_lot FOREIGN KEY(lot_id) REFERENCES {{lots}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_link_actor FOREIGN KEY(linked_by) REFERENCES {{users}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_link_end_actor FOREIGN KEY(ended_by) REFERENCES {{users}}(id) ON DELETE RESTRICT,
UNIQUE KEY uq_active_property_client(active_lot_id),
INDEX idx_client_properties(client_id,is_active,lot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void
    {
        $this->executeSql('DROP TABLE {{client_property_links}}');
        $this->executeSql('DROP TABLE {{clients}}');
    }
}
