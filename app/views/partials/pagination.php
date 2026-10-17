<?php
// FILE: /app/views/partials/pagination.php
// Expects $pagination from Controller::getPagination().
if (empty($pagination) || $pagination['total_pages'] <= 1) {
    if (!empty($pagination)) {
        echo '<p class="muted small">' . (int) $pagination['total'] . ' record(s)</p>';
    }
    return;
}
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($pagination['has_prev']): ?>
        <a class="btn btn-small btn-secondary" href="<?php echo e(query_url(['page' => $pagination['page'] - 1])); ?>">&laquo; Previous</a>
    <?php endif; ?>
    <span>Page <?php echo (int) $pagination['page']; ?> of <?php echo (int) $pagination['total_pages']; ?>
        &middot; <?php echo (int) $pagination['total']; ?> records</span>
    <?php if ($pagination['has_next']): ?>
        <a class="btn btn-small btn-secondary" href="<?php echo e(query_url(['page' => $pagination['page'] + 1])); ?>">Next &raquo;</a>
    <?php endif; ?>
</nav>
