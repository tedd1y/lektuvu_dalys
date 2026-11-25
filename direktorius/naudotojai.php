<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';

checkRole('Direktorius');

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
$id      = (int)($_GET['id'] ?? 0);

function grizti(string $tipas, string $tekstas): never {
    $_SESSION['zinute'] = ['tipas' => $tipas, 'tekstas' => $tekstas];
    $uzklausa = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: naudotojai.php' . ($uzklausa ? "?$uzklausa" : ''));
    exit;
}

// --- «extend» Pakeisti naudotojo teisę ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kam   = (int)($_POST['naudotojo_id'] ?? 0);
    $nauja = $_POST['tipas'] ?? '';

    if ($kam === $manoId) {
        grizti('klaida', 'Savo rolės pakeisti negalite.');
    }
    if (!in_array($nauja, $roles, true)) {
        grizti('klaida', 'Neteisinga rolė.');
    }

    $stmt = $pdo->prepare('SELECT vardas, pavarde, tipas FROM naudotojas WHERE naudotojo_id = ?');
    $stmt->execute([$kam]);
    $n = $stmt->fetch();
    if (!$n) {
        grizti('klaida', 'Naudotojas nerastas.');
    }
    if ($n['tipas'] === $nauja) {
        grizti('klaida', 'Naudotojas jau turi šią rolę.');
    }

    $pdo->prepare('UPDATE naudotojas SET tipas = ? WHERE naudotojo_id = ?')->execute([$nauja, $kam]);
    grizti('sekme', "{$n['vardas']} {$n['pavarde']}: rolė pakeista iš „{$rolesPav($n['tipas'])}“ į „{$rolesPav($nauja)}“.");
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

// Pasirinktas naudotojas + jo veiklos santrauka
$pasirinktas = null;
$veikla = [];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM naudotojas WHERE naudotojo_id = ?');
    $stmt->execute([$id]);
    $pasirinktas = $stmt->fetch() ?: null;
}
if ($pasirinktas) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM preke WHERE tiekejo_id = ?');
    $stmt->execute([$id]);
    $veikla['dalys'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(CASE WHEN busena <> 'Atsauktas' THEN suma END), 0) FROM uzsakymas WHERE vadybininko_id = ?");
    $stmt->execute([$id]);
    [$veikla['uzsakymai'], $veikla['uzsakymu_suma']] = $stmt->fetch(PDO::FETCH_NUM);

    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT up.uzsakymo_id) FROM uzsakymo_preke up
         JOIN preke p ON p.prekes_id = up.prekes_id WHERE p.tiekejo_id = ?"
    );
    $stmt->execute([$id]);
    $veikla['gauti'] = (int)$stmt->fetchColumn();
}

