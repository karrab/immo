<?= partial('page_header', ['title' => $title, 'icon' => 'tags',
    'actions' => can('categories.create') ? '<a class="btn btn-primary" href="' . e(url('categories.create')) . '"><i class="bi bi-plus-lg"></i> Nouvelle catégorie</a>' : '']) ?>
<form class="card card-body mb-3 d-print-none" method="get" action="index.php">
    <input type="hidden" name="r" value="categories.index">
    <div class="input-group"><input class="form-control" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Code ou libellé">
        <button class="btn btn-primary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Code</th><th>Libellé</th><th>Compte immo.</th><th>Compte amort.</th><th class="text-end">Durée</th><th class="text-end">Biens</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($page['rows'] as $c): ?>
        <tr><td class="fw-semibold"><?= e($c['code']) ?></td><td><?= e($c['libelle']) ?></td><td><?= e($c['compte_comptable']) ?></td><td><?= e($c['compte_amortissement']) ?></td>
            <td class="text-end"><?= (int) $c['duree_amortissement'] ?> ans</td>
            <td class="text-end"><a href="<?= e(url('biens.index', ['categorie_id' => $c['id']])) ?>"><?= (int) $c['nb_biens'] ?></a></td>
            <td class="text-end text-nowrap">
                <?php if (can('categories.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('categories.edit', ['id' => $c['id']])) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                <?php if (can('categories.delete')): ?><?= partial('delete', ['route' => 'categories.delete', 'id' => $c['id'], 'label' => $c['libelle']]) ?><?php endif; ?>
            </td></tr>
    <?php endforeach; ?>
    <?php if (!$page['rows']): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">Aucune catégorie.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= partial('pager', ['page' => $page]) ?>
