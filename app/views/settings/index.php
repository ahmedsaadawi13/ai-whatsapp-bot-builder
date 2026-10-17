<?php // FILE: /app/views/settings/index.php ?>
<div class="page-header">
    <h1>Workspace settings</h1>
    <div class="actions"><a href="/settings/api-keys" class="btn btn-secondary">API keys</a></div>
</div>

<div class="grid grid-2">
    <form method="POST" action="/settings/update" class="card">
        <?php echo csrf_field(); ?>
        <h2>Business profile</h2>
        <div class="form-group">
            <label for="name">Business name</label>
            <input type="text" id="name" name="name" value="<?php echo e(old('name', $tenant['name'])); ?>" required maxlength="255">
            <?php echo field_error('name'); ?>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="email">Contact email</label>
                <input type="email" id="email" name="email" value="<?php echo e(old('email', $tenant['email'])); ?>" required maxlength="255">
                <?php echo field_error('email'); ?>
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" value="<?php echo e(old('phone', $tenant['phone'])); ?>" maxlength="50">
                <?php echo field_error('phone'); ?>
            </div>
        </div>
        <div class="form-group">
            <label for="address">Address</label>
            <input type="text" id="address" name="address" value="<?php echo e(old('address', $tenant['address'])); ?>" maxlength="255">
            <?php echo field_error('address'); ?>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="website">Website</label>
                <input type="url" id="website" name="website" value="<?php echo e(old('website', $tenant['website'])); ?>" maxlength="255" placeholder="https://">
                <?php echo field_error('website'); ?>
            </div>
            <div class="form-group">
                <label for="industry">Industry</label>
                <input type="text" id="industry" name="industry" value="<?php echo e(old('industry', $tenant['industry'])); ?>" maxlength="100">
                <?php echo field_error('industry'); ?>
            </div>
        </div>
        <div class="form-group">
            <label for="timezone">Timezone</label>
            <select id="timezone" name="timezone">
                <?php $tz = old('timezone', $tenant['timezone']); foreach ($timezones as $zone): ?>
                    <option value="<?php echo e($zone); ?>"<?php echo selected($tz, $zone); ?>><?php echo e($zone); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="help">Used when scheduling broadcasts. All data is stored in UTC.</div>
            <?php echo field_error('timezone'); ?>
        </div>
        <div class="form-actions"><button type="submit" class="btn">Save settings</button></div>
    </form>

    <div>
        <form method="POST" action="/settings/logo" enctype="multipart/form-data" class="card">
            <?php echo csrf_field(); ?>
            <h2>Logo</h2>
            <?php if ($tenant['logo']): ?>
                <p><img src="/files?path=<?php echo e(rawurlencode($tenant['logo'])); ?>" alt="Current logo" style="max-width:160px;max-height:160px;border-radius:8px;border:1px solid #e5e7eb;"></p>
            <?php else: ?>
                <p class="muted">No logo uploaded yet.</p>
            <?php endif; ?>
            <div class="form-group">
                <label for="logo">Upload a new logo</label>
                <input type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                <div class="help">JPG or PNG, max 2 MB. Counts towards your storage quota.</div>
                <?php echo field_error('logo'); ?>
            </div>
            <div class="form-actions"><button type="submit" class="btn">Upload logo</button></div>
        </form>

        <div class="card">
            <h2>Account</h2>
            <dl class="detail-list">
                <dt>Workspace ID</dt><dd><?php echo (int) $tenant['id']; ?></dd>
                <dt>Slug</dt><dd><?php echo e($tenant['slug']); ?></dd>
                <dt>Status</dt><dd><?php echo badge($tenant['status']); ?></dd>
                <dt>Created</dt><dd><?php echo e(format_date($tenant['created_at'])); ?></dd>
            </dl>
        </div>
    </div>
</div>
