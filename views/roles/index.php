<?= partial('page_header', ['title' => $title, 'icon' => 'shield-lock',
    'actions' => can('roles.create') ? '<a class="btn btn-primary" href="' . e(url('roles.create')) . '"><i class="bi bi-plus-lg"></i> Nouveau rôle</a>' : '']) ?>
<form class="card card-body mb-3" method="get" action="index.php">
    <input type="hidden" name="r" value="roles.index">
    <div class="input-group"><input class="form-control" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Nom du rôle">
        <button class="btn btn-primary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Rôle</th><th>Description</th><th class="text-end">Permissions</th><th class="text-end">Utilisateurs</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($page['rows'] as $r): ?>
        <tr><td class="fw-semibold"><?= e($r['nom']) ?> <?= $r['is_super'] ? '<span class="badge text-bg-danger">super-admin</span>' : '' ?></td>
            <td class="small"><?= e($r['description']) ?></td>
            <td class="text-end"><?= $r['is_super'] ? 'Toutes' : (int) $r['nb_perms'] . ' / ' . $nbPerms ?></td>
            <td class="text-end"><a href="<?= e(url('users.index', ['role_id' => $r['id']])) ?>"><?= (int) $r['nb_users'] ?></a></td>
            <td class="text-end text-nowrap">
                <?php if (can('roles.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('roles.edit', ['id' => $r['id']])) ?>"><i class="bi bi-pencil"></i> Permissions</a><?php endif; ?>
                <?php if (can('roles.delete') && !$r['is_super']): ?><?= partial('delete', ['route' => 'roles.delete', 'id' => $r['id'], 'label' => $r['nom']]) ?><?php endif; ?>
            </td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?= partial('pager', ['page' => $page, 'total' => $total]) ?>
