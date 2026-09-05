<?php
// Botanical Categories CRUD

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role(['admin', 'owner']);
$currentUser = current_user();
$db = get_db();

// Handle Add / Edit Category
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf) && !empty($name)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

        if ($action === 'create') {
            $db->prepare("INSERT INTO categories (name, slug, description, is_active, created_at) VALUES (:name, :slug, :desc, 1, NOW())")->execute(['name' => $name, 'slug' => $slug, 'desc' => $desc]);
            log_audit('create_category', 'category', null, "Created category '{$name}'");
            set_flash('success', "Category '{$name}' created.");
        } elseif ($action === 'toggle') {
            $catId = (int)($_POST['category_id'] ?? 0);
            $db->prepare("UPDATE categories SET is_active = IF(is_active=1, 0, 1) WHERE id = :id")->execute(['id' => $catId]);
            set_flash('success', "Category status updated.");
        }
    }
    redirect('admin/categories.php');
}

// Fetch All Categories
$categories = $db->query("
    SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count
    FROM categories c
    ORDER BY c.id ASC
")->fetchAll();

$pageTitle = 'Manage Plant Categories';
$activePage = 'categories';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-8 bg-slate-50">
        
        <div class="border-b border-slate-200 pb-5">
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Taxonomy</span>
            <h1 class="text-2xl font-extrabold text-slate-900">Plant Categories Management</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Add Category Form -->
            <div class="lg:col-span-4 space-y-4">
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 border-b border-slate-100 pb-3">
                        Add New Category
                    </h3>

                    <form action="categories.php" method="POST" class="space-y-4 text-xs">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Category Name *</label>
                            <input type="text" name="name" required placeholder="e.g. Bonsai & Exotics"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Description</label>
                            <textarea name="description" rows="3" placeholder="Brief summary of this plant family..."
                                      class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-xs transition">
                            Create Category
                        </button>
                    </form>
                </div>
            </div>

            <!-- Categories List Table -->
            <div class="lg:col-span-8 space-y-4">
                <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span>Total Categories: <strong class="text-slate-800"><?= count($categories) ?></strong></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-slate-400 uppercase text-[10px] font-bold">
                                    <th class="p-4">Name</th>
                                    <th class="p-4">Slug</th>
                                    <th class="p-4 text-center">Listed Plants</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4 text-right">Toggle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-slate-700">
                                <?php foreach ($categories as $c): ?>
                                    <tr>
                                        <td class="p-4 font-bold text-slate-900">
                                            <span><?= sanitize(category_display_name($c)) ?></span>
                                            <?php if (is_bangla() && !empty($c['name_bn'])): ?>
                                                <span class="text-[11px] text-slate-400 font-normal block"><?= sanitize($c['name']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 font-mono text-slate-400"><?= sanitize($c['slug']) ?></td>
                                        <td class="p-4 text-center font-bold"><?= format_num_i18n($c['product_count']) ?></td>
                                        <td class="p-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold <?= $c['is_active'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                                                <?= $c['is_active'] ? __('common.active') : (is_bangla() ? 'নিষ্ক্রিয়' : 'Disabled') ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-right">
                                            <form action="categories.php" method="POST" class="inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="px-2.5 py-1.5 <?= $c['is_active'] ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?> font-semibold rounded-lg">
                                                    <?= $c['is_active'] ? (is_bangla() ? 'বন্ধ করুন' : 'Deactivate') : (is_bangla() ? 'চালু করুন' : 'Activate') ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
