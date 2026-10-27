<?php
/**
 * Advanced Google Tag Manager & GA4 DataLayer Module for PrestaShop
 * Compatibility: PrestaShop 1.7.8.0 - 8.2.x / 9.x
 * Universal Backend-Driven Architecture — Zero DOM Scraping.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/PlatformAdapter.php';
require_once __DIR__ . '/classes/PricingEngine.php';
require_once __DIR__ . '/classes/CanonicalDataModel.php';
require_once __DIR__ . '/classes/ProductIdentityResolver.php';
require_once __DIR__ . '/classes/CustomerDataEngine.php';
require_once __DIR__ . '/classes/PayloadBuilder.php';
require_once __DIR__ . '/classes/DeduplicationManager.php';
require_once __DIR__ . '/classes/WishlistIntegrationLayer.php';
require_once __DIR__ . '/classes/UpdateManager.php';
require_once __DIR__ . '/classes/RefundDataResolver.php';
require_once __DIR__ . '/classes/ServerSideWebhookDispatcher.php';

class Ps_Gtm_DataLayer extends Module
{
    public function __construct()
    {
        $this->name = 'ps_gtm_datalayer';
        $this->tab = 'analytics_stats';
        $this->version = '1.42.3';
        $this->author = 'Raihan';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('E-Commerce DataLayer: GA4, Meta, Pinterest, TikTok, Google, Microsoft, OpenAI & Snapchat Ads');
        $this->description = $this->l('Universal backend-driven DataLayer with direct Meta, Pinterest, TikTok, Google Ads, Microsoft Advertising, OpenAI ChatGPT Ads, and Snapchat Ads dynamic catalog matching, zero DOM dependency, authoritative database product identifiers, and sGTM support.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');

        // Keep ps_module database version table synchronized with active version
        if (class_exists('Db')) {
            PlatformAdapter::syncModuleDatabaseVersion($this->name, $this->version);
        }

        // Auto-register core and server-to-server webhook hooks seamlessly without requiring module reinstall
        if (!$this->isRegisteredInHook('actionCarrierProcess')) {
            $this->registerHook('actionCarrierProcess');
        }
        if (!$this->isRegisteredInHook('actionOrderStatusPostUpdate')) {
            $this->registerHook('actionOrderStatusPostUpdate');
        }
        if (!$this->isRegisteredInHook('actionOrderSlipAdd')) {
            $this->registerHook('actionOrderSlipAdd');
        }
        if (!$this->isRegisteredInHook('actionValidateOrder')) {
            $this->registerHook('actionValidateOrder');
        }

        // Auto-register back-office hooks seamlessly when admin visits Back Office
        if (defined('_PS_ADMIN_DIR_') && !Configuration::get('PS_GTM_BO_HOOKS_V143')) {
            if (!$this->isRegisteredInHook('displayBackOfficeTop')) {
                $this->registerHook('displayBackOfficeTop');
            }
            if (!$this->isRegisteredInHook('displayBackOfficeHeader')) {
                $this->registerHook('displayBackOfficeHeader');
            }
            Configuration::updateValue('PS_GTM_BO_HOOKS_V143', 1);
        }
    }

    public function install()
    {
        $allEvents = array_keys(self::getSupportedEvents());
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayAfterBodyOpeningTag')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('actionCartUpdateQuantityBefore')
            && $this->registerHook('actionCarrierProcess')
            && $this->registerHook('displayBackOfficeTop')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionOrderSlipAdd')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionOrderStatusPostUpdate')
            && DeduplicationManager::createDatabaseTable()
            && Configuration::updateValue('PS_GTM_CONTAINER_ID', '')
            && Configuration::updateValue('PS_SGTM_TRANSPORT_URL', '')
            && Configuration::updateValue('PS_SGTM_WEBHOOK_ENDPOINT_PATH', '/ps-webhook')
            && Configuration::updateValue('PS_GTM_WEBHOOK_REFUND_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_RAW_HEAD_CODE', '', true)
            && Configuration::updateValue('PS_GTM_RAW_BODY_CODE', '', true)
            && Configuration::updateValue('PS_GTM_EVENT_PREFIX', '')
            && Configuration::updateValue('PS_GTM_CATALOG_ID_STRATEGY', 'composite')
            && Configuration::updateValue('PS_GTM_ADV_MASTER_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_META_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_TIKTOK_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_GADS_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_GADS_CART_DATA', '1')
            && Configuration::updateValue('PS_GTM_ADV_GADS_MERCHANT_ID', '')
            && Configuration::updateValue('PS_GTM_ADV_PINTEREST_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_MICROSOFT_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_OPENAI_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_SNAPCHAT_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_META_EVENTS', json_encode($allEvents))
            && Configuration::updateValue('PS_GTM_ADV_TIKTOK_EVENTS', json_encode($allEvents))
            && Configuration::updateValue('PS_GTM_ADV_GADS_EVENTS', json_encode($allEvents))
            && Configuration::updateValue('PS_GTM_ADV_PINTEREST_EVENTS', json_encode($allEvents))
            && Configuration::updateValue('PS_GTM_ADV_MICROSOFT_EVENTS', json_encode($allEvents))
            && Configuration::updateValue('PS_GTM_ADV_OPENAI_EVENTS', json_encode($allEvents))
            && Configuration::updateValue('PS_GTM_ADV_SNAPCHAT_EVENTS', json_encode($allEvents));
    }

    public function uninstall()
    {
        return parent::uninstall()
            && DeduplicationManager::dropDatabaseTable()
            && Configuration::deleteByName('PS_GTM_CONTAINER_ID')
            && Configuration::deleteByName('PS_SGTM_TRANSPORT_URL')
            && Configuration::deleteByName('PS_SGTM_WEBHOOK_ENDPOINT_PATH')
            && Configuration::deleteByName('PS_GTM_WEBHOOK_REFUND_ENABLED')
            && Configuration::deleteByName('PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED')
            && Configuration::deleteByName('PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED')
            && Configuration::deleteByName('PS_GTM_RAW_HEAD_CODE')
            && Configuration::deleteByName('PS_GTM_RAW_BODY_CODE')
            && Configuration::deleteByName('PS_GTM_EVENT_PREFIX')
            && Configuration::deleteByName('PS_GTM_CATALOG_ID_STRATEGY')
            && Configuration::deleteByName('PS_GTM_ADV_MASTER_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_META_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_TIKTOK_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_GADS_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_GADS_CART_DATA')
            && Configuration::deleteByName('PS_GTM_ADV_GADS_MERCHANT_ID')
            && Configuration::deleteByName('PS_GTM_ADV_PINTEREST_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_MICROSOFT_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_OPENAI_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_SNAPCHAT_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_META_EVENTS')
            && Configuration::deleteByName('PS_GTM_ADV_TIKTOK_EVENTS')
            && Configuration::deleteByName('PS_GTM_ADV_GADS_EVENTS')
            && Configuration::deleteByName('PS_GTM_ADV_PINTEREST_EVENTS')
            && Configuration::deleteByName('PS_GTM_ADV_MICROSOFT_EVENTS')
            && Configuration::deleteByName('PS_GTM_ADV_OPENAI_EVENTS')
            && Configuration::deleteByName('PS_GTM_ADV_SNAPCHAT_EVENTS');
    }

    
    public function clearModuleCache()
    {
        try {
            $this->_clearCache('views/templates/hook/datalayer_init.tpl');
            $this->_clearCache('views/templates/hook/gtm_head.tpl');
            $this->_clearCache('views/templates/hook/gtm_body.tpl');
        } catch (\Throwable $e) {}
    }

    public function getContent()
    {
        try {
            // Keep database version synchronized and cache fresh
        PlatformAdapter::syncModuleDatabaseVersion($this->name, $this->version);

        // Auto-register displayBackOfficeTop if upgrading from earlier version
        $requiredHooks = [
            'displayBackOfficeTop',
            'displayBackOfficeHeader',
            'actionOrderStatusPostUpdate',
            'actionOrderSlipAdd',
            'actionValidateOrder',
            'actionCarrierProcess',
        ];
        foreach ($requiredHooks as $hookName) {
            if (!$this->isRegisteredInHook($hookName)) {
                $this->registerHook($hookName);
            }
        }

        $output = '';
        $updateManager = new UpdateManager($this);

        // Display post-update confirmation if redirected
        if (Tools::getValue('module_updated')) {
            $updatedVer = Tools::getValue('new_version', $this->version);
            $output .= $this->displayConfirmation(sprintf(
                $this->l('E-Commerce DataLayer: GA4, Meta, Pinterest, TikTok, Google & Microsoft Ads successfully updated to version %s! All your settings were preserved.'),
                htmlspecialchars($updatedVer)
            ));
        }

        // Handle 1-Click Update Execution
        if (Tools::isSubmit('submitRunModuleUpdate')) {
            try {
                $downloadUrl = Tools::getValue('update_download_url');
                $checksum = Tools::getValue('update_checksum');
                $versionTarget = Tools::getValue('update_version', 'latest');
                $updateResult = $updateManager->executeUpdate($downloadUrl, $checksum, $versionTarget);

                if (!empty($updateResult['success'])) {
                    if ($versionTarget !== 'latest') {
                        $this->version = $versionTarget;
                    }
                    UpdateManager::clearUpdateCache();
                    PlatformAdapter::syncModuleDatabaseVersion($this->name, $this->version);
                    PlatformAdapter::clearAllCaches();

                    $cleanUrl = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&module_updated=1&new_version=' . urlencode($this->version);
                    Tools::redirectAdmin($cleanUrl);
                    return '';
                } else {
                    $errMsg = !empty($updateResult['message']) ? $updateResult['message'] : 'Update failed.';
                    $output .= $this->displayError($this->l($errMsg));
                }
            } catch (\Throwable $e) {
                $output .= $this->displayError('Update error: ' . $e->getMessage());
            }
        }

        // Handle AJAX Diagnostic Ping
        if (Tools::getValue('ajax') && Tools::getValue('action') === 'testSgtmPing') {
            header('Content-Type: application/json; charset=utf-8');
            $testUrl = Tools::getValue('PS_SGTM_TRANSPORT_URL');
            $pingResult = ServerSideWebhookDispatcher::testPing($testUrl);
            die(json_encode($pingResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        // Self-heal corrupted or empty PS_GTM_ACTIVE_EVENTS
        $rawActive = Configuration::get('PS_GTM_ACTIVE_EVENTS');
        if ($rawActive === '[]' || (is_string($rawActive) && empty(json_decode($rawActive, true)))) {
            Configuration::deleteByName('PS_GTM_ACTIVE_EVENTS');
        }

        // Enqueue Back Office Styles and Scripts with version cache-buster
        if (isset($this->context->controller) && method_exists($this->context->controller, 'addCSS')) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css?v=' . $this->version);
            $this->context->controller->addJS($this->_path . 'views/js/admin.js?v=' . $this->version);
        }

        // Handle Settings Submission
        if (Tools::isSubmit('submit' . $this->name) || Tools::isSubmit('submitps_gtm_datalayer') || Tools::getValue('submitps_gtm_datalayer')) {
            $settingsSaved = false;

            // 1. Process Core Settings if submitted
            if (Tools::getValue('PS_GTM_CONTAINER_ID') !== false) {
                $gtmId = trim(Tools::getValue('PS_GTM_CONTAINER_ID'));
                $sgtmUrl = trim(Tools::getValue('PS_SGTM_TRANSPORT_URL'));
                $sgtmEndpointPath = trim((string)Tools::getValue('PS_SGTM_WEBHOOK_ENDPOINT_PATH'));
                $rawPrefix = Tools::getValue('PS_GTM_EVENT_PREFIX');
                $prefix = self::normalizePrefix($rawPrefix);
                $catalogStrategy = trim((string)Tools::getValue('PS_GTM_CATALOG_ID_STRATEGY'));
                if (!in_array($catalogStrategy, ['composite', 'id_product', 'reference_sku'])) {
                    $catalogStrategy = 'composite';
                }

                if (!empty($gtmId) && !preg_match('/^GTM-[A-Z0-9]+$/', $gtmId)) {
                    $output .= $this->displayError($this->l('Invalid Google Tag Manager Container ID format (e.g., GTM-XXXXXXX).'));
                } else {
                    Configuration::updateValue('PS_GTM_CONTAINER_ID', $gtmId);
                    Configuration::updateValue('PS_SGTM_TRANSPORT_URL', $sgtmUrl);
                    Configuration::updateValue('PS_SGTM_WEBHOOK_ENDPOINT_PATH', $sgtmEndpointPath ?: '/ps-webhook');
                    $rawHeadCode = Tools::getValue('PS_GTM_RAW_HEAD_CODE', false);
                    if ($rawHeadCode !== false) {
                        Configuration::updateValue('PS_GTM_RAW_HEAD_CODE', $rawHeadCode, true);
                    }
                    $rawBodyCode = Tools::getValue('PS_GTM_RAW_BODY_CODE', false);
                    if ($rawBodyCode !== false) {
                        Configuration::updateValue('PS_GTM_RAW_BODY_CODE', $rawBodyCode, true);
                    }
                    Configuration::updateValue('PS_GTM_EVENT_PREFIX', $prefix);
                    Configuration::updateValue('PS_GTM_CATALOG_ID_STRATEGY', $catalogStrategy);
                    Configuration::updateValue('PS_GTM_WEBHOOK_REFUND_ENABLED', Tools::getValue('PS_GTM_WEBHOOK_REFUND_ENABLED') ? '1' : '0');
                    Configuration::updateValue('PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED', Tools::getValue('PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED') ? '1' : '0');
                    Configuration::updateValue('PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED', Tools::getValue('PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED') ? '1' : '0');
                    $settingsSaved = true;
                }
            }

            // 2. Process Advertising Platform Settings if submitted
            if (Tools::getValue('PS_GTM_ADV_SUBMITTED') || Tools::getValue('PS_GTM_ADV_MASTER_ENABLED') !== false) {
                $advMaster = Tools::getValue('PS_GTM_ADV_MASTER_ENABLED') ? '1' : '0';
                $advMeta = Tools::getValue('PS_GTM_ADV_META_ENABLED') ? '1' : '0';
                $advTiktok = Tools::getValue('PS_GTM_ADV_TIKTOK_ENABLED') ? '1' : '0';
                $advGads = Tools::getValue('PS_GTM_ADV_GADS_ENABLED') ? '1' : '0';
                $advPinterest = Tools::getValue('PS_GTM_ADV_PINTEREST_ENABLED') ? '1' : '0';
                $advMicrosoft = Tools::getValue('PS_GTM_ADV_MICROSOFT_ENABLED') ? '1' : '0';
                $advOpenAi = Tools::getValue('PS_GTM_ADV_OPENAI_ENABLED') ? '1' : '0';
                $advSnapchat = Tools::getValue('PS_GTM_ADV_SNAPCHAT_ENABLED') ? '1' : '0';

                Configuration::updateValue('PS_GTM_ADV_MASTER_ENABLED', $advMaster);
                Configuration::updateValue('PS_GTM_ADV_META_ENABLED', $advMeta);
                Configuration::updateValue('PS_GTM_ADV_TIKTOK_ENABLED', $advTiktok);
                Configuration::updateValue('PS_GTM_ADV_GADS_ENABLED', $advGads);
                Configuration::updateValue('PS_GTM_ADV_GADS_CART_DATA', Tools::getValue('PS_GTM_ADV_GADS_CART_DATA') ? '1' : '0');
                Configuration::updateValue('PS_GTM_ADV_GADS_MERCHANT_ID', trim((string)Tools::getValue('PS_GTM_ADV_GADS_MERCHANT_ID', '')));
                Configuration::updateValue('PS_GTM_ADV_PINTEREST_ENABLED', $advPinterest);
                Configuration::updateValue('PS_GTM_ADV_MICROSOFT_ENABLED', $advMicrosoft);
                Configuration::updateValue('PS_GTM_ADV_OPENAI_ENABLED', $advOpenAi);
                Configuration::updateValue('PS_GTM_ADV_SNAPCHAT_ENABLED', $advSnapchat);

                $metaEvts = [];
                $tiktokEvts = [];
                $gadsEvts = [];
                $pinterestEvts = [];
                $microsoftEvts = [];
                $openaiEvts = [];
                $snapchatEvts = [];
                foreach (array_keys(self::getSupportedEvents()) as $evtName) {
                    if (Tools::getValue('adv_meta_' . $evtName)) {
                        $metaEvts[] = $evtName;
                    }
                    if (Tools::getValue('adv_tiktok_' . $evtName)) {
                        $tiktokEvts[] = $evtName;
                    }
                    if (Tools::getValue('adv_gads_' . $evtName)) {
                        $gadsEvts[] = $evtName;
                    }
                    if (Tools::getValue('adv_pinterest_' . $evtName)) {
                        $pinterestEvts[] = $evtName;
                    }
                    if (Tools::getValue('adv_microsoft_' . $evtName)) {
                        $microsoftEvts[] = $evtName;
                    }
                    if (Tools::getValue('adv_openai_' . $evtName)) {
                        $openaiEvts[] = $evtName;
                    }
                    if (Tools::getValue('adv_snapchat_' . $evtName)) {
                        $snapchatEvts[] = $evtName;
                    }
                }
                Configuration::updateValue('PS_GTM_ADV_META_EVENTS', json_encode($metaEvts));
                Configuration::updateValue('PS_GTM_ADV_TIKTOK_EVENTS', json_encode($tiktokEvts));
                Configuration::updateValue('PS_GTM_ADV_GADS_EVENTS', json_encode($gadsEvts));
                Configuration::updateValue('PS_GTM_ADV_PINTEREST_EVENTS', json_encode($pinterestEvts));
                Configuration::updateValue('PS_GTM_ADV_MICROSOFT_EVENTS', json_encode($microsoftEvts));
                Configuration::updateValue('PS_GTM_ADV_OPENAI_EVENTS', json_encode($openaiEvts));
                Configuration::updateValue('PS_GTM_ADV_SNAPCHAT_EVENTS', json_encode($snapchatEvts));

                $settingsSaved = true;
            }

            // 3. Process Events Active/Enabled Status only if explicit canonical event toggles are submitted
            if (Tools::getValue('PS_GTM_EVENTS_SUBMITTED')) {
                $hasExplicitEvents = false;
                foreach (self::getCanonicalEventsCatalog() as $cEvt) {
                    if (Tools::getValue('event_active_' . $cEvt['name']) !== false && Tools::getValue('event_active_' . $cEvt['name']) !== null) {
                        $hasExplicitEvents = true;
                        break;
                    }
                }
                if ($hasExplicitEvents) {
                    $activeEvents = [];
                    foreach (self::getCanonicalEventsCatalog() as $cEvt) {
                        if (Tools::getValue('event_active_' . $cEvt['name'])) {
                            $activeEvents[] = $cEvt['name'];
                        }
                    }
                    Configuration::updateValue('PS_GTM_ACTIVE_EVENTS', json_encode($activeEvents));
                    $settingsSaved = true;
                }
            }

            if ($settingsSaved) {
                $output .= $this->displayConfirmation($this->l('E-Commerce DataLayer settings successfully saved.'));
            }
        }

        $updateInfo = $updateManager->checkForUpdates(true);
        $output .= $this->renderUpdateBanner($updateInfo);

        return $output . $this->renderAdminTabs();
    } catch (\Throwable $e) {
        if (class_exists('PrestaShopLogger')) {
            PrestaShopLogger::addLog(
                '[ps_gtm_datalayer] getContent() exception: ' . $e->getMessage(),
                3,
                null,
                'ps_gtm_datalayer'
            );
        }
        return $this->displayError(sprintf(
            $this->l('An unexpected error occurred while loading module configuration: %s'),
            htmlspecialchars($e->getMessage())
        ));
    }
}

    private function renderUpdateBanner($updateInfo)
    {
        // When there is no update available, do not render any notification bar or panel.
        // It remains completely hidden until a new version is published.
        if (empty($updateInfo['has_update'])) {
            return '';
        }

        $html = '<div class="panel">';
        $html .= '<h3><i class="icon-refresh"></i> ' . $this->l('Module Version & Update Status') . '</h3>';
        $html .= '<div class="alert alert-warning">';
        $html .= '<h4><strong>' . sprintf($this->l('New version %s is available!'), htmlspecialchars($updateInfo['latest_version'])) . '</strong></h4>';
        $html .= '<p>' . nl2br(htmlspecialchars($updateInfo['release_notes'])) . '</p>';
        $html .= '<hr>';
        $updateActionUrl = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;
        $html .= '<form action="' . Tools::safeOutput($updateActionUrl) . '" method="post" style="display:inline-block;">';
        $html .= '<input type="hidden" name="update_download_url" value="' . htmlspecialchars($updateInfo['download_url']) . '">';
        $html .= '<input type="hidden" name="update_checksum" value="' . htmlspecialchars($updateInfo['checksum']) . '">';
        $html .= '<input type="hidden" name="update_version" value="' . htmlspecialchars($updateInfo['latest_version']) . '">';
        $html .= '<button type="submit" name="submitRunModuleUpdate" class="btn btn-success btn-lg" onclick="return confirm(\'' . $this->l('Are you sure you want to upgrade the module now? All your settings will be preserved.') . '\');">';
        $html .= '<i class="icon-download"></i> ' . $this->l('1-Click Update to Version ') . htmlspecialchars($updateInfo['latest_version']);
        $html .= '</button>';
        $html .= '</form>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function hookDisplayBackOfficeTop($params)
    {
        try {
            // Auto-register displayBackOfficeTop if not registered
            if (!$this->isRegisteredInHook('displayBackOfficeTop')) {
                $this->registerHook('displayBackOfficeTop');
            }

        // 1. Handle 1-Click Update if triggered from Module Manager or any admin page
        if (Tools::isSubmit('submitRunModuleUpdate')) {
            try {
                $downloadUrl = Tools::getValue('update_download_url');
                $checksum = Tools::getValue('update_checksum');
                $versionTarget = Tools::getValue('update_version', 'latest');

                $updateManager = new UpdateManager($this);
                $updateResult = $updateManager->executeUpdate($downloadUrl, $checksum, $versionTarget);

                if (!empty($updateResult['success'])) {
                    if ($versionTarget !== 'latest') {
                        $this->version = $versionTarget;
                    }
                    UpdateManager::clearUpdateCache();
                    PlatformAdapter::syncModuleDatabaseVersion($this->name, $this->version);
                    PlatformAdapter::clearAllCaches();

                    $cleanUrl = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&module_updated=1&new_version=' . urlencode($this->version);
                    Tools::redirectAdmin($cleanUrl);
                    return '';
                } else {
                    $errMsg = !empty($updateResult['message']) ? $updateResult['message'] : 'Update failed.';
                    if (isset($this->context->controller->errors) && is_array($this->context->controller->errors)) {
                        $this->context->controller->errors[] = $this->l($errMsg);
                    }
                }
            } catch (\Throwable $e) {
                if (isset($this->context->controller->errors) && is_array($this->context->controller->errors)) {
                    $this->context->controller->errors[] = 'Update error: ' . $e->getMessage();
                }
            }
        }

        // 2. Target Module Manager and Module List pages
        $controllerName = Tools::getValue('controller');
        $tabName = Tools::getValue('tab');
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        $isModuleManager = (
            $controllerName === 'AdminModules' 
            || $controllerName === 'AdminModulesManage' 
            || $controllerName === 'AdminModulesUpdates' 
            || $controllerName === 'AdminModulesNotifications'
            || $tabName === 'AdminModules'
            || $tabName === 'AdminModulesManage'
            || stripos($requestUri, 'improve/modules') !== false
            || stripos($requestUri, 'module/manage') !== false
            || stripos($requestUri, 'controller=AdminModules') !== false
        );

        // Do not render duplicate banner if already on module configuration page
        if (Tools::getValue('configure') === $this->name) {
            return '';
        }

        if (!$isModuleManager) {
            return '';
        }

        $updateManager = new UpdateManager($this);
        $updateInfo = $updateManager->checkForUpdates();
        if (empty($updateInfo['has_update'])) {
            return '';
        }

        $updateActionUrl = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;

        $this->context->smarty->assign([
            'module_name' => $this->name,
            'module_display_name' => $this->displayName,
            'current_version' => $this->version,
            'latest_version' => $updateInfo['latest_version'],
            'release_notes' => $updateInfo['release_notes'],
            'download_url' => $updateInfo['download_url'],
            'checksum' => $updateInfo['checksum'],
            'update_action_url' => $updateActionUrl,
            'current_request_url' => Tools::safeOutput($_SERVER['REQUEST_URI']),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/module_manager_update_banner.tpl');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        try {
            if (Tools::getValue('configure') === $this->name && isset($this->context->controller) && method_exists($this->context->controller, 'addCSS')) {
                $this->context->controller->addCSS($this->_path . 'views/css/admin.css?v=' . $this->version);
                $this->context->controller->addJS($this->_path . 'views/js/admin.js?v=' . $this->version);
            }
            return '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function normalizePrefix($prefix)
    {
        if (empty($prefix)) {
            return '';
        }

        $cleaned = trim((string)$prefix);
        $cleaned = preg_replace('/[\s\.\-]+/', '_', $cleaned);
        $cleaned = preg_replace('/[^a-zA-Z0-9_]/', '', $cleaned);
        $cleaned = trim($cleaned, '_');

        return $cleaned;
    }

    public static function formatEventName($originalEventName, $prefix = null)
    {
        if ($prefix === null) {
            $prefix = Configuration::get('PS_GTM_EVENT_PREFIX');
        }

        $prefix = self::normalizePrefix($prefix);

        if (empty($prefix)) {
            return (string)$originalEventName;
        }

        if (strpos((string)$originalEventName, $prefix . '_') === 0) {
            return (string)$originalEventName;
        }

        return $prefix . '_' . (string)$originalEventName;
    }

    private function renderSettingHelp($key, $shortDesc, $whatItDoes, $whyItMatters, $trackingBehavior, $whenToUse, $example = null)
    {
        $id = 'ps-gtm-exp-' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);

        $html = '<div class="ps-gtm-setting-explainer">';
        $html .= '<div class="ps-gtm-short-desc-row">';
        $html .= '<span class="ps-gtm-short-desc">' . htmlspecialchars($shortDesc) . '</span>';
        $html .= '<button type="button" class="ps-gtm-learn-more-btn" data-target="' . $id . '" aria-expanded="false" aria-controls="' . $id . '">';
        $html .= '<i class="icon-info-circle"></i> <span>' . $this->l('Learn more') . '</span> <i class="icon-angle-down ps-gtm-chevron"></i>';
        $html .= '</button>';
        $html .= '</div>';

        $html .= '<div id="' . $id . '" class="ps-gtm-expanded-explanation" style="display: none;" role="region">';
        $html .= '<div class="ps-gtm-exp-grid">';

        $html .= '<div class="ps-gtm-exp-item">';
        $html .= '<span class="ps-gtm-exp-label"><i class="icon-info-sign"></i> ' . $this->l('What it does') . '</span>';
        $html .= '<div class="ps-gtm-exp-val">' . $whatItDoes . '</div>';
        $html .= '</div>';

        $html .= '<div class="ps-gtm-exp-item">';
        $html .= '<span class="ps-gtm-exp-label"><i class="icon-lightbulb"></i> ' . $this->l('Why it matters') . '</span>';
        $html .= '<div class="ps-gtm-exp-val">' . $whyItMatters . '</div>';
        $html .= '</div>';

        $html .= '<div class="ps-gtm-exp-item">';
        $html .= '<span class="ps-gtm-exp-label"><i class="icon-exchange"></i> ' . $this->l('Tracking behavior') . '</span>';
        $html .= '<div class="ps-gtm-exp-val">' . $trackingBehavior . '</div>';
        $html .= '</div>';

        $html .= '<div class="ps-gtm-exp-item">';
        $html .= '<span class="ps-gtm-exp-label"><i class="icon-check-circle"></i> ' . $this->l('When to use') . '</span>';
        $html .= '<div class="ps-gtm-exp-val">' . $whenToUse . '</div>';
        $html .= '</div>';

        if (!empty($example)) {
            $html .= '<div class="ps-gtm-exp-item ps-gtm-exp-example" style="grid-column: 1 / -1;">';
            $html .= '<span class="ps-gtm-exp-label"><i class="icon-tag"></i> ' . $this->l('Practical Example') . '</span>';
            $html .= '<div class="ps-gtm-exp-val"><code>' . htmlspecialchars($example) . '</code></div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function renderAdminTabs()
    {
        $eventsCatalog = self::getCanonicalEventsCatalog();
        $supportedAdvEvents = self::getSupportedEvents();

        $rawMeta = Configuration::get('PS_GTM_ADV_META_EVENTS');
        $rawTiktok = Configuration::get('PS_GTM_ADV_TIKTOK_EVENTS');
        $rawGads = Configuration::get('PS_GTM_ADV_GADS_EVENTS');
        $rawPinterest = Configuration::get('PS_GTM_ADV_PINTEREST_EVENTS');
        $rawMicrosoft = Configuration::get('PS_GTM_ADV_MICROSOFT_EVENTS');
        $rawOpenAi = Configuration::get('PS_GTM_ADV_OPENAI_EVENTS');
        $rawSnapchat = Configuration::get('PS_GTM_ADV_SNAPCHAT_EVENTS');

        $metaActive = ($rawMeta !== false && $rawMeta !== null) ? json_decode($rawMeta, true) : array_keys($supportedAdvEvents);
        $tiktokActive = ($rawTiktok !== false && $rawTiktok !== null) ? json_decode($rawTiktok, true) : array_keys($supportedAdvEvents);
        $gadsActive = ($rawGads !== false && $rawGads !== null) ? json_decode($rawGads, true) : array_keys($supportedAdvEvents);
        $pinterestActive = ($rawPinterest !== false && $rawPinterest !== null) ? json_decode($rawPinterest, true) : array_keys($supportedAdvEvents);
        $microsoftActive = ($rawMicrosoft !== false && $rawMicrosoft !== null) ? json_decode($rawMicrosoft, true) : array_keys($supportedAdvEvents);
        $openaiActive = ($rawOpenAi !== false && $rawOpenAi !== null) ? json_decode($rawOpenAi, true) : array_keys($supportedAdvEvents);
        $snapchatActive = ($rawSnapchat !== false && $rawSnapchat !== null) ? json_decode($rawSnapchat, true) : array_keys($supportedAdvEvents);

        if (!is_array($metaActive)) {
            $metaActive = array_keys($supportedAdvEvents);
        }
        if (!is_array($tiktokActive)) {
            $tiktokActive = array_keys($supportedAdvEvents);
        }
        if (!is_array($gadsActive)) {
            $gadsActive = array_keys($supportedAdvEvents);
        }
        if (!is_array($pinterestActive)) {
            $pinterestActive = array_keys($supportedAdvEvents);
        }
        if (!is_array($microsoftActive)) {
            $microsoftActive = array_keys($supportedAdvEvents);
        }
        if (!is_array($openaiActive)) {
            $openaiActive = array_keys($supportedAdvEvents);
        }
        if (!is_array($snapchatActive)) {
            $snapchatActive = array_keys($supportedAdvEvents);
        }

        // Active platforms calculation
        $advMaster = Configuration::get('PS_GTM_ADV_MASTER_ENABLED') !== '0';
        $activePlatformsCount = 0;
        if ($advMaster) {
            if (Configuration::get('PS_GTM_ADV_META_ENABLED') !== '0') $activePlatformsCount++;
            if (Configuration::get('PS_GTM_ADV_GADS_ENABLED') !== '0') $activePlatformsCount++;
            if (Configuration::get('PS_GTM_ADV_TIKTOK_ENABLED') !== '0') $activePlatformsCount++;
            if (Configuration::get('PS_GTM_ADV_PINTEREST_ENABLED') !== '0') $activePlatformsCount++;
            if (Configuration::get('PS_GTM_ADV_MICROSOFT_ENABLED') !== '0') $activePlatformsCount++;
            if (Configuration::get('PS_GTM_ADV_OPENAI_ENABLED') !== '0') $activePlatformsCount++;
            if (Configuration::get('PS_GTM_ADV_SNAPCHAT_ENABLED') !== '0') $activePlatformsCount++;
        }

        // Diagnostics metrics
        $gtmId = trim((string)Configuration::get('PS_GTM_CONTAINER_ID'));
        $gtmIdValid = !empty($gtmId) && preg_match('/^GTM-[A-Z0-9]+$/', $gtmId);
        $gtmIdMsg = empty($gtmId)
            ? $this->l('No GTM Container ID configured. Web tracking is inactive.')
            : ($gtmIdValid
                ? sprintf($this->l('Container ID %s is properly formatted and injecting into storefront.'), htmlspecialchars($gtmId))
                : $this->l('Invalid format. GTM Container ID must match GTM-XXXXXXX.'));

        $sgtmUrl = trim((string)Configuration::get('PS_SGTM_TRANSPORT_URL'));
        $endpointPath = trim((string)Configuration::get('PS_SGTM_WEBHOOK_ENDPOINT_PATH')) ?: '/ps-webhook';
        $targetEndpoint = !empty($sgtmUrl) ? (rtrim($sgtmUrl, '/') . '/' . ltrim($endpointPath, '/')) : 'Not configured';

        $catalogStrategy = Configuration::get('PS_GTM_CATALOG_ID_STRATEGY') ?: 'composite';
        $sampleCatalogId = ($catalogStrategy === 'composite') ? '68_299' : (($catalogStrategy === 'reference_sku') ? 'SNACK-BLAU' : '68');

        $purchaseLogCount = 0;
        try {
            if (class_exists('Db')) {
                $purchaseLogCount = (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'gtm_purchase_log`');
            }
        } catch (\Throwable $e) {}

        $requiredHooks = [
            'displayHeader',
            'displayOrderConfirmation',
            'actionCartUpdateQuantityBefore',
            'actionOrderSlipAdd',
            'actionOrderStatusPostUpdate',
            'actionValidateOrder',
            'actionCarrierProcess',
            'displayBackOfficeTop',
            'displayBackOfficeHeader',
        ];
        $registeredHooksCount = 0;
        foreach ($requiredHooks as $hk) {
            if ($this->isRegisteredInHook($hk)) {
                $registeredHooksCount++;
            }
        }

        // Event Schemas and Live Destination Routing for inspect drawer and table
        $schemasJsonMap = [];
        foreach ($eventsCatalog as &$evt) {
            $eName = $evt['name'];
            $destMap = [
                'meta' => [
                    'key' => 'meta',
                    'name' => 'Meta Pixel & Conversions API (CAPI)',
                    'short_name' => 'Meta',
                    'supported' => in_array($eName, array_keys($supportedAdvEvents)),
                    'globally_enabled' => $advMaster && (Configuration::get('PS_GTM_ADV_META_ENABLED') !== '0'),
                    'routed' => in_array($eName, $metaActive),
                    'code' => isset($evt['platform_mappings']['meta']) ? $evt['platform_mappings']['meta'] : '',
                ],
                'gads' => [
                    'key' => 'gads',
                    'name' => 'Google Ads Dynamic Remarketing',
                    'short_name' => 'Google Ads',
                    'supported' => in_array($eName, array_keys($supportedAdvEvents)),
                    'globally_enabled' => $advMaster && (Configuration::get('PS_GTM_ADV_GADS_ENABLED') !== '0'),
                    'routed' => in_array($eName, $gadsActive),
                    'code' => isset($evt['platform_mappings']['gads']) ? $evt['platform_mappings']['gads'] : '',
                ],
                'tiktok' => [
                    'key' => 'tiktok',
                    'name' => 'TikTok Pixel & Events API',
                    'short_name' => 'TikTok',
                    'supported' => in_array($eName, array_keys($supportedAdvEvents)),
                    'globally_enabled' => $advMaster && (Configuration::get('PS_GTM_ADV_TIKTOK_ENABLED') !== '0'),
                    'routed' => in_array($eName, $tiktokActive),
                    'code' => isset($evt['platform_mappings']['tiktok']) ? $evt['platform_mappings']['tiktok'] : '',
                ],
                'pinterest' => [
                    'key' => 'pinterest',
                    'name' => 'Pinterest Tag & Conversions API (CAPI)',
                    'short_name' => 'Pinterest',
                    'supported' => in_array($eName, array_keys($supportedAdvEvents)),
                    'globally_enabled' => $advMaster && (Configuration::get('PS_GTM_ADV_PINTEREST_ENABLED') !== '0'),
                    'routed' => in_array($eName, $pinterestActive),
                    'code' => isset($evt['platform_mappings']['pinterest']) ? $evt['platform_mappings']['pinterest'] : '',
                ],
                'microsoft' => [
                    'key' => 'microsoft',
                    'name' => 'Microsoft Advertising (Bing Ads) & UET',
                    'short_name' => 'Microsoft Ads',
                    'supported' => in_array($eName, array_keys($supportedAdvEvents)),
                    'globally_enabled' => $advMaster && (Configuration::get('PS_GTM_ADV_MICROSOFT_ENABLED') !== '0'),
                    'routed' => in_array($eName, $microsoftActive),
                    'code' => isset($evt['platform_mappings']['microsoft']) ? $evt['platform_mappings']['microsoft'] : '',
                ],
                'openai' => [
                    'key' => 'openai',
                    'name' => 'OpenAI / ChatGPT Ads Measurement',
                    'short_name' => 'OpenAI Ads',
                    'supported' => in_array($eName, array_keys($supportedAdvEvents)),
                    'globally_enabled' => $advMaster && (Configuration::get('PS_GTM_ADV_OPENAI_ENABLED') !== '0'),
                    'routed' => in_array($eName, $openaiActive),
                    'code' => isset($evt['platform_mappings']['openai']) ? $evt['platform_mappings']['openai'] : '',
                ],
                'snapchat' => [
                    'key' => 'snapchat',
                    'name' => 'Snapchat Pixel & Conversions API (CAPI)',
                    'short_name' => 'Snapchat',
                    'supported' => in_array($eName, array_keys($supportedAdvEvents)),
                    'globally_enabled' => $advMaster && (Configuration::get('PS_GTM_ADV_SNAPCHAT_ENABLED') !== '0'),
                    'routed' => in_array($eName, $snapchatActive),
                    'code' => isset($evt['platform_mappings']['snapchat']) ? $evt['platform_mappings']['snapchat'] : '',
                ],
            ];
            if (!empty($evt['has_sgtm_webhook'])) {
                $destMap['sgtm'] = [
                    'key' => 'sgtm',
                    'name' => 'Server GTM Webhook Client',
                    'short_name' => 'sGTM Webhook',
                    'supported' => true,
                    'globally_enabled' => !empty($sgtmUrl),
                    'routed' => !empty($sgtmUrl),
                    'code' => 'Server-to-server HTTP POST payload to ' . $targetEndpoint,
                ];
            }

            $activeDestCount = 0;
            $activeDestNames = [];
            foreach ($destMap as $pk => $pdata) {
                if ($pk === 'sgtm') {
                    if ($pdata['globally_enabled']) {
                        $activeDestCount++;
                        $activeDestNames[] = $pdata['short_name'];
                    }
                } else {
                    if ($pdata['supported'] && $pdata['globally_enabled'] && $pdata['routed']) {
                        $activeDestCount++;
                        $activeDestNames[] = $pdata['short_name'];
                    }
                }
            }

            $activeDestNamesStr = !empty($activeDestNames) ? implode(', ', $activeDestNames) : '';
            $evt['active_dest_count'] = $activeDestCount;
            $evt['active_dest_names'] = $activeDestNames;
            $evt['active_dest_names_str'] = $activeDestNamesStr;
            $evt['dest_map'] = $destMap;

            $schemasJsonMap[$eName] = [
                'name' => $eName,
                'label' => $evt['label'],
                'icon' => isset($evt['icon']) ? $evt['icon'] : 'icon-bolt',
                'category' => $evt['category'],
                'category_label' => $evt['category_label'],
                'desc' => $evt['desc'],
                'trigger_hook' => $evt['trigger_hook'],
                'dependencies' => isset($evt['dependencies']) ? $evt['dependencies'] : '',
                'is_active' => !empty($evt['is_active']),
                'has_sgtm_webhook' => !empty($evt['has_sgtm_webhook']),
                'schema_fields' => isset($evt['schema_fields']) ? $evt['schema_fields'] : [],
                'destinations' => $destMap,
                'active_dest_count' => $activeDestCount,
                'active_dest_names' => $activeDestNames,
                'active_dest_names_str' => $activeDestNamesStr,
                'platform_mappings' => isset($evt['platform_mappings']) ? $evt['platform_mappings'] : [],
                'example' => json_decode($evt['example_json'], true),
            ];
        }
        unset($evt);

        $eventGroups = [
            'discovery' => [
                'id' => 'discovery',
                'title' => 'Product Discovery',
                'badge_color' => '#0284c7',
                'bg' => '#f0f7ff',
                'border' => '#e0f2fe',
                'icon_color' => '#0284c7',
                'icon_bg' => '#e0f2fe',
                'events' => [],
            ],
            'cart_checkout' => [
                'id' => 'cart_checkout',
                'title' => 'Cart & Checkout',
                'badge_color' => '#7c3aed',
                'bg' => '#f5f3ff',
                'border' => '#ede9fe',
                'icon_color' => '#7c3aed',
                'icon_bg' => '#ede9fe',
                'events' => [],
            ],
            'conversion' => [
                'id' => 'conversion',
                'title' => 'Conversion',
                'badge_color' => '#16a34a',
                'bg' => '#f0fdf4',
                'border' => '#dcfce7',
                'icon_color' => '#16a34a',
                'icon_bg' => '#dcfce7',
                'events' => [],
            ],
        ];

        $eventMeta = [
            'view_item_list' => ['group' => 'discovery', 'label' => 'View Item List', 'icon' => 'icon-th-list'],
            'view_search_results' => ['group' => 'discovery', 'label' => 'Search Results', 'icon' => 'icon-search'],
            'view_item' => ['group' => 'discovery', 'label' => 'View Item', 'icon' => 'icon-cube'],
            'add_to_wishlist' => ['group' => 'cart_checkout', 'label' => 'Add to Wishlist', 'icon' => 'icon-heart'],
            'add_to_cart' => ['group' => 'cart_checkout', 'label' => 'Add to Cart', 'icon' => 'icon-shopping-cart'],
            'remove_from_cart' => ['group' => 'cart_checkout', 'label' => 'Remove from Cart', 'icon' => 'icon-minus-circle'],
            'view_cart' => ['group' => 'cart_checkout', 'label' => 'View Cart', 'icon' => 'icon-folder-open'],
            'begin_checkout' => ['group' => 'cart_checkout', 'label' => 'Begin Checkout', 'icon' => 'icon-credit-card'],
            'add_shipping_info' => ['group' => 'cart_checkout', 'label' => 'Add Shipping Info', 'icon' => 'icon-truck'],
            'add_payment_info' => ['group' => 'cart_checkout', 'label' => 'Add Payment Info', 'icon' => 'icon-lock'],
            'purchase' => ['group' => 'conversion', 'label' => 'Purchase', 'icon' => 'icon-gift'],
        ];

        $advFlatEvents = [];
        foreach ($eventMeta as $evtKey => $meta) {
            if (isset($supportedAdvEvents[$evtKey])) {
                $evtObj = $supportedAdvEvents[$evtKey];
                $evtObj['key'] = $evtKey;
                $evtObj['label'] = $meta['label'];
                $evtObj['icon'] = $meta['icon'];
                $eventGroups[$meta['group']]['events'][] = $evtObj;
                $advFlatEvents[] = $evtObj;
            }
        }

        $activeTab = Tools::getValue('active_tab', 'tab-config');

        $this->context->smarty->assign([
            'module_name' => $this->name,
            'module_display_name' => $this->displayName,
            'module_version' => $this->version,
            'module_logo' => $this->_path . 'logo.png',
            'module_path' => $this->_path,
            'form_action' => $this->context->link->getAdminLink('AdminModules', true)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name,
            'admin_token' => Tools::getAdminTokenLite('AdminModules'),
            'ajax_admin_url' => $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name,
            'active_tab' => $activeTab,
            'config_values' => [
                'PS_GTM_CONTAINER_ID' => $gtmId,
                'PS_GTM_EVENT_PREFIX' => Configuration::get('PS_GTM_EVENT_PREFIX'),
                'PS_GTM_CATALOG_ID_STRATEGY' => $catalogStrategy,
                'PS_SGTM_TRANSPORT_URL' => $sgtmUrl,
                'PS_SGTM_WEBHOOK_ENDPOINT_PATH' => $endpointPath,
                'PS_GTM_RAW_HEAD_CODE' => Configuration::get('PS_GTM_RAW_HEAD_CODE'),
                'PS_GTM_RAW_BODY_CODE' => Configuration::get('PS_GTM_RAW_BODY_CODE'),
                'PS_GTM_WEBHOOK_REFUND_ENABLED' => Configuration::get('PS_GTM_WEBHOOK_REFUND_ENABLED') !== '0',
                'PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED' => Configuration::get('PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED') !== '0',
                'PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED' => Configuration::get('PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED') !== '0',
                'PS_GTM_ADV_MASTER_ENABLED' => $advMaster,
                'PS_GTM_ADV_META_ENABLED' => Configuration::get('PS_GTM_ADV_META_ENABLED') !== '0',
                'PS_GTM_ADV_TIKTOK_ENABLED' => Configuration::get('PS_GTM_ADV_TIKTOK_ENABLED') !== '0',
                'PS_GTM_ADV_GADS_ENABLED' => Configuration::get('PS_GTM_ADV_GADS_ENABLED') !== '0',
                'PS_GTM_ADV_GADS_CART_DATA' => Configuration::get('PS_GTM_ADV_GADS_CART_DATA') !== '0',
                'PS_GTM_ADV_GADS_MERCHANT_ID' => (string)Configuration::get('PS_GTM_ADV_GADS_MERCHANT_ID'),
                'PS_GTM_ADV_PINTEREST_ENABLED' => Configuration::get('PS_GTM_ADV_PINTEREST_ENABLED') !== '0',
                'PS_GTM_ADV_MICROSOFT_ENABLED' => Configuration::get('PS_GTM_ADV_MICROSOFT_ENABLED') !== '0',
                'PS_GTM_ADV_OPENAI_ENABLED' => Configuration::get('PS_GTM_ADV_OPENAI_ENABLED') !== '0',
                'PS_GTM_ADV_SNAPCHAT_ENABLED' => Configuration::get('PS_GTM_ADV_SNAPCHAT_ENABLED') !== '0',
            ],
            'canonical_events' => $eventsCatalog,
            'events_total' => count($eventsCatalog),
            'active_platforms_count' => $activePlatformsCount,
            'meta_events_count' => count($metaActive),
            'gads_events_count' => count($gadsActive),
            'tiktok_events_count' => count($tiktokActive),
            'pinterest_events_count' => count($pinterestActive),
            'microsoft_events_count' => count($microsoftActive),
            'openai_events_count' => count($openaiActive),
            'snapchat_events_count' => count($snapchatActive),
            'meta_active_events' => $metaActive,
            'gads_active_events' => $gadsActive,
            'tiktok_active_events' => $tiktokActive,
            'pinterest_active_events' => $pinterestActive,
            'microsoft_active_events' => $microsoftActive,
            'openai_active_events' => $openaiActive,
            'snapchat_active_events' => $snapchatActive,
            'adv_event_groups' => $eventGroups,
            'adv_flat_events' => $advFlatEvents,
            'adv_total_events_count' => count($advFlatEvents),
            'platform_cards_html' => $this->renderAdvertisingMatrixTable(),
            'catalog_strategy_help_html' => $this->renderCatalogStrategyHelp(),
            'diagnostics' => [
                'gtm_id_valid' => $gtmIdValid,
                'gtm_id_msg' => $gtmIdMsg,
                'target_endpoint' => $targetEndpoint,
                'sample_catalog_id' => $sampleCatalogId,
                'purchase_log_count' => $purchaseLogCount,
                'php_version' => PHP_VERSION,
                'curl_status' => function_exists('curl_init') ? 'Enabled (cURL ready)' : 'Disabled',
                'openssl_status' => extension_loaded('openssl') ? 'Enabled (TLS 1.3)' : 'Disabled',
                'memory_limit' => ini_get('memory_limit') ?: '256M',
                'registered_hooks_count' => $registeredHooksCount,
            ],
            'events_schemas_json' => json_encode($schemasJsonMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        try {
            return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
        } catch (\Throwable $e) {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog(
                    '[ps_gtm_datalayer] Smarty rendering exception: ' . $e->getMessage(),
                    3,
                    null,
                    'ps_gtm_datalayer'
                );
            }
            return $this->displayError(sprintf(
                $this->l('Error rendering configuration template: %s'),
                htmlspecialchars($e->getMessage())
            ));
        }
    }

    public static function isEventEnabled($eventName)
    {
        $raw = Configuration::get('PS_GTM_ACTIVE_EVENTS');
        if ($raw === false || $raw === null || $raw === '' || $raw === '[]') {
            return true;
        }
        $active = json_decode($raw, true);
        if (!is_array($active) || empty($active)) {
            return true;
        }
        return in_array($eventName, $active, true);
    }

    public static function getCanonicalEventsCatalog()
    {
        return [
            [
                'name' => 'view_item_list',
                'label' => 'View Item List',
                'icon' => 'icon-th-list',
                'category' => 'discovery',
                'category_label' => 'Discovery',
                'desc' => 'Viewing category, search results, brand/manufacturer, or catalog listing',
                'trigger_hook' => 'hookDisplayHeader (CategoryController / ManufacturerController)',
                'dependencies' => 'CategoryController, ManufacturerController, or SupplierController',
                'has_sgtm_webhook' => false,
                'is_active' => self::isEventEnabled('view_item_list'),
                'schema_fields' => [
                    ['name' => 'item_list_id', 'type' => 'string', 'source' => 'category_{id_category}', 'destinations' => 'GA4: item_list_id, Meta: content_category', 'desc' => 'Unique category or listing identifier'],
                    ['name' => 'item_list_name', 'type' => 'string', 'source' => 'Category::name', 'destinations' => 'GA4: item_list_name, Pinterest: category_name', 'desc' => 'Authoritative localized category name'],
                    ['name' => 'items', 'type' => 'array', 'source' => 'Backend product models', 'destinations' => 'GA4: items, Meta: contents, GAds: ecomm_prodid', 'desc' => 'Array of displayed catalog items'],
                ],
                'platform_mappings' => [
                    'meta' => "fbq('trackCustom', 'ViewCategory', { content_type: 'product_group', content_ids: ['...'], contents: [...] });",
                    'gads' => "gtag('event', 'view_item_list', { item_list_id: 'category_3', items: [...] });",
                    'tiktok' => "ttq.track('ViewContent', { content_type: 'product_group', contents: [...] });",
                    'pinterest' => "pintrk('track', 'ViewCategory', { category_name: 'Clothes', line_items: [...] });",
                    'microsoft' => "uetq.push('event', 'view_item_list', { ecomm_pagetype: 'category', items: [...] });",
                    'openai' => "oaiq('measure', 'contents_viewed', { type: 'contents', contents: [{ id: '1', name: 'Hummingbird Printed T-Shirt', content_type: 'product_group' }] });",
                    'snapchat' => "snaptr('track', 'LIST_VIEW', { item_category: 'Clothes', item_ids: ['1', '2'] });",
                ],
                'example_json' => json_encode([
                    'event' => 'view_item_list',
                    'ecommerce' => [
                        'item_list_id' => 'category_3',
                        'item_list_name' => 'Clothes',
                        'items' => [
                            [
                                'item_id' => '1_1',
                                'item_name' => 'Hummingbird Printed T-Shirt',
                                'price' => 23.90,
                                'item_brand' => 'Studio Design',
                                'item_category' => 'Clothes',
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'name' => 'view_search_results',
                'label' => 'View Search Results',
                'icon' => 'icon-search',
                'category' => 'discovery',
                'category_label' => 'Discovery',
                'desc' => 'Customer viewing on-site search results with search keyword and matching products',
                'trigger_hook' => 'hookDisplayHeader (SearchController)',
                'dependencies' => 'SearchController or third-party search modules (Warehouse, Leo)',
                'has_sgtm_webhook' => false,
                'is_active' => self::isEventEnabled('view_search_results'),
                'schema_fields' => [
                    ['name' => 'search_term', 'type' => 'string', 'source' => 'Authoritative query string', 'destinations' => 'GA4: search_term, Meta: search_string, TikTok: query, Pinterest: search_query', 'desc' => 'Customer search term'],
                    ['name' => 'items', 'type' => 'array', 'source' => 'Search result product models', 'destinations' => 'GA4: items, GAds: ecomm_prodid', 'desc' => 'Matching search result items'],
                ],
                'platform_mappings' => [
                    'meta' => "fbq('track', 'Search', { search_string: 't-shirt', content_ids: ['1_1'] });",
                    'gads' => "gtag('event', 'view_search_results', { search_term: 't-shirt', items: [...] });",
                    'tiktok' => "ttq.track('Search', { query: 't-shirt', content_ids: ['1_1'] });",
                    'pinterest' => "pintrk('track', 'Search', { search_query: 't-shirt', line_items: [...] });",
                    'microsoft' => "uetq.push('event', 'view_search_results', { search_term: 't-shirt', items: [...] });",
                    'openai' => "oaiq('measure', 'contents_viewed', { type: 'contents', contents: [{ id: '1_1', name: 'Hummingbird Printed T-Shirt', content_type: 'product' }] });",
                    'snapchat' => "snaptr('track', 'SEARCH', { search_string: 't-shirt', item_ids: ['1_1'] });",
                ],
                'example_json' => json_encode([
                    'event' => 'view_search_results',
                    'ecommerce' => [
                        'search_term' => 't-shirt',
                        'items' => [
                            [
                                'item_id' => '1_1',
                                'item_name' => 'Hummingbird Printed T-Shirt',
                                'price' => 23.90,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'name' => 'view_item',
                'label' => 'View Item (PDP & Variants)',
                'icon' => 'icon-eye',
                'category' => 'interaction',
                'category_label' => 'Interaction',
                'desc' => 'Product detail page viewed or a variant selected',
                'trigger_hook' => 'hookDisplayHeader (ProductController) / updatedProduct event bus',
                'dependencies' => 'ProductController, PrestaShop Combination Engine & prestashop.on(updatedProduct)',
                'has_sgtm_webhook' => false,
                'is_active' => self::isEventEnabled('view_item'),
                'schema_fields' => [
                    ['name' => 'item_id', 'type' => 'string', 'source' => 'ProductIdentityResolver', 'destinations' => 'GA4: item_id, Meta: content_ids, GAds: ecomm_prodid, TikTok: content_id', 'desc' => 'Catalog product identifier'],
                    ['name' => 'item_name', 'type' => 'string', 'source' => 'Product::name', 'destinations' => 'GA4: item_name, Meta: content_name', 'desc' => 'Product title'],
                    ['name' => 'price', 'type' => 'number', 'source' => 'PricingEngine', 'destinations' => 'GA4: price, Meta: value, Pinterest: value', 'desc' => 'Exact customer-facing price with tax'],
                    ['name' => 'item_variant', 'type' => 'string', 'source' => 'Combination attributes', 'destinations' => 'GA4: item_variant', 'desc' => 'Combination attributes string'],
                ],
                'platform_mappings' => [
                    'meta' => "fbq('track', 'ViewContent', { content_ids: ['68_299'], content_name: 'Sip-N-Snack Cup', value: 13.00, currency: 'EUR' });",
                    'gads' => "gtag('event', 'view_item', { currency: 'EUR', value: 13.00, items: [{ id: '68_299', price: 13.00 }] });",
                    'tiktok' => "ttq.track('ViewContent', { content_id: '68_299', content_name: 'Sip-N-Snack Cup', value: 13.00, currency: 'EUR' });",
                    'pinterest' => "pintrk('track', 'PageVisit', { line_items: [{ product_id: '68_299' }], value: 13.00, currency: 'EUR' });",
                    'microsoft' => "uetq.push('event', 'view_item', { ecomm_prodid: '68_299', ecomm_totalvalue: 13.00, items: [...] });",
                    'openai' => "oaiq('measure', 'contents_viewed', { type: 'contents', contents: [{ id: '68_299', name: 'Sip-N-Snack Cup', content_type: 'product' }] });",
                    'snapchat' => "snaptr('track', 'VIEW_CONTENT', { price: 13.00, currency: 'EUR', item_ids: ['68_299'], item_category: 'Accessories' });",
                ],
                'example_json' => json_encode([
                    'event' => 'view_item',
                    'ecommerce' => [
                        'currency' => 'EUR',
                        'value' => 13.00,
                        'items' => [
                            [
                                'item_id' => '68_299',
                                'item_name' => 'Sip-N-Snack Cup',
                                'item_variant' => 'Colour: Stahlblau',
                                'price' => 13.00,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'name' => 'add_to_wishlist',
                'label' => 'Add to Wishlist',
                'icon' => 'icon-heart',
                'category' => 'interaction',
                'category_label' => 'Interaction',
                'desc' => 'Customer adding a product to wishlist/favorites via ps_wishlist or blockwishlist',
                'trigger_hook' => 'WishlistIntegrationLayer interception',
                'dependencies' => 'WishlistIntegrationLayer with ps_wishlist or blockwishlist module',
                'has_sgtm_webhook' => false,
                'is_active' => self::isEventEnabled('add_to_wishlist'),
                'schema_fields' => [
                    ['name' => 'currency', 'type' => 'string', 'source' => 'Currency ISO code', 'destinations' => 'GA4, Meta, TikTok, Pinterest', 'desc' => 'Active store currency'],
                    ['name' => 'value', 'type' => 'number', 'source' => 'PricingEngine', 'destinations' => 'GA4, Meta, Pinterest', 'desc' => 'Financial value of added product'],
                    ['name' => 'items', 'type' => 'array', 'source' => 'ProductIdentityResolver', 'destinations' => 'GA4: items, Meta: contents', 'desc' => 'Wishlist product item details'],
                ],
                'platform_mappings' => [
                    'meta' => "fbq('track', 'AddToWishlist', { content_ids: ['1_1'], value: 23.90, currency: 'EUR' });",
                    'gads' => "gtag('event', 'add_to_wishlist', { currency: 'EUR', value: 23.90, items: [{ id: '1_1' }] });",
                    'tiktok' => "ttq.track('AddToWishlist', { content_id: '1_1', value: 23.90, currency: 'EUR' });",
                    'pinterest' => "pintrk('track', 'AddToWishList', { line_items: [{ product_id: '1_1' }], value: 23.90, currency: 'EUR' });",
                    'microsoft' => "uetq.push('event', 'add_to_wishlist', { ecomm_prodid: '1_1', items: [...] });",
                    'openai' => "oaiq('measure', 'custom', { type: 'custom' }, { custom_event_name: 'add_to_wishlist' });",
                    'snapchat' => "snaptr('track', 'ADD_TO_WISHLIST', { price: 23.90, currency: 'EUR', item_ids: ['1_1'] });",
                ],
                'example_json' => json_encode([
                    'event' => 'add_to_wishlist',
                    'ecommerce' => [
                        'currency' => 'EUR',
                        'value' => 23.90,
                        'items' => [
                            [
                                'item_id' => '1_1',
                                'item_name' => 'Hummingbird Printed T-Shirt',
                                'price' => 23.90,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'name' => 'add_to_cart',
                'label' => 'Add to Cart',
                'icon' => 'icon-cart-plus',
                'category' => 'cart',
                'category_label' => 'Cart',
                'desc' => 'A product is added to the shopping cart',
                'trigger_hook' => 'actionCartUpdateQuantityBefore / prestashop.on(updateCart)',
                'dependencies' => 'actionCartUpdateQuantityBefore hook & prestashop.on(updateCart) event bus',
                'has_sgtm_webhook' => false,
                'is_active' => self::isEventEnabled('add_to_cart'),
                'schema_fields' => [
                    ['name' => 'currency', 'type' => 'string', 'source' => 'Cart currency ISO', 'destinations' => 'GA4, Meta, TikTok, Pinterest, UET', 'desc' => 'Currency code'],
                    ['name' => 'value', 'type' => 'number', 'source' => 'Added delta quantity * unit price', 'destinations' => 'GA4, Meta, Pinterest, UET', 'desc' => 'Monetary value of added items'],
                    ['name' => 'items', 'type' => 'array', 'source' => 'Backend resolved item delta', 'destinations' => 'GA4: items, Meta: contents, GAds: ecomm_prodid', 'desc' => 'Item array with quantity delta'],
                ],
                'platform_mappings' => [
                    'meta' => "fbq('track', 'AddToCart', { content_ids: ['68_299'], value: 26.00, currency: 'EUR', contents: [...] });",
                    'gads' => "gtag('event', 'add_to_cart', { currency: 'EUR', value: 26.00, items: [{ id: '68_299', quantity: 2 }] });",
                    'tiktok' => "ttq.track('AddToCart', { content_id: '68_299', value: 26.00, currency: 'EUR', contents: [...] });",
                    'pinterest' => "pintrk('track', 'AddToCart', { line_items: [...], value: 26.00, currency: 'EUR' });",
                    'microsoft' => "uetq.push('event', 'add_to_cart', { revenue_value: 26.00, currency: 'EUR', items: [...] });",
                    'openai' => "oaiq('measure', 'items_added', { type: 'contents', amount: 2600, currency: 'EUR', contents: [{ id: '68_299', quantity: 2, amount: 1300, currency: 'EUR' }] });",
                    'snapchat' => "snaptr('track', 'ADD_CART', { price: 26.00, currency: 'EUR', item_ids: ['68_299'], number_items: 2 });",
                ],
                'example_json' => json_encode([
                    'event' => 'add_to_cart',
                    'ecommerce' => [
                        'currency' => 'EUR',
                        'value' => 26.00,
                        'items' => [
                            [
                                'item_id' => '68_299',
                                'item_name' => 'Sip-N-Snack Cup',
                                'item_variant' => 'Colour: Stahlblau',
                                'price' => 13.00,
                                'quantity' => 2,
                            ],
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'name' => 'remove_from_cart',
                'label' => 'Remove from Cart',
                'icon' => 'icon-trash',
                'category' => 'cart',
                'category_label' => 'Cart',
                'desc' => 'Removing an item or decrementing line item quantity in the cart',
                'trigger_hook' => 'actionCartUpdateQuantityBefore / prestashop.on(updateCart)',
                'dependencies' => 'actionCartUpdateQuantityBefore negative delta resolver',
                'has_sgtm_webhook' => false,
                'is_active' => self::isEventEnabled('remove_from_cart'),
                'schema_fields' => [
                    ['name' => 'currency', 'type' => 'string', 'source' => 'Cart currency ISO', 'destinations' => 'GA4, Meta, TikTok, Pinterest', 'desc' => 'Currency code'],
                    ['name' => 'value', 'type' => 'number', 'source' => 'Removed quantity * unit price', 'destinations' => 'GA4, Meta, Pinterest', 'desc' => 'Monetary value of removed items'],
                    ['name' => 'items', 'type' => 'array', 'source' => 'Backend resolved removal delta', 'destinations' => 'GA4: items, Meta: contents', 'desc' => 'Subtracted item details'],
                ],
                'platform_mappings' => [
                    'meta' => "fbq('trackCustom', 'RemoveFromCart', { content_ids: ['68_299'], value: 13.00, currency: 'EUR' });",
                    'gads' => "gtag('event', 'remove_from_cart', { currency: 'EUR', value: 13.00, items: [{ id: '68_299' }] });",
                    'tiktok' => "ttq.trackCustom('RemoveFromCart', { content_id: '68_299', value: 13.00, currency: 'EUR' });",
                    'pinterest' => "pintrk('trackCustom', 'RemoveFromCart', { line_items: [...], value: 13.00, currency: 'EUR' });",
                    'microsoft' => "uetq.push('event', 'remove_from_cart', { revenue_value: 13.00, currency: 'EUR', items: [...] });",
                    'openai' => "oaiq('measure', 'custom', { type: 'custom' }, { custom_event_name: 'remove_from_cart' });",
                    'snapchat' => "snaptr('track', 'CUSTOM_EVENT_2', { price: 13.00, currency: 'EUR', item_ids: ['68_299'], number_items: 1 });",
                ],
                'example_json' => json_encode([
                    'event' => 'remove_from_cart',
                    'ecommerce' => [
                        'currency' => 'EUR',
                        'value' => 13.00,
                        'items' => [
                            [
                                'item_id' => '68_299',
                                'item_name' => 'Sip-N-Snack Cup',
                                'price' => 13.00,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'name' => 'view_cart',
                'label' => 'View Cart',
                'icon' => 'icon-shopping-cart',
                'category' => 'cart',
                'category_label' => 'Cart',
                'desc' => 'Visiting the shopping cart summary page (/cart)',
                'trigger_hook' => 'hookDisplayHeader (CartController)',
                'dependencies' => 'CartController (/cart summary page)',
                'has_sgtm_webhook' => false,
                'is_active' => self::isEventEnabled('view_cart'),
                'schema_fields' => [
                    ['name' => 'currency', 'type' => 'string', 'source' => 'Cart currency ISO', 'destinations' => 'GA4, Meta, TikTok, Pinterest, UET', 'desc' => 'Cart currency'],
                    ['name' => 'value', 'type' => 'number', 'source' => 'Cart::getOrderTotal(true)', 'destinations' => 'GA4, Meta, Pinterest, UET', 'desc' => 'Cart gross total value'],
                    ['name' => 'items', 'type' => 'array', 'source' => 'Cart::getProducts()', 'destinations' => 'GA4: items, GAds: ecomm_prodid', 'desc' => 'All items currently in cart'],
                ],
                'platform_mappings' => [
                    'meta' => "fbq('track', 'ViewContent', { content_type: 'product', content_ids: ['1_1', '68_299'], num_items: 2 });",
                    'gads' => "gtag('event', 'view_cart', { currency: 'EUR', value: 36.90, items: [...] });",
                    'tiktok' => "ttq.track('Custom', { custom_event_name: 'ViewCart', content_type: 'product', contents: [...], quantity: 2 });",
                    'pinterest' => "pintrk('trackCustom', 'ViewCart', { line_items: [...], value: 36.90, currency: 'EUR' });",
                    'microsoft' => "uetq.push('event', 'view_cart', { revenue_value: 36.90, currency: 'EUR', items: [...] });",
                    'openai' => "oaiq('measure', 'custom', { type: 'custom' }, { custom_event_name: 'view_cart' });",
                    'snapchat' => "snaptr('track', 'CUSTOM_EVENT_4', { price: 36.90, currency: 'EUR', item_ids: ['1_1', '68_299'], number_items: 2 });",
                ],
                'example_json' => json_encode([
                    'event' => 'view_cart',
                    'ecommerce' => [
                        'currency' => 'EUR',
                        'value' => 36.90,
                        'items' => [
                            [
                                'item_id' => '1_1',
                                'item_name' => 'Hummingbird Printed T-Shirt',
                                'price' => 23.90,
                                'quantity' => 1,
                            ],
                            [
                                'item_id' => '68_299',
                                'item_name' => 'Sip-N-Snack Cup',
                                'price' => 13.00,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'name' => 'begin_checkout',
                'label' => 'Begin Checkout',
                'icon' => 'icon-sign-in',
                'category' => 'checkout',
                'category_label' => 'Checkout',
                'desc' => 'Customer proceeding to checkout funnel step 1 (/order)',
                'trigger_hook' => 'hookDisplayHeader (OrderController)',
                'dependencies' => 'OrderController (/order checkout step 1)',
                'has_sgtm_webhook' => false,
                'is_active' => self::isEventEnabled('begin_checkout'),
                'schema_fields' => [
                    ['name' => 'currency', 'type' => 'string', 'source' => 'Cart currency ISO', 'destinations' => 'GA4, Meta: InitiateCheckout, TikTok, Pinterest, UET', 'desc' => 'Order currency'],
                    ['name' => 'value', 'type' => 'number', 'source' => 'Cart total tax incl', 'destinations' => 'GA4, Meta, TikTok, Pinterest', 'desc' => 'Gross checkout value'],
                    ['name' => 'items', 'type' => 'array', 'source' => 'Cart products', 'destinations' => 'GA4: items, Meta: contents', 'desc' => 'Checkout line items'],
                ],
                'platform_mappings' => [
                    'meta' => "fbq('track', 'InitiateCheckout', { content_ids: ['1_1'], num_items: 1, value: 36.90, currency: 'EUR' });",
                    'gads' => "gtag('event', 'begin_checkout', { currency: 'EUR', value: 36.90, items: [...] });",
                    'tiktok' => "ttq.track('InitiateCheckout', { contents: [...], quantity: 1, value: 36.90, currency: 'EUR' });",
                    'pinterest' => "pintrk('track', 'InitiateCheckout', { line_items: [...], value: 36.90, currency: 'EUR' });",
                    'microsoft' => "uetq.push('event', 'begin_checkout', { revenue_value: 36.90, currency: 'EUR', items: [...] });",
                    'openai' => "oaiq('measure', 'checkout_started', { type: 'contents', amount: 3690, currency: 'EUR', contents: [{ id: '1_1', quantity: 1 }] });",
                    'snapchat' => "snaptr('track', 'START_CHECKOUT', { price: 36.90, currency: 'EUR', item_ids: ['1_1'], number_items: 1 });",
                ],
                'example_json' => json_encode([
                    'event' => 'begin_checkout',
                    'ecommerce' => [
                        'currency' => 'EUR',
                        'value' => 36.90,
                        'items' => [
                            [
                                'item_id' => '1_1',
                                'item_name' => 'Hummingbird Printed T-Shirt',
                                'price' => 23.90,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'name' => 'add_shipping_info',
                'label' => 'Add Shipping Info',
                'icon' => 'icon-truck',
                'category' => 'checkout',
                'category_label' => 'Checkout',
                'desc' => 'Selecting a carrier or delivery method in checkout step 3',
                'trigger_hook' => 'actionCarrierProcess / delivery step selection',
                'dependencies' => 'actionCarrierProcess hook & checkout delivery step',
                'has_sgtm_webhook' => false,
                'is_active' => self::isEventEnabled('add_shipping_info'),
                'schema_fields' => [
                    ['name' => 'shipping_tier', 'type' => 'string', 'source' => 'Carrier::name', 'destinations' => 'GA4: shipping_tier', 'desc' => 'Selected delivery provider name'],
                    ['name' => 'value', 'type' => 'number', 'source' => 'Cart total with delivery', 'destinations' => 'GA4, Meta: AddShippingInfo', 'desc' => 'Total order gross value'],
                    ['name' => 'items', 'type' => 'array', 'source' => 'Cart products', 'destinations' => 'GA4: items, Meta: contents', 'desc' => 'Checkout line items'],
                ],
                'platform_mappings' => [
                    'meta' => "fbq('track', 'AddShippingInfo', { content_ids: ['1_1'], value: 41.90, currency: 'EUR' });",
                    'gads' => "gtag('event', 'add_shipping_info', { currency: 'EUR', value: 41.90, shipping_tier: 'DHL Express', items: [...] });",
                    'tiktok' => "ttq.track('AddShippingInfo', { contents: [...], value: 41.90, currency: 'EUR' });",
                    'pinterest' => "pintrk('trackCustom', 'AddShippingInfo', { line_items: [...], value: 41.90, currency: 'EUR' });",
                    'microsoft' => "uetq.push('event', 'add_shipping_info', { revenue_value: 41.90, currency: 'EUR', items: [...] });",
                    'openai' => "oaiq('measure', 'custom', { type: 'custom' }, { custom_event_name: 'add_shipping_info' });",
                    'snapchat' => "snaptr('track', 'CUSTOM_EVENT_3', { price: 41.90, currency: 'EUR', item_ids: ['1_1'] });",
                ],
                'example_json' => json_encode([
                    'event' => 'add_shipping_info',
                    'ecommerce' => [
                        'currency' => 'EUR',
                        'value' => 41.90,
                        'shipping_tier' => 'DHL Express',
                        'items' => [
                            [
                                'item_id' => '1_1',
                                'item_name' => 'Hummingbird Printed T-Shirt',
                                'price' => 23.90,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'name' => 'add_payment_info',
                'label' => 'Add Payment Info',
                'icon' => 'icon-credit-card',
                'category' => 'checkout',
                'category_label' => 'Checkout',
                'desc' => 'Selecting a payment gateway or option in checkout step 4',
                'trigger_hook' => 'Payment gateway selection',
                'dependencies' => 'Checkout payment step & payment gateway selection',
                'has_sgtm_webhook' => false,
                'is_active' => self::isEventEnabled('add_payment_info'),
                'schema_fields' => [
                    ['name' => 'payment_type', 'type' => 'string', 'source' => 'Payment module / method name', 'destinations' => 'GA4: payment_type', 'desc' => 'Gateway name (e.g. PayPal, Bank Wire)'],
                    ['name' => 'value', 'type' => 'number', 'source' => 'Cart total gross', 'destinations' => 'GA4, Meta: AddPaymentInfo', 'desc' => 'Order gross value'],
                    ['name' => 'items', 'type' => 'array', 'source' => 'Cart products', 'destinations' => 'GA4: items, Meta: contents', 'desc' => 'Checkout line items'],
                ],
                'platform_mappings' => [
                    'meta' => "fbq('track', 'AddPaymentInfo', { content_ids: ['1_1'], value: 41.90, currency: 'EUR' });",
                    'gads' => "gtag('event', 'add_payment_info', { currency: 'EUR', value: 41.90, payment_type: 'Credit Card', items: [...] });",
                    'tiktok' => "ttq.track('AddPaymentInfo', { contents: [...], value: 41.90, currency: 'EUR' });",
                    'pinterest' => "pintrk('track', 'AddPaymentInfo', { line_items: [...], value: 41.90, currency: 'EUR' });",
                    'microsoft' => "uetq.push('event', 'add_payment_info', { revenue_value: 41.90, currency: 'EUR', items: [...] });",
                    'openai' => "oaiq('measure', 'custom', { type: 'custom' }, { custom_event_name: 'add_payment_info' });",
                    'snapchat' => "snaptr('track', 'ADD_BILLING', { price: 41.90, currency: 'EUR', item_ids: ['1_1'] });",
                ],
                'example_json' => json_encode([
                    'event' => 'add_payment_info',
                    'ecommerce' => [
                        'currency' => 'EUR',
                        'value' => 41.90,
                        'payment_type' => 'Credit Card (Stripe)',
                        'items' => [
                            [
                                'item_id' => '1_1',
                                'item_name' => 'Hummingbird Printed T-Shirt',
                                'price' => 23.90,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'name' => 'purchase',
                'label' => 'Purchase',
                'icon' => 'icon-check-square-o',
                'category' => 'purchase',
                'category_label' => 'Orders & Returns',
                'desc' => 'Order completed with order ID and item data',
                'trigger_hook' => 'hookDisplayOrderConfirmation / actionValidateOrder',
                'dependencies' => 'hookDisplayOrderConfirmation, actionValidateOrder & gtm_purchase_log table',
                'has_sgtm_webhook' => true,
                'is_active' => self::isEventEnabled('purchase'),
                'schema_fields' => [
                    ['name' => 'transaction_id', 'type' => 'string', 'source' => 'Order::id / reference', 'destinations' => 'GA4, Meta: order_id, GAds, TikTok, Pinterest, UET', 'desc' => 'Unique order identifier with deduplication logging'],
                    ['name' => 'value', 'type' => 'number', 'source' => 'Order::total_paid_tax_incl', 'destinations' => 'GA4, Meta, GAds, TikTok, Pinterest, UET', 'desc' => 'Net financial revenue'],
                    ['name' => 'tax', 'type' => 'number', 'source' => 'Order total tax', 'destinations' => 'GA4: tax, UET: tax', 'desc' => 'Tax amount'],
                    ['name' => 'shipping', 'type' => 'number', 'source' => 'Order shipping tax incl', 'destinations' => 'GA4: shipping, UET: shipping', 'desc' => 'Shipping cost'],
                    ['name' => 'items', 'type' => 'array', 'source' => 'Order line items', 'destinations' => 'GA4: items, Meta: contents, GAds: items', 'desc' => 'Purchased products with unit prices and quantities'],
                    ['name' => 'user_data', 'type' => 'object', 'source' => 'CustomerDataEngine (SHA-256)', 'destinations' => 'GA4, Meta CAPI, TikTok, Pinterest, UET', 'desc' => 'Encrypted email, name, phone, postal code, and country'],
                ],
                'platform_mappings' => [
                    'meta' => "fbq('track', 'Purchase', { content_ids: ['68_299'], value: 17.50, currency: 'EUR', order_id: '1255' });",
                    'gads' => "gtag('event', 'purchase', { transaction_id: '1255', value: 17.50, currency: 'EUR', items: [...] });",
                    'tiktok' => "ttq.track('CompletePayment', { content_id: '68_299', value: 17.50, currency: 'EUR', order_id: '1255' });",
                    'pinterest' => "pintrk('track', 'Checkout', { order_id: '1255', value: 17.50, currency: 'EUR', line_items: [...] });",
                    'microsoft' => "uetq.push('event', 'purchase', { transaction_id: '1255', revenue_value: 17.50, currency: 'EUR', items: [...] });",
                    'openai' => "oaiq('measure', 'order_created', { type: 'contents', amount: 1750, currency: 'EUR', contents: [{ id: '68_299', quantity: 1 }], user: { email_sha256: '...' } });",
                    'snapchat' => "snaptr('track', 'PURCHASE', { price: 17.50, currency: 'EUR', transaction_id: '1255', item_ids: ['68_299'], number_items: 1, user_data: { em: '...' } });",
                ],
                'example_json' => json_encode([
                    'event' => 'purchase',
                    'ecommerce' => [
                        'transaction_id' => '1255',
                        'value' => 17.50,
                        'currency' => 'EUR',
                        'tax' => 2.50,
                        'shipping' => 2.00,
                        'items' => [
                            [
                                'item_id' => '68_299',
                                'item_name' => 'Sip-N-Snack Cup (Colour: Stahlblau)',
                                'price' => 13.00,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                    'user_data' => [
                        'email_address' => '343d1c9d5c5f5daf60fe4c3ef34c0e8869e4153a4697392fa2900617771759c1',
                        'first_name' => '5c6b0208d4104931bb46f713ad38130f31ff160a5e320d63a9c7fb660c6c167b',
                        'last_name' => 'd751f76b33d539f5f12db4e3c006e7f05821591487321469f847312936f2dd4a',
                        'phone_number' => 'aefd09421ab405e7d1b4850f3e37b314cb8dd5a5a18a658064f1bbecf5efcc0c',
                        'address' => [
                            'city' => 'Amsterdam',
                            'postal_code' => '84125',
                            'country' => 'DE',
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'name' => 'refund',
                'label' => 'Refund (Credit Slip)',
                'icon' => 'icon-undo',
                'category' => 'purchase',
                'category_label' => 'Orders & Returns',
                'desc' => 'Dispatched to Server GTM when a Credit Slip is issued in the Back Office',
                'trigger_hook' => 'actionOrderSlipAdd -> ServerSideWebhookDispatcher',
                'dependencies' => 'actionOrderSlipAdd hook & ServerSideWebhookDispatcher',
                'has_sgtm_webhook' => true,
                'is_active' => self::isEventEnabled('refund'),
                'schema_fields' => [
                    ['name' => 'transaction_id', 'type' => 'string', 'source' => 'Order::id', 'destinations' => 'sGTM Webhook Client, GA4 refund, Meta CAPI Refund', 'desc' => 'Original transaction ID to retract or adjust'],
                    ['name' => 'value', 'type' => 'number', 'source' => 'OrderSlip total refunded amount', 'destinations' => 'GA4: value, Meta CAPI: value', 'desc' => 'Refunded financial amount'],
                    ['name' => 'conversion_action', 'type' => 'string', 'source' => '"retract"', 'destinations' => 'Google Ads Offline Retraction, GA4 refund', 'desc' => 'Retraction command for advertising attribution models'],
                    ['name' => 'items', 'type' => 'array', 'source' => 'RefundDataResolver slip items', 'destinations' => 'GA4: items', 'desc' => 'Refunded items and quantities'],
                ],
                'platform_mappings' => [
                    'meta' => 'Meta Conversions API (CAPI) Refund event with retract action',
                    'gads' => 'Google Ads Offline Conversion Adjustment (Retraction) via sGTM',
                    'tiktok' => 'TikTok Events API Order Retraction via sGTM',
                    'pinterest' => 'Pinterest Conversions API Refund via sGTM',
                    'microsoft' => 'Microsoft Advertising Offline Conversion Retraction',
                    'openai' => 'OpenAI ChatGPT Ads Conversion Adjustment via sGTM',
                    'snapchat' => 'Snapchat Conversions API adjustment via sGTM',
                ],
                'example_json' => json_encode([
                    'event_name' => 'refund',
                    'transaction_id' => '1255',
                    'value' => 17.50,
                    'currency' => 'EUR',
                    'conversion_action' => 'retract',
                    'status_transition_type' => 'refund_issued',
                    'items' => [
                        [
                            'item_id' => '68_299',
                            'item_name' => 'Sip-N-Snack Cup (Colour: Stahlblau)',
                            'price' => 13.00,
                            'quantity' => 1,
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'name' => 'order_status_update',
                'label' => 'Order Status Update',
                'icon' => 'icon-refresh',
                'category' => 'purchase',
                'category_label' => 'Orders & Returns',
                'desc' => 'Dispatched to Server GTM on order status transitions (e.g. Bank wire to Payment Accepted)',
                'trigger_hook' => 'actionOrderStatusPostUpdate -> ServerSideWebhookDispatcher',
                'dependencies' => 'actionOrderStatusPostUpdate hook & ServerSideWebhookDispatcher',
                'has_sgtm_webhook' => true,
                'is_active' => self::isEventEnabled('order_status_update'),
                'schema_fields' => [
                    ['name' => 'status_transition_type', 'type' => 'string', 'source' => '"payment_confirmed" | "refund_issued" | "order_canceled"', 'destinations' => 'sGTM Webhook Client router', 'desc' => 'Status transition classification'],
                    ['name' => 'conversion_action', 'type' => 'string', 'source' => '"record" | "retract" | "none"', 'destinations' => 'Google Ads offline conversion adjustments', 'desc' => 'Action required by advertising platform'],
                    ['name' => 'new_status_name', 'type' => 'string', 'source' => 'OrderState::name', 'destinations' => 'sGTM Server container event parameter', 'desc' => 'Human-readable new order status'],
                    ['name' => 'is_paid', 'type' => 'boolean', 'source' => 'OrderState::paid flag', 'destinations' => 'sGTM parameter', 'desc' => 'True if order is now financially confirmed'],
                    ['name' => 'user_data', 'type' => 'object', 'source' => 'CustomerDataEngine (SHA-256)', 'destinations' => 'Google Ads Enhanced Conversions, Meta CAPI', 'desc' => 'Hashed customer email, name, phone, address'],
                ],
                'platform_mappings' => [
                    'meta' => 'Meta Conversions API (CAPI) status transition trigger',
                    'gads' => 'Google Ads Offline Conversion upload on payment_confirmed',
                    'tiktok' => 'TikTok Events API server dispatch on payment_confirmed',
                    'pinterest' => 'Pinterest Conversions API order confirmation dispatch',
                    'microsoft' => 'Microsoft Advertising server offline conversion dispatch',
                ],
                'example_json' => json_encode([
                    'event_name' => 'order_status_update',
                    'transaction_id' => '1255',
                    'order_reference' => 'FTHQKZNUA',
                    'status_transition_type' => 'payment_confirmed',
                    'conversion_action' => 'record',
                    'new_status_id' => 2,
                    'new_status_name' => 'Payment accepted',
                    'is_paid' => true,
                    'currency' => 'EUR',
                    'value' => 17.50,
                    'items' => [
                        [
                            'item_id' => '68_299',
                            'item_name' => 'Sip-N-Snack Cup (Colour: Stahlblau)',
                            'price' => 13.00,
                            'quantity' => 1,
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
        ];
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => [
                'PS_GTM_CONTAINER_ID' => Configuration::get('PS_GTM_CONTAINER_ID'),
                'PS_SGTM_TRANSPORT_URL' => Configuration::get('PS_SGTM_TRANSPORT_URL'),
                'PS_SGTM_WEBHOOK_ENDPOINT_PATH' => Configuration::get('PS_SGTM_WEBHOOK_ENDPOINT_PATH') ?: '/ps-webhook',
                'PS_GTM_RAW_HEAD_CODE' => Configuration::get('PS_GTM_RAW_HEAD_CODE'),
                'PS_GTM_RAW_BODY_CODE' => Configuration::get('PS_GTM_RAW_BODY_CODE'),
                'PS_GTM_EVENT_PREFIX' => Configuration::get('PS_GTM_EVENT_PREFIX'),
                'PS_GTM_CATALOG_ID_STRATEGY' => Configuration::get('PS_GTM_CATALOG_ID_STRATEGY') ?: 'composite',
                'PS_GTM_WEBHOOK_REFUND_ENABLED' => Configuration::get('PS_GTM_WEBHOOK_REFUND_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED' => Configuration::get('PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED' => Configuration::get('PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_MASTER_ENABLED' => Configuration::get('PS_GTM_ADV_MASTER_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_META_ENABLED' => Configuration::get('PS_GTM_ADV_META_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_TIKTOK_ENABLED' => Configuration::get('PS_GTM_ADV_TIKTOK_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_GADS_ENABLED' => Configuration::get('PS_GTM_ADV_GADS_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_PINTEREST_ENABLED' => Configuration::get('PS_GTM_ADV_PINTEREST_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_MICROSOFT_ENABLED' => Configuration::get('PS_GTM_ADV_MICROSOFT_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_OPENAI_ENABLED' => Configuration::get('PS_GTM_ADV_OPENAI_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_MATRIX_TABLE' => $this->renderAdvertisingMatrixTable(),
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm(), $this->getAdvertisingConfigForm()]);
    }

    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Google Tag Manager & DataLayer Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'name' => 'PS_GTM_CONTAINER_ID',
                        'label' => $this->l('GTM Container ID'),
                        'hint' => $this->l('Enter your primary web container ID from Google Tag Manager (e.g. GTM-XXXXXXX).'),
                        'desc' => $this->renderSettingHelp(
                            'container_id',
                            $this->l('Primary Google Tag Manager web container identifier.'),
                            $this->l('Injects the official Google Tag Manager container script into the HTML of all storefront pages.'),
                            $this->l('Required to load GTM tags, GA4 ecommerce events, Meta Pixel, and third-party marketing tags.'),
                            $this->l('Enables execution of your GTM container; all dataLayer events wait for this container to fire associated tags.'),
                            $this->l('Enter your container ID from Google Tag Manager (tagmanager.google.com). Required for standard web tracking.'),
                            'GTM-T8W93L4K'
                        ),
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'name' => 'PS_GTM_EVENT_PREFIX',
                        'label' => $this->l('Data Layer Event Prefix'),
                        'hint' => $this->l('Custom prefix prepended to all dataLayer event names (e.g. raihan_view_item). Leave empty for standard GA4 event names.'),
                        'desc' => $this->renderSettingHelp(
                            'event_prefix',
                            $this->l('Optional prefix added before standard GA4 event names (e.g. ps_view_item).'),
                            $this->l('Prefixes storefront events (e.g. ps_purchase, raihan_view_item) while preserving canonical GA4 names in original_event.'),
                            $this->l('Prevents event collisions when running multiple tracking modules, themes, or agency containers on the same store.'),
                            $this->l('All standard events (view_item, add_to_cart, purchase) fire with your prefix. GTM triggers should match this event name.'),
                            $this->l('Enter an alphanumeric prefix if you need isolated event triggers. Leave empty for default GA4 event names.'),
                            'ps (produces ps_view_item, ps_purchase)'
                        ),
                        'placeholder' => $this->l('Enter your custom prefix (e.g., raihan)'),
                        'size' => 30,
                    ],
                    [
                        'type' => 'select',
                        'name' => 'PS_GTM_CATALOG_ID_STRATEGY',
                        'label' => $this->l('Product Catalog Identifier Strategy'),
                        'hint' => $this->l('Choose which product ID format is sent to advertising feeds (Meta, TikTok, Google Ads).'),
                        'desc' => $this->renderCatalogStrategyHelp(),
                        'options' => [
                            'query' => [
                                [
                                    'id' => 'composite',
                                    'name' => $this->l('Option 1: Composite ID {id_product}_{id_product_attribute} (Recommended for 90% of stores)'),
                                ],
                                [
                                    'id' => 'id_product',
                                    'name' => $this->l('Option 2: Base Product ID {id_product} (Parent-only catalogs)'),
                                ],
                                [
                                    'id' => 'reference_sku',
                                    'name' => $this->l('Option 3: Reference / SKU {sku} (ERP & Barcode feeds)'),
                                ],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'name' => 'PS_SGTM_TRANSPORT_URL',
                        'label' => $this->l('Server-Side GTM Transport URL (Optional)'),
                        'hint' => $this->l('Custom domain endpoint for Server-Side GTM (e.g. https://gtm.yourdomain.com or Stape endpoint).'),
                        'desc' => $this->renderSettingHelp(
                            'sgtm_transport_url',
                            $this->l('First-party domain endpoint for routing dataLayer events through Server-Side GTM.'),
                            $this->l('Re-routes web GTM library requests and event beacons through your first-party server container instead of googletagmanager.com.'),
                            $this->l('Protects tracking from ad blockers, extends Safari ITP cookie lifetime, and enhances user data privacy.'),
                            $this->l('Automatically sets the custom transport URL in the GTM loader script.'),
                            $this->l('Configure only if you have a running Server-Side GTM container (e.g. Stape.io or Google Cloud Run). Leave blank for client-side GTM.'),
                            'https://gtm.yourdomain.com'
                        ),
                        'size' => 50,
                    ],
                    [
                        'type' => 'text',
                        'name' => 'PS_SGTM_WEBHOOK_ENDPOINT_PATH',
                        'label' => $this->l('Server GTM Webhook Path (Optional)'),
                        'hint' => $this->l('HTTP path claimed by your sGTM Client. Default is /ps-webhook. If using Stape Data Client, enter /data.'),
                        'desc' => $this->renderSettingHelp(
                            'sgtm_webhook_endpoint_path',
                            $this->l('Target URL path for Server-to-Server webhooks (refunds, offline orders, status updates).'),
                            $this->l('Default is /ps-webhook for use with the dedicated PrestaShop Webhook Client template.'),
                            $this->l('If you use Stape Data Client in sGTM, set this to /data to transmit pure JSON directly to Data Client.'),
                            $this->l('Can be left blank or overridden with a custom endpoint path if your reverse proxy requires custom routing.'),
                            $this->l('If your Transport URL above already includes a full path, this setting is bypassed automatically.'),
                            '/ps-webhook'
                        ),
                        'size' => 30,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Server-to-Server Refund Webhooks (sGTM)'),
                        'name' => 'PS_GTM_WEBHOOK_REFUND_ENABLED',
                        'hint' => $this->l('Dispatches asynchronous server-to-server refund conversions to your sGTM transport URL when a credit slip is generated.'),
                        'is_bool' => true,
                        'desc' => $this->renderSettingHelp(
                            'webhook_refund',
                            $this->l('Sends server-to-server refund conversions to your Server-Side GTM container when a Credit Slip is issued in the Back Office.'),
                            $this->l('PrestaShop refunds happen inside the admin panel when shoppers are not on your storefront. This webhook bridges the gap by sending refund payloads directly from the server.'),
                            $this->l('Transmits refunded items, quantities, and values directly to GA4, Meta CAPI, and Google Ads without browser dependencies.'),
                            $this->l('Executes via non-blocking cURL upon actionOrderSlipAdd, ensuring instantaneous Back Office performance.'),
                            $this->l('Requires a configured Server-Side GTM Transport URL (e.g. Stape.io or custom sGTM domain). Highly recommended for accurate net revenue reporting.'),
                            'https://your-container.stape.io/g/collect?v=2 (refund event)'
                        ),
                        'values' => [
                            ['id' => 'webhook_refund_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'webhook_refund_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Server-to-Server Offline Order Webhooks (sGTM)'),
                        'name' => 'PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED',
                        'hint' => $this->l('Dispatches purchase conversions to your sGTM container for orders created or validated offline in the Back Office.'),
                        'is_bool' => true,
                        'desc' => $this->renderSettingHelp(
                            'webhook_offline_order',
                            $this->l('Captures and dispatches offline and Back Office orders directly to Server-Side GTM.'),
                            $this->l('Ensures orders created manually by administrators, phone orders, or imported orders are never missed by your advertising attribution models.'),
                            $this->l('Sends full purchase events with line items, customer details, and financials to GA4, Meta CAPI, and Google Ads.'),
                            $this->l('Automatically verifies idempotency via gtm_purchase_log to prevent double-counting of storefront orders.'),
                            $this->l('Enable if you process phone orders or create orders directly in the PrestaShop Back Office.'),
                            'https://your-container.stape.io/g/collect?v=2 (purchase event)'
                        ),
                        'values' => [
                            ['id' => 'webhook_offline_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'webhook_offline_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Server-to-Server Order Status Change Webhooks (sGTM)'),
                        'name' => 'PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED',
                        'hint' => $this->l('Dispatches asynchronous order_status_update events to your sGTM transport URL whenever an order status changes in the Back Office.'),
                        'is_bool' => true,
                        'desc' => $this->renderSettingHelp(
                            'webhook_status_update',
                            $this->l('Captures Back Office order and payment status transitions (e.g. Bank Wire to Payment Accepted, Mirakl accepted, Shipped, Delivered) and dispatches them to Server GTM.'),
                            $this->l('Essential for Bank Wire, Cash on Delivery, or external marketplace orders where payments are confirmed days after initial placement.'),
                            $this->l('Sends order_status_update events with transaction ID, new status ID, status name, is_paid flag, items, and SHA-256 hashed customer data.'),
                            $this->l('Executes via non-blocking cURL on actionOrderStatusPostUpdate with strict timeout protection.'),
                            $this->l('Enables precise Server GTM triggers for Google Ads Offline Conversion Adjustments, Meta CAPI offline events, and ERP reconciliation.'),
                            'https://your-container.stape.io/g/collect?v=2 (order_status_update event)'
                        ),
                        'values' => [
                            ['id' => 'webhook_status_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'webhook_status_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                    [
                        'type' => 'textarea',
                        'name' => 'PS_GTM_RAW_HEAD_CODE',
                        'label' => $this->l('Raw GTM <head> Code (Optional)'),
                        'hint' => $this->l('Paste raw Google Tag Manager container script to inject into HTML head.'),
                        'desc' => $this->renderSettingHelp(
                            'raw_head_code',
                            $this->l('Custom JavaScript script tag injected into the HTML <head> section of all pages.'),
                            $this->l('Injects raw JavaScript directly into the HTML <head> without modifying theme template files.'),
                            $this->l('Allows agencies to deploy custom initialization scripts, Consent Management Platforms, or non-standard container snippets.'),
                            $this->l('Executes early in the page load lifecycle before storefront styles and other tracking scripts.'),
                            $this->l('Use only for custom agency scripts. If GTM Container ID is entered above, the standard script is generated automatically.'),
                            '<script>(function(w,d,s,l,i){...})(window,document,\'script\',\'dataLayer\',\'GTM-XXXXX\');</script>'
                        ),
                        'cols' => 60,
                        'rows' => 5,
                    ],
                    [
                        'type' => 'textarea',
                        'name' => 'PS_GTM_RAW_BODY_CODE',
                        'label' => $this->l('Raw GTM <body> Code (Optional)'),
                        'hint' => $this->l('Paste raw Google Tag Manager noscript tag to inject right after opening body tag.'),
                        'desc' => $this->renderSettingHelp(
                            'raw_body_code',
                            $this->l('Custom noscript iframe fallback injected immediately after the opening <body> tag.'),
                            $this->l('Inserts a <noscript> iframe snippet for visitors or bots with JavaScript disabled in their browsers.'),
                            $this->l('Provides fallback basic page-view verification in non-JavaScript browsing environments.'),
                            $this->l('Executed only when the client browser does not support or has disabled JavaScript.'),
                            $this->l('Use if your GTM setup requires a custom noscript iframe. Otherwise managed automatically when GTM ID is set.'),
                            '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXX" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>'
                        ),
                        'cols' => 60,
                        'rows' => 4,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Core Settings'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];
    }

    protected function getAdvertisingConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Advertising Platform Data Layer Settings'),
                    'icon' => 'icon-bullhorn',
                ],
                'description' => $this->l('Configure dynamic catalog-matching and remarketing payloads for Meta Pixel/CAPI, Google Ads Remarketing, TikTok Events API, Pinterest Tag/CAPI, and Microsoft Advertising (Bing Ads) & UET. Toggle platforms globally or expand each platform below to independently customize event routing.'),
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Advertising DataLayers (Master Switch)'),
                        'name' => 'PS_GTM_ADV_MASTER_ENABLED',
                        'hint' => $this->l('Master toggle. When disabled, all advertising platform namespaces (meta, google_ads, tiktok, pinterest, microsoft_ads) are suppressed from the dataLayer across all events.'),
                        'is_bool' => true,
                        'desc' => $this->renderSettingHelp(
                            'adv_master_enabled',
                            $this->l('Master switch controlling Meta, Pinterest, TikTok, Google Ads, and Microsoft Advertising payloads.'),
                            $this->l('Globally activates or suppresses all advertising-specific namespaces from window.dataLayer across all ecommerce events.'),
                            $this->l('Allows single-click global control over all third-party ad network payloads without losing individual platform event routing preferences.'),
                            $this->l('When enabled, rich advertising payloads are attached. When disabled, only clean GA4 ecommerce payloads are pushed.'),
                            $this->l('Keep Enabled for multi-channel marketing campaigns; Disable if you strictly track Google Analytics 4.'),
                            'meta: { event_name: "InitiateCheckout", ... } attached alongside ecommerce'
                        ),
                        'values' => [
                            ['id' => 'adv_master_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'adv_master_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                    [
                        'type' => 'free',
                        'label' => $this->l('Event-Level Platform Routing Matrix'),
                        'name' => 'PS_GTM_ADV_MATRIX_TABLE',
                        'desc' => $this->l('Expand any supported advertising platform below to configure its master status and independently toggle data layer output for each supported ecommerce event.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Advertising Settings'),
                    'class' => 'btn btn-primary pull-right',
                ],
            ],
        ];
    }

    public static function getSupportedEvents()
    {
        return [
            'view_item_list' => [
                'name' => 'view_item_list',
                'label' => 'View Item List',
                'desc' => 'Viewing category, search results, brand/manufacturer, or catalog listing',
                'meta_spec' => 'fbq: ViewCategory, CAPI: ViewCategory, content_type: product_group, content_ids, contents',
                'tiktok_spec' => 'ttq: ViewContent, Events API: ViewContent, content_type: product_group, contents',
                'gads_spec' => 'ecomm_pagetype: category/searchresults, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: ViewCategory, category_name, line_items, value, currency',
                'microsoft_spec' => 'event_action: view_item_list, ecomm_pagetype: category/searchresults, ecomm_prodid: [ids], ecomm_totalvalue, items',
                'openai_spec' => 'oaiq: contents_viewed, type: contents, contents: [{id: base_id, name, content_type: product_group}]',
                'snapchat_spec' => 'snaptr: LIST_VIEW, CAPI: LIST_VIEW, item_category, item_ids: [ids], dynamic_ads: {content_type: product_group, contents}',
            ],
            'view_search_results' => [
                'name' => 'view_search_results',
                'label' => 'View Search Results',
                'desc' => 'Customer viewing on-site search results with search keyword and matching products',
                'meta_spec' => 'fbq: Search, CAPI: Search, search_string, content_ids, contents',
                'tiktok_spec' => 'ttq: Search, Events API: Search, query, content_ids, contents',
                'gads_spec' => 'event: view_search_results, ecomm_pagetype: searchresults, ecomm_prodid: [ids], search_term, items',
                'pinterest_spec' => 'pintrk: Search, search_query, line_items, product_ids',
                'microsoft_spec' => 'event_action: view_search_results, search_term, ecomm_pagetype: searchresults, ecomm_prodid: [ids], items',
                'openai_spec' => 'oaiq: contents_viewed, type: contents, contents: [{id, name, content_type: product}]',
                'snapchat_spec' => 'snaptr: SEARCH, CAPI: SEARCH, search_string, item_ids: [ids], dynamic_ads: {contents}',
            ],
            'view_item' => [
                'name' => 'view_item',
                'label' => 'View Item (PDP & Variants)',
                'desc' => 'Product detail page view and combination/variant switching',
                'meta_spec' => 'fbq: ViewContent, CAPI: ViewContent, content_type: product, content_ids, content_name, contents',
                'tiktok_spec' => 'ttq: ViewContent, Events API: ViewContent, content_type: product, content_id, content_name, contents',
                'gads_spec' => 'ecomm_pagetype: product, ecomm_prodid: catalog_id, ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: PageVisit, line_items: [{product_id, ...}], value, currency, order_quantity: 1',
                'microsoft_spec' => 'event_action: view_item, ecomm_pagetype: product, ecomm_prodid: catalog_id, ecomm_totalvalue, items',
                'openai_spec' => 'oaiq: contents_viewed, type: contents, contents: [{id: catalog_id, name, content_type: product}]',
                'snapchat_spec' => 'snaptr: VIEW_CONTENT, CAPI: VIEW_CONTENT, price, currency, item_ids: [catalog_id], dynamic_ads: {content_type: product, contents}',
            ],
            'add_to_cart' => [
                'name' => 'add_to_cart',
                'label' => 'Add to Cart',
                'desc' => 'Adding a product or combination to the shopping cart',
                'meta_spec' => 'fbq: AddToCart, CAPI: AddToCart, content_type: product, content_ids, contents, currency, value',
                'tiktok_spec' => 'ttq: AddToCart, Events API: AddToCart, content_type: product, content_id, contents, quantity',
                'gads_spec' => 'ecomm_pagetype: cart, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: AddToCart, line_items: [{product_id, ...}], value, currency, order_quantity',
                'microsoft_spec' => 'event_action: add_to_cart, revenue_value, currency, ecomm_pagetype: cart, ecomm_prodid: [ids], items',
                'openai_spec' => 'oaiq: items_added, type: contents, amount: minor_units, currency, contents: [{id, name, quantity, amount: item_minor_units, currency}]',
                'snapchat_spec' => 'snaptr: ADD_CART, CAPI: ADD_CART, price, currency, item_ids, number_items, dynamic_ads: {contents, num_items}',
            ],
            'remove_from_cart' => [
                'name' => 'remove_from_cart',
                'label' => 'Remove from Cart',
                'desc' => 'Removing an item or decrementing line item quantity in the cart',
                'meta_spec' => 'fbq: Custom (RemoveFromCart), CAPI: Custom, content_ids, contents, currency, value',
                'tiktok_spec' => 'ttq: Custom (RemoveFromCart), Events API: Custom, content_id, contents, quantity',
                'gads_spec' => 'ecomm_pagetype: cart, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: Custom (RemoveFromCart), line_items, value, currency, order_quantity',
                'microsoft_spec' => 'event_action: remove_from_cart, revenue_value, currency, ecomm_pagetype: cart, ecomm_prodid: [ids], items',
                'openai_spec' => 'oaiq: custom, type: custom, options: {custom_event_name: remove_from_cart}',
                'snapchat_spec' => 'snaptr: CUSTOM_EVENT_2 (RemoveFromCart), price, currency, item_ids, number_items',
            ],
            'view_cart' => [
                'name' => 'view_cart',
                'label' => 'View Cart',
                'desc' => 'Visiting the shopping cart summary page (/cart)',
                'meta_spec' => 'fbq: ViewContent, CAPI: ViewContent, content_type: product, content_ids, contents, num_items',
                'tiktok_spec' => 'ttq: Custom (ViewCart), content_type: product, contents, value, currency, quantity',
                'gads_spec' => 'ecomm_pagetype: cart, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: Custom (ViewCart), line_items, value, currency, order_quantity',
                'microsoft_spec' => 'event_action: view_cart, revenue_value, currency, ecomm_pagetype: cart, ecomm_prodid: [ids], items',
                'openai_spec' => 'oaiq: custom, type: custom, custom_event_name: view_cart',
                'snapchat_spec' => 'snaptr: CUSTOM_EVENT_4 (ViewCart), CAPI: CUSTOM_EVENT_4, price, currency, item_ids, number_items, dynamic_ads: {contents}',
            ],
            'begin_checkout' => [
                'name' => 'begin_checkout',
                'label' => 'Begin Checkout',
                'desc' => 'Proceeding to checkout funnel step 1 (/order)',
                'meta_spec' => 'fbq: InitiateCheckout, CAPI: InitiateCheckout, content_ids, num_items, contents, value',
                'tiktok_spec' => 'ttq: InitiateCheckout, Events API: InitiateCheckout, contents, quantity, value',
                'gads_spec' => 'ecomm_pagetype: cart, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: InitiateCheckout, line_items, value, currency, order_quantity',
                'microsoft_spec' => 'event_action: begin_checkout, revenue_value, currency, checkout_step: 1, ecomm_pagetype: cart, items',
                'openai_spec' => 'oaiq: checkout_started, type: contents, amount: minor_units, currency, contents: [{id, name, quantity}]',
                'snapchat_spec' => 'snaptr: START_CHECKOUT, CAPI: START_CHECKOUT, price, currency, item_ids, number_items, dynamic_ads: {contents}',
            ],
            'add_shipping_info' => [
                'name' => 'add_shipping_info',
                'label' => 'Add Shipping Info',
                'desc' => 'Selecting a carrier or delivery method in checkout step 3',
                'meta_spec' => 'fbq: AddShippingInfo, CAPI: AddShippingInfo, content_ids, contents, currency, value',
                'tiktok_spec' => 'ttq: AddShippingInfo, Events API: AddShippingInfo, contents, quantity, currency, value',
                'gads_spec' => 'ecomm_pagetype: cart, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: Custom (AddShippingInfo), line_items, value, currency, property',
                'microsoft_spec' => 'event_action: add_shipping_info, revenue_value, currency, checkout_step: 3, items',
                'openai_spec' => 'oaiq: custom, type: custom, options: {custom_event_name: add_shipping_info}',
                'snapchat_spec' => 'snaptr: CUSTOM_EVENT_3 (AddShippingInfo), price, currency, item_ids',
            ],
            'add_payment_info' => [
                'name' => 'add_payment_info',
                'label' => 'Add Payment Info',
                'desc' => 'Selecting a payment gateway or option in checkout step 4',
                'meta_spec' => 'fbq: AddPaymentInfo, CAPI: AddPaymentInfo, content_ids, contents, currency, value',
                'tiktok_spec' => 'ttq: AddPaymentInfo, Events API: AddPaymentInfo, contents, quantity, currency, value',
                'gads_spec' => 'ecomm_pagetype: cart, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: AddPaymentInfo, line_items, value, currency, property',
                'microsoft_spec' => 'event_action: add_payment_info, revenue_value, currency, checkout_step: 4, items',
                'openai_spec' => 'oaiq: custom, type: custom, options: {custom_event_name: add_payment_info}',
                'snapchat_spec' => 'snaptr: ADD_BILLING, CAPI: ADD_BILLING, price, currency, item_ids',
            ],
            'purchase' => [
                'name' => 'purchase',
                'label' => 'Purchase',
                'desc' => 'Order completed with transaction details, order_totals, and user_data',
                'meta_spec' => 'fbq: Purchase, CAPI: Purchase, order_id, content_ids, num_items, contents, currency, value',
                'tiktok_spec' => 'ttq: CompletePayment, Events API: CompletePayment, order_id, contents, quantity, value, user_data',
                'gads_spec' => 'ecomm_pagetype: purchase, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: Checkout, order_id, value, currency, order_quantity, line_items, user_data',
                'microsoft_spec' => 'event_action: purchase, transaction_id, revenue_value, currency, tax, shipping, coupon, items, pid, ecomm_pagetype: purchase',
                'openai_spec' => 'oaiq: order_created, type: contents, amount: minor_units, currency, contents: [{id, name, quantity}], user: {email_sha256, ...}',
                'snapchat_spec' => 'snaptr: PURCHASE, CAPI: PURCHASE, price, currency, transaction_id, item_ids, number_items, dynamic_ads: {order_id, contents}, user_data: {em, ph, ...}',
            ],
            'add_to_wishlist' => [
                'name' => 'add_to_wishlist',
                'label' => 'Add to Wishlist',
                'desc' => 'Adding an item to wishlist/favorites via ps_wishlist or blockwishlist',
                'meta_spec' => 'fbq: AddToWishlist, CAPI: AddToWishlist, content_ids, contents, currency, value',
                'tiktok_spec' => 'ttq: AddToWishlist, Events API: AddToWishlist, content_id, contents, currency, value',
                'gads_spec' => 'ecomm_pagetype: other, ecomm_prodid: catalog_id, ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: AddToWishList, line_items, value, currency',
                'microsoft_spec' => 'event_action: add_to_wishlist, revenue_value, currency, ecomm_pagetype: other, items',
                'openai_spec' => 'oaiq: custom, type: custom, options: {custom_event_name: add_to_wishlist}',
                'snapchat_spec' => 'snaptr: ADD_TO_WISHLIST, CAPI: ADD_TO_WISHLIST, price, currency, item_ids',
            ],
        ];
    }

    public function renderAdvertisingMatrixTable()
    {
        $events = self::getSupportedEvents();

        $advMeta = Configuration::get('PS_GTM_ADV_META_ENABLED') !== '0';
        $advGads = Configuration::get('PS_GTM_ADV_GADS_ENABLED') !== '0';
        $advTiktok = Configuration::get('PS_GTM_ADV_TIKTOK_ENABLED') !== '0';
        $advPinterest = Configuration::get('PS_GTM_ADV_PINTEREST_ENABLED') !== '0';
        $advMicrosoft = Configuration::get('PS_GTM_ADV_MICROSOFT_ENABLED') !== '0';
        $advOpenAi = Configuration::get('PS_GTM_ADV_OPENAI_ENABLED') !== '0';
        $advSnapchat = Configuration::get('PS_GTM_ADV_SNAPCHAT_ENABLED') !== '0';

        $rawMeta = Configuration::get('PS_GTM_ADV_META_EVENTS');
        $rawTiktok = Configuration::get('PS_GTM_ADV_TIKTOK_EVENTS');
        $rawGads = Configuration::get('PS_GTM_ADV_GADS_EVENTS');
        $rawPinterest = Configuration::get('PS_GTM_ADV_PINTEREST_EVENTS');
        $rawMicrosoft = Configuration::get('PS_GTM_ADV_MICROSOFT_EVENTS');
        $rawOpenAi = Configuration::get('PS_GTM_ADV_OPENAI_EVENTS');
        $rawSnapchat = Configuration::get('PS_GTM_ADV_SNAPCHAT_EVENTS');

        $metaEvents = ($rawMeta !== false && $rawMeta !== null) ? json_decode($rawMeta, true) : array_keys($events);
        $tiktokEvents = ($rawTiktok !== false && $rawTiktok !== null) ? json_decode($rawTiktok, true) : array_keys($events);
        $gadsEvents = ($rawGads !== false && $rawGads !== null) ? json_decode($rawGads, true) : array_keys($events);
        $pinterestEvents = ($rawPinterest !== false && $rawPinterest !== null) ? json_decode($rawPinterest, true) : array_keys($events);
        $microsoftEvents = ($rawMicrosoft !== false && $rawMicrosoft !== null) ? json_decode($rawMicrosoft, true) : array_keys($events);
        $openaiEvents = ($rawOpenAi !== false && $rawOpenAi !== null) ? json_decode($rawOpenAi, true) : array_keys($events);
        $snapchatEvents = ($rawSnapchat !== false && $rawSnapchat !== null) ? json_decode($rawSnapchat, true) : array_keys($events);

        if (!is_array($metaEvents)) $metaEvents = array_keys($events);
        if (!is_array($tiktokEvents)) $tiktokEvents = array_keys($events);
        if (!is_array($gadsEvents)) $gadsEvents = array_keys($events);
        if (!is_array($pinterestEvents)) $pinterestEvents = array_keys($events);
        if (!is_array($microsoftEvents)) $microsoftEvents = array_keys($events);
        if (!is_array($openaiEvents)) $openaiEvents = array_keys($events);
        if (!is_array($snapchatEvents)) $snapchatEvents = array_keys($events);

        $imgBase = $this->_path . 'views/img/';
        $platforms = [
            'meta' => [
                'key' => 'meta',
                'name' => 'Meta Pixel & Conversions API (CAPI)',
                'short_name' => 'Meta',
                'desc' => $this->l('Tracks product views, cart additions, and purchases for Facebook and Instagram Ads with catalog matching and server-side CAPI.'),
                'config_key' => 'PS_GTM_ADV_META_ENABLED',
                'enabled' => $advMeta,
                'active_events' => $metaEvents,
                'icon_img' => $imgBase . 'meta.png',
                'icon_class' => 'icon-facebook',
                'brand_color' => '#1877f2',
                'brand_bg' => '#eaf3fe',
                'spec_key' => 'meta_spec',
            ],
            'gads' => [
                'key' => 'gads',
                'name' => 'Google Ads Dynamic Remarketing',
                'short_name' => 'Google Ads',
                'desc' => $this->l('Powers dynamic retargeting and conversion tracking with cart data across Google Search, Display, and Shopping campaigns.'),
                'config_key' => 'PS_GTM_ADV_GADS_ENABLED',
                'enabled' => $advGads,
                'active_events' => $gadsEvents,
                'icon_img' => $imgBase . 'google_ads.png',
                'icon_class' => 'icon-google',
                'brand_color' => '#ea4335',
                'brand_bg' => '#fdeeed',
                'spec_key' => 'gads_spec',
            ],
            'tiktok' => [
                'key' => 'tiktok',
                'name' => 'TikTok Pixel & Events API',
                'short_name' => 'TikTok',
                'desc' => $this->l('Tracks catalog browsing, cart actions, and completed checkouts for TikTok Ads Manager and TikTok Events API.'),
                'config_key' => 'PS_GTM_ADV_TIKTOK_ENABLED',
                'enabled' => $advTiktok,
                'active_events' => $tiktokEvents,
                'icon_img' => $imgBase . 'tiktok.png',
                'icon_class' => 'icon-music',
                'brand_color' => '#111111',
                'brand_bg' => '#f1f2f6',
                'spec_key' => 'tiktok_spec',
            ],
            'pinterest' => [
                'key' => 'pinterest',
                'name' => 'Pinterest Tag & Conversions API (CAPI)',
                'short_name' => 'Pinterest',
                'desc' => $this->l('Tracks product engagement, cart activity, and checkout conversions for Pinterest shopping ads and Pinterest CAPI.'),
                'config_key' => 'PS_GTM_ADV_PINTEREST_ENABLED',
                'enabled' => $advPinterest,
                'active_events' => $pinterestEvents,
                'icon_img' => $imgBase . 'pinterest.png',
                'icon_class' => 'icon-pinterest',
                'brand_color' => '#bd081c',
                'brand_bg' => '#fdf0f1',
                'spec_key' => 'pinterest_spec',
            ],
            'microsoft' => [
                'key' => 'microsoft',
                'name' => 'Microsoft Advertising (Bing Ads) & UET',
                'short_name' => 'Microsoft Ads',
                'desc' => $this->l('Sends conversion revenue, retail dynamic remarketing, and customer matching to Microsoft Advertising and Universal Event Tracking (UET).'),
                'config_key' => 'PS_GTM_ADV_MICROSOFT_ENABLED',
                'enabled' => $advMicrosoft,
                'active_events' => $microsoftEvents,
                'icon_img' => $imgBase . 'microsoft_ads.png',
                'icon_class' => 'icon-windows',
                'brand_color' => '#008272',
                'brand_bg' => '#e6f7f5',
                'spec_key' => 'microsoft_spec',
            ],
            'openai' => [
                'key' => 'openai',
                'name' => 'OpenAI / ChatGPT Ads Measurement',
                'short_name' => 'OpenAI Ads',
                'desc' => $this->l('Tracks contents viewed, items added, checkout started, and order created in ISO 4217 minor currency units for OpenAI / ChatGPT Ads measurement.'),
                'config_key' => 'PS_GTM_ADV_OPENAI_ENABLED',
                'enabled' => $advOpenAi,
                'active_events' => $openaiEvents,
                'icon_img' => $imgBase . 'openai.png',
                'icon_class' => 'icon-comment',
                'brand_color' => '#10a37f',
                'brand_bg' => '#e8f7f2',
                'spec_key' => 'openai_spec',
            ],
            'snapchat' => [
                'key' => 'snapchat',
                'name' => 'Snapchat Pixel & Conversions API (CAPI)',
                'short_name' => 'Snapchat',
                'desc' => $this->l('Powers full-funnel conversion tracking, catalog dynamic product ads, and EQS customer matching for Snapchat Pixel and Snapchat Conversions API.'),
                'config_key' => 'PS_GTM_ADV_SNAPCHAT_ENABLED',
                'enabled' => $advSnapchat,
                'active_events' => $snapchatEvents,
                'icon_img' => $imgBase . 'snapchat_app.png?v=' . $this->version,
                'icon_class' => 'icon-camera',
                'brand_color' => '#fffc00',
                'brand_bg' => '#fefee6',
                'spec_key' => 'snapchat_spec',
            ],
        ];

        $html = '<div id="ps-gtm-adv-matrix-container" style="max-width: 1080px; margin-top: 5px;">';
        $html .= '<input type="hidden" name="PS_GTM_ADV_SUBMITTED" value="1" />';

        // Scoped Stylesheet
        $html .= '<style>
            #ps-gtm-adv-matrix-container {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            }
            .ps-gtm-toolbar {
                background: #f8f9fa;
                border: 1px solid #dcdde1;
                border-radius: 8px;
                padding: 12px 18px;
                margin-bottom: 16px;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }
            .ps-gtm-platform-card {
                background: #ffffff;
                border: 1px solid #dcdde1;
                border-radius: 8px;
                margin-bottom: 14px;
                overflow: hidden;
                box-shadow: 0 1px 4px rgba(0,0,0,0.03);
                transition: all 0.2s ease;
            }
            .ps-gtm-platform-card:hover {
                border-color: #bdc3c7;
                box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            }
            .ps-gtm-card-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 14px 20px;
                cursor: pointer;
                background: #ffffff;
                user-select: none;
                transition: background 0.15s ease;
            }
            .ps-gtm-card-header:hover {
                background: #fcfdfe;
            }
            .ps-gtm-chevron {
                transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .ps-gtm-chevron.open {
                transform: rotate(180deg);
            }
            .ps-gtm-card-body {
                display: none;
                border-top: 1px solid #edf2f7;
                background: #fafbfc;
                padding: 18px 22px;
            }
            .ps-gtm-switch {
                position: relative;
                display: inline-block;
                width: 44px;
                height: 24px;
                margin: 0;
                vertical-align: middle;
            }
            .ps-gtm-switch input {
                opacity: 0;
                width: 0;
                height: 0;
                position: absolute;
            }
            .ps-gtm-slider {
                position: absolute;
                cursor: pointer;
                top: 0; left: 0; right: 0; bottom: 0;
                background-color: #cbd5e0;
                transition: .25s ease;
                border-radius: 24px;
            }
            .ps-gtm-slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .25s ease;
                border-radius: 50%;
                box-shadow: 0 1px 3px rgba(0,0,0,0.25);
            }
            .ps-gtm-switch input:checked + .ps-gtm-slider {
                background-color: #27ae60;
            }
            .ps-gtm-switch input:focus + .ps-gtm-slider {
                box-shadow: 0 0 1px #27ae60;
            }
            .ps-gtm-switch input:checked + .ps-gtm-slider:before {
                transform: translateX(20px);
            }
            .ps-gtm-switch-sm {
                width: 36px;
                height: 20px;
            }
            .ps-gtm-switch-sm .ps-gtm-slider {
                border-radius: 20px;
            }
            .ps-gtm-switch-sm .ps-gtm-slider:before {
                height: 14px;
                width: 14px;
                left: 3px;
                bottom: 3px;
            }
            .ps-gtm-switch-sm input:checked + .ps-gtm-slider:before {
                transform: translateX(16px);
            }
            .ps-gtm-events-table {
                width: 100%;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                border-collapse: separate;
                border-spacing: 0;
                overflow: hidden;
            }
            .ps-gtm-events-table th {
                background: #f8fafc;
                padding: 12px 16px;
                font-size: 14px;
                font-weight: 700;
                color: #1e293b;
                border-bottom: 1px solid #e2e8f0;
            }
            .ps-gtm-events-table td {
                padding: 14px 16px;
                border-bottom: 1px solid #edf2f7;
                vertical-align: middle;
                font-size: 14px;
                color: #334155;
            }
            .ps-gtm-events-table tr:last-child td {
                border-bottom: none;
            }
            .ps-gtm-events-table tr:hover td {
                background: #f8fafc;
            }
        </style>';

        // Global Toolbar
        $html .= '<div class="ps-gtm-toolbar">';
        $html .= '<div style="display: flex; align-items: center; gap: 10px;">';
        $html .= '<i class="icon-sliders" style="font-size: 18px; color: #0f172a;"></i>';
        $html .= '<span style="font-size: 16px; font-weight: 700; color: #0f172a;">' . $this->l('Global Platform Controls:') . '</span>';
        $html .= '</div>';
        $html .= '<div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">';
        $html .= '<button type="button" class="btn btn-default btn-sm" style="font-size: 13.5px; font-weight: 600;" onclick="psGtmExpandAllPanels(true)"><i class="icon-plus-square"></i> ' . $this->l('Expand All') . '</button>';
        $html .= '<button type="button" class="btn btn-default btn-sm" style="font-size: 13.5px; font-weight: 600;" onclick="psGtmExpandAllPanels(false)"><i class="icon-minus-square"></i> ' . $this->l('Collapse All') . '</button>';
        $html .= '<span style="border-left: 1px solid #cbd5e1; height: 18px; margin: 0 4px;"></span>';
        $html .= '<button type="button" class="btn btn-success btn-sm" style="font-size: 13.5px; font-weight: 600;" onclick="psGtmToggleAllPlatforms(true)"><i class="icon-check-circle"></i> ' . $this->l('Enable All Platforms') . '</button>';
        $html .= '<button type="button" class="btn btn-warning btn-sm" style="font-size: 13.5px; font-weight: 600;" onclick="psGtmToggleAllPlatforms(false)"><i class="icon-ban"></i> ' . $this->l('Disable All Platforms') . '</button>';
        $html .= '</div>';
        $html .= '</div>';

        // Render Platform Accordion Panels
        foreach ($platforms as $pKey => $p) {
            $totalSupportedEvents = count($events);
            $activeCount = 0;
            foreach ($events as $evtK => $evtV) {
                if (in_array($evtK, $p['active_events'])) {
                    $activeCount++;
                }
            }

            $html .= '<div class="ps-gtm-platform-card" id="card_' . $pKey . '">';

            // Header (Accordion Trigger)
            $html .= '<div class="ps-gtm-card-header" onclick="psGtmToggleAccordion(\'' . $pKey . '\')">';

            // Left: Icon & Platform Info
            $html .= '<div style="display: flex; align-items: center;">';
            $html .= '<div style="width: 48px; height: 48px; border-radius: 8px; background: #ffffff; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; margin-right: 16px; flex-shrink: 0; padding: 6px; box-sizing: border-box; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">';
            if (!empty($p['icon_img'])) {
                $html .= '<img src="' . htmlspecialchars($p['icon_img']) . '" alt="' . htmlspecialchars($p['short_name']) . '" style="max-width: 100%; max-height: 100%; object-fit: contain; display: block;" />';
            } else {
                $html .= '<i class="' . $p['icon_class'] . '" style="font-size: 24px; color: ' . $p['brand_color'] . ';"></i>';
            }
            $html .= '</div>';
            $html .= '<div>';
            $html .= '<div style="font-size: 17px; font-weight: 700; color: #0f172a;">' . htmlspecialchars($p['name'], ENT_NOQUOTES, 'UTF-8') . '</div>';
            $html .= '<div style="font-size: 14px; color: #475569; margin-top: 3px;">' . htmlspecialchars($p['desc'], ENT_NOQUOTES, 'UTF-8') . '</div>';
            $html .= '</div>';
            $html .= '</div>';

            // Right: Counter, Status Badge, Master Toggle, Chevron
            $html .= '<div style="display: flex; align-items: center; gap: 14px; flex-shrink: 0;" onclick="event.stopPropagation();">';
            $html .= '<span class="badge" id="counter_' . $pKey . '" style="background: #f1f5f9; color: #1e293b; font-weight: 600; font-size: 13px; padding: 6px 12px; border: 1px solid #cbd5e1;">';
            if (!$p['enabled']) {
                $html .= sprintf($this->l('%d of %d events (Platform Off)'), $activeCount, $totalSupportedEvents);
            } else {
                $html .= sprintf($this->l('%d of %d events active'), $activeCount, $totalSupportedEvents);
            }
            $html .= '</span>';

            $badgeClass = $p['enabled'] ? 'label-success' : 'label-danger';
            $badgeText = $p['enabled'] ? $this->l('Enabled') : $this->l('Disabled');
            $html .= '<span class="label ' . $badgeClass . '" id="badge_' . $pKey . '" style="font-size: 13px; font-weight: 600; padding: 6px 12px;">' . $badgeText . '</span>';

            // Platform Master Toggle Switch
            $html .= '<label class="ps-gtm-switch" title="' . sprintf($this->l('Toggle %s Data Layer Output'), $p['short_name']) . '" style="margin: 0; cursor: pointer;">';
            $html .= '<input type="checkbox" name="' . $p['config_key'] . '" id="master_' . $pKey . '" value="1" ' . ($p['enabled'] ? 'checked="checked"' : '') . ' onchange="psGtmOnMasterToggle(\'' . $pKey . '\', this.checked)">';
            $html .= '<span class="ps-gtm-slider"></span>';
            $html .= '</label>';

            // Chevron Control
            $html .= '<span onclick="psGtmToggleAccordion(\'' . $pKey . '\');" style="cursor: pointer; padding: 4px; display: inline-flex;">';
            $html .= '<i class="icon-chevron-down ps-gtm-chevron" id="chevron_' . $pKey . '" style="font-size: 15px; color: #64748b;"></i>';
            $html .= '</span>';

            $html .= '</div>'; // End Right
            $html .= '</div>'; // End Header

            // Body (Expanded on demand)
            $html .= '<div class="ps-gtm-card-body" id="body_' . $pKey . '">';

            // Subheader Controls Bar
            $html .= '<div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; margin-bottom: 14px; gap: 8px;">';
            $html .= '<div style="font-size: 15px; font-weight: 600; color: #1e293b;">';
            $html .= sprintf($this->l('%s Event Routing Specifications:'), $p['short_name']);
            $html .= '</div>';
            $html .= '<div style="display: flex; gap: 8px;">';
            $html .= '<button type="button" class="btn btn-default btn-sm" style="font-size: 13px; font-weight: 600;" onclick="psGtmPlatformSelectAll(\'' . $pKey . '\', true)"><i class="icon-check" style="color: #16a34a;"></i> ' . $this->l('Enable all events') . '</button>';
            $html .= '<button type="button" class="btn btn-default btn-sm" style="font-size: 13px; font-weight: 600;" onclick="psGtmPlatformSelectAll(\'' . $pKey . '\', false)"><i class="icon-times" style="color: #dc2626;"></i> ' . $this->l('Disable all events') . '</button>';
            $html .= '</div>';
            $html .= '</div>';

            // Warning Notice if Platform is Disabled
            $noticeDisplay = $p['enabled'] ? 'none' : 'block';
            $html .= '<div class="alert alert-warning ps-gtm-disabled-notice" id="notice_' . $pKey . '" style="display: ' . $noticeDisplay . '; padding: 10px 16px; margin-bottom: 16px; font-size: 14px; line-height: 1.5;">';
            $html .= '<i class="icon-warning-sign"></i> <strong>' . sprintf($this->l('%s data layer output is currently disabled.'), $p['short_name']) . '</strong> ' . $this->l('Event selections below are preserved and will reactivate whenever this platform is enabled.');
            $html .= '</div>';

            // Event List Table
            $html .= '<table class="ps-gtm-events-table">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th style="width: 75px; text-align: center;">' . $this->l('Push') . '</th>';
            $html .= '<th style="width: 300px;">' . $this->l('Ecommerce Event') . '</th>';
            $html .= '<th>' . $this->l('Platform DataLayer Specifications') . '</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';

            foreach ($events as $evtK => $e) {
                $isChecked = in_array($evtK, $p['active_events']);

                $html .= '<tr>';

                // Status Column
                $html .= '<td style="text-align: center;">';
                $html .= '<label class="ps-gtm-switch ps-gtm-switch-sm">';
                $html .= '<input type="checkbox" name="adv_' . $pKey . '_' . $evtK . '" class="ps-gtm-evt-cb ps-gtm-evt-' . $pKey . '" data-platform="' . $pKey . '" value="1" ' . ($isChecked ? 'checked="checked"' : '') . ' onchange="psGtmOnEventToggle(\'' . $pKey . '\')">';
                $html .= '<span class="ps-gtm-slider"></span>';
                $html .= '</label>';
                $html .= '</td>';

                // Event Info Column
                $html .= '<td>';
                $html .= '<strong style="color: #0f172a; font-size: 15px;"><code>' . htmlspecialchars($e['name']) . '</code></strong>';
                $html .= '<span style="font-size: 14px; font-weight: 600; color: #334155; margin-left: 6px;">' . htmlspecialchars($e['label']) . '</span>';
                $html .= '<div style="font-size: 13.5px; color: #475569; margin-top: 3px; line-height: 1.4;">' . htmlspecialchars($e['desc']) . '</div>';
                $html .= '</td>';

                // Specification Column
                $html .= '<td>';
                $spec = isset($e[$p['spec_key']]) ? $e[$p['spec_key']] : '';
                $html .= '<div style="font-size: 13px; color: #0f172a; font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; line-height: 1.5;">';
                $html .= htmlspecialchars($spec);
                $html .= '</div>';
                $html .= '</td>';

                $html .= '</tr>';
            }

            $html .= '</tbody>';
            $html .= '</table>';

            if ($pKey === 'gads') {
                $cartDataVal = Configuration::get('PS_GTM_ADV_GADS_CART_DATA') !== '0';
                $gadsMerchantId = (string)Configuration::get('PS_GTM_ADV_GADS_MERCHANT_ID');
                $html .= '<div style="margin-top: 18px; padding: 18px 20px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px;">';
                $html .= '<h4 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #0f172a;"><i class="icon-shopping-cart" style="color: #ea4335;"></i> ' . $this->l('Google Ads Conversions with Cart Data (Basket Reporting)') . '</h4>';
                $html .= '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">';
                $html .= '<div>';
                $html .= '<strong style="font-size: 15px; color: #1e293b;">' . $this->l('Enable Cart Data on Purchase Conversions') . '</strong>';
                $html .= '<div style="font-size: 14px; color: #475569; margin-top: 2px;">' . $this->l('Includes itemized products, quantities, and prices on purchase conversions for Google Ads Basket Cart Data reporting.') . '</div>';
                $html .= '</div>';
                $html .= '<label class="ps-gtm-switch ps-gtm-switch-sm">';
                $html .= '<input type="checkbox" name="PS_GTM_ADV_GADS_CART_DATA" value="1" ' . ($cartDataVal ? 'checked="checked"' : '') . '>';
                $html .= '<span class="ps-gtm-slider"></span>';
                $html .= '</label>';
                $html .= '</div>';
                $html .= '<div style="margin-top: 12px;">';
                $html .= '<label style="font-size: 15px; font-weight: 600; color: #1e293b; display: block; margin-bottom: 6px;">' . $this->l('Google Merchant Center ID (Optional)') . '</label>';
                $html .= '<input type="text" name="PS_GTM_ADV_GADS_MERCHANT_ID" value="' . htmlspecialchars($gadsMerchantId, ENT_NOQUOTES, 'UTF-8') . '" placeholder="e.g. 123456789" class="form-control" style="max-width: 320px; font-size: 15px; padding: 8px 12px;">';
                $html .= '<p class="help-block" style="font-size: 13.5px; color: #475569; margin-top: 5px;">' . $this->l('If provided, automatically populates Merchant Center ID inside cart data.') . '</p>';
                $html .= '</div>';
                $html .= '</div>';
            }

            $html .= '</div>'; // End Body
            $html .= '</div>'; // End Platform Card
        }

        $html .= '</div>'; // End Container

        // Embedded Interactive JavaScript
        $html .= '<script type="text/javascript">
            function psGtmToggleAccordion(platformKey) {
                var body = document.getElementById("body_" + platformKey);
                var chevron = document.getElementById("chevron_" + platformKey);
                if (!body) return;
                var isOpen = (body.style.display === "block");
                body.style.display = isOpen ? "none" : "block";
                if (chevron) {
                    if (isOpen) {
                        chevron.classList.remove("open");
                    } else {
                        chevron.classList.add("open");
                    }
                }
            }

            function psGtmExpandAllPanels(expand) {
                var platforms = ["meta", "gads", "tiktok", "pinterest", "microsoft", "openai"];
                for (var i = 0; i < platforms.length; i++) {
                    var body = document.getElementById("body_" + platforms[i]);
                    var chevron = document.getElementById("chevron_" + platforms[i]);
                    if (body) {
                        body.style.display = expand ? "block" : "none";
                    }
                    if (chevron) {
                        if (expand) {
                            chevron.classList.add("open");
                        } else {
                            chevron.classList.remove("open");
                        }
                    }
                }
            }

            function psGtmOnMasterToggle(platformKey, isChecked) {
                var badge = document.getElementById("badge_" + platformKey);
                var notice = document.getElementById("notice_" + platformKey);
                if (badge) {
                    if (isChecked) {
                        badge.className = "label label-success";
                        badge.textContent = "' . $this->l('Enabled') . '";
                    } else {
                        badge.className = "label label-danger";
                        badge.textContent = "' . $this->l('Disabled') . '";
                    }
                }
                if (notice) {
                    notice.style.display = isChecked ? "none" : "block";
                }
                var masterInputs = document.querySelectorAll("input[name=\"PS_GTM_ADV_" + platformKey.toUpperCase() + "_ENABLED\"]");
                for (var m = 0; m < masterInputs.length; m++) {
                    masterInputs[m].checked = isChecked;
                }
                if (typeof markFormDirty === "function") {
                    markFormDirty();
                }
                psGtmUpdateCounter(platformKey);
            }

            function psGtmPlatformSelectAll(platformKey, state) {
                var cbs = document.querySelectorAll(".ps-gtm-evt-" + platformKey);
                for (var i = 0; i < cbs.length; i++) {
                    cbs[i].checked = state;
                    if (cbs[i].name) {
                        var dupes = document.querySelectorAll("input[name=\"" + cbs[i].name + "\"]");
                        for (var j = 0; j < dupes.length; j++) {
                            dupes[j].checked = state;
                        }
                    }
                }
                if (typeof markFormDirty === "function") {
                    markFormDirty();
                }
                psGtmUpdateCounter(platformKey);
            }

            function psGtmOnEventToggle(platformKey) {
                if (typeof markFormDirty === "function") {
                    markFormDirty();
                }
                psGtmUpdateCounter(platformKey);
            }

            function psGtmUpdateCounter(platformKey) {
                var counter = document.getElementById("counter_" + platformKey);
                if (!counter) return;
                var cbs = document.querySelectorAll(".ps-gtm-evt-" + platformKey);
                var uniqueChecked = {};
                for (var i = 0; i < cbs.length; i++) {
                    if (cbs[i].checked && cbs[i].name) {
                        uniqueChecked[cbs[i].name] = true;
                    }
                }
                var checkedCount = Object.keys(uniqueChecked).length;
                var total = 12;
                var masterCb = document.getElementById("master_" + platformKey);
                var isMasterOn = masterCb ? masterCb.checked : true;
                if (!isMasterOn) {
                    counter.textContent = checkedCount + " of " + total + " ' . $this->l('events (Platform Off)') . '";
                } else {
                    counter.textContent = checkedCount + " of " + total + " ' . $this->l('events active') . '";
                }
            }

            function psGtmToggleAllPlatforms(state) {
                var platforms = ["meta", "gads", "tiktok", "pinterest", "microsoft", "openai"];
                for (var i = 0; i < platforms.length; i++) {
                    psGtmOnMasterToggle(platforms[i], state);
                    var cbs = document.querySelectorAll(".ps-gtm-evt-" + platforms[i]);
                    for (var j = 0; j < cbs.length; j++) {
                        cbs[j].checked = state;
                    }
                }
                if (typeof markFormDirty === "function") {
                    markFormDirty();
                }
            }
        </script>';

        return $html;
    }

    private function renderCatalogStrategyHelp()
    {
        $strategies = [
            'composite' => [
                'id' => 'composite',
                'name' => 'Option 1: Composite Database ID: {id_product}_{id_product_attribute}',
                'badge' => $this->l('Recommended for 90% of stores'),
                'badge_class' => 'label-success',
                'short_desc' => $this->l('Variant-level composite identifiers ensuring exact 1:1 catalog matching for dynamic remarketing.'),
                'when_to_use' => $this->l('Choose this if your advertising product feed (Google Merchant Center Pro, Meta/Facebook Catalog, TikTok, Channable) exports each combination/variant as an individual row. Ensures exact 1:1 catalog matching for variant-level dynamic remarketing.'),
                'format' => '{id_product}_{id_product_attribute}',
                'comb_example' => $this->l('Combination Product (e.g. Product #68 with Variant #299): Sends ID: "68_299" (e.g. Sip-N-Snack Cup in Stahlblau)'),
                'simple_example' => $this->l('Simple Product (e.g. Product #15 without combinations): Sends ID: "15" (automatically falls back cleanly to base database ID)')
            ],
            'id_product' => [
                'id' => 'id_product',
                'name' => 'Option 2: Base Product ID: {id_product}',
                'badge' => $this->l('Parent-only catalogs'),
                'badge_class' => 'label-warning',
                'short_desc' => $this->l('Parent-level tracking that maps all variants strictly to base PrestaShop product IDs.'),
                'when_to_use' => $this->l('Choose this if your advertising feed exports only parent products (1 row per product, ignoring size/color combinations) or if your catalog only lists base database IDs.'),
                'format' => '{id_product}',
                'comb_example' => $this->l('Combination Product (e.g. Product #68 with Variant #299): Always sends parent ID: "68" regardless of which variant or color is viewed'),
                'simple_example' => $this->l('Simple Product (e.g. Product #15): Sends parent ID: "15"')
            ],
            'reference_sku' => [
                'id' => 'reference_sku',
                'name' => 'Option 3: Reference / SKU: {sku}',
                'badge' => $this->l('ERP / Barcode feeds'),
                'badge_class' => 'label-info',
                'short_desc' => $this->l('Alphanumeric SKU tracking for stores synchronized with external ERP, POS, or warehouse feeds.'),
                'when_to_use' => $this->l('Choose this if your store is synchronized with an external ERP, POS, or inventory system (such as SAP, NetSuite, JTL, Odoo) and your feed items are indexed by SKU.'),
                'format' => '{reference}',
                'comb_example' => $this->l('Combination Product (e.g. Product #68 with Variant #299, Reference BHK125-B): Sends ID: "BHK125-B"'),
                'simple_example' => $this->l('Fallback Rule: If an item has no reference configured in PrestaShop Back Office, it automatically falls back gracefully to its database ID ("68_299" or "68").')
            ],
        ];

        $html = '<style>
            #PS_GTM_CATALOG_ID_STRATEGY,
            select[name="PS_GTM_CATALOG_ID_STRATEGY"] {
                width: 100% !important;
                max-width: 720px !important;
                min-width: 480px !important;
                height: 46px !important;
                line-height: 46px !important;
                font-size: 15px !important;
                font-weight: 500 !important;
                color: #0f172a !important;
                background-color: #ffffff !important;
                border: 1.5px solid #cbd5e1 !important;
                border-radius: 6px !important;
                padding: 8px 14px !important;
                display: block !important;
                margin-bottom: 14px !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
            }
            #ps-gtm-strategies-container {
                margin-top: 12px;
                max-width: 860px;
            }
            .ps-gtm-strategy-card {
                background: #ffffff;
                border: 1.5px solid #cbd5e1;
                border-radius: 8px;
                margin-bottom: 14px;
                cursor: pointer;
                transition: all 0.2s ease-in-out;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
                overflow: hidden;
                outline: none;
            }
            .ps-gtm-strategy-card:hover {
                border-color: #64748b;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            }
            .ps-gtm-strategy-card:focus-visible {
                border-color: #2563eb !important;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25) !important;
            }
            .ps-gtm-strategy-card.selected {
                background: #f0fdf4 !important;
                border-color: #10b981 !important;
                box-shadow: 0 4px 14px rgba(16, 185, 129, 0.14) !important;
            }
            .ps-gtm-strategy-main {
                padding: 16px 20px;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .ps-gtm-strategy-top {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
                flex-wrap: wrap;
            }
            .ps-gtm-strategy-title-wrap {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .ps-gtm-strategy-title {
                font-size: 17px;
                font-weight: 700;
                color: #0f172a;
            }
            .ps-gtm-card-radio {
                font-size: 20px;
                color: #94a3b8;
                transition: color 0.2s ease;
            }
            .ps-gtm-strategy-card.selected .ps-gtm-card-radio {
                color: #10b981;
            }
            .ps-gtm-strategy-badge {
                font-size: 13px;
                padding: 5px 12px;
                border-radius: 14px;
                font-weight: 600;
            }
            .ps-gtm-strategy-short-desc {
                padding-left: 32px;
                font-size: 14.5px;
                color: #334155;
                line-height: 1.6;
            }
            .ps-gtm-strategy-actions {
                padding-left: 32px;
                display: flex;
                align-items: center;
            }
            .ps-gtm-strategy-drawer {
                border-top: 1px solid #e2e8f0;
                background: #f8fafc;
                padding: 16px 22px 16px 52px;
                font-size: 14.5px;
                line-height: 1.6;
                color: #1e293b;
            }
            .ps-gtm-strategy-card.selected .ps-gtm-strategy-drawer {
                background: #f8fdf9;
                border-top-color: #dcfce7;
            }
            .ps-gtm-drawer-section {
                margin-bottom: 14px;
            }
            .ps-gtm-drawer-section-last {
                margin-bottom: 0;
            }
            .ps-gtm-section-title {
                display: block;
                font-size: 15px;
                font-weight: 700;
                color: #0f172a;
                margin-bottom: 5px;
            }
            .ps-gtm-section-title i {
                color: #2563eb;
                margin-right: 6px;
            }
            .ps-gtm-strategy-card.selected .ps-gtm-section-title i {
                color: #10b981;
            }
            .ps-gtm-section-text {
                margin: 0;
                color: #334155;
                font-size: 14.5px;
                line-height: 1.6;
            }
            .ps-gtm-example-box {
                background: #ffffff;
                border: 1px solid #cbd5e1;
                border-left: 4px solid #475569;
                border-radius: 6px;
                padding: 12px 16px;
                margin-top: 6px;
            }
            .ps-gtm-strategy-card.selected .ps-gtm-example-box {
                border-left-color: #10b981;
                background: #ffffff;
            }
            .ps-gtm-example-list {
                margin: 0;
                padding-left: 0;
                list-style: none;
                color: #1e293b;
                font-size: 14px;
                line-height: 1.7;
            }
            .ps-gtm-example-list li {
                margin-bottom: 5px;
            }
            .ps-gtm-example-list li:last-child {
                margin-bottom: 0;
            }

            /* Common Setting Explainer & Learn More Styles */
            .ps-gtm-setting-explainer {
                margin-top: 8px;
                font-size: 14px;
                line-height: 1.5;
            }
            .ps-gtm-short-desc-row {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 12px;
            }
            .ps-gtm-short-desc {
                color: #475569;
                font-size: 14px;
            }
            .ps-gtm-learn-more-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #f1f5f9;
                border: 1px solid #cbd5e1;
                border-radius: 14px;
                padding: 4px 12px;
                font-size: 13.5px;
                font-weight: 600;
                color: #1e293b;
                cursor: pointer;
                text-decoration: none !important;
                transition: all 0.2s ease;
                outline: none;
                line-height: 1.3;
            }
            .ps-gtm-learn-more-btn:hover,
            .ps-gtm-learn-more-btn:focus {
                background: #e2e8f0;
                color: #0f172a;
                border-color: #94a3b8;
            }
            .ps-gtm-learn-more-btn:focus-visible {
                box-shadow: 0 0 0 2px #2563eb;
            }
            .ps-gtm-learn-more-btn.is-active {
                background: #dbeafe;
                border-color: #93c5fd;
                color: #1d4ed8;
            }
            .ps-gtm-learn-more-btn .ps-gtm-chevron {
                transition: transform 0.25s ease;
                font-size: 12px;
            }
            .ps-gtm-learn-more-btn.is-active .ps-gtm-chevron {
                transform: rotate(180deg);
            }
            .ps-gtm-expanded-explanation {
                margin-top: 12px;
                background: #f8fafc;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                padding: 16px 20px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            }
            .ps-gtm-exp-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 14px 24px;
            }
            .ps-gtm-exp-item {
                font-size: 14px;
                line-height: 1.6;
            }
            .ps-gtm-exp-label {
                display: block;
                font-weight: 700;
                color: #0f172a;
                margin-bottom: 4px;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.4px;
            }
            .ps-gtm-exp-label i {
                color: #2563eb;
                margin-right: 5px;
            }
            .ps-gtm-exp-val {
                color: #334155;
            }
            .ps-gtm-exp-val code {
                background: #e2e8f0;
                color: #0f172a;
                padding: 3px 8px;
                border-radius: 4px;
                font-size: 13.5px;
                word-break: break-all;
            }
        </style>';

        $html .= '<div id="ps-gtm-strategies-container">';
        $html .= '<p class="text-muted" style="margin-bottom: 14px; font-size: 15px; font-weight: 600; color: #1e293b;">' . $this->l('Click any card below or select in the dropdown above to choose your catalog identifier strategy:') . '</p>';

        foreach ($strategies as $key => $s) {
            $drawerId = 'ps-gtm-strat-drawer-' . $key;
            $html .= '<div class="ps-gtm-strategy-card" data-key="' . htmlspecialchars($key) . '" tabindex="0" role="radio" aria-checked="false">';
            $html .= '<div class="ps-gtm-strategy-main">';

            $html .= '<div class="ps-gtm-strategy-top">';
            $html .= '<div class="ps-gtm-strategy-title-wrap">';
            $html .= '<i class="icon-circle-blank ps-gtm-card-radio"></i>';
            $html .= '<span class="ps-gtm-strategy-title">' . htmlspecialchars($s['name']) . '</span>';
            $html .= '</div>';
            $html .= '<span class="label ' . htmlspecialchars($s['badge_class']) . ' ps-gtm-strategy-badge">' . htmlspecialchars($s['badge']) . '</span>';
            $html .= '</div>';

            $html .= '<div class="ps-gtm-strategy-short-desc">' . htmlspecialchars($s['short_desc']) . '</div>';

            $html .= '<div class="ps-gtm-strategy-actions">';
            $html .= '<button type="button" class="ps-gtm-learn-more-btn" data-target="' . $drawerId . '" aria-expanded="false" aria-controls="' . $drawerId . '">';
            $html .= '<i class="icon-info-circle"></i> <span>' . $this->l('Learn more & examples') . '</span> <i class="icon-angle-down ps-gtm-chevron"></i>';
            $html .= '</button>';
            $html .= '</div>';

            $html .= '</div>'; // .ps-gtm-strategy-main

            $html .= '<div id="' . $drawerId . '" class="ps-gtm-strategy-drawer" style="display: none;" role="region">';

            // When to use section
            $html .= '<div class="ps-gtm-drawer-section">';
            $html .= '<strong class="ps-gtm-section-title"><i class="icon-info-circle"></i> ' . $this->l('When to use:') . '</strong>';
            $html .= '<p class="ps-gtm-section-text">' . $s['when_to_use'] . '</p>';
            $html .= '</div>';

            // Examples section
            $html .= '<div class="ps-gtm-drawer-section ps-gtm-drawer-section-last">';
            $html .= '<strong class="ps-gtm-section-title"><i class="icon-tag"></i> ' . $this->l('PrestaShop Back Office ID Format & Examples:') . '</strong>';
            $html .= '<div class="ps-gtm-example-box">';
            $html .= '<ul class="ps-gtm-example-list">';
            $html .= '<li>' . $s['comb_example'] . '</li>';
            $html .= '<li>' . $s['simple_example'] . '</li>';
            $html .= '</ul>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '</div>'; // .ps-gtm-strategy-drawer

            $html .= '</div>'; // .ps-gtm-strategy-card
        }

        $html .= '</div>';

        $html .= '<script type="text/javascript">
            (function() {
                function applySelectStyles() {
                    var select = document.querySelector("select[name=\'PS_GTM_CATALOG_ID_STRATEGY\']");
                    if (select) {
                        select.style.setProperty("width", "100%", "important");
                        select.style.setProperty("max-width", "720px", "important");
                        select.style.setProperty("min-width", "480px", "important");
                        select.style.setProperty("height", "46px", "important");
                        select.style.setProperty("font-size", "15px", "important");
                        select.style.setProperty("font-weight", "500", "important");
                    }
                }

                function syncStrategyCards() {
                    applySelectStyles();
                    var select = document.querySelector("select[name=\'PS_GTM_CATALOG_ID_STRATEGY\']");
                    if (!select) return;
                    var selectedVal = select.value || "composite";
                    var cards = document.querySelectorAll(".ps-gtm-strategy-card");
                    for (var i = 0; i < cards.length; i++) {
                        var card = cards[i];
                        var key = card.getAttribute("data-key");
                        var radio = card.querySelector(".ps-gtm-card-radio");
                        if (key === selectedVal) {
                            card.classList.add("selected");
                            card.setAttribute("aria-checked", "true");
                            if (radio) {
                                radio.className = "icon-check-circle ps-gtm-card-radio";
                                radio.style.color = "#10b981";
                            }
                        } else {
                            card.classList.remove("selected");
                            card.setAttribute("aria-checked", "false");
                            if (radio) {
                                radio.className = "icon-circle-blank ps-gtm-card-radio";
                                radio.style.color = "#94a3b8";
                            }
                        }
                    }
                }

                function initLearnMoreToggles() {
                    var buttons = document.querySelectorAll(".ps-gtm-learn-more-btn");
                    for (var i = 0; i < buttons.length; i++) {
                        (function(btn) {
                            if (btn.getAttribute("data-initialized") === "1") return;
                            btn.setAttribute("data-initialized", "1");

                            btn.addEventListener("click", function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                var targetId = btn.getAttribute("data-target");
                                var target = document.getElementById(targetId);
                                if (!target) return;

                                var isExpanded = btn.classList.contains("is-active");
                                if (isExpanded) {
                                    btn.classList.remove("is-active");
                                    btn.setAttribute("aria-expanded", "false");
                                    if (window.jQuery) {
                                        $(target).slideUp(200);
                                    } else {
                                        target.style.display = "none";
                                    }
                                } else {
                                    btn.classList.add("is-active");
                                    btn.setAttribute("aria-expanded", "true");
                                    if (window.jQuery) {
                                        $(target).slideDown(200);
                                    } else {
                                        target.style.display = "block";
                                    }
                                }
                            });

                            btn.addEventListener("keydown", function(e) {
                                if (e.key === "Enter" || e.key === " " || e.keyCode === 13 || e.keyCode === 32) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    btn.click();
                                }
                            });
                        })(buttons[i]);
                    }
                }

                function setupCardListeners() {
                    applySelectStyles();
                    var cards = document.querySelectorAll(".ps-gtm-strategy-card");
                    var select = document.querySelector("select[name=\'PS_GTM_CATALOG_ID_STRATEGY\']");
                    for (var i = 0; i < cards.length; i++) {
                        (function(card) {
                            card.addEventListener("click", function(e) {
                                if (e.target.closest(".ps-gtm-learn-more-btn") || e.target.closest(".ps-gtm-strategy-drawer")) {
                                    return;
                                }
                                var key = card.getAttribute("data-key");
                                if (select && select.value !== key) {
                                    select.value = key;
                                    var evt = document.createEvent("HTMLEvents");
                                    evt.initEvent("change", false, true);
                                    select.dispatchEvent(evt);
                                    syncStrategyCards();
                                }
                            });

                            card.addEventListener("keydown", function(e) {
                                if (e.key === "Enter" || e.key === " " || e.keyCode === 13 || e.keyCode === 32) {
                                    if (!e.target.closest(".ps-gtm-learn-more-btn") && !e.target.closest(".ps-gtm-strategy-drawer")) {
                                        e.preventDefault();
                                        card.click();
                                    }
                                }
                            });
                        })(cards[i]);
                    }

                    if (select) {
                        select.addEventListener("change", syncStrategyCards);
                    }
                    syncStrategyCards();
                    initLearnMoreToggles();
                }

                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", setupCardListeners);
                } else {
                    setupCardListeners();
                }
            })();
        </script>';

        return $html;
    }

    public function hookActionCartUpdateQuantityBefore($params)
    {
        try {
            if (!isset($params['product']) || !Validate::isLoadedObject($params['product'])) {
                return;
            }

            $idAttr = isset($params['id_product_attribute']) ? (int)$params['id_product_attribute'] : 0;
            $operator = isset($params['operator']) ? (string)$params['operator'] : 'up';
            $quantity = isset($params['quantity']) ? (int)$params['quantity'] : 1;
            if ($quantity <= 0) {
                $quantity = 1;
            }

            $eventPrefix = Configuration::get('PS_GTM_EVENT_PREFIX');
            $payloadBuilder = new PayloadBuilder($this->context, $eventPrefix);
            $item = $payloadBuilder->formatItem($params['product'], $idAttr, $quantity);

            $canonicalEvent = ($operator === 'down') ? 'remove_from_cart' : 'add_to_cart';
            $this->queuePendingEvent($canonicalEvent, $item);
        } catch (\Throwable $e) {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('ps_gtm_datalayer hookActionCartUpdateQuantityBefore error: ' . $e->getMessage(), 2);
            }
        }
    }

    public function queuePendingEvent($canonicalEvent, $item)
    {
        try {
            if (!self::isEventEnabled($canonicalEvent)) {
                return;
            }

            $events = [];
            if (!empty($this->context->cookie->ps_gtm_pending_events)) {
                $decoded = json_decode($this->context->cookie->ps_gtm_pending_events, true);
                if (is_array($decoded)) {
                    $events = $decoded;
                }
            }

            $eventPrefix = Configuration::get('PS_GTM_EVENT_PREFIX');
            $precision = PlatformAdapter::getCurrencyPrecision($this->context);
            $totalVal = CanonicalDataModel::calculateItemsValue([$item], $precision);
            $currencyCode = $this->context->currency ? (string)$this->context->currency->iso_code : 'EUR';

            $payloadBuilder = new PayloadBuilder($this->context, $eventPrefix);
            $platformNamespaces = $payloadBuilder->buildPlatformNamespaces($canonicalEvent, [$item], $currencyCode, $totalVal);

            $events[] = array_merge([
                'event' => self::formatEventName($canonicalEvent, $eventPrefix),
                'original_event' => $canonicalEvent,
                'ecommerce' => [
                    'currency' => $currencyCode,
                    'value' => $totalVal,
                    'items' => [$item],
                ],
            ], $platformNamespaces);

            $this->context->cookie->ps_gtm_pending_events = json_encode($events);
        } catch (\Throwable $e) {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('ps_gtm_datalayer queuePendingEvent error: ' . $e->getMessage(), 2);
            }
        }
    }

    public function hookActionCarrierProcess($params)
    {
        try {
            $cart = isset($params['cart']) ? $params['cart'] : $this->context->cart;
            if (!Validate::isLoadedObject($cart) || $cart->nbProducts() === 0) {
                return;
            }

            $idCarrier = (int)$cart->id_carrier;
            $carrierName = '';
            if ($idCarrier > 0) {
                $carrier = new Carrier($idCarrier, $this->context->language ? (int)$this->context->language->id : 1);
                if (Validate::isLoadedObject($carrier)) {
                    $carrierName = (string)$carrier->name;
                }
            }
            if (empty($carrierName)) {
                $carrierName = 'Standard Delivery';
            }

            $this->queueCheckoutEvent('add_shipping_info', $cart, ['shipping_tier' => $carrierName]);
        } catch (\Throwable $e) {}
    }

    public function queueCheckoutEvent($canonicalEvent, Cart $cart, $extraEcommerceParams = [])
    {
        if (!Validate::isLoadedObject($cart) || $cart->nbProducts() === 0) {
            return;
        }

        $eventPrefix = Configuration::get('PS_GTM_EVENT_PREFIX');
        $payloadBuilder = new PayloadBuilder($this->context, $eventPrefix);

        if ($canonicalEvent === 'add_shipping_info') {
            $shippingTier = isset($extraEcommerceParams['shipping_tier']) ? $extraEcommerceParams['shipping_tier'] : '';
            $payload = $payloadBuilder->buildAddShippingInfoPayload($shippingTier);
        } elseif ($canonicalEvent === 'add_payment_info') {
            $paymentType = isset($extraEcommerceParams['payment_type']) ? $extraEcommerceParams['payment_type'] : '';
            $payload = $payloadBuilder->buildAddPaymentInfoPayload($paymentType);
        } else {
            return;
        }

        if (!$payload) {
            return;
        }

        $events = [];
        if (!empty($this->context->cookie->ps_gtm_pending_events)) {
            $decoded = json_decode($this->context->cookie->ps_gtm_pending_events, true);
            if (is_array($decoded)) {
                $events = $decoded;
            }
        }

        // Deduplication in cookie: avoid duplicate checkout step events
        foreach ($events as $existing) {
            if (isset($existing['original_event']) && $existing['original_event'] === $canonicalEvent) {
                if ($canonicalEvent === 'add_shipping_info' && isset($existing['ecommerce']['shipping_tier']) && isset($payload['ecommerce']['shipping_tier']) && $existing['ecommerce']['shipping_tier'] === $payload['ecommerce']['shipping_tier']) {
                    return;
                }
                if ($canonicalEvent === 'add_payment_info' && isset($existing['ecommerce']['payment_type']) && isset($payload['ecommerce']['payment_type']) && $existing['ecommerce']['payment_type'] === $payload['ecommerce']['payment_type']) {
                    return;
                }
            }
        }

        $events[] = $payload;
        $this->context->cookie->ps_gtm_pending_events = json_encode($events);
    }

    public function getAndClearPendingEvents()
    {
        if (empty($this->context->cookie->ps_gtm_pending_events)) {
            return [];
        }

        $events = json_decode($this->context->cookie->ps_gtm_pending_events, true);
        unset($this->context->cookie->ps_gtm_pending_events);
        return is_array($events) ? $events : [];
    }

    public function hookDisplayHeader($params)
    {
        try {
            // Self-heal corrupted or empty PS_GTM_ACTIVE_EVENTS
            $rawActive = Configuration::get('PS_GTM_ACTIVE_EVENTS');
            if ($rawActive === '[]' || (is_string($rawActive) && empty(json_decode($rawActive, true)))) {
                Configuration::deleteByName('PS_GTM_ACTIVE_EVENTS');
            }

            $gtmId = Configuration::get('PS_GTM_CONTAINER_ID');
            $sgtmUrl = Configuration::get('PS_SGTM_TRANSPORT_URL');
            $rawHeadCode = Configuration::get('PS_GTM_RAW_HEAD_CODE');
            $eventPrefix = (string)Configuration::get('PS_GTM_EVENT_PREFIX');

            $controller = isset($this->context->controller->php_self) ? $this->context->controller->php_self : '';
            $isSearch = PayloadBuilder::isSearchController($controller, $this->context);
            $payloadBuilder = new PayloadBuilder($this->context, $eventPrefix);

            $initialPayloads = [];
            if ($isSearch) {
                if (self::isEventEnabled('view_search_results')) {
                    $searchQuery = PayloadBuilder::getSearchQuery();
                    if ($searchQuery !== '') {
                        $searchPayload = $payloadBuilder->buildViewSearchResultsPayload($searchQuery, null, [], true);
                        if ($searchPayload) {
                            $initialPayloads[] = $searchPayload;
                        }
                    }
                }
            } else {
                $serverPayload = $payloadBuilder->buildPagePayload($controller, $params);
                if ($serverPayload) {
                    $cEvt = !empty($serverPayload['original_event']) ? $serverPayload['original_event'] : (!empty($serverPayload['event']) ? $serverPayload['event'] : '');
                    if (empty($cEvt) || self::isEventEnabled($cEvt)) {
                        $initialPayloads[] = $serverPayload;
                    }
                }
            }

            // Pre-build combinations dictionary for PDP directly from database models
            $combosMap = [];
            if ($controller === 'product') {
                $idProduct = (int)Tools::getValue('id_product');
                if ($idProduct > 0) {
                    $productObj = new Product($idProduct, false, $this->context->language ? (int)$this->context->language->id : 1);
                    if (Validate::isLoadedObject($productObj)) {
                        $combosMap = ProductIdentityResolver::getProductCombinationsMap($productObj, $this->context->language ? (int)$this->context->language->id : 1);
                    }
                }
            }

            $pendingEvents = $this->getAndClearPendingEvents();
            if (!empty($pendingEvents)) {
                $pendingEvents = array_values(array_filter($pendingEvents, function ($p) {
                    $cEvt = !empty($p['original_event']) ? $p['original_event'] : (!empty($p['event']) ? $p['event'] : '');
                    return empty($cEvt) || self::isEventEnabled($cEvt);
                }));
            }
            $ajaxUrl = $this->context->link->getModuleLink($this->name, 'ajax', ['ajax' => 1]);
            $currencyPrecision = PlatformAdapter::getCurrencyPrecision($this->context);

            // Pre-hydrate Page Context for listing tracking & select_item fallback
            $pageContext = [
                'controller' => $controller,
                'list_id' => '',
                'list_name' => '',
            ];
            if ($controller === 'index') {
                $pageContext['list_id'] = 'home_featured';
                $pageContext['list_name'] = 'Home Page - Featured Products';
            } elseif ($controller === 'category') {
                $category = (isset($this->context->controller->category) && Validate::isLoadedObject($this->context->controller->category))
                    ? $this->context->controller->category
                    : (method_exists($this->context->controller, 'getCategory') ? $this->context->controller->getCategory() : null);
                $idCategory = ($category && Validate::isLoadedObject($category)) ? (int)$category->id : (int)Tools::getValue('id_category');
                if ($idCategory) {
                    if (!$category || !Validate::isLoadedObject($category)) {
                        $category = new Category($idCategory, $this->context->language ? (int)$this->context->language->id : 1);
                    }
                    if (Validate::isLoadedObject($category)) {
                        $pageContext['list_id'] = 'category_' . $idCategory;
                        $pageContext['list_name'] = (string)$category->name;
                    }
                }
            } elseif ($controller === 'product') {
                $pageContext['list_id'] = 'related_products';
                $pageContext['list_name'] = 'Related Products';
            } elseif ($controller === 'cart') {
                $pageContext['list_id'] = 'cross_sell';
                $pageContext['list_name'] = 'Cart Cross-Sell';
            } elseif ($isSearch) {
                $searchQuery = PayloadBuilder::getSearchQuery();
                $pageContext['list_id'] = '';
                $pageContext['list_name'] = '';
                $pageContext['search_term'] = $searchQuery;
            } elseif ($controller === 'prices-drop') {
                $pageContext['list_id'] = 'on_sale';
                $pageContext['list_name'] = 'On Sale Products';
            } elseif ($controller === 'new-products') {
                $pageContext['list_id'] = 'new_products';
                $pageContext['list_name'] = 'New Products';
            } elseif ($controller === 'best-sales') {
                $pageContext['list_id'] = 'best_sellers';
                $pageContext['list_name'] = 'Best Sellers';
            } elseif ($controller === 'manufacturer') {
                $idManufacturer = (int)Tools::getValue('id_manufacturer');
                if ($idManufacturer) {
                    $manufacturer = new Manufacturer($idManufacturer, $this->context->language ? (int)$this->context->language->id : 1);
                    if (Validate::isLoadedObject($manufacturer)) {
                        $pageContext['list_id'] = 'brand_' . $idManufacturer;
                        $pageContext['list_name'] = (string)$manufacturer->name;
                    }
                }
            }

            // Pre-hydrate active cart products map (100% backend-driven, ZERO DOM)
            $cartProductsMap = [];
            $cartProductsData = [];
            if ($this->context->cart && Validate::isLoadedObject($this->context->cart)) {
                $prods = $this->context->cart->getProducts();
                if (is_array($prods)) {
                    $isTaxIncl = PlatformAdapter::isTaxIncluded($this->context);
                    foreach ($prods as $p) {
                        $k = (int)$p['id_product'] . '_' . (int)$p['id_product_attribute'];
                        $cartProductsMap[$k] = (int)$p['cart_quantity'];

                        $priceRaw = $isTaxIncl
                            ? (isset($p['price_wt']) ? (float)$p['price_wt'] : (float)$p['price'])
                            : (isset($p['price']) ? (float)$p['price'] : (float)$p['price_wt']);
                        $price = PricingEngine::roundHalfUp($priceRaw, $currencyPrecision);

                        $cartProductsData[$k] = [
                            'item_id' => (string)$p['id_product'],
                            'variant_id' => !empty($p['id_product_attribute']) ? (string)$p['id_product_attribute'] : '',
                            'sku_id' => !empty($p['reference']) ? (string)$p['reference'] : '',
                            'item_name' => !empty($p['name']) ? (string)$p['name'] : '',
                            'item_brand' => !empty($p['manufacturer_name']) ? (string)$p['manufacturer_name'] : '',
                            'item_category' => !empty($p['category']) ? (string)$p['category'] : '',
                            'item_variant' => !empty($p['attributes']) ? (string)$p['attributes'] : (!empty($p['attributes_small']) ? (string)$p['attributes_small'] : ''),
                            'price' => $price,
                            'quantity' => (int)$p['cart_quantity']
                        ];
                    }
                }
            }

            $advMaster = Configuration::get('PS_GTM_ADV_MASTER_ENABLED') !== '0';
            $advMeta = Configuration::get('PS_GTM_ADV_META_ENABLED') !== '0';
            $advTiktok = Configuration::get('PS_GTM_ADV_TIKTOK_ENABLED') !== '0';
            $advGads = Configuration::get('PS_GTM_ADV_GADS_ENABLED') !== '0';
            $advPinterest = Configuration::get('PS_GTM_ADV_PINTEREST_ENABLED') !== '0';
            $advMicrosoft = Configuration::get('PS_GTM_ADV_MICROSOFT_ENABLED') !== '0';
            $advOpenAi = Configuration::get('PS_GTM_ADV_OPENAI_ENABLED') !== '0';
            $advSnapchat = Configuration::get('PS_GTM_ADV_SNAPCHAT_ENABLED') !== '0';

            $rawMeta = Configuration::get('PS_GTM_ADV_META_EVENTS');
            $rawTiktok = Configuration::get('PS_GTM_ADV_TIKTOK_EVENTS');
            $rawGads = Configuration::get('PS_GTM_ADV_GADS_EVENTS');
            $rawPinterest = Configuration::get('PS_GTM_ADV_PINTEREST_EVENTS');
            $rawMicrosoft = Configuration::get('PS_GTM_ADV_MICROSOFT_EVENTS');
            $rawOpenAi = Configuration::get('PS_GTM_ADV_OPENAI_EVENTS');
            $rawSnapchat = Configuration::get('PS_GTM_ADV_SNAPCHAT_EVENTS');

            $metaEvents = ($rawMeta !== false && $rawMeta !== null) ? json_decode($rawMeta, true) : array_keys(self::getSupportedEvents());
            $tiktokEvents = ($rawTiktok !== false && $rawTiktok !== null) ? json_decode($rawTiktok, true) : array_keys(self::getSupportedEvents());
            $gadsEvents = ($rawGads !== false && $rawGads !== null) ? json_decode($rawGads, true) : array_keys(self::getSupportedEvents());
            $pinterestEvents = ($rawPinterest !== false && $rawPinterest !== null) ? json_decode($rawPinterest, true) : array_keys(self::getSupportedEvents());
            $microsoftEvents = ($rawMicrosoft !== false && $rawMicrosoft !== null) ? json_decode($rawMicrosoft, true) : array_keys(self::getSupportedEvents());
            $openaiEvents = ($rawOpenAi !== false && $rawOpenAi !== null) ? json_decode($rawOpenAi, true) : array_keys(self::getSupportedEvents());
            $snapchatEvents = ($rawSnapchat !== false && $rawSnapchat !== null) ? json_decode($rawSnapchat, true) : array_keys(self::getSupportedEvents());

            $advSettings = [
                'master_enabled' => (bool)$advMaster,
                'meta_enabled' => (bool)$advMeta,
                'tiktok_enabled' => (bool)$advTiktok,
                'gads_enabled' => (bool)$advGads,
                'gads_cart_data' => Configuration::get('PS_GTM_ADV_GADS_CART_DATA') !== '0',
                'gads_merchant_id' => (string)Configuration::get('PS_GTM_ADV_GADS_MERCHANT_ID'),
                'pinterest_enabled' => (bool)$advPinterest,
                'microsoft_enabled' => (bool)$advMicrosoft,
                'openai_enabled' => (bool)$advOpenAi,
                'snapchat_enabled' => (bool)$advSnapchat,
                'meta_events' => is_array($metaEvents) ? $metaEvents : array_keys(self::getSupportedEvents()),
                'tiktok_events' => is_array($tiktokEvents) ? $tiktokEvents : array_keys(self::getSupportedEvents()),
                'gads_events' => is_array($gadsEvents) ? $gadsEvents : array_keys(self::getSupportedEvents()),
                'pinterest_events' => is_array($pinterestEvents) ? $pinterestEvents : array_keys(self::getSupportedEvents()),
                'microsoft_events' => is_array($microsoftEvents) ? $microsoftEvents : array_keys(self::getSupportedEvents()),
                'openai_events' => is_array($openaiEvents) ? $openaiEvents : array_keys(self::getSupportedEvents()),
                'snapchat_events' => is_array($snapchatEvents) ? $snapchatEvents : array_keys(self::getSupportedEvents()),
            ];

            // Pre-hydrate authoritative carrier names directly from database model (Zero DOM)
            $carriersMap = [];
            if (class_exists('Carrier')) {
                $idLang = $this->context->language ? (int)$this->context->language->id : 1;
                $carriers = Carrier::getCarriers($idLang, true, false, false, null, Carrier::ALL_CARRIERS);
                if (is_array($carriers)) {
                    foreach ($carriers as $c) {
                        $carriersMap[(int)$c['id_carrier']] = (string)$c['name'];
                    }
                }
            }

            // Pre-hydrate installed payment method names directly from module instances (Zero DOM)
            $paymentMethodsMap = [];
            if (class_exists('PaymentModule')) {
                $installedPaymentModules = PaymentModule::getInstalledPaymentModules();
                if (is_array($installedPaymentModules)) {
                    foreach ($installedPaymentModules as $pm) {
                        $modInstance = Module::getInstanceByName($pm['name']);
                        if ($modInstance && Validate::isLoadedObject($modInstance)) {
                            $paymentMethodsMap[$pm['name']] = (string)$modInstance->displayName;
                        }
                    }
                }
            }

            // Pre-hydrate authoritative Payment Options Map for checkout step 4
            $paymentOptionsMap = [];
            if ($controller === 'order' && class_exists('PaymentOptionsFinder')) {
                try {
                    $finder = new PaymentOptionsFinder();
                    $presentOptions = $finder->present();
                    if (is_array($presentOptions)) {
                        foreach ($presentOptions as $moduleOptions) {
                            if (is_array($moduleOptions)) {
                                foreach ($moduleOptions as $opt) {
                                    $optId = isset($opt['id']) ? (string)$opt['id'] : '';
                                    $optName = isset($opt['call_to_action_text']) ? (string)$opt['call_to_action_text'] : '';
                                    $modName = isset($opt['module_name']) ? (string)$opt['module_name'] : '';
                                    if ($optId && $optName) {
                                        $paymentOptionsMap[$optId] = $optName;
                                    }
                                    if ($modName && $optName) {
                                        $paymentOptionsMap[$modName] = $optName;
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {}
            }
            if (is_array($paymentMethodsMap)) {
                foreach ($paymentMethodsMap as $pmKey => $pmName) {
                    if (!isset($paymentOptionsMap[$pmKey])) {
                        $paymentOptionsMap[$pmKey] = $pmName;
                    }
                }
            }

            // Pre-hydrate selected carrier name from cart if active
            $selectedCarrierName = '';
            if ($this->context->cart && Validate::isLoadedObject($this->context->cart)) {
                $idCarrier = (int)$this->context->cart->id_carrier;
                if ($idCarrier > 0 && isset($carriersMap[$idCarrier])) {
                    $selectedCarrierName = $carriersMap[$idCarrier];
                }
            }

            // Pre-hydrate active cart voucher / coupon code (GA4 coupon parameter)
            $cartCoupon = '';
            if ($this->context->cart && Validate::isLoadedObject($this->context->cart)) {
                $cartRules = $this->context->cart->getCartRules();
                if (!empty($cartRules)) {
                    $codes = array_filter(array_map(function($r) { return !empty($r['code']) ? trim($r['code']) : ''; }, $cartRules));
                    if (!empty($codes)) {
                        $cartCoupon = implode(', ', $codes);
                    }
                }
            }

            // Pre-hydrate Meta, Pinterest, TikTok, Microsoft Advertising and OpenAI Ads Enhanced Conversions hashed user data
            $metaUserData = null;
            $pinterestUserData = null;
            $tiktokUserData = null;
            $microsoftUserData = null;
            $openaiUserData = null;
            if ($this->context->customer && Validate::isLoadedObject($this->context->customer)) {
                $customerAddress = null;
                if ($this->context->cart && Validate::isLoadedObject($this->context->cart) && !empty($this->context->cart->id_address_delivery)) {
                    $customerAddress = new Address((int)$this->context->cart->id_address_delivery);
                } elseif (!empty($this->context->customer->id)) {
                    $addresses = $this->context->customer->getAddresses($this->context->language ? (int)$this->context->language->id : 1);
                    if (!empty($addresses) && is_array($addresses)) {
                        $firstAddr = reset($addresses);
                        if (!empty($firstAddr['id_address'])) {
                            $customerAddress = new Address((int)$firstAddr['id_address']);
                        }
                    }
                }
                $metaUserData = CustomerDataEngine::buildMetaUserData($this->context->customer, $customerAddress);
                $pinterestUserData = CustomerDataEngine::buildPinterestUserData($this->context->customer, $customerAddress);
                $tiktokUserData = CustomerDataEngine::buildTikTokUserData($this->context->customer, $customerAddress);
                $microsoftUserData = CustomerDataEngine::buildMicrosoftUserData($this->context->customer, $customerAddress);
                $openaiUserData = CustomerDataEngine::buildOpenAiUserData($this->context->customer, $customerAddress);
            }

            $this->context->smarty->assign([
                'gtm_id' => $gtmId,
                'sgtm_url' => $sgtmUrl,
                'raw_head_code' => $rawHeadCode,
                'event_prefix' => $eventPrefix,
                'currency_precision' => $currencyPrecision,
                'ajax_url' => $ajaxUrl,
                'pending_events' => !empty($pendingEvents) ? json_encode($pendingEvents, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'product_combinations' => json_encode($combosMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'cart_products' => !empty($cartProductsMap) ? json_encode($cartProductsMap, JSON_UNESCAPED_SLASHES) : null,
                'cart_products_data' => !empty($cartProductsData) ? json_encode($cartProductsData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'wishlist_capabilities' => json_encode(WishlistIntegrationLayer::getWishlistCapabilities(), JSON_UNESCAPED_SLASHES),
                'catalog_id_strategy' => (Configuration::get('PS_GTM_CATALOG_ID_STRATEGY') ?: 'composite'),
                'advertising_settings' => json_encode($advSettings, JSON_UNESCAPED_SLASHES),
                'carriers_map' => json_encode($carriersMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'payment_methods_map' => json_encode($paymentMethodsMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'payment_options_map' => json_encode($paymentOptionsMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'selected_carrier' => $selectedCarrierName,
                'cart_coupon' => $cartCoupon,
                'meta_user_data' => !empty($metaUserData) ? json_encode($metaUserData, JSON_UNESCAPED_SLASHES) : null,
                'pinterest_user_data' => !empty($pinterestUserData) ? json_encode($pinterestUserData, JSON_UNESCAPED_SLASHES) : null,
                'tiktok_user_data' => !empty($tiktokUserData) ? json_encode($tiktokUserData, JSON_UNESCAPED_SLASHES) : null,
                'microsoft_user_data' => !empty($microsoftUserData) ? json_encode($microsoftUserData, JSON_UNESCAPED_SLASHES) : null,
                'openai_user_data' => !empty($openaiUserData) ? json_encode($openaiUserData, JSON_UNESCAPED_SLASHES) : null,
                'page_context' => json_encode($pageContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'initial_server_payload' => !empty($initialPayloads) ? json_encode((count($initialPayloads) === 1 ? $initialPayloads[0] : $initialPayloads), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            ]);

            if (is_object($this->context->controller) && method_exists($this->context->controller, 'registerJavascript')) {
                $this->context->controller->registerJavascript(
                    'gtm-datalayer-core',
                    'modules/' . $this->name . '/views/js/datalayer-core.js',
                    ['position' => 'head', 'priority' => 10]
                );

                $this->context->controller->registerJavascript(
                    'gtm-prestashop-adapter',
                    'modules/' . $this->name . '/views/js/prestashop-adapter.js',
                    ['position' => 'bottom', 'priority' => 50]
                );
            } elseif (is_object($this->context->controller) && method_exists($this->context->controller, 'addJS')) {
                $this->context->controller->addJS($this->_path . 'views/js/datalayer-core.js');
                $this->context->controller->addJS($this->_path . 'views/js/prestashop-adapter.js');
            }

            return $this->display(__FILE__, 'views/templates/hook/gtm_head.tpl')
                . $this->display(__FILE__, 'views/templates/hook/datalayer_init.tpl');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function hookDisplayAfterBodyOpeningTag($params)
    {
        try {
            $gtmId = Configuration::get('PS_GTM_CONTAINER_ID');
            $rawBodyCode = Configuration::get('PS_GTM_RAW_BODY_CODE');
            if (empty($gtmId) && empty($rawBodyCode)) {
                return '';
            }

            $this->context->smarty->assign([
                'gtm_id' => $gtmId,
                'raw_body_code' => $rawBodyCode,
            ]);
            return $this->display(__FILE__, 'views/templates/hook/gtm_body.tpl');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function hookDisplayOrderConfirmation($params)
    {
        try {
            if (!self::isEventEnabled('purchase')) {
                return '';
            }

            $order = isset($params['order']) ? $params['order'] : null;
            if (!$order || !Validate::isLoadedObject($order)) {
                return '';
            }

            if (DeduplicationManager::isTransactionTracked($order->id)) {
                return '<!-- GTM DataLayer: Purchase transaction ' . (int)$order->id . ' already recorded -->';
            }

            $eventPrefix = (string)Configuration::get('PS_GTM_EVENT_PREFIX');
            $payloadBuilder = new PayloadBuilder($this->context, $eventPrefix);
            $purchasePayload = $payloadBuilder->buildPurchasePayload($order);

            DeduplicationManager::markTransactionTracked($order->id, $order->reference);

            return '<script type="text/javascript">
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                    ecommerce: null,
                    meta: null,
                    tiktok: null,
                    google_ads: null,
                    pinterest: null,
                    microsoft_ads: null
                });
                window.dataLayer.push(' . json_encode($purchasePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');
            </script>';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function hookActionOrderSlipAdd($params)
    {
        try {
            if (!self::isEventEnabled('refund') || Configuration::get('PS_GTM_WEBHOOK_REFUND_ENABLED') === '0') {
                return;
            }

            $sgtmUrl = (string)Configuration::get('PS_SGTM_TRANSPORT_URL');
            if (empty($sgtmUrl)) {
                return;
            }

            $order = isset($params['order']) ? $params['order'] : null;
            if (!$order || !Validate::isLoadedObject($order)) {
                return;
            }

            $productList = isset($params['productList']) && is_array($params['productList']) ? $params['productList'] : [];
            $qtyList = isset($params['qtyList']) && is_array($params['qtyList']) ? $params['qtyList'] : [];

            $refundData = RefundDataResolver::resolveFromOrderSlipParams($order, $productList, $qtyList);
            if (empty($refundData) || (float)$refundData['value'] <= 0) {
                return;
            }

            // Deduplication check: if order slip was already tracked, do not resend
            if (!empty($refundData['id_order_slip']) && DeduplicationManager::isRefundTracked($refundData['id_order_slip'])) {
                return;
            }

            ServerSideWebhookDispatcher::dispatchRefund($refundData, $sgtmUrl);

            if (!empty($refundData['id_order_slip'])) {
                DeduplicationManager::markRefundTracked($refundData['id_order_slip'], $order->id, $refundData['value']);
            }
        } catch (\Throwable $e) {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('ps_gtm_datalayer hookActionOrderSlipAdd error: ' . $e->getMessage(), 2);
            }
        }
    }

    public function hookActionValidateOrder($params)
    {
        try {
            if (Configuration::get('PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED') === '0') {
                return;
            }

            $sgtmUrl = (string)Configuration::get('PS_SGTM_TRANSPORT_URL');
            if (empty($sgtmUrl)) {
                return;
            }

            $order = isset($params['order']) ? $params['order'] : null;
            if (!$order || !Validate::isLoadedObject($order)) {
                return;
            }

            // Deduplication check: if transaction was already tracked, ignore
            if (DeduplicationManager::isTransactionTracked($order->id)) {
                return;
            }

            // Check if order was placed in Back Office or via offline context
            $isBackOffice = defined('_PS_ADMIN_DIR_') 
                || (isset($this->context->controller) && $this->context->controller instanceof AdminController);

            if ($isBackOffice) {
                ServerSideWebhookDispatcher::dispatchOfflinePurchase($order, $sgtmUrl);
                DeduplicationManager::markTransactionTracked($order->id, $order->reference);
            }
        } catch (\Throwable $e) {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('ps_gtm_datalayer hookActionValidateOrder error: ' . $e->getMessage(), 2);
            }
        }
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        try {
            if (!self::isEventEnabled('order_status_update') || Configuration::get('PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED') === '0') {
                return;
            }

            $sgtmUrl = (string)Configuration::get('PS_SGTM_TRANSPORT_URL');
            if (empty($sgtmUrl)) {
                return;
            }

            $idOrder = isset($params['id_order']) ? (int)$params['id_order'] : 0;
            $newOrderStatus = isset($params['newOrderStatus']) ? $params['newOrderStatus'] : null;

            if ($idOrder <= 0 || !Validate::isLoadedObject($newOrderStatus)) {
                return;
            }

            $order = new Order($idOrder);
            if (!Validate::isLoadedObject($order)) {
                return;
            }

            $previousStatusId = 0;
            if (isset($params['oldOrderStatus']) && Validate::isLoadedObject($params['oldOrderStatus'])) {
                $previousStatusId = (int)$params['oldOrderStatus']->id;
            } elseif (isset($params['id_order_state'])) {
                $previousStatusId = (int)$params['id_order_state'];
            }

            // If the status did not change (same status re-saved) or initial assignment with identical status, ignore
            if ($previousStatusId > 0 && $previousStatusId === (int)$newOrderStatus->id) {
                return;
            }

            ServerSideWebhookDispatcher::dispatchOrderStatusUpdate($order, $newOrderStatus, $previousStatusId, $sgtmUrl);
        } catch (\Throwable $e) {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('ps_gtm_datalayer hookActionOrderStatusPostUpdate error: ' . $e->getMessage(), 2);
            }
        }
    }
}
