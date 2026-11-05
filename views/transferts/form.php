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
                <?php if (can('transferts.validate') && App\Core\Scope::chefOfService()): ?>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="valider" value="1" id="valider"><label class="form-check-label" for="valider">Valider immédiatement (si je suis le chef du service source)</label></div>
                <?php endif; ?>
                <div class="form-text"><i class="bi bi-info-circle"></i> La demande sera validée, rejetée ou annulée par le <strong>chef du service</strong> du local source.</div>
            </div></div>
            <button class="btn btn-primary btn-lg w-100"><i class="bi bi-send"></i> Enregistrer le transfert</button>
        </div>
        <div class="col-xl-8">
            <div class="card"><div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-box-seam me-1"></i> Biens à transférer <span class="badge text-bg-primary" id="selCount">0</span></span>
                <input type="search" class="form-control form-control-sm w-auto" id="bienFilter" placeholder="Rechercher code, désignation, n° série…">
            </div>
            <?php if (isset($errors['biens'])): ?><div class="alert alert-danger m-3 mb-0"><?= e($errors['biens']) ?></div><?php endif; ?>
            <div class="table-responsive" style="max-height: 60vh"><table class="table table-sm table-hover mb-0">
                <thead class="sticky-top bg-body"><tr><th style="width:36px"><input class="form-check-input" type="checkbox" id="checkAll"></th><th>Code</th><th>Désignation</th><th>Détenteur</th><th>État</th></tr></thead>
                <tbody id="biensBody"><tr><td colspan="5" class="text-center text-body-secondary py-4">Choisissez d'abord le local source.</td></tr></tbody>
            </table></div>
            <div class="card-footer d-flex justify-content-between align-items-center small">
                <span id="loadedInfo" class="text-body-secondary"></span>
                <button type="button" class="btn btn-sm btn-outline-primary d-none" id="loadMore"><i class="bi bi-chevron-down"></i> Charger la suite</button>
            </div></div>
        </div>
    </div>
</form>
<script>
(() => {
    // Sélection des biens : pagination keyset côté serveur (100 par page) + recherche
    const selected = new Set(<?= json_encode(array_map('intval', $d['biens'] ?? [])) ?>);
    const src = document.getElementById('local_source_id'), body = document.getElementById('biensBody');
    const more = document.getElementById('loadMore'), info = document.getElementById('loadedInfo'), search = document.getElementById('bienFilter');
    let next = null, loaded = 0, timer = null;
    const count = () => document.getElementById('selCount').textContent = selected.size;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const empty = (msg) => { body.innerHTML = `<tr><td colspan="5" class="text-center text-body-secondary py-4">${msg}</td></tr>`; };
    // Champs cachés : conserve la sélection même pour les lignes non affichées
    const form = document.getElementById('transfertForm');
    form.addEventListener('submit', () => {
        form.querySelectorAll('input[name="biens[]"][type=hidden]').forEach((h) => h.remove());
        const shown = new Set([...body.querySelectorAll('input[type=checkbox]')].map((c) => +c.value));
        selected.forEach((id) => { if (!shown.has(id)) form.insertAdjacentHTML('beforeend', `<input type="hidden" name="biens[]" value="${id}">`); });
    });
    async function load(reset) {
        if (reset) { next = null; loaded = 0; }
        if (!src.value) { empty("Choisissez d'abord le local source."); more.classList.add('d-none'); info.textContent = ''; count(); return; }
        if (reset) empty('<div class="spinner-border spinner-border-sm"></div> Chargement…');
        const qs = new URLSearchParams({ r: 'transferts.biens', local_id: src.value, q: search.value.trim() });
        if (next) qs.set('after', next);
        const res = await fetch('index.php?' + qs, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        const html = data.rows.map((b) => `<tr>
            <td><input class="form-check-input" type="checkbox" name="biens[]" value="${b.id}" ${selected.has(b.id) ? 'checked' : ''}></td>
            <td class="fw-semibold">${esc(b.code_inventaire)}</td><td>${esc(b.designation)}</td><td class="small">${esc(b.employe)}</td><td class="small">${esc(b.etat_label)}</td></tr>`).join('');
        if (reset) body.innerHTML = html || '';
        else body.insertAdjacentHTML('beforeend', html);
        loaded += data.rows.length;
        if (!loaded) empty('Aucun bien disponible dans ce local (ou déjà en cours de transfert).');
        next = data.next;
        more.classList.toggle('d-none', !next);
        info.textContent = loaded ? `${loaded} bien(s) affiché(s)` + (next ? ' — d\'autres sont disponibles' : '') : '';
        count();
    }
    src.addEventListener('change', () => { selected.clear(); load(true); });
    more.addEventListener('click', () => load(false));
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(true), 300); });
    search.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
    body.addEventListener('change', (e) => {
        if (e.target.type !== 'checkbox') return;
        e.target.checked ? selected.add(+e.target.value) : selected.delete(+e.target.value); count();
    });
    document.getElementById('checkAll').addEventListener('change', (e) => {
        body.querySelectorAll('input[type=checkbox]').forEach((c) => { c.checked = e.target.checked; e.target.checked ? selected.add(+c.value) : selected.delete(+c.value); });
        count();
    });
    load(true);
})();
</script>
