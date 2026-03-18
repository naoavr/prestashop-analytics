<?php
/**
 * SecurityGuard - SecurityLog class
 * Provides stats and real-time data from the logs table.
 */
class SecurityLog
{
    /**
     * Return aggregate totals (attacks today / blocked IPs / active patterns).
     *
     * @return array
     */
    public static function getStats()
    {
        $db = Db::getInstance();

        $today = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'securityguard_logs`
             WHERE DATE(`date_add`) = CURDATE()'
        );

        $blocked = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'securityguard_blocklist`
             WHERE `is_blocked` = 1
               AND (`expires_at` IS NULL OR `expires_at` > NOW())'
        );

        $patterns = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'securityguard_patterns`
             WHERE `active` = 1'
        );

        return [
            'total_today'    => $today,
            'total_blocked'  => $blocked,
            'total_patterns' => $patterns,
        ];
    }

    /**
     * Return the latest 10 log entries plus last-minute / last-hour counts.
     *
     * @return array
     */
    public static function getRealtimeData()
    {
        $db = Db::getInstance();

        $rows = $db->executeS(
            'SELECT `ip`, `country`, `attack_type`, `form_type`, `threat_score`,
                    DATE_FORMAT(`date_add`, \'%Y-%m-%d %H:%i:%s\') AS `date_add`
             FROM `' . _DB_PREFIX_ . 'securityguard_logs`
             ORDER BY `id_log` DESC
             LIMIT 10'
        );

        $latest = is_array($rows) ? $rows : [];

        $lastMinute = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'securityguard_logs`
             WHERE `date_add` >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)'
        );

        $lastHour = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'securityguard_logs`
             WHERE `date_add` >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );

        return [
            'latest'               => $latest,
            'attacks_last_minute'  => $lastMinute,
            'attacks_last_hour'    => $lastHour,
        ];
    }
}
