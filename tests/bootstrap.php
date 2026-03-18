<?php
/**
 * Test bootstrap
 *
 * Defines the minimum PrestaShop stubs required by SecurityLog and
 * SecurityBlocklist so that the classes can be loaded and exercised
 * without a running PrestaShop installation.
 *
 * Usage inside tests:
 *   // Queue the values that Db methods will return, in call order:
 *   Db::queueValue(5);           // next getValue() call returns 5
 *   Db::queueRows([...]);        // next executeS() call returns [...]
 *   Db::queueExecute(true);      // next execute() call returns true
 *
 *   // Reset between tests (done automatically in setUp/tearDown):
 *   Db::reset();
 */

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}

/**
 * Minimal stub of PrestaShop's Db singleton.
 *
 * All SQL strings passed to getValue(), executeS(), and execute() are
 * ignored – only the pre-queued return values matter.
 */
class Db
{
    /** @var Db|null */
    private static $instance = null;

    /** @var array Queued scalar values for getValue() */
    private $valueQueue = [];

    /** @var array Queued result-sets for executeS() */
    private $rowsQueue = [];

    /** @var array Queued booleans for execute() */
    private $executeQueue = [];

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Queue a scalar value to be returned by the next getValue() call. */
    public static function queueValue($value)
    {
        self::getInstance()->valueQueue[] = $value;
    }

    /**
     * Queue a result-set (array of rows, or false) to be returned by
     * the next executeS() call.
     *
     * @param array|false $rows
     */
    public static function queueRows($rows)
    {
        self::getInstance()->rowsQueue[] = $rows;
    }

    /** Queue a boolean to be returned by the next execute() call. */
    public static function queueExecute($result)
    {
        self::getInstance()->executeQueue[] = (bool) $result;
    }

    /** Reset the singleton and all queues (call in setUp / tearDown). */
    public static function reset()
    {
        self::$instance = null;
    }

    /** @param string $sql */
    public function getValue($sql)
    {
        return count($this->valueQueue) ? array_shift($this->valueQueue) : 0;
    }

    /** @param string $sql */
    public function executeS($sql)
    {
        return count($this->rowsQueue) ? array_shift($this->rowsQueue) : [];
    }

    /** @param string $sql */
    public function execute($sql)
    {
        return count($this->executeQueue) ? array_shift($this->executeQueue) : true;
    }
}

require_once __DIR__ . '/../modules/securityguard/classes/SecurityLog.php';
require_once __DIR__ . '/../modules/securityguard/classes/SecurityBlocklist.php';
