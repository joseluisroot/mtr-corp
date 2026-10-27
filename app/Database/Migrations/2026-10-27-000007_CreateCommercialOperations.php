<?php
declare(strict_types=1);
namespace App\Database\Migrations;
use App\Database\MySqlMigration;

final class CreateCommercialOperations extends MySqlMigration
{
    public function up(): void
    {
        $this->executeSql(<<<'SQL'
CREATE TABLE {{commercial_operations}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
lot_id BIGINT UNSIGNED NOT NULL,
client_id BIGINT UNSIGNED NOT NULL,
seller_id INT UNSIGNED NOT NULL,
state VARCHAR(20) NOT NULL,
origin VARCHAR(20) NOT NULL DEFAULT 'new',
purchase_kind VARCHAR(20) NULL,
house_model_id BIGINT UNSIGNED NULL,
currency CHAR(3) NOT NULL,
land_amount DECIMAL(14,2) NULL,
construction_amount DECIMAL(14,2) NULL,
total_amount DECIMAL(14,2) GENERATED ALWAYS AS (land_amount + construction_amount) STORED,
price_source VARCHAR(20) NULL,
reference_land_amount DECIMAL(14,2) NULL,
reference_construction_amount DECIMAL(14,2) NULL,
price_reason VARCHAR(500) NULL,
operation_date DATE NOT NULL,
reserved_on DATE NULL,
sold_on DATE NULL,
negotiation_days SMALLINT UNSIGNED NULL,
expires_at DATETIME NULL,
lock_version INT UNSIGNED NOT NULL DEFAULT 0,
note VARCHAR(2000) NOT NULL,
created_by INT UNSIGNED NOT NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
active_lot_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN state IN ('negotiating','reserved','sold') THEN lot_id ELSE NULL END) STORED,
interest_lot_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN state='interested' THEN lot_id ELSE NULL END) STORED,
interest_client_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN state='interested' THEN client_id ELSE NULL END) STORED,
CONSTRAINT chk_commercial_state CHECK(state IN ('interested','negotiating','reserved','sold','expired','cancelled')),
CONSTRAINT chk_commercial_origin CHECK(origin IN ('new','historical')),
CONSTRAINT chk_purchase_kind CHECK(purchase_kind IS NULL OR purchase_kind IN ('lot','lot_house')),
CONSTRAINT chk_commercial_amounts CHECK((land_amount IS NULL OR land_amount>=0) AND (construction_amount IS NULL OR construction_amount>=0)),
CONSTRAINT chk_commercial_terms CHECK(state IN ('interested','cancelled','expired') OR (purchase_kind IS NOT NULL AND land_amount IS NOT NULL AND construction_amount IS NOT NULL AND total_amount>0 AND ((purchase_kind='lot' AND house_model_id IS NULL AND construction_amount=0) OR (purchase_kind='lot_house' AND house_model_id IS NOT NULL)))),
CONSTRAINT chk_negotiation_expiry CHECK(state<>'negotiating' OR (expires_at IS NOT NULL AND negotiation_days>0)),
CONSTRAINT fk_operation_lot FOREIGN KEY(lot_id) REFERENCES {{lots}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_operation_client FOREIGN KEY(client_id) REFERENCES {{clients}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_operation_seller FOREIGN KEY(seller_id) REFERENCES {{users}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_operation_model FOREIGN KEY(house_model_id) REFERENCES {{house_models}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_operation_actor FOREIGN KEY(created_by) REFERENCES {{users}}(id) ON DELETE RESTRICT,
UNIQUE KEY uq_exclusive_operation(active_lot_id),
UNIQUE KEY uq_open_interest(interest_lot_id,interest_client_id),
INDEX idx_operation_client(client_id,state),
INDEX idx_operation_expiry(state,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeSql(<<<'SQL'
CREATE TABLE {{commercial_operation_events}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
operation_id BIGINT UNSIGNED NOT NULL,
from_state VARCHAR(20) NULL,
to_state VARCHAR(20) NOT NULL,
actor_id INT UNSIGNED NULL,
reason VARCHAR(2000) NOT NULL,
effective_date DATE NOT NULL,
terms_snapshot JSON NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_operation_event FOREIGN KEY(operation_id) REFERENCES {{commercial_operations}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_operation_event_actor FOREIGN KEY(actor_id) REFERENCES {{users}}(id) ON DELETE RESTRICT,
INDEX idx_operation_event(operation_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeSql(<<<'SQL'
CREATE TABLE {{commercial_operation_documents}} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
operation_id BIGINT UNSIGNED NOT NULL,
storage_key VARCHAR(80) NOT NULL UNIQUE,
original_name VARCHAR(180) NOT NULL,
mime_type VARCHAR(60) NOT NULL,
size_bytes INT UNSIGNED NOT NULL,
sha256 CHAR(64) NOT NULL,
uploaded_by INT UNSIGNED NOT NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_operation_document FOREIGN KEY(operation_id) REFERENCES {{commercial_operations}}(id) ON DELETE RESTRICT,
CONSTRAINT fk_operation_document_actor FOREIGN KEY(uploaded_by) REFERENCES {{users}}(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void
    {
        $this->executeSql('DROP TABLE {{commercial_operation_documents}}');
        $this->executeSql('DROP TABLE {{commercial_operation_events}}');
        $this->executeSql('DROP TABLE {{commercial_operations}}');
    }
}
