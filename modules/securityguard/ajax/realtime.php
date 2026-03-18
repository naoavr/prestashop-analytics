<?php
/**
 * SecurityGuard - Realtime AJAX endpoint (BackOffice only)
 *
 * Returns JSON: { ok, kpis, latest, server_time }
 *
 * Security:
 *   - Validates ?token against Tools::getAdminTokenLite('AdminModules')
 *   - Requires an authenticated BO employee
 */

require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';

header('Content-Type: application/json; charset=utf-8');

// Disable any output buffering that might corrupt JSON.
while (ob_get_level()) {
    ob_end_clean();
}

try {
    // --- Token validation -----------------------------------------------
    $token    = (string) Tools::getValue('token', '');
    $expected = Tools::getAdminTokenLite('AdminModules');

    if (!$token || !hash_equals($expected, $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'invalid_token']);
        exit;
    }

    // --- Authentication check -------------------------------------------
    $context = Context::getContext();
    if (!$context || !$context->employee || !(int) $context->employee->id) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
        exit;
    }

    // --- Load module classes --------------------------------------------
    $classFile = _PS_MODULE_DIR_ . 'securityguard/classes/SecurityLog.php';
    if (!file_exists($classFile)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'missing_class']);
        exit;
    }
    require_once $classFile;

    // --- Realtime data --------------------------------------------------
    $data = SecurityLog::getRealtimeData();

    // Lightweight KPI totals
    $db = Db::getInstance();

    $totalToday = (int) $db->getValue(
        'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'securityguard_logs`
         WHERE DATE(`date_add`) = CURDATE()'
    );

    $totalBlocked = (int) $db->getValue(
        'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'securityguard_blocklist`
         WHERE `is_blocked` = 1
           AND (`expires_at` IS NULL OR `expires_at` > NOW())'
    );

    $totalPatterns = (int) $db->getValue(
        'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'securityguard_patterns`
         WHERE `active` = 1'
    );

    echo json_encode([
        'ok'          => true,
        'kpis'        => [
            'total_today'         => $totalToday,
            'total_blocked'       => $totalBlocked,
            'total_patterns'      => $totalPatterns,
            'attacks_last_minute' => (int) ($data['attacks_last_minute'] ?? 0),
            'attacks_last_hour'   => (int) ($data['attacks_last_hour']   ?? 0),
        ],
        'latest'      => $data['latest'] ?? [],
        'server_time' => date('Y-m-d H:i:s'),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'exception',
        'message' => $e->getMessage(),
    ]);
}
