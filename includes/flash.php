<?php foreach (take_flashes() as $flash_type => $flash_messages): ?>
    <div class="alert alert-<?= $flash_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show alert-flash" role="alert">
        <i class="fas fa-<?= $flash_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?php if (count($flash_messages) === 1): ?>
            <?= e($flash_messages[0]) ?>
        <?php else: ?>
            <ul class="mb-0">
                <?php foreach ($flash_messages as $flash_message): ?>
                    <li><?= e($flash_message) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>
