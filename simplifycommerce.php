<?php
/**
 * Copyright (c) 2017-2024 Mastercard
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

require_once _PS_MODULE_DIR_ . 'simplifycommerce/simplify-api-logger.php';

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButton;
use PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButtonsCollection;


if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * This payment module enables the processing of
 * card transactions through the Simplify
 * Commerce framework.
 */
class SimplifyCommerce extends PaymentModule
{
    const TXN_MODE_PURCHASE         = 'purchase';
    const TXN_MODE_AUTHORIZE        = 'authorize';
    const PAYMENT_OPTION_MODAL      = 'modal';
    const PAYMENT_OPTION_EMBEDDED   = 'embedded';
    const SIMPLIFY_MODULE_KEY       = '937f0cb1dd5fdb7c8971be3c30ac879a895406b0f4a73642e7aedbec99c341f1';
    const MPGS_API_URL              = 'https://mpgs.fingent.wiki/wp-json/mpgs/v2/update-repo-status';
    const SQL_CREATE_TABLE          = 'CREATE TABLE IF NOT EXISTS `';
    const SQL_TABLE_ENGINE          = ' ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 AUTO_INCREMENT=1';

    /**
     * @var string
     */
    public $defaultModalOverlayColor = '#22A6CA';

    /**
     * @var string
     */
    protected $defaultTitle;

    /**
     * @var string
     */
    protected $controllerAdmin;

    /**
    * @var Log
    */
    public $loggingEnabled;

    /**
    * @var module
    */
    public $module;
    
