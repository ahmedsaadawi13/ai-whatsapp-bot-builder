<?php
// FILE: /app/models/Payment.php

/**
 * Payment Model
 *
 * Handles payment transactions.
 */
class Payment extends Model {

    protected $table = 'payments';

    /**
     * Process payment (dummy implementation)
     *
     * @param int $tenantId
     * @param int|null $invoiceId
     * @param float $amount
     * @param string $paymentMethod
     * @return array Payment result
     */
    public function processPayment($tenantId, $invoiceId, $amount, $paymentMethod = 'credit_card') {
        // Simulate payment processing
        $transactionId = 'txn_' . uniqid() . time();

        $paymentId = $this->create([
            'tenant_id' => $tenantId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
            'status' => 'completed'
        ]);

        // Update invoice if provided
        if ($invoiceId) {
            require_once __DIR__ . '/Invoice.php';
            $invoiceModel = new Invoice();
            $invoiceModel->markAsPaid($invoiceId, $tenantId);
        }

        return [
            'success' => true,
            'payment_id' => $paymentId,
            'transaction_id' => $transactionId
        ];
    }
}
