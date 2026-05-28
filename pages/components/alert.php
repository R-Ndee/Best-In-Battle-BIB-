<?php
// pages/components/alert.php
// Include di setiap halaman setelah <body> atau di dalam form area
if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <span>✓</span>
        <span><?= htmlspecialchars($_SESSION['success']) ?></span>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <span>✕</span>
        <span><?= htmlspecialchars($_SESSION['error']) ?></span>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['info'])): ?>
    <div class="alert alert-info">
        <span>ℹ</span>
        <span><?= htmlspecialchars($_SESSION['info']) ?></span>
    </div>
    <?php unset($_SESSION['info']); ?>
<?php endif; ?>