    /**
     * Simplify Commerce's module constructor
     */
    public function __construct()
    {
        $this->name                     = 'simplifycommerce';
        $this->tab                      = 'payments_gateways';
        $this->version                  = '2.4.4';
        $this->author                   = 'Mastercard';
        $this->ps_versions_compliancy   = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->currencies               = true;
        $this->currencies_mode          = 'checkbox';
        $this->module_key               = '8b7703c5901ec736bd931bbbb8cfd13c';

        parent::__construct();

        $this->displayName              = $this->l('Mastercard Payment Gateway Services - Simplify');
        $this->description              = $this->l(
            'Payments made easy - Start securely accepting card payments instantly.'
        );
        $this->confirmUninstall         = $this->l('Warning: Are you sure you want to uninstall this module?');
        $this->defaultTitle             = $this->l('Pay with Card');
        $this->controllerAdmin          = 'AdminSimplify';

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->trans(
                'No currency has been set for this module.',
                array(),
                'Modules.SimplifyCommerce.Admin'
            );
        }
        $this->public_key               = Configuration::get('SIMPLIFY_PUBLIC_KEY');
        $this->private_key              = Configuration::get('SIMPLIFY_PRIVATE_KEY');
        $this->loggingEnabled           = Configuration::get('SIMPLIFY_ENABLED_ERROR_LOG');
        $this->enable_line_items        = Configuration::get('SIMPLIFY_ENABLE_LINE_ITEMS');
    }

    /**
     * @return int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function installTab()
    {
        $tab                = new Tab();
        $tab->class_name    = $this->controllerAdmin;
        $tab->active        = 1;
        $tab->name          = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $this->name;
        }
        $tab->id_parent     = -1;
        $tab->module        = $this->name;

        return $tab->add();
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function uninstallTab()
    {
        $idTab = (int)Tab::getIdFromClassName($this->controllerAdmin);
        $tab    = new Tab($idTab);
        if (Validate::isLoadedObject($tab)) {
            return $tab->delete();
        }

        return true;
    }

    public function checkCurrency($cart)
    {
        $currencyOrder     = new Currency((int)($cart->id_currency));
        $currenciesModule  = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currenciesModule)) {
            foreach ($currenciesModule as $currency_module) {
                if ($currencyOrder->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }


    public function getBaseLink()
    {
        return __PS_BASE_URI__;
    }

    public function getLangLink()
    {
        return '';
    }

    public function hookDisplayHeader()
    {
        if (!$this->active) {
            return;
        }

        $this->context->controller->addCSS($this->_path.'views/css/style.css', 'all');
        

        if (Configuration::get('SIMPLIFY_ENABLED_PAYMENT_WINDOW')) {
            if (Configuration::get('SIMPLIFY_PAYMENT_OPTION') === self::PAYMENT_OPTION_EMBEDDED) {
                $this->context->controller->addJS($this->_path.'views/js/simplify.embedded.js');
            } else {
                $this->context->controller->addJS($this->_path.'views/js/simplify.js');
                $this->context->controller->addJS($this->_path.'views/js/simplify.form.js');
            }
        }

        $this->context->controller->registerJavascript(
            'remote-simplifypayments-hp',
            'https://www.simplify.com/commerce/simplify.pay.js',
            [
                'server'    => 'remote',
                'position'  => 'bottom',
                'priority'  => 20
            ]
        );
    }

    /**
     * Simplify Commerce module adding ajax
     *
     * @return bool Install result
     */
    public function hookBackOfficeHeader()
    {
        if (!$this->active) {
            return;
        }

        // Add JavaScript and CSS files
        $this->addAssets();

        $orderId = Tools::getValue('id_order');
        if (Validate::isUnsignedId($orderId)) {
            // Fetch and define refund data
            $refundData = $this->getRefundData($orderId);
            if (!empty($refundData)) {
                Media::addJsDef(['refundData' => json_encode($refundData)]);
            }

            // Fetch and define capture data
            $captureData = $this->getCaptureData($orderId);
            if (!empty($captureData)) {
                Media::addJsDef(['captureData' => json_encode($captureData)]);
            }

            // Fetch and define total refund amount
            $totalAmount = $this->getTotalRefundAmount($orderId);
            if ($totalAmount > 0) {
                Media::addJsDef(['refundmaxamount' => $totalAmount]);
            }
        }
    }

    private function addAssets()
    {
        $this->context->controller->addJS($this->_path . 'views/js/refund.js', 'all');
        $this->context->controller->addCSS($this->_path . 'views/css/refund.css', 'all');

        $adminAjaxLink = $this->context->link->getAdminLink('AdminSimplify');
        Media::addJsDef(['adminajax_link' => $adminAjaxLink]);
    }

    private function getRefundData($orderId)
    {
        $results = Db::getInstance()->executeS('SELECT refund_description, refund_id, amount,
         comment, date_created FROM ' . _DB_PREFIX_ . 'refund_table WHERE order_id = ' . (int)$orderId);

        $refundData = [];
        if (!empty($results)) {
            foreach ($results as $result) {
                $refundData[] = [
                    'refund_description' => $result['refund_description'],
                    'refund_id'          => $result['refund_id'],
                    'amount'             => $result['amount'],
                    'comment'            => $result['comment'],
                    'date_created'       => $result['date_created'],
                ];
            }
        }

        return $refundData;
    }

    private function getCaptureData($orderId)
    {
        $results = Db::getInstance()->executeS('SELECT payment_transcation_id, amount,
         comment, transcation_date FROM ' . _DB_PREFIX_ . 'capture_table WHERE order_id = ' . (int)$orderId);

        $captureData = [];
        if (!empty($results)) {
            foreach ($results as $result) {
                $captureData[] = [
                    'payment_transcation_id' => $result['payment_transcation_id'],
                    'amount'                 => $result['amount'],
                    'comment'                => $result['comment'],
                    'transcation_date'       => $result['transcation_date'],
                ];
            }
        }

        return $captureData;
    }

    private function getTotalRefundAmount($orderId)
    {
        $tableName = 'refund_table';
        $idOrder   = 'order_id';

        $sql = 'SELECT ' . pSQL($idOrder) . ', SUM(amount) AS total_amount
            FROM ' . pSQL(_DB_PREFIX_ . $tableName) . '
            WHERE ' . pSQL($idOrder) . ' = \'' . pSQL($orderId) . '\'
            AND comment = "Transaction Successful."
            GROUP BY ' . pSQL($idOrder);

        $result = Db::getInstance()->executeS($sql);
        return !empty($result) ? $result[0]['total_amount'] : 0;
    }

    /**
     * Simplify Commerce's module installation
     *
     * @return boolean Install result
     */
    public function install()
    {
        // Install admin tab
        if (!$this->installTab()) {
            return false;
        }

        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('orderConfirmation')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('displayAdminOrderLeft')
            && $this->registerHook('actionGetAdminOrderButtons')
            && $this->registerHook('displayAdminOrder')
            && Configuration::updateValue('SIMPLIFY_MODE', 0)
            && Configuration::updateValue('SIMPLIFY_SAVE_CUSTOMER_DETAILS', 1)
            && Configuration::updateValue('SIMPLIFY_ENABLED_ERROR_LOG', 1)
            && Configuration::updateValue('SIMPLIFY_ENABLE_LINE_ITEMS', 1)
            && Configuration::updateValue('SIMPLIFY_OVERLAY_COLOR', $this->defaultModalOverlayColor)
            && Configuration::updateValue('SIMPLIFY_PAYMENT_ORDER_STATUS', (int)Configuration::get('PS_OS_PAYMENT'))
            && Configuration::updateValue('SIMPLIFY_PAYMENT_TITLE', $this->defaultTitle)
            && Configuration::updateValue('SIMPLIFY_TXN_MODE', self::TXN_MODE_PURCHASE)
            && $this->createCustomerTable()
            && $this->createCaptureTable()
            && $this->createRefundTable()
            && $this->createVoidTable()
            && $this->installOrderState();
    }

    /**
     * Add buttons to main buttons bar
     *
     * @return void
     */
    public function hookActionGetAdminOrderButtons(array $params)
    {
        if (!$this->active) {
            return;
        }

        $order = new Order($params['id_order']);
        if ($order->payment != $this->displayName) {
            return;
        }

        $isAuthorized       = $order->current_state == Configuration::get('SIMPLIFY_OS_AUTHORIZED');
        $canVoid            = $isAuthorized;
        $canCapture         = $isAuthorized;
        $canRefund          = $order->current_state == Configuration::get('PS_OS_PAYMENT');
        $canPatialRefund    = $order->current_state == Configuration::get('PS_OS_PAYMENT') ||
         $order->current_state == Configuration::get('SIMPLIFY_OS_PARTIAL_REFUND');
        $canAction          = $isAuthorized || $canVoid || $canCapture || $canPatialRefund ;

        if (!$canAction) {
            return;
        }

        $link = new Link();

        /** @var ActionsBarButtonsCollection $bar */
        $bar = $params['actions_bar_buttons_collection'];

         if ($canCapture) {
            $captureUrl = $link->getAdminLink(
                'AdminSimplify',
                true,
                [],
                [
                    'action'   => 'capture',
                    'id_order' => $order->id,
                ]
            );
            $bar->add(
                new ActionsBarButton(
                    'btn-action',
                    ['href' => $captureUrl],
                    $this->l('Capture Payment')
                )
            );
        }

        if ($canRefund) {
            $bar->add(
                new ActionsBarButton(
                    'btn-action',
                    ['id' => 'fullrefund'],
                    $this->l('Full Refund')
                )
            );
        }

        if ($canPatialRefund) {
            $bar->add(
                new ActionsBarButton(
                    'btn-action',
                    [
                    'class' => 'partialrefund',
                    'id'    => 'refundpartial',
                    ],
                    $this->l('Partial Refund')
                )
            );

        }

        if ($canVoid) {
            $voidUrl = $link->getAdminLink(
                'AdminSimplify',
                true,
                [],
                [
                    'action'   => 'void',
                    'id_order' => $order->id,
                ]
            );
            $bar->add(
                new ActionsBarButton(
                    'btn-action',
                    ['href' => $voidUrl],
                    $this->l('Void')
                )
            );
        }
    }

    /**
     * @param $params
     *
     * @return false|string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayAdminOrderLeft($params)
    {
        if (!$this->active) {
            return '';
        }

        $order = new Order($params['id_order']);
        if ($order->payment != $this->displayName) {
            return '';
        }

        $isAuthorized   = $order->current_state == Configuration::get('SIMPLIFY_OS_AUTHORIZED');
        $canVoid        = $isAuthorized;
        $canCapture     = $isAuthorized;
        $canRefund      = $order->current_state == Configuration::get('PS_OS_PAYMENT');
        $canAction      = $isAuthorized || $canVoid || $canCapture || $canRefund;

        $this->smarty->assign(
            array(
                'module_dir'         => $this->_path,
                'order'              => $order,
                'simplify_order_ref' => (string)$order->id_cart,
                'can_void'           => $canVoid,
                'can_capture'        => $canCapture,
                'can_refund'         => $canRefund,
                'is_authorized'      => $isAuthorized,
                'can_action'         => $canAction,
            )
        );

        return $this->display(__FILE__, 'views/templates/hook/order_actions.tpl');
    }

    /**
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function installOrderState()
    {
        if (!Configuration::get('SIMPLIFY_OS_AUTHORIZED')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('SIMPLIFY_OS_AUTHORIZED')))) {
            $orderState = new OrderState();
            foreach (Language::getLanguages() as $language) {
                $orderState->name[$language['id_lang']] = 'Payment Authorized';
                $orderState->template[$language['id_lang']] = 'payment';
            }
            $orderState->send_email    = true;
            $orderState->color         = '#4169E1';
            $orderState->hidden        = false;
            $orderState->delivery      = false;
            $orderState->logable       = true;
            $orderState->paid          = true;
            $orderState->invoice       = false;
            if ($orderState->add()) {
                $source         = _PS_ROOT_DIR_.'/img/os/10.gif';
                $destination    = _PS_ROOT_DIR_.'/img/os/'.(int)$orderState->id.'.gif';
                copy($source, $destination);
            }

            return Configuration::updateValue('SIMPLIFY_OS_AUTHORIZED', (int)$orderState->id);
        }
        if (!Configuration::get('SIMPLIFY_OS_PARTIAL_REFUND')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('SIMPLIFY_OS_PARTIAL_REFUND')))) {
            $orderState = new OrderState();
            foreach (Language::getLanguages() as $language) {
                $orderState->name[$language['id_lang']] = 'Partial Refund';
                $orderState->template[$language['id_lang']] = 'refund';
            }
            $orderState->send_email    = true;
            $orderState->color         = '#01B887';
            $orderState->hidden        = false;
            $orderState->delivery      = false;
            $orderState->logable       = true;
            $orderState->paid          = true;
            $orderState->invoice       = false;
            if ($orderState->add()) {
                $source         = _PS_ROOT_DIR_.'/img/os/15.gif';
                $destination    = _PS_ROOT_DIR_.'/img/os/'.(int)$orderState->id.'.gif';
                copy($source, $destination);
            }

            return Configuration::updateValue('SIMPLIFY_OS_PARTIAL_REFUND', (int)$orderState->id);
        }

        return true;
    }

    /**
     * Simplify Customer tables creation
     *
     * @return boolean Database tables installation result
     */
    public function createCustomerTable()
    {
        return Db::getInstance()->Execute(
            self::SQL_CREATE_TABLE._DB_PREFIX_.'simplify_customer` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `customer_id` varchar(32) NOT NULL,
                `simplify_customer_id` varchar(32) NOT NULL,
                `date_created` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `customer_id` (`customer_id`),
                KEY `simplify_customer_id` (`simplify_customer_id`)
            )'. self::SQL_TABLE_ENGINE
        );
    }

    /**
     * Simplify capture details creation
     *
     * @return boolean Database tables installation result
     */
    public function createCaptureTable()
    {
        return Db::getInstance()->Execute(
            self::SQL_CREATE_TABLE._DB_PREFIX_.'capture_table` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `order_id` int(10) unsigned NOT NULL,
                `capture_transcation_id` varchar(32) NOT NULL,
                `payment_transcation_id` varchar(32) NOT NULL,
                `amount` decimal(10,2) NOT NULL,
                `comment` varchar(100) NOT NULL,
                `transcation_date` datetime NOT NULL,
                PRIMARY KEY (`id`)
            )'. self::SQL_TABLE_ENGINE
        );
    }

    /**
     * Simplify refund details creation
     *
     * @return boolean Database tables installation result
     */
    public function createRefundTable()
    {
        return Db::getInstance()->Execute(
            self::SQL_CREATE_TABLE._DB_PREFIX_.'refund_table` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `order_id` int(10) unsigned NOT NULL,
                `refund_id` varchar(32) NOT NULL,
                `transcation_id` varchar(32) NOT NULL,
                `refund_description` varchar(100) NOT NULL,
                `amount` decimal(10,2) NOT NULL,
                `comment` varchar(100) NOT NULL,
                `date_created` datetime NOT NULL,
                PRIMARY KEY (`id`)
            )'. self::SQL_TABLE_ENGINE
        );
    }

    /**
     * Simplify Void table creation
     *
     * @return boolean Database tables installation result
     */
    public function createVoidTable()
    {
        return Db::getInstance()->Execute(
            self::SQL_CREATE_TABLE._DB_PREFIX_.'simplify_void_table` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `order_id` int(10) unsigned NOT NULL,
                `transcation_id` varchar(32) NOT NULL,
                `amount` decimal(10,2) NOT NULL,
                `date_created` datetime NOT NULL,
                PRIMARY KEY (`id`)
            )'. self::SQL_TABLE_ENGINE
        );
    }

    /**
     * Simplify Commerce's module uninstalling. Remove the config values and delete the tables.
     *
     * @return boolean Uninstall result
     */
    public function uninstall()
    {
        $this->uninstallTab();

        return parent::uninstall()
            && Configuration::deleteByName('SIMPLIFY_MODE')
            && Configuration::deleteByName('SIMPLIFY_SAVE_CUSTOMER_DETAILS')
            && Configuration::deleteByName('SIMPLIFY_ENABLED_ERROR_LOG')
            && Configuration::deleteByName('SIMPLIFY_ENABLE_LINE_ITEMS')
            && Configuration::deleteByName('SIMPLIFY_PUBLIC_KEY_TEST')
            && Configuration::deleteByName('SIMPLIFY_PUBLIC_KEY_LIVE')
            && Configuration::deleteByName('SIMPLIFY_PRIVATE_KEY_TEST')
            && Configuration::deleteByName('SIMPLIFY_PRIVATE_KEY_LIVE')
            && Configuration::deleteByName('SIMPLIFY_PAYMENT_ORDER_STATUS')
            && Configuration::deleteByName('SIMPLIFY_OVERLAY_COLOR')
            && Configuration::deleteByName('SIMPLIFY_PAYMENT_TITLE')
            && Configuration::deleteByName('SIMPLIFY_TXN_MODE')
            && Configuration::deleteByName('SIMPLIFY_PAYMENT_OPTION')
            && Db::getInstance()->Execute('DROP TABLE IF EXISTS`'._DB_PREFIX_.'simplify_customer`')
            && $this->unregisterHook('paymentOptions')
            && $this->unregisterHook('orderConfirmation')
            && $this->unregisterHook('displayHeader')
            && $this->unregisterHook('displayAdminOrder')
            && $this->unregisterHook('displayAdminOrderLeft');

    }

    /**
     * @return void
     */
    public function initSimplify()
    {
        include_once(dirname(__FILE__).'/lib/Simplify.php');

        $apiKeys               = $this->getSimplifyAPIKeys();
        Simplify::$publicKey    = $apiKeys->public_key;
        Simplify::$privateKey   = $apiKeys->private_key;
    }

    /**
     * Display the Simplify Commerce's payment form
     *
     * @return string[]|bool Simplify Commerce's payment form
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart'])) {
            return [];
        }

        $this->initSimplify();
        $this->assignCustomerDetails();

        $cardholderDetails = $this->getCardholderDetails();
        $this->assignCardholderDetails($cardholderDetails);

        $this->assignHostedPaymentDetails();

        $this->smarty->assign('module_dir', $this->_path);
        $currency = new Currency((int)$this->context->cart->id_currency);
        $this->smarty->assign('currency_iso', $currency->iso_code);

        if (!Configuration::get('SIMPLIFY_ENABLED_PAYMENT_WINDOW')) {
            return [];
        }

        return $this->getPaymentOptions();
    }

    private function assignCustomerDetails()
    {
        $isTokenizationEnabled = (bool)Configuration::get('SIMPLIFY_SAVE_CUSTOMER_DETAILS');
        $isLogged = $this->context->customer->isLogged();

        if ($isTokenizationEnabled && $isLogged) {
            $this->smarty->assign('show_save_customer_details_checkbox', true);
            $simplifyCustomerId = Db::getInstance()->getValue(
                'SELECT simplify_customer_id FROM ' .
                _DB_PREFIX_ . 'simplify_customer WHERE customer_id = ' . (int)$this->context->cookie->id_customer
            );

            if ($simplifyCustomerId) {
                $this->fetchAndAssignCustomerDetails($simplifyCustomerId);
            }
        }
    }

    private function fetchAndAssignCustomerDetails($simplifyCustomerId)
    {
        try {
            $customer = Simplify_Customer::findCustomer($simplifyCustomerId);
            $this->smarty->assign('show_saved_card_details', true);
            $this->smarty->assign('customer_details', $customer);
        } catch (Simplify_ApiException $e) {
            $this->logMessage('Simplify Commerce - Error retrieving customer' .
             $e->getErrorCode() . $simplifyCustomerId);

            if ($e->getErrorCode() === 'object.not.found') {
                $this->deleteCustomerFromDB();
            }
        }
    }

    private function assignCardholderDetails($cardholderDetails)
    {
        $this->smarty->assign('simplify_public_key', Simplify::$publicKey);
        $this->smarty->assign(
            'customer_name',
            sprintf(
                '%s %s',
                $this->safe($cardholderDetails->firstname),
                $this->safe($cardholderDetails->lastname)
            )
        );
        $this->smarty->assign('firstname', $this->safe($cardholderDetails->firstname));
        $this->smarty->assign('lastname', $this->safe($cardholderDetails->lastname));
        $this->smarty->assign('city', $this->safe($cardholderDetails->city));
        $this->smarty->assign('address1', $this->safe($cardholderDetails->address1));
        $this->smarty->assign('address2', $this->safe($cardholderDetails->address2));
        $this->smarty->assign('state', isset($cardholderDetails->state) ? $this->safe($cardholderDetails->state) : '');
        $this->smarty->assign('postcode', $this->safe($cardholderDetails->postcode));
    }

    private function assignHostedPaymentDetails()
    {
        $this->smarty->assign('hosted_payment_name', $this->safe($this->context->shop->name));
        $this->smarty->assign(
            'hosted_payment_description',
            $this->safe($this->context->shop->name) .
            $this->l(' Order Number: ') .
            (int)$this->context->cart->id
        );
        $this->smarty->assign('hosted_payment_reference', 'Order Number' . (int)$this->context->cart->id);
        $this->smarty->assign('hosted_payment_amount', ($this->context->cart->getOrderTotal() * 100));
        $this->smarty->assign(
            'overlay_color',
            Configuration::get('SIMPLIFY_OVERLAY_COLOR') ?:
            $this->defaultModalOverlayColor
        );
    }

    private function getPaymentOptions()
    {
        $options = [];
        if (Configuration::get('SIMPLIFY_PAYMENT_OPTION') === self::PAYMENT_OPTION_EMBEDDED) {
            $this->smarty->assign('enabled_payment_window', 0);
            $this->smarty->assign('enabled_embedded', 1);
            $options[] = $this->getEmbeddedPaymentOption();
        } else {
            $this->smarty->assign('enabled_payment_window', 1);
            $this->smarty->assign('enabled_embedded', 0);
            $options[] = $this->getPaymentOption();
        }
        return $options;
    }

    protected function safe($field)
    {
        $copy       = $field;
        $encoding   = mb_detect_encoding($field);
        if ($encoding !== 'ASCII') {
            if (function_exists('transliterator_transliterate')) {
                $field = transliterator_transliterate('Any-Latin; Latin-ASCII', $field);
            } else {
                if (function_exists('iconv')) {
                    // fall back to iconv if intl module not available
                    $field = iconv($encoding, 'ASCII//TRANSLIT//IGNORE', $field);
                    $field = str_ireplace('?', '', $field);
                    $field = trim($field);
                } else {
                    // no transliteration possible, revert to original field
                    return $field;
                }
            }
            if (!$field) {
                // if translit turned the string into any false-like value, return original instead
                return $copy;
            }
        }

        return $field;
    }

    public function getPaymentOption()
    {
        $option = new PaymentOption();
        $option
            ->setCallToActionText(Configuration::get('SIMPLIFY_PAYMENT_TITLE') ?: $this->defaultTitle)
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setModuleName('simplifycommerce')
            ->setForm($this->fetch('module:simplifycommerce/views/templates/front/payment.tpl'));

        return $option;
    }

    public function getEmbeddedPaymentOption()
    {
        $option = new PaymentOption();
        $option
            ->setCallToActionText(Configuration::get('SIMPLIFY_PAYMENT_TITLE') ?: $this->defaultTitle)
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setModuleName('simplifycommerce_embedded')
            ->setForm($this->fetch('module:simplifycommerce/views/templates/front/embedded-payment.tpl'));

        return $option;
    }

    /**
     * Display a confirmation message after an order has been placed.
     *
     * @param array $params Hook parameters
     *
     * @return string Simplify Commerce's payment confirmation screen
     */
    public function hookOrderConfirmation($params)
    {
        if (!isset($params['objOrder']) || ($params['objOrder']->module != $this->name)) {
            return false;
        }

        if ($params['objOrder'] && Validate::isLoadedObject($params['objOrder']) && isset($params['objOrder']->valid)) {
            $order = array(
                'reference' => $params['objOrder']->reference ?? sprintf('#%06d', $params['objOrder']->id),
                'valid'     => $params['objOrder']->valid,
            );
            $this->smarty->assign('simplify_order', $order);
        }

        return $this->display(__FILE__, 'views/templates/hook/order-confirmation.tpl');
    }

    private function getLineItems()
    {
        if (!Configuration::get('SIMPLIFY_ENABLE_LINE_ITEMS')) {
            return null;
        }

        $lineItems = $this->context->cart;
        $products   = $lineItems->getProducts();
        $sendItems = [];
        foreach ($products as $product) {
            $sendItems[] = array(
                'name'              => $product['name'],
                'description'       => strip_tags($product['description_short']),
                'quantity'          => $product['cart_quantity'],
                'amount'            => $product['total'] * 100,
                'reference'         => $product['reference'],
            );
        }

        return $sendItems;
    }

    /**
     * Process a payment with Simplify Commerce.
     * Depeding on the customer's input, we can delete/update
     * existing customer card details and charge a payment
     * from the generated card token.
     */
    public function processPayment()
    {
        if (!$this->active) {
            return false;
        }

        $this->initSimplify();
        $paymentRequestData = $this->getPaymentRequestData();

        try {
            $simplifyPayment = $this->executePayment($paymentRequestData);
            $this->validatePaymentStatus($simplifyPayment);
            $this->logTransaction($simplifyPayment);
            $this->createOrder($simplifyPayment);
            $this->redirectToOrderConfirmation();
        } catch (Simplify_ApiException $e) {
            $this->failPayment($e->getMessage());
            $this->logMessage('Payment processing error: ' . $e->getMessage());
            return false;
        }
    }

    private function handleCustomerActions($token)
    {
        $deleteCustomerCard = Tools::getValue('deleteCustomerCard');
        $saveCustomer = Tools::getValue('saveCustomer');
        $customerId = $this->context->cookie->id_customer;
        $simplifyCustomerId = $this->getSimplifyCustomerID($customerId);

        if ($deleteCustomerCard && $simplifyCustomerId) {
            $this->deleteCustomer($simplifyCustomerId);
            $simplifyCustomerId = null;
        }

        if ($saveCustomer === 'on') {
            $simplifyCustomerId = $this->createOrUpdateCustomer($simplifyCustomerId, $token);
        }

        return $simplifyCustomerId;
    }

    private function deleteCustomer($simplifyCustomerId)
    {
        try {
            $customer = Simplify_Customer::findCustomer($simplifyCustomerId);
            $customer->deleteCustomer();
            $this->deleteCustomerFromDB();
        } catch (Simplify_ApiException $e) {
            $this->logMessage('Error deleting customer: ' . $e->getMessage());
        }
    }

    private function createOrUpdateCustomer($simplifyCustomerId, $token)
    {
        if ($simplifyCustomerId) {
            $this->deleteCustomer($simplifyCustomerId);
        }

        return $this->createNewSimplifyCustomer($token);
    }

    private function getPaymentDescription()
    {
        return sprintf(
            "%s Cart Number: %d",
            $this->context->shop->name,
            (int)$this->context->cart->id
        );
    }

    private function getCustomerID()
    {
        return Simplify_Customer::createCustomer(
            array(
                'email'     => (string)$this->context->cookie->email,
                'name'      => (string)$this->context->cookie->customer_firstname.'
                 '.(string)$this->context->cookie->customer_lastname,
                'reference' => sprintf(
                    "%s %d",
                    $this->context->shop->name,
                    (int)$this->context->cookie->id_customer
                ),
            )
        );
    }

    private function getOrderDetails()
    {
        $shippingAddress = new Address((int)$this->context->cart->id_address_delivery);
        $shipCountry     = new Country($shippingAddress->id_country);
        $shipState       = new State($shippingAddress->id_state);
        $customerId      = $this->getCustomerID();
        $this->logMessage('Customer Details.'.$customerId);
        $orderInfo = [
            'reference' => $customerId->id,
            'shippingAddress' => [
                'line1'   => $shippingAddress->address1,
                'city'    => $shippingAddress->city,
                'zip'     => $shippingAddress->postcode,
                'state'   => $shipState->iso_code,
                'country' => $shipCountry->iso_code,
            ]
        ];

        $items = $this->getLineItems();

        if ($items !== null) {
            $orderInfo['items'] = $items;
        }
        return $orderInfo;
    }

    private function getPaymentRequestData()
    {
        $currencyOrder = new Currency((int)$this->context->cart->id_currency);
        $charge = (float)$this->context->cart->getOrderTotal();
        $token = Tools::getValue('simplifyToken');
        $simplifyCustomerId = $this->handleCustomerActions($token);
        $customerId      = $this->getCustomerID();

        $requestData = [
            'amount' => number_format($charge * 100),
            'currency' => $currencyOrder->iso_code,
            'description' => $this->getPaymentDescription(),
        ];

        if ($simplifyCustomerId) {
            $requestData['customer'] = $simplifyCustomerId;
        } else {
            $requestData['customer'] = $customerId->id;
            $requestData['token']    = $token;
            $requestData['order']    = $this->getOrderDetails();
        }

        return $requestData;
    }

    private function executePayment($requestData)
    {
        $txnMode = Configuration::get('SIMPLIFY_TXN_MODE');

        if ($txnMode === self::TXN_MODE_PURCHASE) {
            return Simplify_Payment::createPayment($requestData);
        }

        if ($txnMode === self::TXN_MODE_AUTHORIZE) {
            return Simplify_Authorization::createAuthorization($requestData);
        }
    }

    private function validatePaymentStatus($simplifyPayment)
    {
        if ($simplifyPayment->paymentStatus === 'APPROVED') {
            $this->logMessage($simplifyPayment);
            return true;
        }

        if ($simplifyPayment->paymentStatus != 'APPROVED') {
            $this->failPayment(
                sprintf(
                    "The payment was %s",
                    $simplifyPayment->paymentStatus
                )
            );
        }
    }

    private function logTransaction($simplifyPayment)
    {
        $message = $this->l('Simplify Commerce Transaction Details:').'\n\n'.
                   $this->l('Payment ID:').' '.$simplifyPayment->id.'\n'.
                   $this->l('Payment Status:').' '.$simplifyPayment->paymentStatus.'\n'.
                   $this->l('Amount:').' '.$simplifyPayment->amount * 0.01 .'\n'.
                   $this->l('Currency:').' '.$simplifyPayment->currency.'\n'.
                   $this->l('Description:').' '.$simplifyPayment->description.'\n'.
                   $this->l('Auth Code:').' '.$simplifyPayment->authCode.'\n'.
                   $this->l('Fee:').' '.$simplifyPayment->fee * 0.01 .'\n'.
                   $this->l('Card Last 4:').' '.$simplifyPayment->card->last4.'\n'.
                   $this->l('Card Expiry Year:').' '.$simplifyPayment->card->expYear.'\n'.
                   $this->l('Card Expiry Month:').' '.$simplifyPayment->card->expMonth.'\n'.
                   $this->l('Card Type:').' '.$simplifyPayment->card->type.'\n';

        $this->logMessage('Transcation log.'.$message);
    }

    private function createOrder($simplifyPayment)
    {
        $newStatus = Configuration::get('SIMPLIFY_TXN_MODE') === self::TXN_MODE_AUTHORIZE
            ? (int)Configuration::get('SIMPLIFY_OS_AUTHORIZED')
            : (int)Configuration::get('SIMPLIFY_PAYMENT_ORDER_STATUS');

        $this->validateOrder(
            (int)$this->context->cart->id,
            $newStatus,
            (float)$this->context->cart->getOrderTotal(),
            $this->displayName,
            '',
            [],
            null,
            false,
            $this->context->customer->secure_key
        );

        $this->updatePaymentDetails($simplifyPayment);
    }

    private function updatePaymentDetails($simplifyPayment)
    {
        if (version_compare(_PS_VERSION_, '1.5', '>=')) {
            $newOrder = new Order((int)$this->currentOrder);

            if (Validate::isLoadedObject($newOrder)) {
                $payment = $newOrder->getOrderPaymentCollection();
                if (isset($payment[0])) {
                    $payment[0]->transaction_id = pSQL($simplifyPayment->id);
                    $paymentCard = $simplifyPayment->card;
                    if ($paymentCard) {
                        $payment[0]->card_number = pSQL($paymentCard->last4);
                        $payment[0]->card_brand = pSQL($paymentCard->type);
                        $payment[0]->card_expiration = sprintf(
                            "%s/%s",
                            pSQL($paymentCard->expMonth),
                            pSQL($paymentCard->expYear)
                        );
                        $payment[0]->card_holder = pSQL($paymentCard->name);
                    }
                    $payment[0]->save();
                }
            }
        }
    }
    

    private function redirectToOrderConfirmation()
    {
        Tools::redirect(
            $this->context->link->getPageLink(
                'order-confirmation.php',
                null,
                null,
                [
                    'id_cart' => (int)$this->context->cart->id,
                    'id_module' => (int)$this->id,
                    'id_order' => (int)$this->currentOrder,
                    'key' => $this->context->customer->secure_key,
                ]
            )
        );
    }
    /**
     * @return Address|stdClass
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getCardholderDetails()
    {
        // Create empty object by default
        $cardholderDetails = new stdClass;

        // Send the cardholder's details with the payment
        if (isset($this->context->cart->id_address_invoice)) {
            $invoiceAddress = new Address((int)$this->context->cart->id_address_invoice);

            if ($invoiceAddress->id_state) {
                $state = new State((int)$invoiceAddress->id_state);

                if (Validate::isLoadedObject($state)) {
                    $invoiceAddress->state = $state->iso_code;
                }
            }

            $cardholderDetails = $invoiceAddress;
        }

        return $cardholderDetails;
    }

    /**
     * Function to check if customer still exists in Simplify and if not to delete them from the DB.
     *
     * @return string Simplify customer's id.
     */
    private function getSimplifyCustomerID($customerId)
    {
        $simplifyCustomerId = null;

        try {
            $customer = Simplify_Customer::findCustomer($customerId);
            $simplifyCustomerId = $customer->id;
        } catch (Simplify_ApiException $e) {
            // can't find the customer on Simplify, so no need to delete
            $this->logMessage('Simplify Commerce - Error retrieving customer' . $e->getErrorCode() .$customer);

            if ($e->getErrorCode() == 'object.not.found') {
                $this->deleteCustomerFromDB();
            } // remove the old customer from the database, as it no longer exists in Simplify
        }

        return $simplifyCustomerId;
    }

    /**
     * Function to create a new Simplify customer and to store its id in the database.
     *
     * @return string Simplify customer's id.
     */
    private function deleteCustomerFromDB()
    {
        Db::getInstance()->Execute(
            'DELETE FROM '._DB_PREFIX_.'simplify_customer WHERE customer_id = '
            .(int)$this->context->cookie->id_customer.';'
        );
    }

    /**
     * Function to create a new Simplify customer and to store its id in the database.
     *
     * @param $token
     *
     * @return string Simplify customer's id.
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function createNewSimplifyCustomer($token)
    {
        try {
            $customer = Simplify_Customer::createCustomer(
                array(
                    'email'     => (string)$this->context->cookie->email,
                    'name'      => (string)$this->context->cookie->customer_firstname.' '
                    .(string)$this->context->cookie->customer_lastname,
                    'token'     => $token,
                    'reference' => sprintf(
                        "%s %d",
                        $this->context->shop->name,
                        (int)$this->context->cookie->id_customer
                    ),
                )
            );

            $simplifyCustomerId = pSQL($customer->id);

            Db::getInstance()->Execute(
                '
                INSERT INTO '._DB_PREFIX_.'simplify_customer (id, customer_id, simplify_customer_id, date_created)
                VALUES (NULL, '.(int)$this->context->cookie->id_customer.', \''.$simplifyCustomerId.'\', NOW())'
            );
        } catch (Simplify_ApiException $e) {
            $this->failPayment($e->getMessage());
            $this->logMessage('Simplify Commerce - Payment transaction failed. '.$e->getMessage() . $customer);
        }

        return $simplifyCustomerId;
    }

    /**
     * Function to return the user's Simplify API Keys depending on the account mode in the settings.
     *
     * @return object Simple object containin the Simplify public & private key values.
     */
    private function getSimplifyAPIKeys()
    {
        $apiKeys = new stdClass;
        $apiKeys->public_key = Configuration::get('SIMPLIFY_MODE') ?
            Configuration::get('SIMPLIFY_PUBLIC_KEY_LIVE') : Configuration::get('SIMPLIFY_PUBLIC_KEY_TEST');
        $apiKeys->private_key = Configuration::get('SIMPLIFY_MODE') ?
            Configuration::get('SIMPLIFY_PRIVATE_KEY_LIVE') : Configuration::get('SIMPLIFY_PRIVATE_KEY_TEST');

        return $apiKeys;
    }

    /**
     * Function to log a failure message and redirect the user
     * back to the payment processing screen with the error.
     *
     * @param string $message Error message to log and to display to the user
     */
    private function failPayment($message)
    {

        $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
        
        $location = sprintf(
            "%s%sstep=3&simplify_error=There was a problem with your payment: %s.#simplify_error",
            $this->context->link->getPageLink($controller),
            strpos($controller, '?') !== false ? '&' : '?',
            $message
        );
        Tools::redirect($location);
        exit;
    }

    /**
     * Check settings requirements to make sure the Simplify Commerce's
     * API keys are set.
     *
     * @return boolean Whether the API Keys are set or not.
     */
    public function checkSettings()
    {
        if (Configuration::get('SIMPLIFY_MODE')) {
            return Configuration::get('SIMPLIFY_PUBLIC_KEY_LIVE') != '' && Configuration::get(
                    'SIMPLIFY_PRIVATE_KEY_LIVE'
                ) != '';
        } else {
            return Configuration::get('SIMPLIFY_PUBLIC_KEY_TEST') != '' && Configuration::get(
                    'SIMPLIFY_PRIVATE_KEY_TEST'
                ) != '';
        }
    }

    /**
     * Check key prefix
     * API keys are set.
     *
     * @return boolean Whether the API Keys are set or not.
     */
    public function checkKeyPrefix()
    {
        if (Configuration::get('SIMPLIFY_MODE')) {
            return strpos(Configuration::get('SIMPLIFY_PUBLIC_KEY_LIVE'), 'lvpb_') === 0;
        } else {
            return strpos(Configuration::get('SIMPLIFY_PUBLIC_KEY_TEST'), 'sbpb_') === 0;
        }
    }

    /**
     * Check technical requirements to make sure the Simplify Commerce's module will work properly
     *
     * @return array Requirements tests results
     */
    public function checkRequirements()
    {
        $tests = array('result' => true);
        $tests['curl'] = array(
            'name'   => $this->l('PHP cURL extension must be enabled on your server'),
            'result' => extension_loaded('curl'),
        );

        if (Configuration::get('SIMPLIFY_MODE')) {
            $tests['ssl'] = array(
                'name'   => $this->l('SSL must be enabled on your store (before entering Live mode)'),
                'result' => Configuration::get('PS_SSL_ENABLED') || (!empty($_SERVER['HTTPS']) && Tools::strtolower(
                            $_SERVER['HTTPS']
                        ) != 'off'),
            );
        }

        $tests['php52'] = array(
            'name'   => $this->l('Your server must run PHP 5.3 or greater'),
            'result' => version_compare(PHP_VERSION, '5.3.0', '>='),
        );

        $tests['configuration'] = array(
            'name'   => $this->l('You must set your Simplify Commerce API Keys'),
            'result' => $this->checkSettings(),
        );

        if ($tests['configuration']['result']) {
            $tests['keyprefix'] = array(
                'name'   => $this->l(
                    'Your API Keys appears to be invalid. Please make sure that you specified the right keys.'
                ),
                'result' => $this->checkKeyPrefix(),
            );
        }

        foreach ($tests as $k => $test) {
            if ($k != 'result' && !$test['result']) {
                $tests['result'] = false;
            }
        }

        return $tests;
    }

    /**
     * Display the Simplify Commerce's module settings page
     * for the user to set their API Key pairs and choose
     * whether their customer's can save their card details for
     * repeate visits.
     *
     * @return string Simplify settings page
     */
    public function getContent()
    {
        $html = '';

        // Get the latest release information from GitHub
        $latestRelease = $this->checkForUpdates();
        $latestversion = $latestRelease['version'];

        if ($latestRelease['available']) {
            // Display the alert
            $html .= $this->displayWarning(
                $this->l(
                'A new version ('.$latestversion.') of the module is now available! Please refer to the '
                ).
                '<a href="https://mpgs.fingent.wiki/simplify-commerce/simplify-commerce-payment
                -module-for-prestashop/release-notes" target="_blank">'.
                $this->l('Release Notes') .
                '</a>' .
                $this->l(' section for information about its compatibility and features.'),
                false
            );
        }

        // Update Simplify settings
        if (Tools::isSubmit('SubmitSimplify')) {
            $configurationValues = array(
                'SIMPLIFY_MODE'                   => Tools::getValue('simplify_mode'),
                'SIMPLIFY_SAVE_CUSTOMER_DETAILS'  => Tools::getValue('simplify_save_customer_details'),
                'SIMPLIFY_ENABLED_ERROR_LOG'      => Tools::getValue('simplify_enabled_error_log'),
                'SIMPLIFY_ENABLE_LINE_ITEMS'      => Tools::getValue('simplify_enable_line_items'),
                'SIMPLIFY_PUBLIC_KEY_TEST'        => Tools::getValue('simplify_public_key_test'),
                'SIMPLIFY_PUBLIC_KEY_LIVE'        => Tools::getValue('simplify_public_key_live'),
                'SIMPLIFY_PRIVATE_KEY_TEST'       => Tools::getValue('simplify_private_key_test'),
                'SIMPLIFY_PRIVATE_KEY_LIVE'       => Tools::getValue('simplify_private_key_live'),
                'SIMPLIFY_ENABLED_PAYMENT_WINDOW' => Tools::getValue('simplify_enabled_payment_window'),
                'SIMPLIFY_PAYMENT_ORDER_STATUS'   => (int)Tools::getValue('simplify_payment_status'),
                'SIMPLIFY_OVERLAY_COLOR'          => Tools::getValue('simplify_overlay_color'),
                'SIMPLIFY_PAYMENT_TITLE'          => Tools::getValue('simplify_payment_title'),
                'SIMPLIFY_TXN_MODE'               => Tools::getValue('simplify_txn_mode'),
                'SIMPLIFY_PAYMENT_OPTION'         => Tools::getValue('simplify_payment_option'),
            );

            $ok = true;

            foreach ($configurationValues as $configuration_key => $configuration_value) {
                $ok &= Configuration::updateValue($configuration_key, $configuration_value);
            }
            if ($ok) {
                $html .= $this->displayConfirmation($this->l('Settings updated successfully'));
            } else {
                $html .= $this->displayError($this->l('Error occurred during settings update'));
            }
            $this->simplifydatasend();
        }

        $requirements = $this->checkRequirements();

        $this->smarty->assign('path', $this->_path);
        $this->smarty->assign('module_name', $this->name);
        $this->smarty->assign('http_host', urlencode($_SERVER['HTTP_HOST']));
        $this->smarty->assign('requirements', $requirements);
        $this->smarty->assign('result', $requirements['result']);
        $this->smarty->assign('simplify_mode', Configuration::get('SIMPLIFY_MODE'));
        $this->smarty->assign('private_key_test', Configuration::get('SIMPLIFY_PRIVATE_KEY_TEST'));
        $this->smarty->assign('public_key_test', Configuration::get('SIMPLIFY_PUBLIC_KEY_TEST'));
        $this->smarty->assign('private_key_live', Configuration::get('SIMPLIFY_PRIVATE_KEY_LIVE'));
        $this->smarty->assign('public_key_live', Configuration::get('SIMPLIFY_PUBLIC_KEY_LIVE'));
        $this->smarty->assign('enabled_payment_window', Configuration::get('SIMPLIFY_ENABLED_PAYMENT_WINDOW'));
        $this->smarty->assign('enabled_embedded', Configuration::get('SIMPLIFY_ENABLED_EMBEDDED'));
        $this->smarty->assign('save_customer_details', Configuration::get('SIMPLIFY_SAVE_CUSTOMER_DETAILS'));
        $this->smarty->assign('enabled_error_log', Configuration::get('SIMPLIFY_ENABLED_ERROR_LOG'));
        $this->smarty->assign('enable_line_items', Configuration::get('SIMPLIFY_ENABLE_LINE_ITEMS'));
        $this->smarty->assign('statuses', OrderState::getOrderStates((int)$this->context->cookie->id_lang));
        $this->smarty->assign('request_uri', Tools::safeOutput($_SERVER['REQUEST_URI']));
        $this->smarty->assign(
            'overlay_color',
            Configuration::get('SIMPLIFY_OVERLAY_COLOR') != null ? Configuration::get(
                'SIMPLIFY_OVERLAY_COLOR'
            ) : $this->defaultModalOverlayColor
        );
        $this->smarty->assign('payment_title', Configuration::get('SIMPLIFY_PAYMENT_TITLE') ?: $this->defaultTitle);
        $this->smarty->assign(
            'embedded_payment_title',
            Configuration::get('SIMPLIFY_EMBEDDED_PAYMENT_TITLE') ?: $this->defaultTitle
        );
        $this->smarty->assign('txn_mode', Configuration::get('SIMPLIFY_TXN_MODE') ?: self::TXN_MODE_PURCHASE);
        $this->smarty->assign(
            'txn_mode_options',
            array(
                array(
                    'label' => $this->l('Payment'),
                    'value' => self::TXN_MODE_PURCHASE,
                ),
                array(
                    'label' => $this->l('Authorize'),
                    'value' => self::TXN_MODE_AUTHORIZE,
                ),
            )
        );
        $this->smarty->assign(
            'payment_option',
            Configuration::get('SIMPLIFY_PAYMENT_OPTION') ?: self::PAYMENT_OPTION_EMBEDDED
        );
        $this->smarty->assign(
            'payment_options',
            array(
                array(
                    'label' => $this->l('Embedded Payment Form'),
                    'value' => self::PAYMENT_OPTION_EMBEDDED,
                ),
                array(
                    'label' => $this->l('Modal Payment Window'),
                    'value' => self::PAYMENT_OPTION_MODAL,
                ),
            )
        );
        $this->smarty->assign(
            'statuses_options',
            array(
                array(
                    'name'          => 'simplify_payment_status',
                    'label'         => $this->l('Successful Payment Order Status'),
                    'current_value' => Configuration::get('SIMPLIFY_PAYMENT_ORDER_STATUS'),
                ),
            )
        );

        $baseImg = $this->context->link->getBaseLink().'modules/'.$this->name.'/views/img/';

        $this->smarty->assign('ok_icon_link', $baseImg.'checkmark-24.ico');
        $this->smarty->assign('nok_icon_link', $baseImg.'x-mark-24.ico');

        $html .= $this->display(__FILE__, 'views/templates/hook/module-wrapper.tpl');

        return $html;
    }
    
    /**
     * Check for the latest update available
     * for the module from the github
     *
     */
    public function checkForUpdates()
    {
        // Get the latest release information from GitHub
        $latestRelease = $this->getLatestGitHubVersion();

        // Compare the latest release version with the current module version
        if ($latestRelease !== null && version_compare($latestRelease['version'], $this->version, '>')) {
            // Newer version available
            return [
                'available'     => true,
                'version'       => $latestRelease['version'],
                'download_url'  => $latestRelease['download_url']
            ];
        } else {
            // Module is up to date
            return [
                'available' => false,
                'version'   => $this->version
            ];
        }
    }

    private function getLatestGitHubVersion()
    {
        $owner  = 'fingent-corp';
        $repo   = 'simplify-prestashop-mastercard-module';
        $url    = "https://api.github.com/repos/{$owner}/{$repo}/releases/latest";
        $ch     = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mastercard');
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return null;
        }
        curl_close($ch);
        $data = json_decode($response, true);
        
        if (isset($data['tag_name']) && isset($data['assets'][0]['browser_download_url'])) {
            return [
                'version' => $data['tag_name'],
                'download_url' => $data['assets'][0]['browser_download_url']
            ];
        } else {
            return null;
        }
    }

    /**
     * Create a log file in the prestashop path
     * ./var/logs/mastercard_simplify.log in the
     * encrypted format.
     *
     */
    public function logMessage($message)
    {
        if ($this->loggingEnabled) {

            // Define the path to the log file
            $logPath = _PS_ROOT_DIR_ . '/var/logs/mastercard_simplify.log';

            // Ensure the directory exists
            if (!is_dir(dirname($logPath))) {
                mkdir(dirname($logPath), 0755, true);
            }

            $hash    = hash('sha256', $this->public_key . $this->private_key);
            $logger  = new MastercardSimplifyApiLogger($hash);
            $message = date('Y-m-d g:i a') . ' : ' . $message;
            $logger->writeEncryptedlog($message);
        }
    }

    /**
     * Display the Simplify Commerce's void details
     * for the user in the Order details page
     *
     */
    public function hookDisplayAdminOrder($params)
    {
        // Assuming you get the request object in $params
        $request = $params['request'];
        
        // Get the orderId from the request attributes
        $orderId = $request->attributes->get('orderId');

        // Now use $orderId to perform your logic
        if ($orderId) {
            // Fetch void details if available
            $sql = new DbQuery();
            $sql->select('*');
            $sql->from('simplify_void_table');
            $sql->where('order_id = ' . (int)$orderId);

            $voidDetails = Db::getInstance()->getRow($sql);

            if ($voidDetails) {
                // Assign the void details to the template
                $this->context->smarty->assign([
                    'voidDetails' => $voidDetails,
                ]);

                // Return the rendered template
                return $this->display(__FILE__, 'views/templates/admin/voiddetails.tpl');
            }
            return '';
        }
    }

    public function simplifydatasend()
    {
        $countryId      = Configuration::get('PS_COUNTRY_DEFAULT');
        $country        = new Country($countryId);
        $countryName    = $country->name[$this->context->language->id];
        $countryCode    = $country->iso_code;
        $flag           = Configuration::get('SIMPLIFY_SET_FLAG');
        $version        = Configuration::get('SIMPLIFY_VERSION');
        $storeName      = Configuration::get('PS_SHOP_NAME');
        $storeUrl       = Configuration::get('PS_SHOP_DOMAIN');
        $publicKey      = Configuration::get('SIMPLIFY_PUBLIC_KEY_LIVE');
        $privateKey     = Configuration::get('SIMPLIFY_PRIVATE_KEY_LIVE');
        $data[]         = null;
        if (!empty($publicKey && $privateKey)) {
            if (($version != $this->version) && $flag || empty($flag)) {
                $data = [
                    'repo_name'      => 'simplify-prestashop-mastercard-module',
                    'plugin_type'    => 'simplify',
                    'tag_name'       => $this->version,
                    'latest_release' => '1',
                    'country_code'   => $countryCode,
                    'country'        => $countryName,
                    'shop_name'      => $storeName,
                    'shop_url'       => $storeUrl,
                ];
                Configuration::updateValue('SIMPLIFY_SET_FLAG', 1);
                Configuration::updateValue('SIMPLIFY_VERSION', $this->version);
            } else {
                return null;
            }
        }
        
        // Define the URL for the WordPress REST API endpoint
        $url         = self::MPGS_API_URL;
        // Set your Bearer token here
        $bearerToken = self::SIMPLIFY_MODULE_KEY;
        // Set up headers
        $headers     = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $bearerToken,
        ];
        // Initialize cURL
        $ch          = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Execute the request
        $response = curl_exec($ch);
        // Check for errors
        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            return 'Error: ' . $errorMsg;
        }

        // Close cURL
        curl_close($ch);
        return $response;
    }
}
