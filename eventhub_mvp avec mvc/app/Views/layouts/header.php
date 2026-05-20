<?php
$title = $title ?? 'EventHub Pro';
$active = $active ?? 'events';
$bodyPage = $bodyPage ?? '';
$publicBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$legacyBase = preg_replace('#/public$#', '', $publicBase) ?: '';
$routeUrl = static function (string $route) use ($publicBase): string {
    return $publicBase . '/index.php?route=' . rawurlencode($route);
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <style>
        :root {
            --navy: #0f1f3d;
            --blue: #2563eb;
            --amber: #f59e0b;
            --green: #159a8a;
            --red: #dc2626;
            --slate: #64748b;
            --line: #dbe3ef;
            --bg: #eef3f9;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: var(--bg); color: #172033; }
        a { color: inherit; text-decoration: none; }
        .topbar { background: rgba(15, 31, 61, .94); color: #fff; box-shadow: 0 10px 24px rgba(15, 31, 61, .2); }
        .wrap { width: min(1120px, calc(100% - 32px)); margin: 0 auto; }
        .topbar .wrap { display: flex; align-items: center; justify-content: space-between; gap: 18px; min-height: 68px; }
        .brand { font-weight: 800; font-size: 20px; letter-spacing: .02em; }
        .brand span { color: var(--amber); }
        .nav { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .nav a { padding: 9px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; color: rgba(255,255,255,.74); }
        .nav a.active { color: var(--amber); border: 1px solid rgba(245,158,11,.45); background: rgba(245,158,11,.1); }
        .page { padding: 34px 0 54px; }
        .hero { display: flex; justify-content: space-between; gap: 20px; align-items: flex-end; margin-bottom: 24px; }
        h1 { margin: 0; font-size: clamp(28px, 4vw, 46px); color: #0f172a; }
        .muted { color: var(--slate); }
        .toolbar { display: grid; grid-template-columns: 1fr 190px 160px; gap: 12px; margin: 22px 0; }
        input, select, textarea { width: 100%; border: 1px solid #cfd8e6; border-radius: 8px; padding: 12px 13px; font: inherit; background: #fff; color: #172033; }
        textarea { min-height: 120px; resize: vertical; }
        .tabs { display: inline-flex; gap: 6px; background: #fff; border: 1px solid var(--line); border-radius: 999px; padding: 5px; margin-bottom: 24px; }
        .tab-btn { border: 0; background: transparent; color: #65748a; padding: 10px 18px; border-radius: 999px; font-weight: 800; cursor: pointer; }
        .tab-btn.active { background: var(--navy); color: #fff; }
        .events-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
        .event-card, .panel, .stat-card { background: #fff; border: 1px solid var(--line); border-radius: 8px; box-shadow: 0 10px 24px rgba(15,23,42,.06); }
        .badge { display: inline-block; border-radius: 999px; padding: 5px 10px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; }
        .cap-bar { height: 7px; background: #e8edf4; border-radius: 999px; overflow: hidden; }
        .cap-bar-fill { height: 100%; border-radius: inherit; transition: width .25s ease; }
        .btn { border: 0; border-radius: 8px; background: var(--blue); color: #fff; padding: 12px 15px; font-weight: 800; cursor: pointer; }
        .btn.secondary { background: #fff; color: var(--navy); border: 1px solid var(--line); }
        .btn:disabled { opacity: .55; cursor: wait; }
        .grid-2 { display: grid; grid-template-columns: 1.4fr .8fr; gap: 18px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 22px; }
        .stat-card { padding: 18px; }
        .stat-label { margin: 0 0 9px; font-size: 12px; color: var(--slate); text-transform: uppercase; font-weight: 800; }
        .stat-value { font-size: 34px; font-weight: 900; color: #0f172a; transition: transform .2s ease, background .2s ease; border-radius: 8px; display: inline-block; padding: 2px 6px; }
        .stat-value.value-changed { transform: scale(1.08); background: #dbeafe; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid var(--line); text-align: left; font-size: 14px; }
        th { color: #475569; background: #f8fafc; font-size: 12px; text-transform: uppercase; }
        .modal { position: fixed; inset: 0; background: rgba(15, 23, 42, .58); display: grid; place-items: center; padding: 18px; z-index: 20; }
        .modal-box { background: #fff; border-radius: 10px; width: min(500px, 100%); padding: 22px; box-shadow: 0 24px 70px rgba(15,23,42,.25); }
        .hidden { display: none !important; }
        .spinner { width: 16px; height: 16px; border-radius: 50%; border: 3px solid #bfdbfe; border-top-color: var(--blue); animation: spin .75s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .toast { position: fixed; right: 22px; bottom: 22px; z-index: 30; padding: 14px 16px; border-radius: 8px; color: #fff; background: var(--navy); box-shadow: 0 16px 40px rgba(15,23,42,.22); }
        .toast.success { background: var(--green); }
        .toast.error { background: var(--red); }
        .stack > * + * { margin-top: 12px; }
        .status-pill { display: inline-flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--line); border-radius: 999px; padding: 9px 12px; color: #475569; }
        .status-pill.error { border-color: #fecaca; color: #b91c1c; background: #fff1f2; }
        @media (max-width: 860px) { .toolbar, .events-grid, .grid-2, .stats-grid { grid-template-columns: 1fr; } .hero { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body data-page="<?= htmlspecialchars($bodyPage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
<header class="topbar">
    <div class="wrap">
        <a class="brand" href="<?= htmlspecialchars($routeUrl('/events'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">EventHub <span>Pro</span></a>
        <nav class="nav">
            <a class="<?= $active === 'events' ? 'active' : '' ?>" href="<?= htmlspecialchars($routeUrl('/events'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Evenements</a>
            <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars($routeUrl('/dashboard'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Dashboard</a>
            <a class="<?= $active === 'create' ? 'active' : '' ?>" href="<?= htmlspecialchars($routeUrl('/events/create'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Creer</a>
        </nav>
    </div>
</header>
<main class="page">
    <div class="wrap">
