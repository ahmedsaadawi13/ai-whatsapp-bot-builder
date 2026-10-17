<?php // FILE: /app/views/tenants/create.php ?>
<div class="page-header">
    <h1>New tenant</h1>
    <div class="actions"><a href="/tenants" class="btn btn-secondary">Back</a></div>
</div>

<form method="POST" action="/tenants/store" class="card">
    <?php echo csrf_field(); ?>
    <h2>Business details</h2>
    <div class="form-row">
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?php echo e(old('name')); ?>" required maxlength="255">
            <?php echo field_error('name'); ?>
        </div>
        <div class="form-group">
            <label for="email">Contact email</label>
            <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" required maxlength="255">
            <?php echo field_error('email'); ?>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="tel" id="phone" name="phone" value="<?php echo e(old('phone')); ?>" maxlength="50">
            <?php echo field_error('phone'); ?>
        </div>
        <div class="form-group">
            <label for="industry">Industry</label>
            <input type="text" id="industry" name="industry" value="<?php echo e(old('industry')); ?>" maxlength="100">
            <?php echo field_error('industry'); ?>
        </div>
        <div class="form-group">
            <label for="plan_id">Plan</label>
            <select id="plan_id" name="plan_id" required>
                <?php foreach ($plans as $plan): ?>
                    <option value="<?php echo (int) $plan['id']; ?>"<?php echo selected(old('plan_id'), $plan['id']); ?>><?php echo e($plan['name']); ?> - $<?php echo e($plan['price']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php echo field_error('plan_id'); ?>
        </div>
    </div>

    <h2 class="mt">First tenant admin</h2>
    <div class="form-row">
        <div class="form-group">
            <label for="admin_name">Name</label>
            <input type="text" id="admin_name" name="admin_name" value="<?php echo e(old('admin_name')); ?>" required maxlength="255">
            <?php echo field_error('admin_name'); ?>
        </div>
        <div class="form-group">
            <label for="admin_email">Login email</label>
            <input type="email" id="admin_email" name="admin_email" value="<?php echo e(old('admin_email')); ?>" required maxlength="255">
            <?php echo field_error('admin_email'); ?>
        </div>
        <div class="form-group">
            <label for="admin_password">Password</label>
            <input type="password" id="admin_password" name="admin_password" required minlength="8">
            <?php echo field_error('admin_password'); ?>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn">Create tenant</button>
        <a href="/tenants" class="btn btn-secondary">Cancel</a>
    </div>
</form>
