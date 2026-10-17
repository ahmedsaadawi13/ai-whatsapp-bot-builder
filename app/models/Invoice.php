<?php
// FILE: /app/models/Invoice.php

/**
 * Invoice Model - billing invoices per tenant.
 */
class Invoice extends Model {

    const TAX_RATE = 0.10;

    protected $table = 'invoices';
    protected $searchable = ['invoice_number', 'description'];

    /** Sequential-looking, unique invoice number, e.g. INV-2026-000042. */
    public function generateInvoiceNumber() {
        $next = (int) $this->query("SELECT COALESCE(MAX(id), 0) + 1 AS n FROM {$this->table}")->fetch()['n'];
        do {
            $number = sprintf('INV-%s-%06d', date('Y'), $next++);
            $exists = $this->query("SELECT 1 FROM {$this->table} WHERE invoice_number = ?", [$number])->fetch();
        } while ($exists);
        return $number;
    }

    public function createForPlan($tenantId, $subscriptionId, $plan, $start, $end) {
        $amount = round((float) $plan['price'], 2);
        $tax = round($amount * self::TAX_RATE, 2);
        return $this->create([
            'tenant_id' => $tenantId,
            'subscription_id' => $subscriptionId,
            'invoice_number' => $this->generateInvoiceNumber(),
            'description' => $plan['name'] . ' plan (' . $plan['billing_period'] . ')',
            'amount' => $amount,
            'tax' => $tax,
            'total' => $amount + $tax,
            'status' => 'pending',
            'period_start' => date('Y-m-d', strtotime($start)),
            'period_end' => date('Y-m-d', strtotime($end)),
            'due_date' => date('Y-m-d', strtotime('+7 days'))
        ]);
    }

    public function markAsPaid($invoiceId, $tenantId) {
        return $this->update($invoiceId, ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')], $tenantId);
    }

    public function getPayments($invoiceId, $tenantId) {
        $stmt = $this->query(
            "SELECT * FROM payments WHERE invoice_id = ? AND tenant_id = ? ORDER BY id DESC",
            [$invoiceId, $tenantId]
        );
        return $stmt->fetchAll();
    }

    public function totals($tenantId) {
        $stmt = $this->query(
            "SELECT COALESCE(SUM(CASE WHEN status = 'paid' THEN total END), 0) AS paid,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN total END), 0) AS outstanding
             FROM {$this->table} WHERE tenant_id = ?",
            [$tenantId]
        );
        return $stmt->fetch();
    }
}
