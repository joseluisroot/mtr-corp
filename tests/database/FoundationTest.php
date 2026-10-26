<?php
declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** Run only against an explicitly designated, disposable MySQL database. */
final class FoundationTest extends CIUnitTestCase
{
    public function testMySqlFoundation(): void
    {
        if (getenv('MTR_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Set MTR_MYSQL_TESTS=1 and configure database.tests on a disposable MySQL database.');
        }
        $db = Database::connect('tests');
        $this->assertSame('MySQLi', $db->DBDriver);
        $this->assertStringEndsWith('_test', $db->database, 'Test database name must end with _test.');
        $forge = Database::forge('tests');
        $classes = [];
        foreach (glob(APPPATH . 'Database/Migrations/*.php') as $file) {
            require_once $file;
            $classes[] = 'App\\Database\\Migrations\\' . substr(basename($file, '.php'), 18);
        }
        // Use the official Shield migration on this same connection.
        $shieldFile = glob(ROOTPATH . 'vendor/codeigniter4/shield/src/Database/Migrations/*.php')[0];
        require_once $shieldFile;
        $shield = new \CodeIgniter\Shield\Database\Migrations\CreateAuthTables($forge);
        $shield->up();
        try {
            foreach ($classes as $class) {
                (new $class($forge))->up();
            }
            try {
                $seed = new \App\Database\Seeds\InitialSettingsSeeder(config(Database::class), $db);
                $seed->run();
                $seed->run();
                $this->assertSame(2, $db->table('business_settings')->countAllResults());
                $this->assertSame(2, $db->table('business_setting_history')->countAllResults());
                $this->assertSame('15', (string) $db->table('business_settings')->where('setting_key', 'negotiation_days')->get()->getRow()->value_days);
                $db->table('business_settings')->where('setting_key', 'payment_grace_days')->update(['value_days' => 12]);
                $seed->run();
                $this->assertSame('12', (string) $db->table('business_settings')->where('setting_key', 'payment_grace_days')->get()->getRow()->value_days);

                $db->table('projects')->insert(['code'=>'TEST','name'=>'Synthetic project']);
                $project = $db->insertID();
                $db->table('polygons')->insert(['project_id'=>$project,'code'=>'A','name'=>'A']);
                $polygon = $db->insertID();
                $db->table('lots')->insert(['polygon_id'=>$polygon,'number'=>'01']);
                $lot = $db->insertID();
                $this->assertSame('01', $db->table('lots')->where('id',$lot)->get()->getRow()->number);
                $this->assertSame('0', (string) $db->table('lots')->where('id',$lot)->get()->getRow()->is_enabled);
                $this->rejects(fn () => $db->table('lots')->insert(['polygon_id'=>$polygon,'number'=>'01']), 'Duplicate lot');
                $this->rejects(fn () => $db->table('lots')->insert(['polygon_id'=>999999,'number'=>'02']), 'Orphan polygon');
                $this->rejects(fn () => $db->table('lots')->where('id',$lot)->update(['commercial_status'=>'paid']), 'Financial state in commercial status');
                $this->rejects(fn () => $db->table('lots')->where('id',$lot)->update(['front_m'=>'-1']), 'Negative dimensions');
                $this->rejects(fn () => $db->table('lot_prices')->insert(['lot_id'=>$lot,'amount'=>'-0.01','valid_from'=>'2026-01-01 00:00:00']), 'Negative price');
                $this->rejects(fn () => $db->table('lot_house_prices')->insert(['lot_house_model_id'=>999999,'land_amount'=>'1','construction_amount'=>'1','valid_from'=>'2026-01-01 00:00:00']), 'Unconfigured combination');
                $db->table('house_models')->insert(['code'=>'B','name'=>'Synthetic model']);
                $model=$db->insertID();
                $db->table('lot_house_models')->insert(['lot_id'=>$lot,'house_model_id'=>$model]);
                $combination=$db->insertID();
                $db->table('lot_house_prices')->insert(['lot_house_model_id'=>$combination,'land_amount'=>'123.45','construction_amount'=>'10.20','valid_from'=>'2026-01-01 00:00:00']);
                $this->assertSame('133.65', $db->table('lot_house_prices')->get()->getRow()->total_amount);
                $this->rejects(fn () => $db->table('lot_prices')->insert(['lot_id'=>$lot,'amount'=>'1','valid_from'=>'2026-02-01 00:00:00','valid_until'=>'2026-01-01 00:00:00']), 'Reversed price period');
                $this->rejects(fn () => $db->table('polygons')->where('id',$polygon)->delete(), 'Deleting occupied polygon');
            } finally {
                foreach (array_reverse($classes) as $class) {
                    (new $class($forge))->down();
                }
            }
            $this->assertFalse($db->tableExists('lots'));
            // A second up verifies that rollback leaves the schema reusable.
            foreach ($classes as $class) {
                (new $class($forge))->up();
            }
            foreach (array_reverse($classes) as $class) {
                (new $class($forge))->down();
            }
        } finally {
            $shield->down();
        }
    }

    private function rejects(callable $action, string $label): void
    {
        try {
            $result = $action();
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            $this->assertTrue(true, $label);
            return;
        }
        $this->assertFalse($result, $label);
    }
}
