<?php

require_once _PS_MODULE_DIR_ . 'tamaraprestashop/TamaraConfiguration.php';

class tamaraprestashopCallbackModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        parent::initContent();
        $this->setTemplate('module:tamaraprestashop/views/templates/front/payment_infos.tpl');
    }

    public function postProcess()
    {
        $orderId       = Tools::getValue('orderId');
        $paymentStatus = Tools::getValue('paymentStatus');

        if (!$orderId || !$paymentStatus) {
            PrestaShopLogger::addLog('Missing orderId or paymentStatus');
            return;
        }

        // Get PrestaShop order ID safely
        $idOrder = (int) Db::getInstance()->getValue(
            'SELECT id_order FROM ' . _DB_PREFIX_ . 'tamara WHERE order_id = "' . pSQL($orderId) . '"'
        );

        if (!$idOrder) {
            PrestaShopLogger::addLog('Order not found for Tamara orderId: ' . $orderId);
            return;
        }

        $this->context->smarty->assign([
            'id_order' => $idOrder,
            'status'   => $paymentStatus,
        ]);

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            PrestaShopLogger::addLog('Invalid Order object: ' . $idOrder);
            return;
        }

        // Resolve environment
        $mode    = (int) TamaraConfiguration::get('mode');
        $baseUrl = ($mode === 1)
            ? 'https://api-sandbox.tamara.co/'
            : 'https://api.tamara.co/';

        switch ($paymentStatus) {

            case 'approved':
                PrestaShopLogger::addLog('Tamara payment approved: ' . $orderId);

                // Call Tamara authorise endpoint
                $endpoint = $baseUrl . 'orders/' . $orderId . '/authorise';

                $ch = curl_init($endpoint);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . TamaraConfiguration::get('api_token'),
                    ],
                ]);

                $response = curl_exec($ch);
                curl_close($ch);

                PrestaShopLogger::addLog('Tamara authorise response: ' . $response);

                // update order state
                if ((int) $order->current_state !== (int) Configuration::get('PS_OS_PAYMENT')) {
                    $history = new OrderHistory();
                    $history->id_order = $order->id;
                    $history->changeIdOrderState(
                        (int) Configuration::get('PS_OS_PAYMENT'),
                        $order->id
                    );
                    $history->addWithemail(true);
                }
                break;

            case 'declined':
            case 'expired':
            case 'canceled':
                PrestaShopLogger::addLog('Tamara payment rejected: ' . $orderId);

                if ((int) $order->current_state !== (int) Configuration::get('PS_OS_CANCELED')) {
                    $history = new OrderHistory();
                    $history->id_order = $order->id;
                    $history->changeIdOrderState(
                        (int) Configuration::get('PS_OS_CANCELED'),
                        $order->id
                    );
                    $history->addWithemail(false);
                }
                break;

            default:
                PrestaShopLogger::addLog('Unhandled payment status: ' . $paymentStatus);
                break;
        }

        $this->setTemplate('module:tamaraprestashop/views/templates/front/payment_infos.tpl');

    }
}
