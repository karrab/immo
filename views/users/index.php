<?php use App\Core\Ref; $f = $filters; ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'person-gear',
    'actions' => can('users.create') ? '<a class="btn btn-primary" href="' . e(url('users.create')) . '"><i class="bi bi-person-plus"></i> Nouvel utilisateur</a>' : '']) ?>
<form class="card card-body mb-3" method="get" action="index.php">
    <input type="hidden" name="r" value="users.index">
    <div class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label small mb-1">Recherche</label><input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Identifiant, nom, email"></div>
        <div class="col-md-3"><label class="form-label small mb-1">Rôle</label><select class="form-select" name="role_id"><?= options(Ref::pairs(Ref::roles(), 'nom'), $f['role_id'], 'Tous') ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Actif</label><select class="form-select" name="actif"><?= options(['1' => 'Oui', '0' => 'Non'], $f['actif'], 'Tous') ?></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> Filtrer</button><a class="btn btn-outline-secondary" href="<?= e(url('users.index')) ?>"><i class="bi bi-x-lg"></i></a></div>
    </div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Identifiant</th><th>Nom complet</th><th>Email</th><th>Rôle</th><th>Employé lié</th><th>Dernière connexion</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($page['rows'] as $u): ?>
        <tr><td class="fw-semibold"><?= e($u['username']) ?></td><td><?= e($u['nom_complet']) ?></td><td class="small"><?= e($u['email']) ?></td>
            <td><span class="badge text-bg-info"><?= e($u['role']) ?></span></td><td class="small"><?= e($u['employe']) ?><?php if ($u['employe_type']): ?> <?= badge(TYPES_EMPLOYE, $u['employe_type']) ?><div class="text-body-secondary"><?= e($u['service']) ?></div><?php endif; ?></td>
            <td class="small"><?= datetime_fr($u['last_login_at']) ?: '<span class="text-body-secondary">jamais</span>' ?></td>
            <td><?= $u['actif'] ? '<span class="badge text-bg-success">Actif</span>' : '<span class="badge text-bg-secondary">Désactivé</span>' ?></td>
            <td class="text-end text-nowrap">
                <?php if (can('traces.view')): ?><a class="btn btn-sm btn-outline-dark" href="<?= e(url('traces.index', ['user_id' => $u['id']])) ?>" title="Traces de l'utilisateur"><i class="bi bi-journal-text"></i></a><?php endif; ?>
                <?php if (can('users.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('users.edit', ['id' => $u['id']])) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                <?php if (can('users.delete') && (int) $u['id'] !== (int) user()['id']): ?><?= partial('delete', ['route' => 'users.delete', 'id' => $u['id'], 'label' => $u['username']]) ?><?php endif; ?>
            </td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?= partial('pager', ['page' => $page, 'total' => $total]) ?>
