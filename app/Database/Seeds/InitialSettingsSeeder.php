<?php
declare(strict_types=1);
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;

/** Does not overwrite an administrator's existing values. */
final class InitialSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->db->transException(true)->transStart();
        foreach (['negotiation_days' => [15, 'Duración de negociación en días'], 'payment_grace_days' => [10, 'Gracia de cuotas en días']] as $key => [$days, $label]) {
            $table = $this->db->table('business_settings');
            if ($table->where('setting_key', $key)->countAllResults() > 0) {
                continue;
            }
            $table->insert(['setting_key' => $key, 'value_days' => $days, 'description' => $label]);
            $this->db->table('business_setting_history')->insert([
                'setting_key' => $key, 'new_value_days' => $days,
                'effective_at' => date('Y-m-d H:i:s'), 'reason' => 'Configuración inicial aprobada',
            ]);
        }
        $this->db->transComplete();
    }
}
