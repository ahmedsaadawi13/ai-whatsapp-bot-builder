<?php // FILE: /app/views/users/create.php ?>
<div class="page-header">
    <h1>Add team member</h1>
    <div class="actions"><a href="/users" class="btn btn-secondary">Back</a></div>
</div>

<form method="POST" action="/users/store" class="card">
    <?php echo csrf_field(); ?>
    <div class="form-row">
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?php echo e(old('name')); ?>" required maxlength="255">
            <?php echo field_error('name'); ?>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" required maxlength="255">
            <?php echo field_error('email'); ?>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="password">Temporary password</label>
            <input type="password" id="password" name="password" required minlength="8">
            <?php echo field_error('password'); ?>
        </div>
        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?php echo e($r); ?>"<?php echo selected(old('role', 'agent'), $r); ?>><?php echo e(ucfirst(str_replace('_', ' ', $r))); ?></option>
                <?php endforeach; ?>
            </select>
            <?php echo field_error('role'); ?>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn">Add member</button>
        <a href="/users" class="btn btn-secondary">Cancel</a>
    </div>
</form>
