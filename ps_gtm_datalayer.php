<?php
/**
 * Advanced Google Tag Manager & GA4 DataLayer Module for PrestaShop
 * Compatibility: PrestaShop 1.7.8.0 - 8.2.x / 9.x
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/PayloadBuilder.php';
require_once __DIR__ . '/classes/DeduplicationManager.php';
require_once __DIR__ . '/classes/UserDataHasher.php';

class Ps_Gtm_DataLayer extends Module
{
    public function __construct()
    {
        $this->name = 'ps_gtm_datalayer';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'Analytics Engineer';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Enterprise GA4 & sGTM DataLayer');
        $this->description = $this->l('Generates standardized GA4 ecommerce DataLayer and supports Server-Side GTM tracking.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayAfterBodyOpeningTag')
            && $this->registerHook('displayOrderConfirmation')
            && DeduplicationManager::createDatabaseTable()
            && Configuration::updateValue('PS_GTM_CONTAINER_ID', '')
            && Configuration::updateValue('PS_SGTM_TRANSPORT_URL', '')
            && Configuration::updateValue('PS_GTM_CONSENT_MODE', '1')
            && Configuration::updateValue('PS_GTM_USE_SKU', '1');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && DeduplicationManager::dropDatabaseTable()
            && Configuration::deleteByName('PS_GTM_CONTAINER_ID')
            && Configuration::deleteByName('PS_SGTM_TRANSPORT_URL')
            && Configuration::deleteByName('PS_GTM_CONSENT_MODE')
            && Configuration::deleteByName('PS_GTM_USE_SKU');
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            $gtmId = trim(Tools::getValue('PS_GTM_CONTAINER_ID'));
            $sgtmUrl = trim(Tools::getValue('PS_SGTM_TRANSPORT_URL'));
            $consent = (int)Tools::getValue('PS_GTM_CONSENT_MODE');
            $useSku = (int)Tools::getValue('PS_GTM_USE_SKU');

            if (!empty($gtmId) && !preg_match('/^GTM-[A-Z0-9]+$/', $gtmId)) {
                $output .= $this->displayError($this->l('Invalid Google Tag Manager Container ID format (expected GTM-XXXXXX).'));
            } else {
                Configuration::updateValue('PS_GTM_CONTAINER_ID', $gtmId);
                Configuration::updateValue('PS_SGTM_TRANSPORT_URL', rtrim($sgtmUrl, '/'));
                Configuration::updateValue('PS_GTM_CONSENT_MODE', $consent);
                Configuration::updateValue('PS_GTM_USE_SKU', $useSku);

                $output .= $this->displayConfirmation($this->l('Settings updated successfully.'));
            }
        }

        return $output . $this->renderForm();
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
                'PS_GTM_CONSENT_MODE' => Configuration::get('PS_GTM_CONSENT_MODE'),
                'PS_GTM_USE_SKU' => Configuration::get('PS_GTM_USE_SKU'),
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
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
                        'desc' => $this->l('Your primary web container ID (e.g., GTM-XXXXXXX).'),
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'name' => 'PS_SGTM_TRANSPORT_URL',
                        'label' => $this->l('Server-Side GTM Transport URL (Optional)'),
                        'desc' => $this->l('Custom domain endpoint for Server-Side GTM (e.g. https://gtm.yourdomain.com or Stape endpoint). Leave blank to use Google directly.'),
                        'size' => 50,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Google Consent Mode v2'),
                        'name' => 'PS_GTM_CONSENT_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Initialize default denied consent states prior to tag loading for GDPR/DMA compliance.'),
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Prefer SKU/Reference as Item ID'),
                        'name' => 'PS_GTM_USE_SKU',
                        'is_bool' => true,
                        'desc' => $this->l('Use product reference/SKU as item_id. If disabled or reference is empty, product ID will be used.'),
                        'values' => [
                            ['id' => 'sku_on', 'value' => 1, 'label' => $this->l('SKU / Reference')],
                            ['id' => 'sku_off', 'value' => 0, 'label' => $this->l('Product ID')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Settings'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];
    }

    public function hookDisplayHeader($params)
    {
        $gtmId = Configuration::get('PS_GTM_CONTAINER_ID');
        $sgtmUrl = Configuration::get('PS_SGTM_TRANSPORT_URL');
        $consentEnabled = (bool)Configuration::get('PS_GTM_CONSENT_MODE');

        $controller = isset($this->context->controller->php_self) ? $this->context->controller->php_self : '';
        $payloadBuilder = new PayloadBuilder($this->context);
        $serverPayload = $payloadBuilder->buildPagePayload($controller, $params);

        $this->context->smarty->assign([
            'gtm_id' => $gtmId,
            'sgtm_url' => $sgtmUrl,
            'consent_mode_enabled' => $consentEnabled,
            'initial_server_payload' => $serverPayload ? json_encode($serverPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        ]);

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

        return $this->display(__FILE__, 'views/templates/hook/gtm_head.tpl')
            . $this->display(__FILE__, 'views/templates/hook/datalayer_init.tpl');
    }

    public function hookDisplayAfterBodyOpeningTag($params)
    {
        $gtmId = Configuration::get('PS_GTM_CONTAINER_ID');
        if (empty($gtmId)) {
            return '';
        }

        $this->context->smarty->assign(['gtm_id' => $gtmId]);
        return $this->display(__FILE__, 'views/templates/hook/gtm_body.tpl');
    }

    public function hookDisplayOrderConfirmation($params)
    {
        $order = isset($params['order']) ? $params['order'] : null;
        if (!$order || !Validate::isLoadedObject($order)) {
            return '';
        }

        if (DeduplicationManager::isTransactionTracked($order->id)) {
            return '<!-- GTM DataLayer: Purchase transaction ' . (int)$order->id . ' already recorded -->';
        }

        $payloadBuilder = new PayloadBuilder($this->context);
        $purchasePayload = $payloadBuilder->buildPurchasePayload($order);

        DeduplicationManager::markTransactionTracked($order->id, $order->reference);

        return '<script type="text/javascript">
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push(' . json_encode($purchasePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');
        </script>';
    }
}
