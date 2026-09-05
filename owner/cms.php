<?php
// Owner Dynamic Homepage CMS Editor

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/functions.php';

require_role('owner');
$currentUser = current_user();
$db = get_db();

// Handle CMS Copy Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($csrf)) {
        $contents = $_POST['contents'] ?? [];

        try {
            $stmt = $db->prepare("UPDATE dynamic_contents SET content_value = :val, updated_at = NOW() WHERE content_key = :k");
            foreach ($contents as $key => $val) {
                $stmt->execute(['val' => trim($val), 'k' => $key]);
            }

            log_audit('update_cms', 'dynamic_contents', null, "Owner updated dynamic homepage copy");
            set_flash('success', 'Homepage dynamic content updated successfully.');
            redirect('owner/cms.php');
        } catch (Exception $e) {
            set_flash('error', 'CMS update error: ' . $e->getMessage());
        }
    }
}

// Fetch All Dynamic Contents
$dynamicContents = $db->query("SELECT * FROM dynamic_contents ORDER BY page_section ASC, id ASC")->fetchAll();

$pageTitle = 'Dynamic Homepage CMS';
$activePage = 'cms';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="flex-1 flex flex-col lg:flex-row">
    
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 sm:p-10 space-y-8 bg-slate-50">
        
        <div class="border-b border-slate-200 pb-5">
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Content Management</span>
            <h1 class="text-2xl font-extrabold text-slate-900">Dynamic Homepage Copy & FAQs Editor</h1>
            <p class="text-xs text-slate-500 mt-0.5">Edit live storefront marketing titles, banners, and knowledge base FAQs without modifying code</p>
        </div>

        <form action="cms.php" method="POST" class="space-y-6 max-w-4xl text-xs">
            <?= csrf_field() ?>

            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                
                <?php foreach ($dynamicContents as $dc): ?>
                    <div class="space-y-1.5 border-b border-slate-100 pb-4 last:border-b-0 last:pb-0">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-slate-800"><?= sanitize($dc['title']) ?></label>
                            <span class="text-[10px] font-mono text-slate-400 bg-slate-100 px-2 py-0.5 rounded">
                                Section: <?= sanitize($dc['page_section']) ?> (<?= sanitize($dc['content_key']) ?>)
                            </span>
                        </div>

                        <?php if (strlen($dc['content_value']) > 80 || strpos($dc['content_key'], 'faq') !== false): ?>
                            <textarea name="contents[<?= $dc['content_key'] ?>]" rows="3" 
                                      class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"><?= sanitize($dc['content_value']) ?></textarea>
                        <?php else: ?>
                            <input type="text" name="contents[<?= $dc['content_key'] ?>]" value="<?= sanitize($dc['content_value']) ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

            </div>

            <button type="submit" class="px-8 py-4 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold rounded-2xl shadow-lg shadow-emerald-900/20 transition transform hover:-translate-y-0.5">
                Save & Publish Homepage Copy
            </button>
        </form>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
