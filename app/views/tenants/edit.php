<?php // FILE: /app/views/tenants/edit.php ?>
<div class="page-header">
    <h1>Edit <?php echo e($tenant['name']); ?></h1>
    <div class="actions"><a href="/tenants/<?php echo (int) $tenant['id']; ?>" class="btn btn-secondary">Back</a></div>
</div>

<form method="POST" action="/tenants/<?php echo (int) $tenant['id']; ?>/update" class="card">
    <?php echo csrf_field(); ?>
    <div class="form-row">
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?php echo e(old('name', $tenant['name'])); ?>" required maxlength="255">
            <?php echo field_error('name'); ?>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo e(old('email', $tenant['email'])); ?>" required maxlength="255">
            <?php echo field_error('email'); ?>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="tel" id="phone" name="phone" value="<?php echo e(old('phone', $tenant['phone'])); ?>" maxlength="50">
            <?php echo field_error('phone'); ?>
        </div>
        <div class="form-group">
            <label for="industry">Industry</label>
            <input type="text" id="industry" name="industry" value="<?php echo e(old('industry', $tenant['industry'])); ?>" maxlength="100">
            <?php echo field_error('industry'); ?>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="timezone">Timezone</label>
            <select id="timezone" name="timezone">
                <?php $tz = old('timezone', $tenant['timezone']); foreach ($timezones as $zone): ?>
                    <option value="<?php echo e($zone); ?>"<?php echo selected($tz, $zone); ?>><?php echo e($zone); ?></option>
                <?php endforeach; ?>
            </select>
            <?php echo field_error('timezone'); ?>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php $st = old('status', $tenant['status']); foreach (['active', 'suspended', 'cancelled'] as $s): ?>
                    <option value="<?php echo e($s); ?>"<?php echo selected($st, $s); ?>><?php echo e(ucfirst($s)); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="help">Suspended or cancelled tenants cannot log in, use the API or receive webhooks.</div>
            <?php echo field_error('status'); ?>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn">Save changes</button>
        <a href="/tenants/<?php echo (int) $tenant['id']; ?>" class="btn btn-secondary">Cancel</a>
    </div>
</form>
