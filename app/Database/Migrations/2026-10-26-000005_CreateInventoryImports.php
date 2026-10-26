<?php
declare(strict_types=1);
namespace App\Database\Migrations;
use App\Database\MySqlMigration;

final class CreateInventoryImports extends MySqlMigration
{
    public function up(): void
    {
        $this->executeSql(<<<'SQL'
ALTER TABLE {{lots}}
ADD import_review_required BOOLEAN NOT NULL DEFAULT FALSE,
ADD legacy_status VARCHAR(40) NULL,
ADD CONSTRAINT chk_import_review CHECK(import_review_required IN (0,1))
SQL);
        $this->executeSql(<<<'SQL'
CREATE TABLE {{inventory_import_batches}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
project_id BIGINT UNSIGNED NOT NULL,
created_by INT UNSIGNED NOT NULL,
file_name VARCHAR(180) NOT NULL,
file_sha256 CHAR(64) NOT NULL,
status VARCHAR(20) NOT NULL DEFAULT 'preview',
row_count INT UNSIGNED NOT NULL,
imported_count INT UNSIGNED NOT NULL DEFAULT 0,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
committed_at DATETIME NULL,
CONSTRAINT chk_import_batch_status CHECK(status IN ('preview','committed')),
CONSTRAINT fk_import_project FOREIGN KEY(project_id) REFERENCES {{projects}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_import_actor FOREIGN KEY(created_by) REFERENCES {{users}}(id) ON DELETE RESTRICT,
INDEX idx_import_owner(created_by,id),
INDEX idx_import_file(project_id,file_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeSql(<<<'SQL'
CREATE TABLE {{inventory_import_rows}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
batch_id BIGINT UNSIGNED NOT NULL,
source_row INT UNSIGNED NOT NULL,
source_key VARCHAR(80) NULL,
polygon_code VARCHAR(30) NULL,
lot_number VARCHAR(30) NULL,
legacy_status VARCHAR(40) NOT NULL,
review_required BOOLEAN NOT NULL DEFAULT TRUE,
issues JSON NOT NULL,
disposition VARCHAR(20) NOT NULL,
lot_id BIGINT UNSIGNED NULL,
CONSTRAINT chk_import_disposition CHECK(disposition IN ('ready','existing','invalid','imported','omitted')),
CONSTRAINT chk_row_review CHECK(review_required IN (0,1)),
CONSTRAINT fk_import_row_batch FOREIGN KEY(batch_id) REFERENCES {{inventory_import_batches}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_import_row_lot FOREIGN KEY(lot_id) REFERENCES {{lots}}(id) ON DELETE RESTRICT,
UNIQUE KEY uq_import_source_row(batch_id,source_row)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->executeSql('DROP TABLE {{inventory_import_rows}}');
        $this->executeSql('DROP TABLE {{inventory_import_batches}}');
        $this->executeSql('ALTER TABLE {{lots}} DROP CHECK chk_import_review, DROP COLUMN legacy_status, DROP COLUMN import_review_required');
    }
}
