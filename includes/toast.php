<?php
// Toast Notifications Component

require_once __DIR__ . '/../helpers/functions.php';

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
$flashInfo    = get_flash('info');
$flashWarning = get_flash('warning');
?>

<div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col items-end space-y-2 pointer-events-none">
    <?php if ($flashSuccess): ?>
        <div class="flash-toast pointer-events-auto bg-emerald-800/95 backdrop-blur-md text-white px-4 py-3 rounded-xl shadow-2xl border border-emerald-600/50 flex items-center space-x-3 text-sm animate-bounce">
            <i class="fa-solid fa-circle-check text-emerald-300 text-lg"></i>
            <span class="font-medium"><?= sanitize($flashSuccess) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="flash-toast pointer-events-auto bg-rose-800/95 backdrop-blur-md text-white px-4 py-3 rounded-xl shadow-2xl border border-rose-600/50 flex items-center space-x-3 text-sm">
            <i class="fa-solid fa-triangle-exclamation text-rose-300 text-lg"></i>
            <span class="font-medium"><?= sanitize($flashError) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($flashInfo): ?>
        <div class="flash-toast pointer-events-auto bg-sky-800/95 backdrop-blur-md text-white px-4 py-3 rounded-xl shadow-2xl border border-sky-600/50 flex items-center space-x-3 text-sm">
            <i class="fa-solid fa-circle-info text-sky-300 text-lg"></i>
            <span class="font-medium"><?= sanitize($flashInfo) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($flashWarning): ?>
        <div class="flash-toast pointer-events-auto bg-amber-800/95 backdrop-blur-md text-white px-4 py-3 rounded-xl shadow-2xl border border-amber-600/50 flex items-center space-x-3 text-sm">
            <i class="fa-solid fa-circle-exclamation text-amber-300 text-lg"></i>
            <span class="font-medium"><?= sanitize($flashWarning) ?></span>
        </div>
    <?php endif; ?>
</div>
