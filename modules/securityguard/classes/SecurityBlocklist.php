<?php
/**
 * SecurityGuard - SecurityBlocklist class
 * Manages the blocklist table (clean expired blocks, list active IPs).
 */
class SecurityBlocklist
{
    /**
     * Remove blocks whose expiry date has passed.
     *
     * @return bool
     */
    public static function cleanExpired()
    {
        return Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'securityguard_blocklist`
             SET `is_blocked` = 0
             WHERE `is_blocked` = 1
               AND `expires_at` IS NOT NULL
               AND `expires_at` <= NOW()'
        );
    }

    /**
     * Return all currently active blocks.
     *
     * @return array
     */
    public static function getBlockedIPs()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `ip`, `reason`, `reputation_score`, `attack_count`,
                    DATE_FORMAT(`expires_at`, \'%Y-%m-%d %H:%i:%s\') AS `expires_at`,
                    DATE_FORMAT(`date_add`,   \'%Y-%m-%d %H:%i:%s\') AS `date_add`
             FROM `' . _DB_PREFIX_ . 'securityguard_blocklist`
             WHERE `is_blocked` = 1
               AND (`expires_at` IS NULL OR `expires_at` > NOW())
             ORDER BY `date_add` DESC'
        );

        return is_array($rows) ? $rows : [];
    }
}
