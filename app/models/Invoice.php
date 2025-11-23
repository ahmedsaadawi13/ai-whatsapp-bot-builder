<?php
// FILE: /app/models/Invoice.php

/**
 * Invoice Model
 *
 * Handles billing invoices.
 */
class Invoice extends Model {

    protected $table = 'invoices';

    /**
     * Generate invoice number
     *
     * @return string
     */
    public function generateInvoiceNumber() {
        $year = date('Y');
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE invoice_number LIKE ?";
        $stmt = $this->query($sql, ["INV-{$year}-%"]);
        $count = $stmt->fetch()['count'] + 1;

        return sprintf('INV-%s-%04d', $year, $count);
    }

    /**
     * Create invoice
     *
     * @param array $data
     * @return int Invoice ID
     */
    public function createInvoice($data) {
        if (!isset($data['invoice_number'])) {
            $data['invoice_number'] = $this->generateInvoiceNumber();
        }

        return $this->create($data);
    }

    /**
     * Mark invoice as paid
     *
     * @param int $invoiceId
     * @param int $tenantId
     * @return bool
     */
    public function markAsPaid($invoiceId, $tenantId) {
        return $this->update($invoiceId, [
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s')
        ], $tenantId);
    }

    /**
     * Get invoices with payment info
     *
     * @param int $tenantId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getWithPayments($tenantId, $limit = 100, $offset = 0) {
        $sql = "SELECT i.*, p.payment_method, p.transaction_id, p.created_at as payment_date
                FROM {$this->table} i
                LEFT JOIN payments p ON i.id = p.invoice_id
                WHERE i.tenant_id = ?
                ORDER BY i.id DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->query($sql, [$tenantId, $limit, $offset]);
        return $stmt->fetchAll();
    }
}
