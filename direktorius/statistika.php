<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';

checkRole('Direktorius');

// --- Laikotarpis ---
$data = fn($s) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $s ?? '') ? $s : '';
$nuo = $data($_GET['nuo'] ?? '');
$iki = $data($_GET['iki'] ?? '');
$rik = ($_GET['rik'] ?? '') === 'suma' ? 'suma' : 'kiekis';

$salyga = '1';
$params = [];
if ($nuo) { $salyga .= ' AND u.pateikimo_data >= ?';                 $params[] = $nuo; }
if ($iki) { $salyga .= ' AND u.pateikimo_data < ? + INTERVAL 1 DAY'; $params[] = $iki; }

// Atšaukti užsakymai į sumas ir kiekius neįskaičiuojami
$aktyvus = "$salyga AND u.busena <> 'Atsauktas'";

function uzklausa(PDO $pdo, string $sql, array $params): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Bendri rodikliai
$bendri = uzklausa($pdo,
    "SELECT COUNT(*) AS uzsakymu, COALESCE(SUM(u.suma), 0) AS suma, COALESCE(AVG(u.suma), 0) AS vidurkis,
            (SELECT COALESCE(SUM(up.kiekis), 0) FROM uzsakymo_preke up JOIN uzsakymas u ON u.uzsakymo_id = up.uzsakymo_id WHERE $aktyvus) AS vienetu
     FROM uzsakymas u WHERE $aktyvus",
    array_merge($params, $params))[0];

// Pagal būseną
$pagalBusena = [];
foreach (uzklausa($pdo, "SELECT u.busena, COUNT(*) AS kiek, COALESCE(SUM(u.suma), 0) AS suma FROM uzsakymas u WHERE $salyga GROUP BY u.busena", $params) as $r) {
    $pagalBusena[$r['busena']] = $r;
}

