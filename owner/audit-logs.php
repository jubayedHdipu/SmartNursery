<?php
// System-Wide Audit Trail Inspector

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role('owner');
$currentUser = current_user();
$db = get_db();

// Filter Action or Entity
$actionFilter = trim($_GET['action_type'] ?? '');
$where = '1=1';
$params = [];

if (!empty($actionFilter)) {
    $where = "al.action LIKE :act";
    $params['act'] = '%' . $actionFilter . '%';
}

$stmt = $db->prepare("
    SELECT al.*, u.name AS user_name, u.email AS user_email, r.display_name AS role_display
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE {$where}
    ORDER BY al.id DESC
    LIMIT 50
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$pageTitle = 'System Audit Trail';
$activePage = 'audit';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-6 bg-slate-50">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Security & Integrity</span>
                <h1 class="text-2xl font-extrabold text-slate-900">System Audit Trail</h1>
            </div>

            <!-- Search Action Filter -->
            <form action="audit-logs.php" method="GET" class="flex items-center space-x-2 text-xs">
                <input type="text" name="action_type" value="<?= sanitize($actionFilter) ?>" placeholder="Filter by action (e.g. login, stock)..."
                       class="bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white font-bold rounded-xl">
                    Filter
                </button>
            </form>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4">Timestamp</th>
                            <th class="p-4">Actor / User</th>
                            <th class="p-4">Action Event</th>
                            <th class="p-4">Entity Type</th>
                            <th class="p-4">IP Address</th>
                            <th class="p-4">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-700">
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td class="p-4 font-mono text-slate-400 text-[11px]"><?= format_date($l['created_at']) ?></td>
                                    <td class="p-4">
                                        <?php if ($l['user_name']): ?>
                                            <strong class="text-slate-900"><?= sanitize($l['user_name']) ?></strong>
                                            <span class="text-[10px] text-slate-400 block"><?= sanitize($l['role_display'] ?? '') ?></span>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic">Guest / System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-800">
                                            <?= sanitize($l['action']) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-slate-500 uppercase font-semibold text-[10px]"><?= sanitize($l['entity_type']) ?> (ID: <?= sanitize($l['entity_id'] ?: '-') ?>)</td>
                                    <td class="p-4 font-mono text-slate-400 text-[11px]"><?= sanitize($l['ip_address']) ?></td>
                                    <td class="p-4 text-slate-600 max-w-xs truncate"><?= sanitize($l['details'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-400">No audit logs match your filter.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
