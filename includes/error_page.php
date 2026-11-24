<?php include __DIR__ . '/header.php'; ?>
<?php include __DIR__ . '/navbar.php'; ?>
<main class="py-5">
    <div class="container text-center py-5">
        <h1 class="h3 mb-3"><?= e($error_title ?? 'Something went wrong') ?></h1>
        <p class="text-muted mb-4"><?= e($error_message ?? '') ?></p>
        <a href="<?= e(url('index.php')) ?>" class="btn btn-primary">Back to home</a>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
