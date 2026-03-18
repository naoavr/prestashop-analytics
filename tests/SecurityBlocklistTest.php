<?php
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SecurityBlocklist
 *
 * All database interactions are handled by the Db stub defined in
 * tests/bootstrap.php – no real database connection is required.
 */
class SecurityBlocklistTest extends TestCase
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
    // cleanExpired()
    // ------------------------------------------------------------------

    public function testCleanExpiredReturnsTrueOnSuccess()
    {
        Db::queueExecute(true);

        $this->assertTrue(SecurityBlocklist::cleanExpired());
    }

    public function testCleanExpiredReturnsFalseOnDbError()
    {
        Db::queueExecute(false);

        $this->assertFalse(SecurityBlocklist::cleanExpired());
    }

    // ------------------------------------------------------------------
    // getBlockedIPs()
    // ------------------------------------------------------------------

    public function testGetBlockedIPsReturnsArray()
    {
        Db::queueRows([]);

        $this->assertIsArray(SecurityBlocklist::getBlockedIPs());
    }

    public function testGetBlockedIPsReturnsEmptyArrayWhenNoneBlocked()
    {
        Db::queueRows([]);

        $result = SecurityBlocklist::getBlockedIPs();

        $this->assertEmpty($result);
    }

    public function testGetBlockedIPsReturnsBlockedEntries()
    {
        $rows = [
            [
                'ip'               => '10.0.0.1',
                'reason'           => 'brute_force',
                'reputation_score' => 90,
                'attack_count'     => 10,
                'expires_at'       => '2099-12-31 23:59:59',
                'date_add'         => '2024-01-01 00:00:00',
            ],
        ];

        Db::queueRows($rows);

        $result = SecurityBlocklist::getBlockedIPs();

        $this->assertCount(1,            $result);
        $this->assertSame('10.0.0.1',    $result[0]['ip']);
        $this->assertSame('brute_force', $result[0]['reason']);
        $this->assertSame(10,            $result[0]['attack_count']);
    }

    public function testGetBlockedIPsReturnsEmptyArrayWhenDbReturnsFalse()
    {
        // executeS() may return false; getBlockedIPs() must normalise to [].
        Db::queueRows(false);

        $result = SecurityBlocklist::getBlockedIPs();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetBlockedIPsReturnsMultipleEntries()
    {
        $rows = [
            ['ip' => '192.168.1.1', 'reason' => 'sqli',     'reputation_score' => 80, 'attack_count' => 5,  'expires_at' => '2099-01-01 00:00:00', 'date_add' => '2024-06-01 10:00:00'],
            ['ip' => '192.168.1.2', 'reason' => 'xss',      'reputation_score' => 60, 'attack_count' => 3,  'expires_at' => '2099-01-01 00:00:00', 'date_add' => '2024-06-01 09:00:00'],
            ['ip' => '192.168.1.3', 'reason' => 'honeypot', 'reputation_score' => 40, 'attack_count' => 1,  'expires_at' => null,                  'date_add' => '2024-06-01 08:00:00'],
        ];

        Db::queueRows($rows);

        $result = SecurityBlocklist::getBlockedIPs();

        $this->assertCount(3,             $result);
        $this->assertSame('192.168.1.1',  $result[0]['ip']);
        $this->assertSame('192.168.1.2',  $result[1]['ip']);
        $this->assertNull($result[2]['expires_at']);
    }
}
