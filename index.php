<?php
require __DIR__ . '/includes/functions.php';

// Choose a page from the URL, or send visitors to the right starting page.
$page = $_GET['page'] ?? (user() ? 'dashboard' : 'login');

// Process submitted forms before displaying a page.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = trim($_POST['email'] ?? '');
    if ($page === 'login') {
        $statement = db()->prepare('SELECT * FROM users WHERE email = ?');
        $statement->execute([$email]);
        $account = $statement->fetch();
        if (!$account || !password_verify($_POST['password'] ?? '', $account['password'])) {
            remember_form(['email' => $email]);
            fail_form(['The supplied credentials are incorrect.'], 'login');
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $account['id'];
        $_SESSION['current'] = $account;
        clear_form();
        redirect('dashboard');
    }
    if ($page === 'register') {
        $data = ['name' => trim($_POST['name'] ?? ''), 'email' => $email, 'role' => $_POST['role'] ?? '', 'department' => trim($_POST['department'] ?? ''), 'student_id' => trim($_POST['student_id'] ?? '')];
        remember_form($data);
        $errors = [];
        if ($data['name'] === '' || strlen($data['name']) > 100) $errors[] = 'Name is required and must be 100 characters or fewer.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (!in_array($data['role'], ['student', 'teacher'], true)) $errors[] = 'Choose a valid account type.';
        if (strlen($_POST['password'] ?? '') < 8 || ($_POST['password'] ?? '') !== ($_POST['password_confirmation'] ?? '')) $errors[] = 'Passwords must match and contain at least 8 characters.';
        $statement = db()->prepare('SELECT id FROM users WHERE email = ?'); $statement->execute([$email]);
        if ($statement->fetch()) $errors[] = 'That email address is already registered.';
        if ($errors) fail_form($errors, 'register');
        $statement = db()->prepare('INSERT INTO users (name,email,password,role,department,student_id,created_at,updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())');
        $statement->execute([$data['name'], $email, password_hash($_POST['password'], PASSWORD_DEFAULT), $data['role'], $data['department'] ?: null, $data['student_id'] ?: null]);
        $_SESSION['user_id'] = (int) db()->lastInsertId(); unset($_SESSION['current']); clear_form(); flash('success', 'Welcome to SDMS, your account is ready.'); redirect('dashboard');
    }
    if ($page === 'logout') { session_destroy(); redirect('login'); }
    $current = require_login();
    if ($page === 'application-store' || $page === 'application-update') {
        require_role('student');
        $id = (int) ($_POST['id'] ?? 0); $existing = $id ? application($id) : null;
        if ($existing && ((int) $existing['student_id'] !== (int) $current['id'] || $existing['status'] !== 'pending')) exit('This application cannot be edited.');
        $title = trim($_POST['title'] ?? ''); $description = trim($_POST['description'] ?? ''); $teacher = (int) ($_POST['teacher_id'] ?? 0); $errors = [];
        if ($title === '' || strlen($title) > 150) $errors[] = 'Title is required and must be 150 characters or fewer.';
        if ($description === '') $errors[] = 'Description is required.';
        $statement = db()->prepare("SELECT id FROM users WHERE id = ? AND role IN ('teacher','admin')"); $statement->execute([$teacher]);
        if (!$statement->fetch()) $errors[] = 'Choose a valid teacher or administrator.';
        $document = upload_document('document');
        if (!empty($_FILES['document']['name']) && !$document) $errors[] = 'Documents must be PDF, DOC, DOCX, JPG, JPEG, or PNG files up to 5 MB.';
        if ($errors) { remember_form($_POST); fail_form($errors, $page === 'application-store' ? 'application-create' : 'application-edit', $page === 'application-update' ? ['id' => $id] : []); }
        if ($page === 'application-store') { $statement = db()->prepare('INSERT INTO applications (student_id,teacher_id,title,description,document_path,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())'); $statement->execute([$current['id'], $teacher, $title, $description, $document]); $target = (int) db()->lastInsertId(); flash('success', 'Application submitted successfully.'); }
        else { $statement = db()->prepare('UPDATE applications SET teacher_id=?,title=?,description=?,document_path=COALESCE(?,document_path),updated_at=NOW() WHERE id=?'); $statement->execute([$teacher, $title, $description, $document, $id]); $target = $id; flash('success', 'Application updated.'); }
        clear_form(); redirect('application', ['id' => $target]);
    }
    if ($page === 'review') {
        $current = require_role('teacher', 'admin'); $id = (int) ($_POST['id'] ?? 0); $item = application($id);
        if (!$item || ($current['role'] === 'teacher' && (int) $item['teacher_id'] !== (int) $current['id'])) exit('You cannot review this application.');
        if (!in_array($_POST['status'] ?? '', ['accepted', 'rejected'], true)) exit('Invalid review status.');
        $statement = db()->prepare('UPDATE applications SET status=?,review_message=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?'); $statement->execute([$_POST['status'], trim($_POST['review_message'] ?? '') ?: null, $id]);
        flash('success', 'Application ' . $_POST['status'] . ' and student notified.'); redirect('application', ['id' => $id]);
    }
}

