<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/funkcijos.php';

tikrintiRole('Vadybininkas');

$q       = trim($_GET['q'] ?? '');
$dalisId = (int)($_GET['dalis'] ?? 0);

// raktas => [tekstas sąraše, ORDER BY]
$rikiavimai = [
    'kaina'        => ['Kaina: nuo mažiausios',     'p.kaina ASC, p.pavadinimas ASC'],
    'kaina_desc'   => ['Kaina: nuo didžiausios',    'p.kaina DESC, p.pavadinimas ASC'],
    'pavadinimas'  => ['Pavadinimas: A–Z',          'p.pavadinimas ASC, p.kaina ASC'],
    'pavadinimas_desc' => ['Pavadinimas: Z–A',      'p.pavadinimas DESC, p.kaina ASC'],
    'pristatymas'  => ['Greičiausias pristatymas',  'p.pristatymo_laikas IS NULL, p.pristatymo_laikas ASC, p.kaina ASC'],
    'gamintojas'   => ['Gamintojas: A–Z',           'p.gamintojas IS NULL, p.gamintojas ASC, p.kaina ASC'],
    'modelis'      => ['Modelis: A–Z',              'p.modelis IS NULL, p.modelis ASC, p.kaina ASC'],
];
$rik = array_key_exists($_GET['rik'] ?? '', $rikiavimai) ? $_GET['rik'] : 'kaina';
$orderBy = $rikiavimai[$rik][1];

$laukai = 'p.prekes_id, p.pavadinimas, p.gamintojas, p.modelis, p.kaina, p.kiekis, p.pristatymo_laikas, p.tiekejo_id,
           COALESCE(n.pavadinimas, CONCAT(n.vardas, " ", n.pavarde)) AS tiekejas';

function likeReiksme(string $s): string {
    return '%' . addcslashes($s, '%_\\') . '%';
}

// --- Paieška arba atsitiktinės dalys ---
if ($q !== '') {
    $stmt = $pdo->prepare(
        "SELECT $laukai
         FROM preke p JOIN naudotojas n ON n.naudotojo_id = p.tiekejo_id
         WHERE p.pavadinimas LIKE :q1 OR p.gamintojas LIKE :q2 OR p.modelis LIKE :q3
         ORDER BY $orderBy"
    );
    $like = likeReiksme($q);
    $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like]);
    $dalys = $stmt->fetchAll();
} else {
    // Be paieškos – visos dalys
    $dalys = $pdo->query(
        "SELECT $laukai
         FROM preke p JOIN naudotojas n ON n.naudotojo_id = p.tiekejo_id
         ORDER BY $orderBy"
    )->fetchAll();
}

// --- «extend» Palyginti panašias dalis ---
$pasirinkta = null;
$panasios   = [];

if ($dalisId) {
    $stmt = $pdo->prepare("SELECT $laukai FROM preke p JOIN naudotojas n ON n.naudotojo_id = p.tiekejo_id WHERE p.prekes_id = ?");
    $stmt->execute([$dalisId]);
    $pasirinkta = $stmt->fetch() ?: null;
}

if ($pasirinkta) {
    // Pavadinimo žodžiai (bent 3 raidės) – kiekvienas sutapęs žodis didina panašumą
    $zodziai = array_unique(array_filter(
        preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($pasirinkta['pavadinimas'])),
        fn($z) => mb_strlen($z) >= 3
    ));

    $balas  = [];
    $params = [];
    foreach (array_values($zodziai) as $i => $z) {
        $balas[]        = "(p.pavadinimas LIKE :z$i)";
        $params["z$i"] = likeReiksme($z);
    }
    // Tas pats modelis ar gamintojas – stiprus panašumo požymis
    // (COALESCE, nes palyginimas su NULL grąžintų NULL ir „sugadintų“ visą sumą)
    $balas[] = 'COALESCE(p.modelis = :modelis, 0) * 3';
    $balas[] = 'COALESCE(p.gamintojas = :gamintojas, 0)';
    $params['modelis']    = $pasirinkta['modelis'];
    $params['gamintojas'] = $pasirinkta['gamintojas'];
    $params['tiekejas']   = $pasirinkta['tiekejo_id'];

    $balasSql = implode(' + ', $balas);
    $stmt = $pdo->prepare(
        "SELECT * FROM (
             SELECT $laukai, ($balasSql) AS panasumas
             FROM preke p JOIN naudotojas n ON n.naudotojo_id = p.tiekejo_id
             WHERE p.tiekejo_id <> :tiekejas
         ) x
         WHERE panasumas > 0
         ORDER BY " . str_replace('p.', 'x.', $orderBy) . "
         LIMIT 20"
    );
    $stmt->execute($params);
    $panasios = $stmt->fetchAll();
}

