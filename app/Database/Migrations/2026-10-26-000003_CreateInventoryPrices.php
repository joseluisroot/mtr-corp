<?php
declare(strict_types=1);
namespace App\Database\Migrations;
use App\Database\MySqlMigration;
final class CreateInventoryPrices extends MySqlMigration
{
    public function up(): void
    {
        $this->executeSql(<<<'SQL'
CREATE TABLE {{lot_prices}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
lot_id BIGINT UNSIGNED NOT NULL,
amount DECIMAL(14,2) NOT NULL,
valid_from DATETIME NOT NULL,
valid_until DATETIME NULL,
created_by INT UNSIGNED NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_price_lot FOREIGN KEY(lot_id) REFERENCES {{lots}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_price_user FOREIGN KEY(created_by) REFERENCES {{users}}(id) ON DELETE RESTRICT,
CONSTRAINT chk_lot_price CHECK(amount >= 0),
CONSTRAINT chk_lot_price_period CHECK(valid_until IS NULL OR valid_until > valid_from),
UNIQUE KEY uq_lot_price_start(lot_id,valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeSql(<<<'SQL'
CREATE TABLE {{lot_house_prices}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
lot_house_model_id BIGINT UNSIGNED NOT NULL,
land_amount DECIMAL(14,2) NOT NULL,
construction_amount DECIMAL(14,2) NOT NULL,
total_amount DECIMAL(14,2) GENERATED ALWAYS AS (land_amount + construction_amount) STORED,
valid_from DATETIME NOT NULL,
valid_until DATETIME NULL,
created_by INT UNSIGNED NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_package_compat FOREIGN KEY(lot_house_model_id) REFERENCES {{lot_house_models}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_package_user FOREIGN KEY(created_by) REFERENCES {{users}}(id) ON DELETE RESTRICT,
CONSTRAINT chk_package_amounts CHECK(land_amount >= 0 AND construction_amount >= 0),
CONSTRAINT chk_package_period CHECK(valid_until IS NULL OR valid_until > valid_from),
UNIQUE KEY uq_package_start(lot_house_model_id,valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void
    {
        $this->executeSql('DROP TABLE {{lot_house_prices}}');
        $this->executeSql('DROP TABLE {{lot_prices}}');
    }
}