// Populiariausios dalys
$dalys = uzklausa($pdo,
    "SELECT p.pavadinimas, p.gamintojas, p.modelis,
            COALESCE(t.pavadinimas, CONCAT(t.vardas, ' ', t.pavarde)) AS tiekejas,
            SUM(up.kiekis) AS kiekis, COUNT(DISTINCT up.uzsakymo_id) AS uzsakymu, SUM(up.suma) AS suma
     FROM uzsakymo_preke up
     JOIN uzsakymas u ON u.uzsakymo_id = up.uzsakymo_id
     JOIN preke p ON p.prekes_id = up.prekes_id
     JOIN naudotojas t ON t.naudotojo_id = p.tiekejo_id
     WHERE $aktyvus
     GROUP BY p.prekes_id
     ORDER BY " . ($rik === 'suma' ? 'suma DESC, kiekis DESC' : 'kiekis DESC, suma DESC') . "
     LIMIT 10", $params);

// Pagal tiekėją
$tiekejai = uzklausa($pdo,
    "SELECT COALESCE(t.pavadinimas, CONCAT(t.vardas, ' ', t.pavarde)) AS vardas,
            COUNT(DISTINCT up.uzsakymo_id) AS uzsakymu, SUM(up.kiekis) AS kiekis, SUM(up.suma) AS suma
     FROM uzsakymo_preke up
     JOIN uzsakymas u ON u.uzsakymo_id = up.uzsakymo_id
     JOIN preke p ON p.prekes_id = up.prekes_id
     JOIN naudotojas t ON t.naudotojo_id = p.tiekejo_id
     WHERE $aktyvus
     GROUP BY t.naudotojo_id ORDER BY suma DESC", $params);

// Pagal vadybininką
$vadybininkai = uzklausa($pdo,
    "SELECT CONCAT(v.vardas, ' ', v.pavarde) AS vardas, v.pavadinimas AS imone,
            COUNT(*) AS uzsakymu, SUM(u.suma) AS suma
     FROM uzsakymas u JOIN naudotojas v ON v.naudotojo_id = u.vadybininko_id
     WHERE $aktyvus
     GROUP BY v.naudotojo_id ORDER BY suma DESC", $params);

// Pagal mėnesį
$menesiai = uzklausa($pdo,
    "SELECT DATE_FORMAT(u.pateikimo_data, '%Y-%m') AS men, COUNT(*) AS uzsakymu, SUM(u.suma) AS suma
     FROM uzsakymas u WHERE $aktyvus
     GROUP BY men ORDER BY men DESC LIMIT 12", $params);

function eur($s): string {
    return number_format((float)$s, 2, ',', ' ') . ' €';
}

// Horizontali juosta (vienas atspalvis – rodo dydį), užvedus rodoma tiksli reikšmė
function juosta(float $reiksme, float $max, string $paaiskinimas): string {
    $proc = $max > 0 ? max(1, round($reiksme / $max * 100)) : 0;
    return '<div class="h-2 w-full rounded-full bg-slate-100" title="' . htmlspecialchars($paaiskinimas) . '">'
         . '<div class="h-2 rounded-full bg-sky-600" style="width:' . $proc . '%"></div></div>';
}

$busenos = [
    'Pateiktas' => ['Pateikti',  '●', 'text-sky-700'],
    'Priimtas'  => ['Priimti',   '◐', 'text-amber-700'],
    'Ivykdytas' => ['Įvykdyti',  '✓', 'text-green-700'],
    'Atsauktas' => ['Atšaukti',  '✕', 'text-slate-500'],
];

$puslapioPavadinimas = 'Statistika';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-semibold">Užsakymų statistika</h1>
        <p class="text-sm text-slate-500">
            <?= $nuo || $iki
                ? 'Laikotarpis: ' . ($nuo ?: 'nuo pradžios') . ' – ' . ($iki ?: 'iki dabar')
                : 'Visas laikotarpis' ?>
            · atšaukti užsakymai į sumas neįskaičiuojami
        </p>
    </div>

    <form method="get" class="flex flex-wrap items-end gap-2 text-sm">
        <input type="hidden" name="rik" value="<?= $rik ?>">
        <label class="flex flex-col gap-1"><span class="text-slate-500">Nuo</span>
            <input type="date" name="nuo" value="<?= $nuo ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5"></label>
        <label class="flex flex-col gap-1"><span class="text-slate-500">Iki</span>
            <input type="date" name="iki" value="<?= $iki ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5"></label>
        <button class="rounded-lg bg-sky-600 px-4 py-2 font-medium text-white hover:bg-sky-700">Rodyti</button>
        <?php if ($nuo || $iki): ?>
            <a href="statistika.php" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-200">Visas laikotarpis</a>
        <?php endif; ?>
    </form>
</div>

<!-- Pagrindiniai rodikliai -->
<div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mb-6">
    <?php foreach ([
        ['Užsakymų',              (int)$bendri['uzsakymu']],
        ['Bendra suma',           eur($bendri['suma'])],
        ['Vidutinis užsakymas',   eur($bendri['vidurkis'])],
        ['Užsakyta dalių',        (int)$bendri['vienetu'] . ' vnt.'],
    ] as [$pav, $reiksme]): ?>
        <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-5">
            <div class="text-sm text-slate-500"><?= $pav ?></div>
            <div class="mt-1 text-2xl font-semibold tabular-nums"><?= $reiksme ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Pagal būseną -->
<div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mb-8">
    <?php foreach ($busenos as $raktas => [$pav, $ikona, $spalva]): ?>
        <?php $b = $pagalBusena[$raktas] ?? ['kiek' => 0, 'suma' => 0]; ?>
        <div class="rounded-xl bg-white border border-slate-200 p-4">
            <div class="text-sm <?= $spalva ?>"><span aria-hidden="true"><?= $ikona ?></span> <?= $pav ?></div>
            <div class="mt-1 flex items-baseline justify-between gap-2">
                <span class="text-xl font-semibold tabular-nums text-slate-800"><?= (int)$b['kiek'] ?></span>
                <span class="text-sm text-slate-500 tabular-nums"><?= eur($b['suma']) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Populiariausios dalys -->
<section class="bg-white rounded-xl shadow-sm border border-slate-200 mb-8">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-slate-200">
        <h2 class="font-semibold">Daugiausiai užsakytos dalys</h2>
        <div class="flex rounded-lg border border-slate-300 text-sm overflow-hidden">
            <?php foreach (['kiekis' => 'Pagal kiekį', 'suma' => 'Pagal sumą'] as $r => $t): ?>
                <a href="?<?= htmlspecialchars(http_build_query(array_filter(['nuo' => $nuo, 'iki' => $iki, 'rik' => $r]))) ?>"
                   class="px-3 py-1.5 <?= $rik === $r ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' ?>"><?= $t ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if (!$dalys): ?>
        <p class="p-5 text-slate-500">Šiuo laikotarpiu užsakymų nėra.</p>
    <?php else: ?>
        <?php $max = max(array_column($dalys, $rik)); ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-5 py-3 font-medium w-8">#</th>
                        <th class="px-5 py-3 font-medium">Dalis</th>
                        <th class="px-5 py-3 font-medium">Tiekėjas</th>
                        <th class="px-5 py-3 font-medium text-right">Užsakymų</th>
                        <th class="px-5 py-3 font-medium text-right">Kiekis</th>
                        <th class="px-5 py-3 font-medium text-right">Suma</th>
                        <th class="px-5 py-3 font-medium w-40 hidden md:table-cell"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($dalys as $i => $d): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3 text-slate-400"><?= $i + 1 ?></td>
                            <td class="px-5 py-3">
                                <div class="font-medium"><?= htmlspecialchars($d['pavadinimas']) ?></div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($d['gamintojas'] ?? '—') ?> · <?= htmlspecialchars($d['modelis'] ?? '—') ?></div>
                            </td>
                            <td class="px-5 py-3"><?= htmlspecialchars($d['tiekejas']) ?></td>
                            <td class="px-5 py-3 text-right tabular-nums"><?= (int)$d['uzsakymu'] ?></td>
                            <td class="px-5 py-3 text-right tabular-nums whitespace-nowrap <?= $rik === 'kiekis' ? 'font-semibold' : '' ?>"><?= (int)$d['kiekis'] ?> vnt.</td>
                            <td class="px-5 py-3 text-right tabular-nums whitespace-nowrap <?= $rik === 'suma' ? 'font-semibold' : '' ?>"><?= eur($d['suma']) ?></td>
                            <td class="px-5 py-3 hidden md:table-cell">
                                <?= juosta((float)$d[$rik], (float)$max, $rik === 'suma' ? eur($d['suma']) : $d['kiekis'] . ' vnt.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="grid gap-8 lg:grid-cols-2 mb-8">
    <!-- Pagal tiekėją -->
    <section class="bg-white rounded-xl shadow-sm border border-slate-200">
        <h2 class="font-semibold px-5 py-4 border-b border-slate-200">Pagal tiekėją</h2>
        <?php if (!$tiekejai): ?>
            <p class="p-5 text-slate-500">Duomenų nėra.</p>
        <?php else: ?>
            <?php $max = max(array_column($tiekejai, 'suma')); ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($tiekejai as $t): ?>
                    <li class="px-5 py-3">
                        <div class="flex items-baseline justify-between gap-3 text-sm mb-1.5">
                            <span class="font-medium"><?= htmlspecialchars($t['vardas']) ?></span>
                            <span class="tabular-nums font-semibold"><?= eur($t['suma']) ?></span>
                        </div>
                        <?= juosta((float)$t['suma'], (float)$max, eur($t['suma'])) ?>
                        <div class="mt-1 text-xs text-slate-500"><?= (int)$t['uzsakymu'] ?> užsak. · <?= (int)$t['kiekis'] ?> vnt.</div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Pagal vadybininką -->
    <section class="bg-white rounded-xl shadow-sm border border-slate-200">
        <h2 class="font-semibold px-5 py-4 border-b border-slate-200">Pagal vadybininką</h2>
        <?php if (!$vadybininkai): ?>
            <p class="p-5 text-slate-500">Duomenų nėra.</p>
        <?php else: ?>
            <?php $max = max(array_column($vadybininkai, 'suma')); ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($vadybininkai as $v): ?>
                    <li class="px-5 py-3">
                        <div class="flex items-baseline justify-between gap-3 text-sm mb-1.5">
                            <span class="font-medium"><?= htmlspecialchars($v['vardas']) ?>
                                <?php if ($v['imone']): ?><span class="font-normal text-slate-500">· <?= htmlspecialchars($v['imone']) ?></span><?php endif; ?>
                            </span>
                            <span class="tabular-nums font-semibold"><?= eur($v['suma']) ?></span>
                        </div>
                        <?= juosta((float)$v['suma'], (float)$max, eur($v['suma'])) ?>
                        <div class="mt-1 text-xs text-slate-500"><?= (int)$v['uzsakymu'] ?> užsak.</div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<!-- Pagal mėnesį -->
<section class="bg-white rounded-xl shadow-sm border border-slate-200">
    <h2 class="font-semibold px-5 py-4 border-b border-slate-200">Pagal mėnesį</h2>
    <?php if (!$menesiai): ?>
        <p class="p-5 text-slate-500">Duomenų nėra.</p>
    <?php else: ?>
        <?php $max = max(array_column($menesiai, 'suma')); ?>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-5 py-3 font-medium">Mėnuo</th>
                    <th class="px-5 py-3 font-medium text-right">Užsakymų</th>
                    <th class="px-5 py-3 font-medium text-right">Suma</th>
                    <th class="px-5 py-3 w-1/3 hidden md:table-cell"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($menesiai as $m): ?>
                    <tr>
                        <td class="px-5 py-3"><?= htmlspecialchars($m['men']) ?></td>
                        <td class="px-5 py-3 text-right tabular-nums"><?= (int)$m['uzsakymu'] ?></td>
                        <td class="px-5 py-3 text-right tabular-nums font-medium whitespace-nowrap"><?= eur($m['suma']) ?></td>
                        <td class="px-5 py-3 hidden md:table-cell"><?= juosta((float)$m['suma'], (float)$max, eur($m['suma'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
