<?php
// FILE: /app/controllers/SubscriptionController.php

/**
 * Subscription Controller - current plan, usage vs limits, plan changes.
 */
class SubscriptionController extends Controller {

    public function index() {
        $tenantId = $this->requireTenant(['tenant_admin']);
        (new UsageTracking())->recalculate($tenantId);
        $this->view('subscription/index', [
            'title' => 'Subscription',
            'subscription' => (new Subscription())->getActiveSubscription($tenantId),
            'quota' => QuotaService::summary($tenantId),
            'history' => (new Subscription())->getHistory($tenantId),
            'usageHistory' => (new UsageTracking())->getHistory($tenantId, 6)
        ]);
    }

    public function plans() {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $this->view('subscription/plans', [
            'title' => 'Plans',
            'plans' => (new Plan())->getActivePlans(),
            'subscription' => (new Subscription())->getActiveSubscription($tenantId)
        ]);
    }

    /**
     * Switch plan. Downgrades are refused while current usage exceeds the
     * new plan's limits. A pending invoice is created for paid plans.
     */
    public function changePlan() {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $this->requireCsrf();

        $plan = (new Plan())->findActive((int) $this->request->post('plan_id'));
        if (!$plan) {
            $this->session->flash('error', 'Please choose a valid plan');
            $this->redirect('/subscription/plans');
        }
        $current = (new Subscription())->getActiveSubscription($tenantId);
        if ($current && (int) $current['plan_id'] === (int) $plan['id']) {
            $this->session->flash('error', 'You are already on the ' . $plan['name'] . ' plan');
            $this->redirect('/subscription/plans');
        }

        $blocking = [];
        foreach (['channels' => 'max_channels', 'bots' => 'max_bots', 'contacts' => 'max_contacts', 'storage' => 'max_storage_mb'] as $resource => $limitKey) {
            $used = QuotaService::used($tenantId, $resource);
            if ($used > (int) $plan[$limitKey]) {
                $blocking[] = QuotaService::label($resource) . " ({$used} used, plan allows {$plan[$limitKey]})";
            }
        }
        if (!empty($blocking)) {
            $this->session->flash('error', 'Cannot switch to ' . $plan['name'] . ': reduce usage first - ' . implode('; ', $blocking));
            $this->redirect('/subscription/plans');
        }

        $result = (new Subscription())->switchPlan($tenantId, $plan);
        if ($result['invoice_id']) {
            $this->session->flash('success', 'Plan changed to ' . $plan['name'] . '. An invoice was created - pay it to keep your subscription active.');
            $this->redirect('/billing/invoices/' . $result['invoice_id']);
        }
        $this->session->flash('success', 'Plan changed to ' . $plan['name']);
        $this->redirect('/subscription');
    }
}
