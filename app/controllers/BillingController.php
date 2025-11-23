<?php
// FILE: /app/controllers/BillingController.php

class BillingController extends Controller {
    public function index() {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        require_once __DIR__ . '/../models/Invoice.php';
        $invoiceModel = new Invoice();
        $invoices = $invoiceModel->getWithPayments($tenantId);
        $this->view('billing/index', ['title' => 'Billing', 'invoices' => $invoices]);
    }

    public function showInvoice($id) {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        require_once __DIR__ . '/../models/Invoice.php';
        $invoiceModel = new Invoice();
        $invoice = $invoiceModel->find($id, $tenantId);
        if (!$invoice) {
            $this->session->flash('error', 'Invoice not found');
            $this->redirect('/billing');
        }
        $this->view('billing/invoice', ['title' => 'Invoice Details', 'invoice' => $invoice]);
    }

    public function payInvoice() {
        $this->requireAuth();
        $this->requireCsrf();
        $tenantId = $this->getTenantId();
        $invoiceId = $this->request->post('invoice_id');
        require_once __DIR__ . '/../models/Invoice.php';
        require_once __DIR__ . '/../models/Payment.php';
        $invoiceModel = new Invoice();
        $paymentModel = new Payment();
        $invoice = $invoiceModel->find($invoiceId, $tenantId);
        if (!$invoice || $invoice['status'] === 'paid') {
            $this->session->flash('error', 'Invalid invoice');
            $this->redirect('/billing');
        }
        $result = $paymentModel->processPayment($tenantId, $invoiceId, $invoice['total'], 'credit_card');
        if ($result['success']) {
            $this->session->flash('success', 'Payment processed successfully');
        } else {
            $this->session->flash('error', 'Payment failed');
        }
        $this->redirect('/billing');
    }
}
