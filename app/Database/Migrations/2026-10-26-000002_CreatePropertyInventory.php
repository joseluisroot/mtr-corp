<?php
declare(strict_types=1);
namespace App\Database\Migrations;
use App\Database\MySqlMigration;
final class CreatePropertyInventory extends MySqlMigration
{
    public function up(): void
    {
        $this->executeSql(<<<'SQL'
CREATE TABLE {{projects}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
code VARCHAR(30) NOT NULL UNIQUE,
name VARCHAR(160) NOT NULL,
currency CHAR(3) NOT NULL DEFAULT 'USD',
is_active BOOLEAN NOT NULL DEFAULT TRUE,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT chk_project_active CHECK(is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeSql(<<<'SQL'
CREATE TABLE {{polygons}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
project_id BIGINT UNSIGNED NOT NULL,
code VARCHAR(30) NOT NULL,
name VARCHAR(120) NOT NULL,
sales_policy VARCHAR(20) NOT NULL DEFAULT 'blocked',
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
UNIQUE KEY uq_polygon_code(project_id,code),
CONSTRAINT fk_polygon_project FOREIGN KEY(project_id) REFERENCES {{projects}}(id) ON DELETE RESTRICT,
CONSTRAINT chk_polygon_policy CHECK(sales_policy IN ('blocked','partial','enabled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeSql(<<<'SQL'
CREATE TABLE {{lot_types}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
code VARCHAR(30) NOT NULL UNIQUE,
name VARCHAR(120) NOT NULL,
front_m DECIMAL(10,3) NULL,
depth_m DECIMAL(10,3) NULL,
area_m2 DECIMAL(12,3) NULL,
is_active BOOLEAN NOT NULL DEFAULT TRUE,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT chk_type_dimensions CHECK((front_m IS NULL OR front_m>0) AND (depth_m IS NULL OR depth_m>0) AND (area_m2 IS NULL OR area_m2>0)),
CONSTRAINT chk_type_active CHECK(is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeSql(<<<'SQL'
CREATE TABLE {{house_models}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
code VARCHAR(30) NOT NULL UNIQUE,
name VARCHAR(120) NOT NULL,
description TEXT NULL,
is_active BOOLEAN NOT NULL DEFAULT TRUE,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT chk_model_active CHECK(is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeSql(<<<'SQL'
CREATE TABLE {{lots}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
polygon_id BIGINT UNSIGNED NOT NULL,
lot_type_id BIGINT UNSIGNED NULL,
number VARCHAR(30) NOT NULL,
legacy_code VARCHAR(80) NULL,
front_m DECIMAL(10,3) NULL,
depth_m DECIMAL(10,3) NULL,
area_m2 DECIMAL(12,3) NULL,
is_enabled BOOLEAN NOT NULL DEFAULT FALSE,
commercial_status VARCHAR(20) NOT NULL DEFAULT 'available',
lock_version INT UNSIGNED NOT NULL DEFAULT 0,
notes TEXT NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
UNIQUE KEY uq_lot_number(polygon_id,number),
CONSTRAINT fk_lot_polygon FOREIGN KEY(polygon_id) REFERENCES {{polygons}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_lot_type FOREIGN KEY(lot_type_id) REFERENCES {{lot_types}}(id) ON DELETE RESTRICT,
CONSTRAINT chk_lot_dimensions CHECK((front_m IS NULL OR front_m>0) AND (depth_m IS NULL OR depth_m>0) AND (area_m2 IS NULL OR area_m2>0)),
CONSTRAINT chk_lot_enabled CHECK(is_enabled IN (0,1)),
CONSTRAINT chk_commercial_status CHECK(commercial_status IN ('available','negotiating','reserved','sold')),
INDEX idx_inventory(commercial_status,is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeSql(<<<'SQL'
CREATE TABLE {{lot_house_models}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
lot_id BIGINT UNSIGNED NOT NULL,
house_model_id BIGINT UNSIGNED NOT NULL,
is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
notes VARCHAR(500) NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
UNIQUE KEY uq_lot_model(lot_id,house_model_id),
CONSTRAINT fk_compat_lot FOREIGN KEY(lot_id) REFERENCES {{lots}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_compat_model FOREIGN KEY(house_model_id) REFERENCES {{house_models}}(id) ON DELETE RESTRICT,
CONSTRAINT chk_compat_enabled CHECK(is_enabled IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void
    {
        $this->executeSql('DROP TABLE {{lot_house_models}}');
        $this->executeSql('DROP TABLE {{lots}}');
        $this->executeSql('DROP TABLE {{house_models}}');
        $this->executeSql('DROP TABLE {{lot_types}}');
        $this->executeSql('DROP TABLE {{polygons}}');
        $this->executeSql('DROP TABLE {{projects}}');
    }
}
