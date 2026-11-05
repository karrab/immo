<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e($title ?? '') ?></title>
<style>
    @page { margin: 22mm 12mm 18mm 12mm; }
    body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9px; color: #222; }
    header.doc { position: fixed; top: -16mm; left: 0; right: 0; height: 12mm; border-bottom: 1.5px solid #0d6efd; }
    header.doc .org { font-size: 11px; font-weight: bold; color: #0d6efd; }
    header.doc .app { font-size: 8px; color: #777; }
    h1 { font-size: 15px; margin: 0 0 6px; color: #0d3b8c; }
    h2 { font-size: 11px; margin: 14px 0 5px; padding: 3px 6px; background: #e9f0fb; color: #0d3b8c; }
    table { width: 100%; border-collapse: collapse; }
    table.data th { background: #0d6efd; color: #fff; text-align: left; padding: 4px; font-size: 8.5px; }
    table.data td { padding: 3px 4px; border-bottom: 0.5px solid #ccc; vertical-align: top; }
    table.data tr:nth-child(even) td { background: #f6f8fb; }
    table.kv td { padding: 3px 5px; border: 0.5px solid #d5d9e0; }
    table.kv td.k { width: 22%; background: #f3f5f8; font-weight: bold; }
    .r, table.data th.r { text-align: right; } .c, table.data th.c { text-align: center; } .muted { color: #777; }
    .filters { margin: 0 0 8px; font-size: 8.5px; color: #555; }
    .total td { font-weight: bold; background: #e9f0fb !important; border-top: 1px solid #0d6efd; }
    .sign { margin-top: 30px; } .sign td { width: 33%; padding: 6px; vertical-align: top; height: 60px; border: 0.5px solid #bbb; }
    .badge { padding: 1px 4px; border-radius: 3px; background: #eee; }
</style>
</head>
<body>
<header class="doc">
    <div class="org"><?= e(config('app.organisation')) ?></div>
    <div class="app"><?= e(config('app.name')) ?> — Gestion des immobilisations</div>
</header>
<?= $content ?>
</body>
</html>