// Shared page helpers keep layout and database queries in one place.
function view_start(string $title): void
{
    $GLOBALS['title'] = $title;
    require __DIR__ . '/includes/header.php';
}

function view_end(): void
{
    require __DIR__ . '/includes/footer.php';
}

function stat_cards(array $counts): void
{
    foreach ($counts as $label => $value) {
        echo '<div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div>
                <div>
                    <div class="stat-value">' . e($value) . '</div>
                    <div class="stat-label">' . e(ucfirst($label)) . '</div>
                </div>
            </div>
        </div>';
    }
}

function teachers(): array
{
    return db()->query("SELECT id, name, role FROM users WHERE role IN ('teacher', 'admin') ORDER BY name")->fetchAll();
}

function application_rows(array $current): array
{
    if ($current['role'] === 'student') {
        $where = 'a.student_id = ' . (int) $current['id'];
    } elseif ($current['role'] === 'teacher') {
        $where = 'a.teacher_id = ' . (int) $current['id'];
    } else {
        $where = '1 = 1';
    }

    $query = "SELECT a.*, s.name AS student_name, t.name AS teacher_name
        FROM applications a
        JOIN users s ON s.id = a.student_id
        LEFT JOIN users t ON t.id = a.teacher_id
        WHERE $where
        ORDER BY a.created_at DESC";

    return db()->query($query)->fetchAll();
}

// Login and registration are the only pages available without signing in.
if ($page === 'login' || $page === 'register') {
    if (user()) redirect('dashboard'); view_start(ucfirst($page)); ?>
    <div class="row justify-content-center"><div class="col-md-6 col-lg-5"><div class="login-brand text-center"><img src="public/images/ius-logo.png" alt="IUS university logo"><div class="login-brand-name">IUS <span>SDMS</span></div><div class="login-brand-subtitle">Student Document Management System</div></div><div class="form-card p-4 p-md-5"><div class="eyebrow">SDMS ACCESS</div><h1 class="page-title mb-4"><?= $page === 'login' ? 'Welcome back' : 'Create an account' ?></h1>
    <form method="post" action="<?= url($page) ?>"><?= csrf_field() ?><?php if ($page === 'register'): ?><label>Name</label><input class="form-control mb-3" name="name" value="<?= old('name') ?>" required><label>Email</label><?php endif; ?><input class="form-control mb-3" type="email" name="email" value="<?= old('email') ?>" placeholder="Email address" required><?php if ($page === 'register'): ?><label>Account type</label><select class="form-select mb-3" name="role"><option value="student">Student</option><option value="teacher">Teacher</option></select><label>Department</label><input class="form-control mb-3" name="department" value="<?= old('department') ?>"><label>Student ID (optional)</label><input class="form-control mb-3" name="student_id" value="<?= old('student_id') ?>"><?php endif; ?><label>Password</label><input class="form-control mb-3" type="password" name="password" required><?php if ($page === 'register'): ?><label>Confirm password</label><input class="form-control mb-3" type="password" name="password_confirmation" required><?php endif; ?><button class="btn btn-primary w-100"><?= $page === 'login' ? 'Sign in' : 'Register' ?></button></form><p class="text-muted small mt-4 mb-0"><?= $page === 'login' ? 'Need an account? ' . '<a href="' . url('register') . '">Register here</a>' : 'Already registered? ' . '<a href="' . url('login') . '">Sign in</a>' ?></p></div></div></div>
    <?php if ($page === 'login'): ?>
    <div class="row justify-content-center mt-3"><div class="col-md-6 col-lg-5 text-center">
        <div class="small fw-semibold mb-2">Demo account emails</div>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-demo-email="admin@sdms.test">Admin: admin@sdms.test</button>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-demo-email="teacher@sdms.test">Teacher: teacher@sdms.test</button>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-demo-email="student@sdms.test">Student: student@sdms.test</button>
        </div>
        <div class="form-text mt-2">Password for all demo accounts: <code>password</code></div>
    </div></div>
    <?php endif; ?>
    <?php view_end(); exit;
}

