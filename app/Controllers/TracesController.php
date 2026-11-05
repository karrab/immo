<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Csv;
use App\Core\Database;
use App\Core\Keyset;
use App\Core\Pdf;
use App\Core\View;

/** Journal des traces (lecture seule). */
final class TracesController extends Controller
{
    private const SELECT = 'SELECT t.id, t.user_id, t.username, t.action, t.module, t.entite_id, t.description, t.ip, t.created_at FROM traces t';

    private function filters(): array
    {
        $w = [];
        $p = [];
        $f = [
            'user_id'   => $this->qint('user_id'),
            'module'    => $this->enum('module', MODULES, $_GET),
            'action'    => $this->q('action', 40),
            'entite_id' => $this->qint('entite_id'),
            'date_du'   => $this->date('date_du', $_GET),
            'date_au'   => $this->date('date_au', $_GET),
            'q'         => $this->q('q'),
            'ip'        => $this->q('ip', 45),
        ];
        if ($f['user_id'])   { $w[] = 't.user_id = ?';   $p[] = $f['user_id']; }
        if ($f['module'])    { $w[] = 't.module = ?';    $p[] = $f['module']; }
        if ($f['action'])    { $w[] = 't.action = ?';    $p[] = $f['action']; }
        if ($f['entite_id']) { $w[] = 't.entite_id = ?'; $p[] = $f['entite_id']; }
        if ($f['date_du'])   { $w[] = 't.created_at >= ?'; $p[] = $f['date_du'] . ' 00:00:00'; }
        if ($f['date_au'])   { $w[] = 't.created_at <= ?'; $p[] = $f['date_au'] . ' 23:59:59'; }
        if ($f['q'])         { $w[] = 't.description LIKE ?'; $p[] = '%' . $f['q'] . '%'; }
        if ($f['ip'])        { $w[] = 't.ip = ?'; $p[] = $f['ip']; }
        return [$w, $p, $f];
    }

    public function index(): void
    {
        $this->authorize('traces.view');
        [$w, $p, $f] = $this->filters();
        $page = (new Keyset(self::SELECT, $w, $p, 't.id', 'id', 't.id', 'DESC', $this->perPage()))
            ->paginate($this->q('after', 200), $this->q('before', 200));
        $actions = array_column(Database::all('SELECT DISTINCT action FROM traces ORDER BY action'), 'action');
        $this->render('traces/index', ['title' => 'Journal des traces', 'page' => $page, 'filters' => $f, 'actions' => $actions]);
    }

    public function show(): void
    {
        $this->authorize('traces.view');
        $t = Database::one('SELECT t.*, u.nom_complet FROM traces t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?', [(int) $this->qint('id')])
            ?? $this->notFound();
        $this->render('traces/show', ['title' => 'Trace #' . $t['id'], 't' => $t]);
    }

    public function export(): void
    {
        $this->authorize('traces.export');
        [$w, $p] = $this->filters();
        $rows = Database::all(self::SELECT . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY t.id DESC LIMIT ' . (int) config('app.export_max'), $p);
        Audit::log('export', 'traces', null, 'Export du journal des traces (' . count($rows) . ' lignes)');
        if (($_GET['format'] ?? 'pdf') === 'csv') {
            Csv::stream('traces_' . date('Ymd_His') . '.csv', ['#', 'Date', 'Utilisateur', 'Module', 'Action', 'Entité', 'Description', 'IP'],
                array_map(static fn($r) => [$r['id'], datetime_fr($r['created_at']), $r['username'], MODULES[$r['module']] ?? $r['module'],
                    $r['action'], $r['entite_id'], $r['description'], $r['ip']], $rows));
        }
        $html = View::render('pdf/traces_liste', ['title' => 'Journal des traces', 'rows' => $rows], 'layout/pdf');
        Pdf::stream($html, 'traces_' . date('Ymd_His') . '.pdf', 'landscape', !empty($_GET['inline']));
    }
}
