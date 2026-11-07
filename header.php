<?php $current = user(); $title = $title ?? 'SDMS'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | IUS SDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="public/css/sdms.css" rel="stylesheet">
    <style>
        .ius-page::before{background-size:min(82vw,780px) auto;opacity:.075}
        .login-brand img{width:180px;height:90px}
        @media(max-width:576px){.ius-page::before{background-size:88vw auto;background-position:center 52%}.login-brand img{width:140px;height:70px}}
    </style>
</head>
<body class="ius-page">
<nav class="navbar navbar-expand-lg navbar-dark bg-navy shadow-sm"><div class="container"><a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= url($current ? 'dashboard' : 'login') ?>"><img class="university-logo" style="width:34px;height:34px" src="public/images/ius-logo.png" alt="IUS university logo" title="IUS SDMS"> <strong>IUS</strong> <small>SDMS</small></a>
<?php if ($current): ?><button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button><div class="collapse navbar-collapse" id="nav"><ul class="navbar-nav me-auto"><li class="nav-item"><a class="nav-link" href="<?= url('dashboard') ?>">Dashboard</a></li><li class="nav-item"><a class="nav-link" href="<?= url('applications') ?>">Applications</a></li><?php if ($current['role'] === 'student'): ?><li class="nav-item"><a class="nav-link" href="<?= url('application-create') ?>">New application</a></li><?php endif; ?><?php if ($current['role'] === 'admin'): ?><li class="nav-item"><a class="nav-link" href="<?= url('reports') ?>">Reports</a></li><li class="nav-item"><a class="nav-link" href="<?= url('users') ?>">Users</a></li><?php endif; ?></ul><span class="text-white-50 me-3 small"><?= e($current['name']) ?> <span class="role-chip"><?= e(ucfirst($current['role'])) ?></span></span><form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?><button class="btn btn-outline-light btn-sm">Sign out</button></form></div><?php endif; ?></div></nav>
<main class="container py-4">
<?php if ($message = flash('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= e($message) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php foreach (form_errors() as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>