// Protect all other pages and show content based on the user's role.
$current = require_login();
if ($page === 'dashboard') {
    if ($current['role'] === 'admin') redirect('admin-dashboard'); if ($current['role'] === 'teacher') redirect('teacher-dashboard');
    $rows = application_rows($current); $counts = ['total' => count($rows), 'pending' => count(array_filter($rows, fn($r) => $r['status'] === 'pending')), 'accepted' => count(array_filter($rows, fn($r) => $r['status'] === 'accepted')), 'rejected' => count(array_filter($rows, fn($r) => $r['status'] === 'rejected'))]; view_start('Student dashboard'); ?><div class="eyebrow">STUDENT SPACE</div><h1 class="page-title">Your document journey</h1><div class="row g-3 mt-3"><?php stat_cards($counts); ?></div><div class="section-heading"><h2>Recent applications</h2><a href="<?= url('applications') ?>">View all</a></div><?php $rows = array_slice($rows, 0, 5); include __DIR__ . '/includes/application-table.php'; view_end(); exit;
}
if ($page === 'teacher-dashboard' || $page === 'admin-dashboard') {
    require_role($page === 'teacher-dashboard' ? 'teacher' : 'admin'); $rows = application_rows($current); $counts = ['applications' => count($rows), 'pending' => count(array_filter($rows, fn($r) => $r['status'] === 'pending')), 'accepted' => count(array_filter($rows, fn($r) => $r['status'] === 'accepted')), 'rejected' => count(array_filter($rows, fn($r) => $r['status'] === 'rejected'))]; view_start(ucfirst(str_replace('-', ' ', $page))); ?><div class="eyebrow"><?= strtoupper($current['role']) ?> SPACE</div><h1 class="page-title">Overview</h1><div class="row g-3 mt-3"><?php stat_cards($counts); ?></div><div class="section-heading"><h2>Recent applications</h2><a href="<?= url('applications') ?>">View all</a></div><?php $rows = array_slice($rows, 0, 8); include __DIR__ . '/includes/application-table.php'; view_end(); exit;
}
if ($page === 'applications') { $rows = application_rows($current); view_start('Applications'); ?><div class="d-flex justify-content-between align-items-center mb-4"><div><div class="eyebrow">DOCUMENT WORKFLOW</div><h1 class="page-title">Applications</h1></div><?php if ($current['role'] === 'student'): ?><a class="btn btn-primary" href="<?= url('application-create') ?>"><i class="bi bi-plus-lg"></i> New application</a><?php endif; ?></div><?php include __DIR__ . '/includes/application-table.php'; view_end(); exit; }
if ($page === 'application-create' || $page === 'application-edit') { require_role('student'); $item = $page === 'application-edit' ? application((int) ($_GET['id'] ?? 0)) : null; if ($item && ($item['student_id'] != $current['id'] || $item['status'] !== 'pending')) exit('This application cannot be edited.'); $people = teachers(); view_start($item ? 'Edit application' : 'New application'); ?><div class="row justify-content-center"><div class="col-lg-8"><div class="form-card p-4"><div class="eyebrow">STUDENT SUBMISSION</div><h1 class="page-title mb-4"><?= $item ? 'Edit application' : 'New application' ?></h1><form method="post" enctype="multipart/form-data" action="<?= url($item ? 'application-update' : 'application-store') ?>"><?= csrf_field() ?><?php if ($item): ?><input type="hidden" name="id" value="<?= $item['id'] ?>"><?php endif; ?><label>Title</label><input class="form-control mb-3" name="title" value="<?= old('title', $item['title'] ?? '') ?>" required><label>Assign to</label><select class="form-select mb-3" name="teacher_id" required><?php foreach ($people as $person): ?><option value="<?= $person['id'] ?>" <?= (old('teacher_id', (string) ($item['teacher_id'] ?? '')) == $person['id']) ? 'selected' : '' ?>><?= e($person['name']) ?> (<?= e(ucfirst($person['role'])) ?>)</option><?php endforeach; ?></select><label>Description</label><textarea class="form-control mb-3" name="description" rows="6" required><?= old('description', $item['description'] ?? '') ?></textarea><label>Document</label><input class="form-control mb-4" type="file" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"><button class="btn btn-primary">Save application</button></form></div></div></div><?php view_end(); exit; }
if ($page === 'application') { $item = application((int) ($_GET['id'] ?? 0)); if (!$item || !can_access_application($item, $current)) exit('Application not found.'); view_start('Application details'); ?><div class="d-flex justify-content-between align-items-center mb-4"><div><div class="eyebrow">APPLICATION #<?= $item['id'] ?></div><h1 class="page-title"><?= e($item['title']) ?></h1></div><span class="badge text-bg-<?= status_class($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></div><div class="detail-card p-4"><p class="text-muted">Submitted by <?= e($item['student_name']) ?> to <?= e($item['teacher_name'] ?? 'Unassigned') ?> on <?= format_date($item['created_at']) ?></p><p><?= nl2br(e($item['description'])) ?></p><?php if ($item['document_path']): ?><a target="_blank" href="storage/<?= e($item['document_path']) ?>" class="btn btn-outline-primary"><i class="bi bi-paperclip"></i> Open document</a><?php endif; ?><?php if ($current['role'] === 'student' && $item['status'] === 'pending'): ?><a class="btn btn-primary ms-2" href="<?= url('application-edit', ['id' => $item['id']]) ?>">Edit</a><?php endif; ?></div><?php if ($item['review_message']): ?><div class="callout mt-3"><strong>Reviewer message</strong><p class="mb-0 mt-2"><?= nl2br(e($item['review_message'])) ?></p></div><?php endif; ?><?php if (in_array($current['role'], ['teacher','admin'], true) && $item['status'] === 'pending' && ($current['role'] === 'admin' || $item['teacher_id'] == $current['id'])): ?><div class="form-card p-4 mt-3"><h2 class="h5">Review application</h2><form method="post" action="<?= url('review') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $item['id'] ?>"><textarea class="form-control my-3" name="review_message" rows="3" placeholder="Optional message to the student"></textarea><button class="btn btn-success" name="status" value="accepted">Accept</button> <button class="btn btn-danger" name="status" value="rejected">Reject</button></form></div><?php endif; ?><?php view_end(); exit; }
if ($page === 'users') { require_role('admin'); $rows = db()->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll(); view_start('Users'); ?><div class="eyebrow">ADMINISTRATION</div><h1 class="page-title mb-4">Users</h1><div class="table-card"><table class="table mb-0"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Joined</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= e($row['email']) ?></td><td><span class="badge text-bg-secondary"><?= e(ucfirst($row['role'])) ?></span></td><td><?= e($row['department'] ?: '-') ?></td><td><?= format_date($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php view_end(); exit; }
if ($page === 'reports') { require_role('admin'); $from = $_GET['from'] ?? date('Y-m-01'); $to = $_GET['to'] ?? date('Y-m-d'); $statement = db()->prepare('SELECT a.*,s.name student_name,t.name teacher_name FROM applications a JOIN users s ON s.id=a.student_id LEFT JOIN users t ON t.id=a.teacher_id WHERE a.created_at BETWEEN ? AND ? ORDER BY a.created_at DESC'); $statement->execute([$from . ' 00:00:00', $to . ' 23:59:59']); $rows = $statement->fetchAll(); view_start('Reports'); ?><div class="d-flex justify-content-between align-items-center mb-4"><div><div class="eyebrow">ADMINISTRATION</div><h1 class="page-title">Application report</h1></div><button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button></div><form class="row g-2 mb-4"><input type="hidden" name="page" value="reports"><div class="col-sm-4"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div><div class="col-sm-4"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div><div class="col-sm-4 align-self-end"><button class="btn btn-primary">Filter</button></div></form><?php include __DIR__ . '/includes/application-table.php'; view_end(); exit; }

http_response_code(404); echo 'Page not found.';