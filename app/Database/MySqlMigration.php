<?php
declare(strict_types=1);
namespace App\Database;
use CodeIgniter\Database\Migration;

/** MySQL 8.0.16+ is required for enforced CHECK constraints. */
abstract class MySqlMigration extends Migration
{
    protected function executeSql(string $sql): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            throw new \RuntimeException('Estas migraciones requieren MySQL 8.0.16+ con MySQLi.');
        }
        $version = (string) $this->db->query('SELECT VERSION() AS version')->getRow()->version;
        if (stripos($version, 'MariaDB') !== false || version_compare($version, '8.0.16', '<')) {
            throw new \RuntimeException('Se requiere MySQL 8.0.16 o posterior.');
        }
        $sql = preg_replace_callback('/\{\{([a-z_]+)\}\}/', fn ($m) => $this->db->escapeIdentifiers($this->db->prefixTable($m[1])), $sql);
        $this->db->query($sql);
    }
}
