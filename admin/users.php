<?php
// Customer & Delivery Staff Moderation

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role(['admin', 'owner']);
redirect('admin/customers.php');

// Fetch Customers and Delivery Staff
$users = $db->query("
    SELECT u.*, r.display_name AS role_display, r.name AS role_name,
           (SELECT COUNT(*) FROM orders WHERE customer_id = u.id) AS order_count
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE r.name IN ('customer', 'delivery')
    ORDER BY u.id DESC
")->fetchAll();

$pageTitle = 'Manage Customers & Riders';
$activePage = 'users';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-6 bg-slate-50">
        
        <div class="border-b border-slate-200 pb-5">
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">User Moderation</span>
            <h1 class="text-2xl font-extrabold text-slate-900">Customers & Delivery Riders</h1>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4">User</th>
                            <th class="p-4">Role</th>
                            <th class="p-4">Contact Phone</th>
                            <th class="p-4 text-center">Orders Placed</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Moderation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-700">
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="p-4 font-bold text-slate-900"><?= sanitize($u['name']) ?><br><span class="text-slate-400 font-normal text-[11px]"><?= sanitize($u['email']) ?></span></td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $u['role_name'] === 'delivery' ? 'bg-sky-100 text-sky-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                        <?= sanitize($u['role_display']) ?>
                                    </span>
                                </td>
                                <td class="p-4 text-slate-600"><?= sanitize($u['phone'] ?: 'N/A') ?></td>
                                <td class="p-4 text-center font-bold"><?= $u['order_count'] ?></td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold <?= $u['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                        <?= sanitize($u['status']) ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <form action="users.php" method="POST" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'suspended' : 'active' ?>">
                                        <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded-lg <?= $u['status'] === 'active' ? 'bg-rose-50 hover:bg-rose-100 text-rose-700' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700' ?>">
                                            <?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