// Pagalbinės funkcijos
function nuoroda(array $keisti): string {
    $p = array_merge(['q' => $_GET['q'] ?? null, 'rik' => $_GET['rik'] ?? null, 'dalis' => $_GET['dalis'] ?? null], $keisti);
    return '?' . http_build_query(array_filter($p, fn($v) => $v !== null && $v !== ''));
}
function pristatymas(array $d): string {
    return pristatymoLaikas($d['pristatymo_laikas']);
}
function kaina($k): string {
    return number_format((float)$k, 2, ',', ' ') . ' €';
}

$puslapioPavadinimas = 'Dalių paieška';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="text-2xl font-semibold mb-6">Dalių paieška</h1>

<!-- Paieškos juosta -->
<form method="get" class="mb-8 flex flex-col sm:flex-row gap-3">
    <?php if ($pasirinkta): ?>
        <!-- keičiant rikiavimą palyginimas lieka atidarytas -->
        <input type="hidden" name="dalis" value="<?= (int)$pasirinkta['prekes_id'] ?>">
    <?php endif; ?>
    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" autofocus
           placeholder="Įveskite dalies pavadinimą, gamintoją ar modelį…"
           class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500">
    <select name="rik" onchange="this.form.submit()" aria-label="Rikiuoti"
            class="rounded-lg border border-slate-300 bg-white px-3 py-2.5">
        <?php foreach ($rikiavimai as $raktas => [$tekstas]): ?>
            <option value="<?= $raktas ?>" <?= $rik === $raktas ? 'selected' : '' ?>><?= $tekstas ?></option>
        <?php endforeach; ?>
    </select>
    <button class="rounded-lg bg-sky-600 px-6 py-2.5 font-medium text-white hover:bg-sky-700">Ieškoti</button>
</form>

<?php if ($dalisId && !$pasirinkta): ?>
    <div class="mb-6 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">Tokia dalis nerasta.</div>
<?php endif; ?>

<!-- Palyginimas -->
<?php if ($pasirinkta): ?>
    <section class="mb-10 bg-white rounded-xl shadow-sm border border-sky-200">
        <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-slate-200">
            <div>
                <h2 class="text-lg font-semibold">Lyginamos prekės</h2>
            </div>
            <a href="<?= htmlspecialchars(nuoroda(['dalis' => null])) ?>" class="text-sm text-slate-500 hover:text-slate-800">✕ Uždaryti</a>
        </div>

        <?php
        $visos   = array_merge([$pasirinkta], $panasios);
        $pigiausia = min(array_column($visos, 'kaina'));
        ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-5 py-3 font-medium">Pavadinimas</th>
                        <th class="px-5 py-3 font-medium">Tiekėjas</th>
                        <th class="px-5 py-3 font-medium">Gamintojas</th>
                        <th class="px-5 py-3 font-medium">Modelis</th>
                        <th class="px-5 py-3 font-medium text-right">Pristatymas</th>
                        <th class="px-5 py-3 font-medium text-right">Kaina</th>
                        <th class="px-5 py-3 font-medium text-right">Sandėlyje</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($visos as $i => $d): ?>
                        <tr class="<?= $i === 0 ? 'bg-sky-50' : 'hover:bg-slate-50' ?>">
                            <td class="px-5 py-3 font-medium">
                                <?= htmlspecialchars($d['pavadinimas']) ?>
                                <?php if ($i === 0): ?><span class="ml-1 rounded bg-sky-600 px-1.5 py-0.5 text-xs text-white">pasirinkta</span><?php endif; ?>
                            </td>
                            <td class="px-5 py-3">
                                <a href="tiekejai.php?id=<?= (int)$d['tiekejo_id'] ?>" class="text-sky-700 hover:underline"><?= htmlspecialchars($d['tiekejas']) ?></a>
                            </td>
                            <td class="px-5 py-3"><?= htmlspecialchars($d['gamintojas'] ?? '—') ?></td>
                            <td class="px-5 py-3"><?= htmlspecialchars($d['modelis'] ?? '—') ?></td>
                            <td class="px-5 py-3 text-right whitespace-nowrap"><?= pristatymas($d) ?></td>
                            <td class="px-5 py-3 text-right whitespace-nowrap <?= (float)$d['kaina'] == (float)$pigiausia && count($visos) > 1 ? 'text-green-700 font-semibold' : '' ?>">
                                <?= kaina($d['kaina']) ?>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap"><?= likutis($d) ?></td>
                            <td class="px-5 py-3 text-right"><?= krepselioMygtukas($d, $d['tiekejas']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!$panasios): ?>
            <p class="px-5 py-4 text-sm text-slate-500 border-t border-slate-100">Kiti tiekėjai panašių dalių nesiūlo.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- Rezultatai -->
