<?php
// FILE: /app/models/Payment.php

/**
 * Payment Model - dummy payment gateway.
 *
 * processPayment() simulates a card charge. A card number ending in "0002"
 * simulates a decline so the failure path can be tested.
 */
class Payment extends Model {

    protected $table = 'payments';

    /**
     * @return array ['success' => bool, 'transaction_id' => string|null, 'message' => string]
     */
    public function processPayment($tenantId, $invoice, $method = 'credit_card', $cardNumber = '') {
        $declined = substr(preg_replace('/\D/', '', (string) $cardNumber), -4) === '0002';
        $transactionId = 'txn_' . bin2hex(random_bytes(8));

        $this->beginTransaction();
        try {
            $this->create([
                'tenant_id' => $tenantId,
                'invoice_id' => $invoice['id'],
                'amount' => $invoice['total'],
                'payment_method' => $method,
                'transaction_id' => $transactionId,
                'status' => $declined ? 'failed' : 'completed',
                'notes' => $declined ? 'Simulated decline (test card ending 0002)' : 'Simulated payment (dummy gateway)'
            ]);

            if (!$declined) {
                (new Invoice())->markAsPaid($invoice['id'], $tenantId);
                if (!empty($invoice['subscription_id']) && !empty($invoice['period_end'])) {
                    (new Subscription())->extendTo($invoice['subscription_id'], $tenantId, $invoice['period_end'] . ' 23:59:59');
                }
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }

        return [
            'success' => !$declined,
            'transaction_id' => $transactionId,
            'message' => $declined ? 'Payment declined by the (simulated) bank' : 'Payment successful'
        ];
    }

    public function getHistory($tenantId, $limit = 20) {
        $sql = "SELECT p.*, i.invoice_number FROM {$this->table} p
                LEFT JOIN invoices i ON i.id = p.invoice_id
                WHERE p.tenant_id = ? ORDER BY p.id DESC " . $this->limitClause($limit);
        return $this->query($sql, [$tenantId])->fetchAll();
    }
}
