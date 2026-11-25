<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/funkcijos.php';

tikrintiRole('Direktorius');

$manoId = (int)$_SESSION['naudotojo_id'];

// Galimos rolės imamos iš DB ENUM, tad pridėjus naują (pvz. 'Naudotojas') kodo keisti nereikės
$enum  = $pdo->query("SHOW COLUMNS FROM naudotojas LIKE 'tipas'")->fetch()['Type'];
preg_match_all("/'([^']+)'/", $enum, $m);
$roles = $m[1];

$roliuPav = ['Naudotojas' => 'Naudotojas', 'Vadybininkas' => 'Vadybininkas', 'Tiekejas' => 'Tiekėjas', 'Direktorius' => 'Direktorius'];
$roliuSpalva = [
    'Naudotojas'   => 'bg-slate-100 text-slate-700',
    'Vadybininkas' => 'bg-sky-100 text-sky-800',
    'Tiekejas'     => 'bg-amber-100 text-amber-800',
    'Direktorius'  => 'bg-violet-100 text-violet-800',
];
$rolesPav = fn(?string $r) => $roliuPav[$r] ?? ($r ?? '—');

$q       = trim($_GET['q'] ?? '');
$filtras = in_array($_GET['role'] ?? '', $roles, true) ? $_GET['role'] : '';

// --- «extend» Pakeisti naudotojo teisę ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kam   = (int)($_POST['naudotojo_id'] ?? 0);
    $nauja = $_POST['tipas'] ?? '';

    if ($kam === $manoId) {
        grizti('klaida', 'Savo prieigos pakeisti negalite.');
    }
    if (!in_array($nauja, $roles, true)) {
        grizti('klaida', 'Neteisinga prieiga.');
    }

    $stmt = $pdo->prepare('SELECT vardas, pavarde, tipas FROM naudotojas WHERE naudotojo_id = ?');
    $stmt->execute([$kam]);
    $n = $stmt->fetch();
    if (!$n) {
        grizti('klaida', 'Naudotojas nerastas.');
    }
    if ($n['tipas'] === $nauja) {
        grizti('klaida', 'Naudotojas jau turi šią prieigą.');
    }

    $pdo->prepare('UPDATE naudotojas SET tipas = ? WHERE naudotojo_id = ?')->execute([$nauja, $kam]);
    grizti('sekme', "{$n['vardas']} {$n['pavarde']}: prieiga pakeista iš „{$rolesPav($n['tipas'])}“ į „{$rolesPav($nauja)}“.");
}

// --- Peržiūrėti naudotojus ---
$sql = 'SELECT naudotojo_id, vardas, pavarde, pavadinimas, pastas, telefonas, tipas FROM naudotojas WHERE 1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (CONCAT(vardas, " ", pavarde) LIKE ? OR pavadinimas LIKE ? OR pastas LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
if ($filtras) {
    $sql .= ' AND tipas = ?';
    $params[] = $filtras;
}
$sql .= ' ORDER BY pavarde, vardas';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$naudotojai = $stmt->fetchAll();

$kiekPagalRole = $pdo->query('SELECT tipas, COUNT(*) FROM naudotojas GROUP BY tipas')->fetchAll(PDO::FETCH_KEY_PAIR);

function nuoroda(array $keisti): string {
    $p = array_merge(['q' => $_GET['q'] ?? null, 'role' => $_GET['role'] ?? null], $keisti);
    return '?' . http_build_query(array_filter($p, fn($v) => $v !== null && $v !== ''));
}

$puslapioPavadinimas = 'Naudotojai';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="text-2xl font-semibold mb-6">Naudotojai</h1>

