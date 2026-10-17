<?php
// FILE: /app/controllers/BillingController.php

/**
 * Billing Controller - invoices, billing history and the dummy payment gateway.
 */
class BillingController extends Controller {

    const METHODS = ['credit_card' => 'Credit card', 'bank_transfer' => 'Bank transfer', 'paypal' => 'PayPal'];

    public function index() {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $model = new Invoice();
        $filters = ['status' => in_array($this->request->get('status'), ['pending', 'paid', 'cancelled'], true) ? $this->request->get('status') : null];
        $search = $this->request->get('search');
        $pagination = $this->getPagination($model->count($filters, $tenantId, $search));

        $this->view('billing/index', [
            'title' => 'Billing',
            'invoices' => $model->findAll($filters, $tenantId, $pagination['per_page'], $pagination['offset'], $search),
            'pagination' => $pagination,
            'filters' => $filters + ['search' => $search],
            'totals' => $model->totals($tenantId),
            'payments' => (new Payment())->getHistory($tenantId, 10)
        ]);
    }

    public function showInvoice($id) {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $invoice = $this->findOwned(new Invoice(), $id, $tenantId);
        $this->view('billing/invoice', [
            'title' => 'Invoice ' . $invoice['invoice_number'],
            'invoice' => $invoice,
            'tenant' => (new Tenant())->find($tenantId),
            'payments' => (new Invoice())->getPayments($invoice['id'], $tenantId),
            'methods' => self::METHODS
        ]);
    }

    /**
     * Simulated payment. Any card number works except one ending in 0002 (decline).
     */
    public function payInvoice() {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $this->requireCsrf();

        $invoice = (new Invoice())->find((int) $this->request->post('invoice_id'), $tenantId);
        if (!$invoice) {
            $this->abort(404, 'Invoice not found');
        }
        if ($invoice['status'] !== 'pending') {
            $this->session->flash('error', 'This invoice is not awaiting payment');
            $this->redirect('/billing/invoices/' . $invoice['id']);
        }

        $method = $this->request->post('payment_method', 'credit_card');
        if (!array_key_exists($method, self::METHODS)) {
            $method = 'credit_card';
        }
        $card = preg_replace('/\D/', '', (string) $this->request->post('card_number', ''));
        if ($method === 'credit_card' && (strlen($card) < 12 || strlen($card) > 19)) {
            $this->failValidation(['card_number' => ['Enter a test card number (12-19 digits)']]);
        }

        $result = (new Payment())->processPayment($tenantId, $invoice, $method, $card);
        $this->session->flash($result['success'] ? 'success' : 'error',
            $result['message'] . ' (transaction ' . $result['transaction_id'] . ')');
        $this->redirect('/billing/invoices/' . $invoice['id']);
    }
}
