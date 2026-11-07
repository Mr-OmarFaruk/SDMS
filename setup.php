<?php
require __DIR__ . '/includes/functions.php';

// Setup runs once; the lock file prevents accidental reinstallation.
$lockFile = __DIR__ . '/storage/setup.lock';
$configFile = __DIR__ . '/config.local.php';
$error = '';
$installed = is_file($lockFile);
$settings = [
    'db_host' => config('db_host'),
    'db_port' => config('db_port'),
    'db_name' => config('db_name'),
    'db_user' => config('db_user'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    check_csrf();

    // Keep submitted settings so the user can correct them if validation fails.
    $settings = [
        'db_host' => trim($_POST['db_host'] ?? ''),
        'db_port' => trim($_POST['db_port'] ?? ''),
        'db_name' => trim($_POST['db_name'] ?? ''),
        'db_user' => trim($_POST['db_user'] ?? ''),
    ];
    $password = $_POST['db_pass'] ?? '';
    if ($password === '' && !empty($_POST['clear_password'])) $password = '';
    elseif ($password === '') $password = config('db_pass', '');

    if (!preg_match('/^[A-Za-z0-9.:-]+$/', $settings['db_host']) || $settings['db_user'] === '' || !preg_match('/^[A-Za-z0-9_]+$/', $settings['db_name'])) {
        $error = 'Enter a valid MySQL host and username, and use only letters, numbers, and underscores in the database name.';
    } elseif (!ctype_digit($settings['db_port']) || (int) $settings['db_port'] < 1 || (int) $settings['db_port'] > 65535) {
        $error = 'Port must be a number between 1 and 65535.';
    } else {
        try {
            // Connect to the existing database and create the application's tables.
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
            $dsn = 'mysql:host=' . $settings['db_host'] . ';port=' . $settings['db_port'] . ';dbname=' . $settings['db_name'] . ';charset=utf8mb4';
            $connection = new PDO($dsn, $settings['db_user'], $password, $options);
            $schema = file_get_contents(__DIR__ . '/database/schema.sql');
            foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) $connection->exec($statement);

            // Save credentials outside the web page so the application can reconnect.
            $savedConfig = $settings;
            $savedConfig['db_pass'] = $password;
            if (file_put_contents($configFile, "<?php\nreturn " . var_export($savedConfig, true) . ";\n", LOCK_EX) === false) {
                throw new RuntimeException('The database is ready, but PHP could not save config.local.php. Check that the project folder is writable.');
            }
            if (file_put_contents($lockFile, 'Setup completed.', LOCK_EX) === false) {
                throw new RuntimeException('The database is ready, but PHP could not create storage/setup.lock. Check that the storage folder is writable.');
            }
            $installed = true;
        } catch (Throwable $exception) {
            $error = $exception instanceof PDOException
                ? 'Could not connect or install the database. Check the host, port, username, password, and database permissions.'
                : $exception->getMessage();
            $error .= ' Details: ' . $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database setup | IUS SDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/sdms.css" rel="stylesheet">
    <style>
        body.setup-page{min-height:100vh;background:linear-gradient(135deg,#edf4f2 0%,#f7f9fc 58%,#f4eee5 100%);color:#162333}
        .setup-panel{max-width:680px;background:#fff;border:1px solid #e6ebf0;border-radius:8px;padding:clamp(24px,5vw,48px);box-shadow:0 12px 36px rgba(28,45,72,.08)}
        .setup-panel .form-control{min-height:46px}
    </style>
</head>
<body class="setup-page">
<main class="container py-5">
    <div class="setup-panel mx-auto">
        <div class="eyebrow">IUS SDMS INSTALLER</div>
        <h1 class="page-title mb-2">Database connection</h1>
        <p class="text-muted mb-4">Enter your MySQL details to test the connection and install the tables. On InfinityFree or other shared hosts, create the database in the hosting control panel first, then use its exact database name, username, and MySQL hostname. Do not assume the hostname is <code>localhost</code>.</p>
        <?php if ($installed): ?>
            <div class="alert alert-success">Setup is complete and the database connection is saved.</div>
            <a class="btn btn-primary" href="index.php">Open IUS SDMS</a>
            <p class="small text-muted mt-4 mb-0">To run setup again, remove <code>storage/setup.lock</code> and reopen this page.</p>
        <?php else: ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-8"><label for="db_host">MySQL hostname from your hosting panel</label><input class="form-control" id="db_host" name="db_host" value="<?= e($settings['db_host']) ?>" placeholder="Use the hostname provided by your host" required></div>
                    <div class="col-md-4"><label for="db_port">Port</label><input class="form-control" id="db_port" name="db_port" value="<?= e($settings['db_port']) ?>" inputmode="numeric" placeholder="3306" required></div>
                    <div class="col-md-6"><label for="db_name">Existing database name</label><input class="form-control" id="db_name" name="db_name" value="<?= e($settings['db_name']) ?>" placeholder="Exact name from your hosting panel" required></div>
                    <div class="col-md-6"><label for="db_user">Database username</label><input class="form-control" id="db_user" name="db_user" value="<?= e($settings['db_user']) ?>" autocomplete="username" required></div>
                    <div class="col-12"><label for="db_pass">Password</label><input class="form-control" id="db_pass" name="db_pass" type="password" autocomplete="new-password" placeholder="Leave blank to keep the current password"><div class="form-text">For a new installation with a blank MySQL password, leave this empty.</div><div class="form-check mt-2"><input class="form-check-input" id="clear_password" name="clear_password" type="checkbox" value="1"><label class="form-check-label" for="clear_password">Use a blank password instead</label></div></div>
                </div>
                <button class="btn btn-primary mt-4" type="submit">Test connection and set up</button>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>