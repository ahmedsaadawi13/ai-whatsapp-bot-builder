<?php // FILE: /app/views/users/edit.php ?>
<div class="page-header">
    <h1>Edit <?php echo e($editUser['name']); ?></h1>
    <div class="actions"><a href="/users" class="btn btn-secondary">Back</a></div>
</div>

<form method="POST" action="/users/<?php echo (int) $editUser['id']; ?>/update" class="card">
    <?php echo csrf_field(); ?>
    <div class="form-row">
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?php echo e(old('name', $editUser['name'])); ?>" required maxlength="255">
            <?php echo field_error('name'); ?>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo e(old('email', $editUser['email'])); ?>" required maxlength="255">
            <?php echo field_error('email'); ?>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role">
                <?php $role = old('role', $editUser['role']); foreach ($roles as $r): ?>
                    <option value="<?php echo e($r); ?>"<?php echo selected($role, $r); ?>><?php echo e(ucfirst(str_replace('_', ' ', $r))); ?></option>
                <?php endforeach; ?>
            </select>
            <?php echo field_error('role'); ?>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php $status = old('status', $editUser['status']); ?>
                <option value="active"<?php echo selected($status, 'active'); ?>>Active</option>
                <option value="inactive"<?php echo selected($status, 'inactive'); ?>>Inactive (cannot log in)</option>
            </select>
            <?php echo field_error('status'); ?>
        </div>
        <div class="form-group">
            <label for="password">New password</label>
            <input type="password" id="password" name="password" minlength="8" autocomplete="new-password">
            <div class="help">Leave blank to keep the current password.</div>
            <?php echo field_error('password'); ?>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn">Save changes</button>
        <a href="/users" class="btn btn-secondary">Cancel</a>
    </div>
</form>
