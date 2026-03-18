<?php
/**
 * SecurityGuard - PrestaShop Security Module
 *
 * Compatible with PrestaShop 1.7.8.11
 * All UI is rendered inside getContent() - no custom Admin tab/controller.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/SecurityLog.php';
require_once dirname(__FILE__) . '/classes/SecurityBlocklist.php';

class SecurityGuard extends Module
{
    /** Configuration keys managed by this module */
    const CONFIG_KEYS = [
        'SECURITYGUARD_ENABLED',
        'SECURITYGUARD_AUTO_BLOCK',
        'SECURITYGUARD_MAX_ATTEMPTS',
        'SECURITYGUARD_BLOCK_DURATION',
        'SECURITYGUARD_ALERT_EMAIL',
        'SECURITYGUARD_LEARN_MODE',
        'SECURITYGUARD_HONEYPOT',
        'SECURITYGUARD_DEBUG',
    ];

    public function __construct()
    {
        $this->name                   = 'securityguard';
        $this->tab                    = 'administration';
        $this->version                = '1.0.0';
        $this->author                 = 'SecurityGuard';
        $this->need_instance          = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap              = true;

        parent::__construct();

        $this->displayName = $this->l('SecurityGuard');
        $this->description = $this->l('Real-time security monitoring and protection for your PrestaShop store.');
    }

    // -----------------------------------------------------------------------
    // Install / Uninstall
    // -----------------------------------------------------------------------

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // Create database tables
        if (!$this->installSql()) {
            return false;
        }

        // Register hooks (safe: ignore if hook doesn't exist)
        try {
            $this->registerHook('displayHeader');
        } catch (Exception $e) {
            // Non-fatal; hook may not exist in some PS versions
        }

        // Default configuration
        Configuration::updateValue('SECURITYGUARD_ENABLED',        1);
        Configuration::updateValue('SECURITYGUARD_AUTO_BLOCK',     1);
        Configuration::updateValue('SECURITYGUARD_MAX_ATTEMPTS',   5);
        Configuration::updateValue('SECURITYGUARD_BLOCK_DURATION', 1440);
        Configuration::updateValue('SECURITYGUARD_ALERT_EMAIL',    Configuration::get('PS_SHOP_EMAIL'));
        Configuration::updateValue('SECURITYGUARD_LEARN_MODE',     1);
        Configuration::updateValue('SECURITYGUARD_HONEYPOT',       1);
        Configuration::updateValue('SECURITYGUARD_DEBUG',          0);

        return true;
    }

    public function uninstall()
    {
        // Drop tables
        $db = Db::getInstance();
        $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'securityguard_logs`');
        $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'securityguard_blocklist`');
        $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'securityguard_patterns`');

        // Delete configuration
        foreach (self::CONFIG_KEYS as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    /**
     * Execute install.sql, replacing PREFIX_ placeholder with the real prefix.
     */
    private function installSql()
    {
        $sqlFile = dirname(__FILE__) . '/install/install.sql';
        if (!file_exists($sqlFile)) {
            return false;
        }

        $sql = file_get_contents($sqlFile);
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);

        $db = Db::getInstance();
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                if (!$db->execute($statement)) {
                    return false;
                }
            }
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // Hooks
    // -----------------------------------------------------------------------

    public function hookDisplayHeader($params)
    {
        if (!(int) Configuration::get('SECURITYGUARD_ENABLED')) {
            return;
        }

        // Basic security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Honeypot CSS (hides honeypot fields from real users)
        if ((int) Configuration::get('SECURITYGUARD_HONEYPOT')) {
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/honeypot.css');
        }
    }

    // -----------------------------------------------------------------------
    // Back-office configuration
    // -----------------------------------------------------------------------

    public function getContent()
    {
        $output = '';

        // Handle form submission
        if (Tools::isSubmit('submit_securityguard')) {
            $output .= $this->postProcess();
        }

        // Clean expired blocks before showing dashboard
        try {
            SecurityBlocklist::cleanExpired();
        } catch (Exception $e) {
            // Non-fatal
        }

        // Fetch data for dashboard
        $stats      = ['total_today' => 0, 'total_blocked' => 0, 'total_patterns' => 0];
        $blockedIPs = [];

        try {
            $stats      = SecurityLog::getStats();
            $blockedIPs = SecurityBlocklist::getBlockedIPs();
        } catch (Exception $e) {
            // Tables may not exist yet on first load; use safe defaults
        }

        // Normalise to int so Smarty never gets null
        $stats = [
            'total_today'    => (int) ($stats['total_today']    ?? 0),
            'total_blocked'  => (int) ($stats['total_blocked']  ?? 0),
            'total_patterns' => (int) ($stats['total_patterns'] ?? 0),
        ];

        // Ajax URL for real-time polling (includes admin token)
        $ajaxUrl = $this->getPathUri()
            . 'ajax/realtime.php?token='
            . Tools::getAdminTokenLite('AdminModules');

        // Assign Smarty variables
        $this->context->smarty->assign([
            'stats'          => $stats,
            'blocked_ips'    => $blockedIPs,
            'module_dir'     => $this->getPathUri(),
            'ajax_url'       => $ajaxUrl,
            'sg_enabled'     => (int) Configuration::get('SECURITYGUARD_ENABLED'),
            'sg_auto_block'  => (int) Configuration::get('SECURITYGUARD_AUTO_BLOCK'),
            'sg_learn_mode'  => (int) Configuration::get('SECURITYGUARD_LEARN_MODE'),
            'sg_debug'       => (int) Configuration::get('SECURITYGUARD_DEBUG'),
        ]);

        $output .= $this->renderConfigForm();
        $output .= $this->context->smarty->fetch(
            'module:securityguard/views/templates/admin/dashboard.tpl'
        );

        return $output;
    }

    /**
     * Process configuration form POST.
     */
    private function postProcess()
    {
        $fields = [
            'SECURITYGUARD_ENABLED'        => (int) Tools::getValue('SECURITYGUARD_ENABLED'),
            'SECURITYGUARD_AUTO_BLOCK'      => (int) Tools::getValue('SECURITYGUARD_AUTO_BLOCK'),
            'SECURITYGUARD_MAX_ATTEMPTS'    => max(1, (int) Tools::getValue('SECURITYGUARD_MAX_ATTEMPTS')),
            'SECURITYGUARD_BLOCK_DURATION'  => max(1, (int) Tools::getValue('SECURITYGUARD_BLOCK_DURATION')),
            'SECURITYGUARD_ALERT_EMAIL'     => pSQL(Tools::getValue('SECURITYGUARD_ALERT_EMAIL', '')),
            'SECURITYGUARD_LEARN_MODE'      => (int) Tools::getValue('SECURITYGUARD_LEARN_MODE'),
            'SECURITYGUARD_HONEYPOT'        => (int) Tools::getValue('SECURITYGUARD_HONEYPOT'),
            'SECURITYGUARD_DEBUG'           => (int) Tools::getValue('SECURITYGUARD_DEBUG'),
        ];

        foreach ($fields as $key => $value) {
            Configuration::updateValue($key, $value);
        }

        return $this->displayConfirmation($this->l('Settings saved.'));
    }

    /**
     * Build the HelperForm configuration panel.
     */
    private function renderConfigForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar        = false;
        $helper->table               = $this->table;
        $helper->module              = $this;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->identifier          = $this->identifier;
        $helper->submit_action       = 'submit_securityguard';
        $helper->currentIndex        = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token               = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    private function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('SecurityGuard Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Enable Module'),
                        'name'    => 'SECURITYGUARD_ENABLED',
                        'is_bool' => true,
                        'values'  => [
                            ['id' => 'active_on',  'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Auto-block attackers'),
                        'name'    => 'SECURITYGUARD_AUTO_BLOCK',
                        'is_bool' => true,
                        'values'  => [
                            ['id' => 'auto_block_on',  'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'auto_block_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Max attempts before block'),
                        'name'  => 'SECURITYGUARD_MAX_ATTEMPTS',
                        'class' => 'fixed-width-xs',
                    ],
                    [
                        'type'   => 'text',
                        'label'  => $this->l('Block duration (minutes)'),
                        'name'   => 'SECURITYGUARD_BLOCK_DURATION',
                        'class'  => 'fixed-width-sm',
                        'suffix' => $this->l('min'),
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Alert email'),
                        'name'  => 'SECURITYGUARD_ALERT_EMAIL',
                        'class' => 'fixed-width-xxl',
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Learn mode (log only, do not block)'),
                        'name'    => 'SECURITYGUARD_LEARN_MODE',
                        'is_bool' => true,
                        'values'  => [
                            ['id' => 'learn_on',  'value' => 1, 'label' => $this->l('On')],
                            ['id' => 'learn_off', 'value' => 0, 'label' => $this->l('Off')],
                        ],
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Enable honeypot'),
                        'name'    => 'SECURITYGUARD_HONEYPOT',
                        'is_bool' => true,
                        'values'  => [
                            ['id' => 'honeypot_on',  'value' => 1, 'label' => $this->l('On')],
                            ['id' => 'honeypot_off', 'value' => 0, 'label' => $this->l('Off')],
                        ],
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Debug mode'),
                        'name'    => 'SECURITYGUARD_DEBUG',
                        'is_bool' => true,
                        'desc'    => $this->l('Shows a debug panel in the dashboard with AJAX diagnostics.'),
                        'values'  => [
                            ['id' => 'debug_on',  'value' => 1, 'label' => $this->l('On')],
                            ['id' => 'debug_off', 'value' => 0, 'label' => $this->l('Off')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    private function getConfigFormValues()
    {
        return [
            'SECURITYGUARD_ENABLED'        => (int) Configuration::get('SECURITYGUARD_ENABLED'),
            'SECURITYGUARD_AUTO_BLOCK'      => (int) Configuration::get('SECURITYGUARD_AUTO_BLOCK'),
            'SECURITYGUARD_MAX_ATTEMPTS'    => (int) Configuration::get('SECURITYGUARD_MAX_ATTEMPTS'),
            'SECURITYGUARD_BLOCK_DURATION'  => (int) Configuration::get('SECURITYGUARD_BLOCK_DURATION'),
            'SECURITYGUARD_ALERT_EMAIL'     => Configuration::get('SECURITYGUARD_ALERT_EMAIL'),
            'SECURITYGUARD_LEARN_MODE'      => (int) Configuration::get('SECURITYGUARD_LEARN_MODE'),
            'SECURITYGUARD_HONEYPOT'        => (int) Configuration::get('SECURITYGUARD_HONEYPOT'),
            'SECURITYGUARD_DEBUG'           => (int) Configuration::get('SECURITYGUARD_DEBUG'),
        ];
    }
}
