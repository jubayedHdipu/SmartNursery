<?php
// 1-Click Database Setup & Seeder Wizard

require_once dirname(__DIR__) . '/config/app.php';

$message = '';
$error = '';
$logs = [];

// Ensure image asset folders exist
$uploadDirs = [
    ROOT_PATH . '/assets/images',
    ROOT_PATH . '/assets/images/plants',
    ROOT_PATH . '/assets/images/nurseries',
    ROOT_PATH . '/assets/images/categories',
    ROOT_PATH . '/assets/images/branding',
    ROOT_PATH . '/assets/images/avatars',
    ROOT_PATH . '/assets/images/proofs'
];

foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_setup') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = $_POST['db_pass'] ?? '';
    $dbName = 'smart_nursery';

    try {
        // 1. Connect without selecting database
        $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $logs[] = "Connected to MySQL server successfully.";

        // 2. Read schema.sql
        $schemaFile = file_exists(ROOT_PATH . '/database/schema.sql') 
            ? (ROOT_PATH . '/database/schema.sql') 
            : (ROOT_PATH . '/schema.sql');
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found at " . $schemaFile);
        }

        $sql = file_get_contents($schemaFile);
        $logs[] = "Loaded schema.sql (" . strlen($sql) . " bytes).";

        // 3. Execute queries
        // Split by semicolons while respecting multi-line inserts
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $pdo->exec("USE `{$dbName}`;");
        $logs[] = "Database `{$dbName}` selected/created.";

        // Execute SQL script directly
        $pdo->exec($sql);
        $logs[] = "Executed schema tables and realistic seed data successfully!";

        $message = "Smart Nursery Database initialized successfully with all tables and demo accounts!";
    } catch (Exception $e) {
        $error = "Setup Failed: " . $e->getMessage();
    }
}

$pageTitle = 'Database Setup & Seeder Wizard';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="min-h-screen bg-slate-900 text-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-xl bg-slate-800/90 border border-slate-700/80 rounded-3xl p-8 shadow-2xl space-y-6 backdrop-blur-xl">
        
        <div class="text-center space-y-2">
            <div class="w-16 h-16 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-3xl mx-auto shadow-inner border border-emerald-500/30">
                <i class="fa-solid fa-seedling"></i>
            </div>
            <h1 class="text-2xl font-black text-white">Database Setup Wizard</h1>
            <p class="text-xs text-slate-400">Initialize schema and seed demo multi-role accounts in 1-Click.</p>
        </div>

        <?php if ($message): ?>
            <div class="p-4 bg-emerald-500/20 border border-emerald-500/40 rounded-2xl text-emerald-300 text-xs flex items-center space-x-3">
                <i class="fa-solid fa-circle-check text-lg text-emerald-400"></i>
                <div class="flex-1 font-medium"><?= sanitize($message) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="p-4 bg-rose-500/20 border border-rose-500/40 rounded-2xl text-rose-300 text-xs flex items-center space-x-3">
                <i class="fa-solid fa-triangle-exclamation text-lg text-rose-400"></i>
                <div class="flex-1 font-medium"><?= sanitize($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($logs)): ?>
            <div class="bg-slate-950/80 rounded-2xl p-4 border border-slate-700/50 space-y-1 font-mono text-[11px] text-emerald-400 max-h-40 overflow-y-auto">
                <?php foreach ($logs as $log): ?>
                    <div>&gt; <?= sanitize($log) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="run_setup">

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <label class="block text-slate-400 font-bold mb-1">DB Host</label>
                    <input type="text" name="db_host" value="localhost" required 
                           class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-slate-400 font-bold mb-1">DB Port</label>
                    <input type="text" name="db_port" value="3306" required 
                           class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <label class="block text-slate-400 font-bold mb-1">DB Username</label>
                    <input type="text" name="db_user" value="root" required 
                           class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-slate-400 font-bold mb-1">DB Password</label>
                    <input type="password" name="db_pass" value="" placeholder="(empty for default XAMPP)" 
                           class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-emerald-500">
                </div>
            </div>

            <button type="submit" 
                    class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-emerald-950/40 transition flex items-center justify-center space-x-2">
                <i class="fa-solid fa-play text-emerald-200"></i>
                <span>Execute 1-Click Database Setup</span>
            </button>
        </form>

        <div class="border-t border-slate-700/60 pt-4 text-center space-y-2">
            <a href="<?= BASE_URL ?>/index.php" class="text-xs text-emerald-400 hover:underline font-bold inline-flex items-center space-x-1">
                <span>&larr; Return to Smart Nursery Storefront</span>
            </a>
            
            <div class="text-[11px] text-slate-400">
                Demo Accounts: <strong>owner@nursery.com</strong>, <strong>greenhaven@nursery.com</strong>, <strong>alice@gmail.com</strong> (Password: <code>Password123!</code>)
            </div>
        </div>

    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
