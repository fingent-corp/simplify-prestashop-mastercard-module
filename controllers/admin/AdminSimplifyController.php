<?php
/**
 * Copyright (c) 2019-2024 Mastercard
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

class AdminSimplifyController extends ModuleAdminController
{
    public const TRANSACTION_SUCCESS_MESSAGE = "Transaction Successful.";
    public const TRANSACTION_FAILED_MESSAGE  = "Transaction failed.";
    public const TRANSCATION_DECLINED        = "Transaction Declined.";
    public const AMOUNT_MISMATCH             = "Transaction paid amount does not equal order amount.";
    public const AUTHORIZED_AMOUNT_MISMATCH  = "Authorized amount does not equal paid amount.";
    public const CAPTURE_DB_ERROR            = "Error inserting capture data into the database.";
    public const VOID_DB_ERROR               = "Error inserting Void data into the database.";
    public const REFUND_DB_ERROR             = "Error inserting refund data into the database.";

    /**
     * @return bool|ObjectModel|void
     * @throws Exception
     */
    public function postProcess()
    {
        $action = Tools::getValue('action');
        if (!$action) {
            return;
        }

        $actionName = $action . 'Action';
        $orderId    = Tools::getValue('id_order');
        $order      = new Order($orderId);

        $this->module->initSimplify();

        try {
            $this->{$actionName}($order);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders')
                .'&conf=4&id_order='.(int)$order->id.'&vieworder');
        } catch (Simplify_ApiException $e) {
            $this->errors[] = $e->describe();
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage() . ' (' . $e->getCode() . ')';
        }

        parent::postProcess();
    }

    /**
     * @param Order $order
     * @return void
     * @throws Exception
     */
    protected function captureAction($order)
    {
        $payment        = $order->getOrderPaymentCollection()->getFirst();
        $txnId          = $payment->transaction_id;
        $orderId        = $order->id;
        $paymentAmount  = number_format($payment->amount, 2);
        $paidAmount     = number_format($order->total_paid, 2);

        if ($paymentAmount !== $paidAmount) {
            throw new Simplify_ApiException(self::AMOUNT_MISMATCH);
        }

        $currencyOrder = new Currency((int)($order->id_currency));

        $auth = Simplify_Authorization::findAuthorization($txnId);

        if ($auth->amount !== (int) round($payment->amount * 100)) {
            throw new Simplify_ApiException(self::AUTHORIZED_AMOUNT_MISMATCH);
        }

        $payment = Simplify_Payment::createPayment([
            'authorization' => $auth->id,
            'currency'      => strtoupper($currencyOrder->iso_code),
            'amount'        => $auth->amount
        ]);

        $this->module->logMessage("Capture payment details: " . $payment);

        if ($payment->declineReason === "AUTHORIZATION_EXPIRED") {
            $comment = "System error : Authorization expired for this order";
            $this->insertCapture($payment, $orderId, $comment);
            $newStatus = Configuration::get('PS_OS_CANCELED');
            if ($newStatus !== null) {
                $history = new OrderHistory();
                $history->id_order = (int)$order->id;
                $history->changeIdOrderState($newStatus, $order, true);
                $history->addWithemail(true, array());
                throw new Simplify_ApiException(self::TRANSCATION_DECLINED);
            }
        } elseif ($payment->paymentStatus === "DECLINED") {
            $comment = self::TRANSACTION_FAILED_MESSAGE;
            $this->insertCapture($payment, $orderId, $comment);
            throw new Simplify_ApiException(self::TRANSACTION_FAILED_MESSAGE);
            
        } else {
            $comment = self::TRANSACTION_SUCCESS_MESSAGE;
            // Insert the capture details into the database
            $this->insertCapture($payment, $orderId, $comment);
            $this->updateOrder(
                $auth->id,
                $order
            );
        }
    }

    /**
     * @param Payment $payment
     * @return true
     * @throws Exception
     */
    private function insertCapture($payment, $orderId, $comment)
    {
        // Get the values of the fields that you want to insert into the database.
        $captureTranscationId = $payment->authorization->id;
        $paymentTranscationId = $payment->id;
        $amount                 = $payment->amount / 100;
        $date                   = $payment->transactionData->date;

        // Build the SQL INSERT statement using the Db::getInstance()->insert method.
        $data = array(
            'order_id'               => $orderId,
            'capture_transcation_id' => $captureTranscationId,
            'payment_transcation_id' => $paymentTranscationId,
            'amount'                 => $amount,
            'comment'                => $comment,
            'transcation_date'       => $date,
        );
        if (!Db::getInstance()->insert('capture_table', $data)) {
            throw new Simplify_ApiException(self::CAPTURE_DB_ERROR);
        }
        return true; // Return true on success, or handle errors as needed.
    }

    /**
     * @param Order $order
     * @return void
     */
    protected function voidAction($order)
    {
        $payment = $order->getOrderPaymentCollection()->getFirst();
        $txnId   = $payment->transaction_id;
        $auth    = Simplify_Authorization::findAuthorization($txnId);
        $auth->deleteAuthorization();
        $this->module->logMessage("Void details: " . $auth);
        $this->updateOrder(
            $auth->id,
            $order
        );
    }

    /**
     * @param string $paymentId
     * @param Order $order
     */
    protected function updateOrder($paymentId, $order)
    {
        $newStatus = null;
        $payment   = Simplify_Authorization::findAuthorization($paymentId);

        if ($payment->reversed === true) {
            
            $newStatus = Configuration::get('PS_OS_CANCELED');
            $data      = array(
                'order_id'       => $order->id,
                'transcation_id' => $payment->id,
                'amount'         => $payment->amount / 100,
                'date_created'   => $payment->transactionData->date,
            );
            if (!Db::getInstance()->insert('simplify_void_table', $data)) {
                throw new Simplify_ApiException(self::VOID_DB_ERROR);
            }
        }

        if ($payment->captured === true) {
           
            $newStatus = Configuration::get('SIMPLIFY_PAYMENT_ORDER_STATUS');
        }

        if ($newStatus !== null) {
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState($newStatus, $order, true);
            $history->addWithemail(true, array());
        }
    }

    /**
     * @param string $orderReference
     *
     * @return string
     */
    private function getUniqueTransactionId($txnId)
    {
        $uniqId = substr(uniqid(), 7, 6);
        return sprintf('%s-%s', $txnId, $uniqId);
    }

    /**
     * Partial refund action for your module.
     * Handles partial refunds for an order.
     *
     * @return JSON
     */
    public function PartialRefundAction()
    {
        if (isset($_POST['action']) && $_POST['action'] === 'partialRefund') {
            $refundAmount  = $_POST['RefundAmount'];
            $refundReason  = trim($_POST['Refundreason']);
            $orderId       = trim($_POST['OrderId']);
            $productAmount = trim($_POST['ProductAmount']);

            $referenceId       = $this->getOrderReference($orderId);
            $transactionId     = $this->getTransactionId($referenceId);
            $paymentTransactionId = $this->getPaymentTransactionId($transactionId);

            $valueToUse = $paymentTransactionId ?: $transactionId;

            $newtxnId = $this->getUniqueTransactionId($transactionId);
            $amount   = number_format(($refundAmount * 100), 2);

            $refund = $this->createSimplifyRefund($newtxnId, $refundReason, $amount, $valueToUse);

            $this->module->logMessage("Partial refund details: " . $refund);

            if ($refund->paymentStatus === "APPROVED") {
                $this->handleApprovedRefund($refund, $orderId, $productAmount);
            } else {
                $this->handleFailedRefund($refund, $orderId);
            }
        }
    }

    private function getOrderReference($orderId)
    {
        $sql = "SELECT reference FROM " . pSQL(_DB_PREFIX_ . "orders") .
         " WHERE id_order = '" . pSQL($orderId) . "'";
        return Db::getInstance()->getValue($sql);
    }

    private function getTransactionId($referenceId)
    {
        $sql = "SELECT transaction_id FROM " . pSQL(_DB_PREFIX_ . "order_payment") .
         " WHERE order_reference = '" . pSQL($referenceId) . "'";
        return Db::getInstance()->getValue($sql);
    }

    private function getPaymentTransactionId($transactionId)
    {
        $sql = "SELECT payment_transcation_id FROM " . pSQL(_DB_PREFIX_ . "capture_table") .
         " WHERE capture_transcation_id = '" . pSQL($transactionId) . "'";
        return Db::getInstance()->getValue($sql);
    }

    private function createSimplifyRefund($newtxnId, $refundReason, $amount, $valueToUse)
    {
        return Simplify_Refund::createRefund([
            'reference' => $newtxnId,
            'reason'    => $refundReason,
            'amount'    => $amount,
            'payment'   => $valueToUse,
        ]);
    }

    private function handleApprovedRefund($refund, $orderId, $productAmount)
    {
        $comment = self::TRANSACTION_SUCCESS_MESSAGE;
        $this->insertRefund($refund, $orderId, $comment);

        $totalAmount = $this->getTotalRefundedAmount($orderId);

        $newStatus = $this->determineNewOrderStatus($productAmount, $totalAmount, $refund->paymentStatus);

        if ($newStatus !== null) {
            $this->updateOrderStatus($orderId, $newStatus);
        }

        echo json_encode('{"status":"success"}');
        exit;
    }

    private function handleFailedRefund($refund, $orderId)
    {
        $comment = "Transaction Failed.";
        $this->insertRefund($refund, $orderId, $comment);
        echo json_encode('{"status":"failed"}');
        exit;
    }

    private function getTotalRefundedAmount($orderId)
    {
        $sql = "SELECT SUM(amount) AS total_amount FROM " . pSQL(_DB_PREFIX_ . "refund_table") .
         " WHERE order_id = '" . pSQL($orderId) . "' GROUP BY order_id";
        $result = Db::getInstance()->executeS($sql);
        return $result[0]['total_amount'] ?? 0;
    }

    private function determineNewOrderStatus($productAmount, $totalAmount, $paymentStatus)
    {
        if ($paymentStatus !== 'APPROVED') {
            return null;
        }

        if ($productAmount == $totalAmount) {
            return Configuration::get('PS_OS_REFUND');
        }

        return Configuration::get('SIMPLIFY_OS_PARTIAL_REFUND');
    }

    private function updateOrderStatus($orderId, $newStatus)
    {
        $history = new OrderHistory();
        $history->id_order = (int)$orderId;
        $history->changeIdOrderState($newStatus, $orderId, true);
        $history->addWithemail(true, array());
    }

    /**
     * Full refund action for your module.
     * Handles Full refunds for an order.
     *
     * @return JSON
     */
    public function FullRefundAction()
    {
        if (isset($_POST['action']) && $_POST['action'] === 'fullRefund') {
            $refundAmount = trim($_POST['RefundAmount']);
            $refundReason = trim($_POST['Refundreason']);
            $orderId      = trim($_POST['OrderId']);

            $sql = "SELECT reference FROM " . pSQL(_DB_PREFIX_ . "orders") .
             " WHERE id_order = '" . pSQL($orderId) . "'";
            $referenceId  = Db::getInstance()->getValue($sql);

            $sql = "SELECT transaction_id FROM " . pSQL(_DB_PREFIX_ . "order_payment") .
             " WHERE order_reference = '" . pSQL($referenceId) . "'";
            $transactionId = Db::getInstance()->getValue($sql);

            $sql = "SELECT payment_transcation_id FROM " . pSQL(_DB_PREFIX_ . "capture_table") .
             " WHERE capture_transcation_id = '" . pSQL($transactionId) . "'";
            $paymentTransactionId = Db::getInstance()->getValue($sql);

            // Determine the value to use
            if ($paymentTransactionId) {
                $valueToUse = $paymentTransactionId;
            } else {
                $valueToUse = $transactionId;
            }

            $newtxnId   = $this->getUniqueTransactionId($transactionId);
            $amount     = number_format(($refundAmount * 100), 2);
            $refund     = Simplify_Refund::createRefund([
                'reference' => $newtxnId,
                'reason'    => $refundReason,
                'amount'    => $amount,
                'payment'   => $valueToUse,
            ]);

            $this->module->logMessage("Full refund details: " . $refund);

            if ($refund->paymentStatus === "APPROVED") {
                $comment = self::TRANSACTION_SUCCESS_MESSAGE;
                // Insert the refund details into the database
                $this->insertRefund($refund, $orderId, $comment);

                // Check the refund status and update the order status accordingly
                $newStatus = null;
                $refundId  = $refund->id;
                $payment   = Simplify_Refund::findRefund($refundId);

                if ($payment->paymentStatus === 'APPROVED') {
                    
                    $newStatus = Configuration::get('PS_OS_REFUND');
                }

                if ($newStatus !== null) {
                    $history = new OrderHistory();
                    $history->id_order = (int)$orderId;
                    $history->changeIdOrderState($newStatus, $orderId, true);
                    $history->addWithemail(true, array());
                }
                // Return JSON response
               echo json_encode('{"status":"success"}');
                exit;
            } else {
                // Insert the refund details into the database
                $comment = "Transaction Failed.";
                $this->insertRefund($refund, $orderId, $comment);
                echo json_encode('{"status":"failed"}');
                exit;
            }
        }
    }

    /**
     * @param Refund $refund
     * @return true
     * @throws Exception
     */
    private function insertRefund($refund, $orderId, $comment)
    {
        // Get the values of the fields that you want to insert into the database.
        $refundId           = $refund->id;
        $transcationId      = $refund->payment->id;
        $amount             = $refund->amount / 100;
        $refundDescription  = $refund->description;
        $dateCreated        = $refund->dateCreated;
        $timeStamp          = $dateCreated / 1000;
        $date               = date("Y-m-d H:i:s", $timeStamp);
        // Build the SQL INSERT statement using the Db::getInstance()->insert method.
        $data = array(
            'refund_id'          => $refundId,
            'order_id'           => $orderId,
            'transcation_id'     => $transcationId,
            'refund_description' => $refundDescription,
            'amount'             => $amount,
            'comment'            => $comment,
            'date_created'       => $date,
        );

        if (!Db::getInstance()->insert('refund_table', $data)) {
            throw new Simplify_ApiException(self::REFUND_DB_ERROR);
        }

        return true; // Return true on success, or handle errors as needed.
    }
}