<h2 class="text-lg font-semibold mb-3">
    <?= $q !== '' ? 'Rasta dalių: ' . count($dalys) : 'Visos dalys' ?>
</h2>

<?php if (!$dalys): ?>
    <div class="rounded-xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
        <?= $q !== '' ? 'Pagal „' . htmlspecialchars($q) . '“ nieko nerasta.' : 'Dalių sistemoje kol kas nėra.' ?>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-5 py-3 font-medium">Pavadinimas</th>
                    <th class="px-5 py-3 font-medium">Tiekėjas</th>
                    <th class="px-5 py-3 font-medium">Gamintojas</th>
                    <th class="px-5 py-3 font-medium">Modelis</th>
                    <th class="px-5 py-3 font-medium text-right">Pristatymas</th>
                    <th class="px-5 py-3 font-medium text-right">Kaina</th>
                    <th class="px-5 py-3 font-medium text-right">Sandėlyje</th>
                    <th class="px-5 py-3"><span class="sr-only">Veiksmai</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($dalys as $d): ?>
                    <?php $aktyvi = $pasirinkta && (int)$d['prekes_id'] === (int)$pasirinkta['prekes_id']; ?>
                    <tr class="<?= $aktyvi ? 'bg-sky-50' : 'hover:bg-slate-50' ?>">
                        <td class="px-5 py-3 font-medium text-slate-800"><?= htmlspecialchars($d['pavadinimas']) ?></td>
                        <td class="px-5 py-3">
                            <a href="tiekejai.php?id=<?= (int)$d['tiekejo_id'] ?>" class="text-sky-700 hover:underline"><?= htmlspecialchars($d['tiekejas']) ?></a>
                        </td>
                        <td class="px-5 py-3 text-slate-600"><?= htmlspecialchars($d['gamintojas'] ?? '—') ?></td>
                        <td class="px-5 py-3 text-slate-600"><?= htmlspecialchars($d['modelis'] ?? '—') ?></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap text-slate-600"><?= pristatymas($d) ?></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap font-medium"><?= kaina($d['kaina']) ?></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap"><?= likutis($d) ?></td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= htmlspecialchars(nuoroda(['dalis' => (int)$d['prekes_id']])) ?>"
                                   title="Palyginti su kitų tiekėjų dalimis" aria-label="Palyginti „<?= htmlspecialchars($d['pavadinimas']) ?>“"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg border <?= $aktyvi ? 'border-sky-400 bg-sky-100 text-sky-800' : 'border-slate-300 text-slate-600 hover:bg-slate-50 hover:text-sky-700' ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z"/>
                                    </svg>
                                </a>
                                <?= krepselioMygtukas($d, $d['tiekejas']) ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