function nuoroda(array $keisti): string {
    $p = array_merge(['q' => $_GET['q'] ?? null, 'role' => $_GET['role'] ?? null, 'id' => $_GET['id'] ?? null], $keisti);
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
        <a href="<?= htmlspecialchars(nuoroda(['role' => null, 'id' => null])) ?>"
           class="rounded-full px-4 py-1.5 border <?= $filtras === '' ? 'bg-slate-800 border-slate-800 text-white' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
            Visi <span class="opacity-60"><?= array_sum($kiekPagalRole) ?></span>
        </a>
        <?php foreach ($roles as $r): ?>
            <a href="<?= htmlspecialchars(nuoroda(['role' => $r, 'id' => null])) ?>"
               class="rounded-full px-4 py-1.5 border <?= $filtras === $r ? 'bg-slate-800 border-slate-800 text-white' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
                <?= $rolesPav($r) ?> <span class="opacity-60"><?= (int)($kiekPagalRole[$r] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<?php if ($id && !$pasirinktas): ?>
    <div class="mb-6 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">Naudotojas nerastas.</div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-5">

    <!-- Sąrašas -->
    <section class="lg:col-span-3">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <?php if (!$naudotojai): ?>
                <p class="p-5 text-slate-500">Naudotojų nerasta.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($naudotojai as $n): ?>
                        <?php $aktyvus = (int)$n['naudotojo_id'] === $id; ?>
                        <li>
                            <a href="<?= htmlspecialchars(nuoroda(['id' => (int)$n['naudotojo_id']])) ?>"
                               class="flex items-center gap-4 px-5 py-3 border-l-4 <?= $aktyvus ? 'bg-sky-50 border-sky-500' : 'border-transparent hover:bg-slate-50' ?>">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-600">
                                    <?= htmlspecialchars(mb_substr($n['vardas'], 0, 1) . mb_substr($n['pavarde'], 0, 1)) ?>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium truncate">
                                        <?= htmlspecialchars($n['vardas'] . ' ' . $n['pavarde']) ?>
                                        <?php if ((int)$n['naudotojo_id'] === $manoId): ?><span class="text-xs text-slate-400">(jūs)</span><?php endif; ?>
                                    </span>
                                    <span class="block text-sm text-slate-500 truncate">
                                        <?= htmlspecialchars($n['pastas']) ?><?= $n['pavadinimas'] ? ' · ' . htmlspecialchars($n['pavadinimas']) : '' ?>
                                    </span>
                                </span>
                                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium <?= $roliuSpalva[$n['tipas']] ?? 'bg-slate-100 text-slate-700' ?>">
                                    <?= $rolesPav($n['tipas']) ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <!-- Pasirinktas naudotojas -->
    <section class="lg:col-span-2">
        <?php if (!$pasirinktas): ?>
            <div class="rounded-xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
                Pasirinkite naudotoją, kad pamatytumėte jo duomenis ir galėtumėte pakeisti rolę.
            </div>
        <?php else: ?>
            <?php $p = $pasirinktas; $asPats = (int)$p['naudotojo_id'] === $manoId; ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 lg:sticky lg:top-4">
                <div class="px-5 py-4 border-b border-slate-200">
                    <h2 class="text-lg font-semibold"><?= htmlspecialchars($p['vardas'] . ' ' . $p['pavarde']) ?></h2>
                    <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-medium <?= $roliuSpalva[$p['tipas']] ?? 'bg-slate-100 text-slate-700' ?>">
                        <?= $rolesPav($p['tipas']) ?>
                    </span>
                </div>

                <dl class="px-5 py-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Įmonė</dt><dd class="text-right"><?= htmlspecialchars($p['pavadinimas'] ?? '—') ?></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">El. paštas</dt><dd class="text-right break-all"><?= htmlspecialchars($p['pastas']) ?></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Telefonas</dt><dd class="text-right"><?= htmlspecialchars($p['telefonas'] ?? '—') ?></dd></div>
                </dl>

                <dl class="grid grid-cols-3 border-y border-slate-200 text-center text-sm">
                    <div class="px-2 py-3"><dd class="text-lg font-semibold"><?= $veikla['dalys'] ?></dd><dt class="text-xs text-slate-500">siūlomų dalių</dt></div>
                    <div class="px-2 py-3 border-x border-slate-200"><dd class="text-lg font-semibold"><?= (int)$veikla['uzsakymai'] ?></dd><dt class="text-xs text-slate-500">pateiktų užsakymų</dt></div>
                    <div class="px-2 py-3"><dd class="text-lg font-semibold"><?= $veikla['gauti'] ?></dd><dt class="text-xs text-slate-500">gautų užsakymų</dt></div>
                </dl>

                <div class="px-5 py-4">
                    <?php if ($asPats): ?>
                        <p class="text-sm text-slate-500">Savo rolės pakeisti negalite.</p>
                    <?php else: ?>
                        <form method="post" class="space-y-3"
                              onsubmit="return confirm('Pakeisti naudotojo rolę į „' + this.tipas.options[this.tipas.selectedIndex].text + '“?');">
                            <input type="hidden" name="naudotojo_id" value="<?= (int)$p['naudotojo_id'] ?>">
                            <label for="tipas" class="block text-sm font-medium text-slate-700">Pakeisti rolę</label>
                            <div class="flex gap-2">
                                <select id="tipas" name="tipas" class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2">
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?= $r ?>" <?= $r === $p['tipas'] ? 'selected' : '' ?>><?= $rolesPav($r) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Išsaugoti</button>
                            </div>
                            <?php if ($veikla['dalys'] > 0 && $p['tipas'] === 'Tiekejas'): ?>
                                <p class="text-xs text-amber-700">Šis tiekėjas siūlo <?= $veikla['dalys'] ?> dal. Pakeitus rolę jos liks sistemoje, bet tiekėjas nebegalės jų tvarkyti.</p>
                            <?php endif; ?>
                            <p class="text-xs text-slate-500">Pakeitimas įsigalioja iškart: naudotojui nereikia jungtis iš naujo.</p>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
