<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
require_once _PS_MODULE_DIR_ . 'tamaraprestashop/TamaraConfiguration.php';

class TamaraPrestashopWebhookModuleFrontController extends ModuleFrontController
{

  public function postProcess()
  {
            PrestaShopLogger::addLog("Webhook Running",1, null, 'TamaraModule', null, true);
            $mode = TamaraConfiguration::get('mode');
            $prod = "https://api.tamara.co/";
            $sandbox = "https://api-sandbox.tamara.co/";
            $notificationKey = TamaraConfiguration::get('not_url');

            // Get the JWT token from the webhook URL query parameter
            $jwtToken = Tools::getValue('tamaraToken');

            if (!$jwtToken) {
               PrestaShopLogger::addLog("JWT token missing in webhook URL", 3, null, 'TamaraModule');
               http_response_code(401);
               exit('Unauthorized: token missing');
            }

            // Validate JWT
            try {
               $decoded = JWT::decode($jwtToken, new Key($notificationKey, 'HS256'));
               PrestaShopLogger::addLog("JWT token validated successfully",1, null, 'TamaraModule', null, true);
            } catch (\Exception $e) {
               PrestaShopLogger::addLog("JWT validation failed: " . $e->getMessage(), 3, null, 'TamaraModule');
               http_response_code(401);
               exit('Unauthorized: invalid token');
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                PrestaShopLogger::addLog('Invalid request method',3, null, 'TamaraModule', null, true);
                http_response_code(405); // Method Not Allowed
                exit('Invalid request method');
            }

            $raw_payload = file_get_contents('php://input');
            $payload = json_decode($raw_payload, true);

            if (!is_array($payload)) {
                PrestaShopLogger::addLog('Invalid webhook payload',3, null, 'TamaraModule', null, true);
                http_response_code(400);
                exit('Invalid payload');
            }

            PrestaShopLogger::addLog(json_encode($payload));

            $order_id = $payload['order_id'] ?? null;
            $order_status = $payload['order_status'] ?? null;

            if (!$order_id || !$order_status) {
              PrestaShopLogger::addLog('Missing order_id or order_status');
              return;
            }

            PrestaShopLogger::addLog("order status is : ".$order_status);

            switch ($order_status) {

              case 'approved':
                  PrestaShopLogger::addLog(sprintf(
                      'Tamara approved order. PS order ref: %s | Tamara order: %s',
                      $order_id,
                      $order_id
                  ));

                  // Resolve endpoint
                  $baseUrl = ($mode == 1)
                      ? $sandbox
                      : $prod;

                  $endpoint = $baseUrl . 'orders/' . $order_id . '/authorise';

                  // Call Tamara API
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

                  // Get PrestaShop order ID
                  $idOrder = (int) Db::getInstance()->getValue(
                      'SELECT id_order FROM ' . _DB_PREFIX_ . 'tamara WHERE order_id = "' . pSQL($order_id) . '"'
                  );

                  if ($idOrder) {
                      $order = new Order($idOrder);

                      if (Validate::isLoadedObject($order)) {
                          $history = new OrderHistory();
                          $history->id_order = $order->id;
                          $history->changeIdOrderState(
                              (int) Configuration::get('PS_OS_PAYMENT'),
                              $order->id
                          );
                          $history->addWithemail(true);

                          PrestaShopLogger::addLog('Order marked as paid: ' . $order->id);
                      }
                  }
                  break;

              case 'canceled':
              case 'declined':
              case 'expired':

                  PrestaShopLogger::addLog('Tamara rejected order: ' . $order_id);

                  $idOrder = (int) Db::getInstance()->getValue(
                      'SELECT id_order FROM ' . _DB_PREFIX_ . 'tamara WHERE order_id = "' . pSQL($order_id) . '"'
                  );

                  if ($idOrder) {
                      $order = new Order($idOrder);

                      if (Validate::isLoadedObject($order)) {
                          $history = new OrderHistory();
                          $history->id_order = $order->id;
                          $history->changeIdOrderState(
                              (int) Configuration::get('PS_OS_CANCELED'),
                              $order->id
                          );
                          $history->addWithemail(false);

                          PrestaShopLogger::addLog('Order canceled: ' . $order->id);
                      }
                  }
                  break;

              default:
                  PrestaShopLogger::addLog('Unhandled Tamara order status: ' . $order_status);
                  break;

      }

  }

}