<div class="mb-6 flex flex-col lg:flex-row lg:items-center gap-3">
    <form method="get" class="flex gap-2 flex-1 max-w-xl">
        <?php if ($filtras): ?><input type="hidden" name="role" value="<?= htmlspecialchars($filtras) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ieškoti pagal vardą, įmonę ar el. paštą…"
               class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500">
        <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">Ieškoti</button>
    </form>
    <nav class="flex flex-wrap gap-2 text-sm">
        <a href="<?= htmlspecialchars(nuoroda(['role' => null])) ?>"
           class="rounded-full px-4 py-1.5 border <?= $filtras === '' ? 'bg-slate-800 border-slate-800 text-white' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
            Visi <span class="opacity-60"><?= array_sum($kiekPagalRole) ?></span>
        </a>
        <?php foreach ($roles as $r): ?>
            <a href="<?= htmlspecialchars(nuoroda(['role' => $r])) ?>"
               class="rounded-full px-4 py-1.5 border <?= $filtras === $r ? 'bg-slate-800 border-slate-800 text-white' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
                <?= $rolesPav($r) ?> <span class="opacity-60"><?= (int)($kiekPagalRole[$r] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<?php if (!$naudotojai): ?>
    <div class="rounded-xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">Naudotojų nerasta.</div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-5 py-3 font-medium">Vardas, pavardė</th>
                    <th class="px-5 py-3 font-medium">Įmonė</th>
                    <th class="px-5 py-3 font-medium">El. paštas</th>
                    <th class="px-5 py-3 font-medium">Telefonas</th>
                    <th class="px-5 py-3 font-medium">Prieiga</th>
                    <th class="px-5 py-3 w-16"><span class="sr-only">Keisti prieigą</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($naudotojai as $n): ?>
                    <?php $nid = (int)$n['naudotojo_id']; $asPats = $nid === $manoId; ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium text-slate-800 whitespace-nowrap">
                            <?= htmlspecialchars($n['vardas'] . ' ' . $n['pavarde']) ?>
                            <?php if ($asPats): ?><span class="ml-1 text-xs font-normal text-slate-400">(jūs)</span><?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-slate-600"><?= htmlspecialchars($n['pavadinimas'] ?? '—') ?></td>
                        <td class="px-5 py-3">
                            <a href="mailto:<?= htmlspecialchars($n['pastas']) ?>" class="text-sky-700 hover:underline"><?= htmlspecialchars($n['pastas']) ?></a>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap text-slate-600"><?= htmlspecialchars($n['telefonas'] ?? '—') ?></td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $roliuSpalva[$n['tipas']] ?? 'bg-slate-100 text-slate-700' ?>">
                                <?= $rolesPav($n['tipas']) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <button type="button" <?= $asPats ? 'disabled' : 'data-atidaryti="role-' . $nid . '"' ?>
                                    aria-label="Keisti <?= htmlspecialchars($n['vardas'] . ' ' . $n['pavarde']) ?> prieigą"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 disabled:border-slate-200 disabled:text-slate-300 disabled:hover:bg-transparent disabled:cursor-not-allowed">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- «extend» Pakeisti naudotojo teisę – popup langai -->
    <?php foreach ($naudotojai as $n): ?>
        <?php $nid = (int)$n['naudotojo_id']; if ($nid === $manoId) continue; ?>
        <dialog id="role-<?= $nid ?>" class="w-full max-w-md rounded-xl p-0 shadow-xl backdrop:bg-slate-900/50">
            <form method="post" action="<?= htmlspecialchars(nuoroda([])) ?>"
                  onsubmit="return confirm('Pakeisti naudotojo prieigą į „' + this.tipas.options[this.tipas.selectedIndex].text + '“?');">
                <input type="hidden" name="naudotojo_id" value="<?= $nid ?>">

                <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-slate-200">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-800"><?= htmlspecialchars($n['vardas'] . ' ' . $n['pavarde']) ?></h2>
                        <p class="text-sm text-slate-500"><?= htmlspecialchars($n['pastas']) ?></p>
                    </div>
                    <button type="button" data-uzdaryti aria-label="Uždaryti" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5">
                    <label for="tipas-<?= $nid ?>" class="block text-sm font-medium text-slate-700 mb-1">Prieiga</label>
                    <select id="tipas-<?= $nid ?>" name="tipas" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r ?>" <?= $r === $n['tipas'] ? 'selected' : '' ?>><?= $rolesPav($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex justify-end gap-2 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-xl">
                    <button type="button" data-uzdaryti class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-200">Atšaukti</button>
                    <button class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Išsaugoti</button>
                </div>
            </form>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.addEventListener('click', e => {
    const atidaryti = e.target.closest('[data-atidaryti]');
    if (atidaryti) document.getElementById(atidaryti.dataset.atidaryti)?.showModal();

    const uzdaryti = e.target.closest('[data-uzdaryti]');
    if (uzdaryti) uzdaryti.closest('dialog')?.close();
});
// Uždaryti paspaudus šalia lango
document.querySelectorAll('dialog').forEach(d => {
    d.addEventListener('click', e => { if (e.target === d) d.close(); });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
