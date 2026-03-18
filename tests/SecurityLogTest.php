<?php
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SecurityLog
 *
 * All database interactions are handled by the Db stub defined in
 * tests/bootstrap.php – no real database connection is required.
 */
class SecurityLogTest extends TestCase
{
    protected function setUp(): void
    {
        Db::reset();
    }

    protected function tearDown(): void
    {
        Db::reset();
    }

    // ------------------------------------------------------------------
    // getStats()
    // ------------------------------------------------------------------

    public function testGetStatsReturnsRequiredKeys()
    {
        Db::queueValue(0);
        Db::queueValue(0);
        Db::queueValue(0);

        $stats = SecurityLog::getStats();

        $this->assertArrayHasKey('total_today',    $stats);
        $this->assertArrayHasKey('total_blocked',  $stats);
        $this->assertArrayHasKey('total_patterns', $stats);
    }

    public function testGetStatsReturnsQueuedValues()
    {
        Db::queueValue(3);   // total_today
        Db::queueValue(7);   // total_blocked
        Db::queueValue(12);  // total_patterns

        $stats = SecurityLog::getStats();

        $this->assertSame(3,  $stats['total_today']);
        $this->assertSame(7,  $stats['total_blocked']);
        $this->assertSame(12, $stats['total_patterns']);
    }

    public function testGetStatsDefaultsToZeroWhenDbReturnsZero()
    {
        // No values queued – stub returns 0 for every getValue() call
        $stats = SecurityLog::getStats();

        $this->assertSame(0, $stats['total_today']);
        $this->assertSame(0, $stats['total_blocked']);
        $this->assertSame(0, $stats['total_patterns']);
    }

    public function testGetStatsValuesAreCastToInt()
    {
        // DB may return strings; getStats() casts with (int)
        Db::queueValue('5');
        Db::queueValue('2');
        Db::queueValue('9');

        $stats = SecurityLog::getStats();

        $this->assertIsInt($stats['total_today']);
        $this->assertIsInt($stats['total_blocked']);
        $this->assertIsInt($stats['total_patterns']);
    }

    // ------------------------------------------------------------------
    // getRealtimeData()
    // ------------------------------------------------------------------

    public function testGetRealtimeDataReturnsRequiredKeys()
    {
        Db::queueRows([]);
        Db::queueValue(0);
        Db::queueValue(0);

        $data = SecurityLog::getRealtimeData();

        $this->assertArrayHasKey('latest',              $data);
        $this->assertArrayHasKey('attacks_last_minute', $data);
        $this->assertArrayHasKey('attacks_last_hour',   $data);
    }

    public function testGetRealtimeDataReturnsLogRows()
    {
        $rows = [
            [
                'ip'           => '1.2.3.4',
                'country'      => 'PT',
                'attack_type'  => 'sqli',
                'form_type'    => 'login',
                'threat_score' => '80',
                'date_add'     => '2024-01-01 00:00:00',
            ],
        ];

        Db::queueRows($rows);
        Db::queueValue(1);   // attacks_last_minute
        Db::queueValue(5);   // attacks_last_hour

        $data = SecurityLog::getRealtimeData();

        $this->assertCount(1,             $data['latest']);
        $this->assertSame('1.2.3.4',      $data['latest'][0]['ip']);
        $this->assertSame('PT',           $data['latest'][0]['country']);
        $this->assertSame(1,              $data['attacks_last_minute']);
        $this->assertSame(5,              $data['attacks_last_hour']);
    }

    public function testGetRealtimeDataLatestIsArrayWhenDbReturnsFalse()
    {
        // executeS() may return false on error; getRealtimeData() must
        // normalise this to an empty array.
        Db::queueRows(false);
        Db::queueValue(0);
        Db::queueValue(0);

        $data = SecurityLog::getRealtimeData();

        $this->assertIsArray($data['latest']);
        $this->assertEmpty($data['latest']);
    }

    public function testGetRealtimeDataCountsAreCastToInt()
    {
        Db::queueRows([]);
        Db::queueValue('3');
        Db::queueValue('15');

        $data = SecurityLog::getRealtimeData();

        $this->assertIsInt($data['attacks_last_minute']);
        $this->assertIsInt($data['attacks_last_hour']);
    }
}
