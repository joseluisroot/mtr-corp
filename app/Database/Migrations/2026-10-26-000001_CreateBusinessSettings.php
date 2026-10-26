<?php
declare(strict_types=1);
namespace App\Database\Migrations;
use App\Database\MySqlMigration;
final class CreateBusinessSettings extends MySqlMigration
{
    public function up(): void
    {
        $this->executeSql(<<<'SQL'
CREATE TABLE {{business_settings}} (
setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
value_days SMALLINT UNSIGNED NOT NULL,
description VARCHAR(255) NOT NULL,
updated_by INT UNSIGNED NULL,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT chk_setting_days CHECK(value_days <= 3650),
CONSTRAINT fk_setting_user FOREIGN KEY(updated_by) REFERENCES {{users}}(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeSql(<<<'SQL'
CREATE TABLE {{business_setting_history}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
setting_key VARCHAR(80) NOT NULL,
old_value_days SMALLINT UNSIGNED NULL,
new_value_days SMALLINT UNSIGNED NOT NULL,
effective_at DATETIME NOT NULL,
changed_by INT UNSIGNED NULL,
reason VARCHAR(500) NOT NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT chk_history_days CHECK(new_value_days <= 3650 AND (old_value_days IS NULL OR old_value_days <= 3650)),
CONSTRAINT fk_history_setting FOREIGN KEY(setting_key) REFERENCES {{business_settings}}(setting_key) ON DELETE RESTRICT,
CONSTRAINT fk_history_user FOREIGN KEY(changed_by) REFERENCES {{users}}(id) ON DELETE RESTRICT,
INDEX idx_setting_effective(setting_key,effective_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void
    {
        $this->executeSql('DROP TABLE {{business_setting_history}}');
        $this->executeSql('DROP TABLE {{business_settings}}');
    }
}
