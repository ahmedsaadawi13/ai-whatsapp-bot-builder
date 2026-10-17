<?php
// FILE: /app/controllers/NotificationController.php

/**
 * Notification Controller - log of simulated emails (welcome, quota, daily summary).
 * Tenant admins see their tenant's emails; the platform admin sees all.
 */
class NotificationController extends Controller {

    public function index() {
        $this->requireRole(['tenant_admin', 'platform_admin']);
        $tenantId = $this->getTenantId();
        $model = new Notification();
        $search = $this->request->get('search');
        $pagination = $this->getPagination($model->count([], $tenantId, $search));

        $this->view('notifications/index', [
            'title' => 'Email Notifications',
            'notifications' => $model->findAll([], $tenantId, $pagination['per_page'], $pagination['offset'], $search),
            'pagination' => $pagination,
            'filters' => ['search' => $search]
        ]);
    }
}
