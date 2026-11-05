<?php use App\Core\Ref; $d = $data; $locaux = Ref::pairs(Ref::locaux(), [Ref::class, 'localLabel']); ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'arrow-left-right', 'actions' => '<a class="btn btn-outline-secondary" href="' . e(url('transferts.index')) . '"><i class="bi bi-arrow-left"></i> Retour</a>']) ?>
<form method="post" action="<?= e(url('transferts.store')) ?>" id="transfertForm">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card mb-3"><div class="card-header"><i class="bi bi-signpost-split me-1"></i> Mouvement</div><div class="card-body">
                <div class="mb-3"><label class="form-label">Local source (bureau de départ) *</label>
                    <select class="form-select<?= invalid($errors, 'local_source_id') ?>" name="local_source_id" id="local_source_id" required><?= options($locaux, $d['local_source_id'] ?? null, '— Choisir —') ?></select>
                    <?= field_error($errors, 'local_source_id') ?></div>
                <div class="text-center text-primary fs-4"><i class="bi bi-arrow-down"></i></div>
                <div class="mb-3"><label class="form-label">Local destination (bureau d'arrivée) *</label>
                    <select class="form-select<?= invalid($errors, 'local_destination_id') ?>" name="local_destination_id" required><?= options($locaux, $d['local_destination_id'] ?? null, '— Choisir —') ?></select>
                    <?= field_error($errors, 'local_destination_id') ?></div>
                <div class="mb-3"><label class="form-label">Nouvel employé détenteur</label>
                    <select class="form-select js-select<?= invalid($errors, 'employe_destination_id') ?>" name="employe_destination_id"><?= options(Ref::pairs(Ref::employes(), static fn($e) => $e['nom_complet'] . ' (' . $e['matricule'] . ')'), $d['employe_destination_id'] ?? null, '— Aucun (biens non affectés) —') ?></select>
                    <?= field_error($errors, 'employe_destination_id') ?>
                    <div class="form-text">À la validation, les biens seront affectés à cet employé (ou désaffectés si vide).</div></div>
                <div class="mb-3"><label class="form-label">Date du transfert *</label>
                    <input type="date" class="form-control<?= invalid($errors, 'date_transfert') ?>" name="date_transfert" value="<?= e($d['date_transfert'] ?? '') ?>" required><?= field_error($errors, 'date_transfert') ?></div>
                <div class="mb-3"><label class="form-label">Motif *</label>
                    <input class="form-control<?= invalid($errors, 'motif') ?>" name="motif" value="<?= e($d['motif'] ?? '') ?>" maxlength="255" required placeholder="Réorganisation, réaffectation, réparation…"><?= field_error($errors, 'motif') ?></div>
                <?php if (can('transferts.validate')): ?>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="valider" value="1" id="valider"><label class="form-check-label" for="valider">Valider immédiatement (déplacer les biens)</label></div>
                <?php endif; ?>
            </div></div>
            <button class="btn btn-primary btn-lg w-100"><i class="bi bi-send"></i> Enregistrer le transfert</button>
        </div>
        <div class="col-xl-8">
            <div class="card"><div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-box-seam me-1"></i> Biens à transférer <span class="badge text-bg-primary" id="selCount">0</span></span>
                <input type="search" class="form-control form-control-sm w-auto" id="bienFilter" placeholder="Filtrer…">
            </div>
            <?php if (isset($errors['biens'])): ?><div class="alert alert-danger m-3 mb-0"><?= e($errors['biens']) ?></div><?php endif; ?>
            <div class="table-responsive" style="max-height: 60vh"><table class="table table-sm table-hover mb-0">
                <thead class="sticky-top bg-body"><tr><th style="width:36px"><input class="form-check-input" type="checkbox" id="checkAll"></th><th>Code</th><th>Désignation</th><th>Détenteur</th><th>État</th></tr></thead>
                <tbody id="biensBody"><tr><td colspan="5" class="text-center text-body-secondary py-4">Choisissez d'abord le local source.</td></tr></tbody>
            </table></div></div>
        </div>
    </div>
</form>
<script>
(() => {
    const preselected = new Set(<?= json_encode(array_map('intval', $d['biens'] ?? [])) ?>);
    const src = document.getElementById('local_source_id'), body = document.getElementById('biensBody');
    const count = () => document.getElementById('selCount').textContent = body.querySelectorAll('input:checked').length;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    async function load() {
        if (!src.value) { body.innerHTML = '<tr><td colspan="5" class="text-center text-body-secondary py-4">Choisissez d\'abord le local source.</td></tr>'; count(); return; }
        body.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div> Chargement…</td></tr>';
        const res = await fetch('index.php?r=transferts.biens&local_id=' + encodeURIComponent(src.value), {headers: {'Accept': 'application/json'}});
        const rows = await res.json();
        body.innerHTML = rows.length ? rows.map((b) => `<tr>
            <td><input class="form-check-input" type="checkbox" name="biens[]" value="${b.id}" ${preselected.has(b.id) ? 'checked' : ''}></td>
            <td class="fw-semibold">${esc(b.code_inventaire)}</td><td>${esc(b.designation)}</td><td class="small">${esc(b.employe)}</td><td class="small">${esc(b.etat_label)}</td></tr>`).join('')
            : '<tr><td colspan="5" class="text-center text-body-secondary py-4">Aucun bien disponible dans ce local (ou déjà en cours de transfert).</td></tr>';
        count();
    }
    src.addEventListener('change', () => { preselected.clear(); load(); });
    body.addEventListener('change', count);
    document.getElementById('checkAll').addEventListener('change', (e) => {
        body.querySelectorAll('tr:not(.d-none) input[type=checkbox]').forEach((c) => c.checked = e.target.checked); count();
    });
    document.getElementById('bienFilter').addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase();
        body.querySelectorAll('tr').forEach((tr) => tr.classList.toggle('d-none', !tr.textContent.toLowerCase().includes(q)));
    });
    load();
})();
</script>
