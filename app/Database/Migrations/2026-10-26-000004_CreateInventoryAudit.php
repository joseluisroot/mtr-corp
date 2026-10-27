<?php
declare(strict_types=1);
namespace App\Database\Migrations;
use App\Database\MySqlMigration;

final class CreateInventoryAudit extends MySqlMigration
{
    public function up(): void
    {
        $this->executeSql(<<<'SQL'
CREATE TABLE {{inventory_audit}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
actor_id INT UNSIGNED NOT NULL,
entity_type VARCHAR(40) NOT NULL,
entity_id VARCHAR(80) NOT NULL,
action VARCHAR(40) NOT NULL,
before_data JSON NULL,
after_data JSON NOT NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_inventory_audit_actor FOREIGN KEY(actor_id) REFERENCES {{users}}(id) ON DELETE RESTRICT,
INDEX idx_inventory_audit_entity(entity_type,entity_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->executeSql('DROP TABLE {{inventory_audit}}');
    }
}
