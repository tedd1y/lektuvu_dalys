<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';

checkRole('Tiekejas');

$manoId = (int)$_SESSION['naudotojo_id'];

// Leidžiami būsenos perėjimai: dabartinė => [nauja => mygtuko tekstas]
const PEREJIMAI = [
    'Pateiktas' => ['Priimtas' => 'Priimti', 'Atsauktas' => 'Atmesti'],
    'Priimtas'  => ['Ivykdytas' => 'Pažymėti įvykdytu'],
];

function grizti(string $tipas, string $tekstas, int $atidaryti = 0): never {
    $_SESSION['zinute'] = ['tipas' => $tipas, 'tekstas' => $tekstas];
    $uzklausa = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: gauti_uzsakymai.php' . ($uzklausa ? "?$uzklausa" : '') . ($atidaryti ? "#u$atidaryti" : ''));
    exit;
}

// --- «extend» Pakeisti užsakymo būseną ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uzsakymoId = (int)($_POST['uzsakymo_id'] ?? 0);
    $nauja      = $_POST['busena'] ?? '';

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT busena FROM uzsakymas WHERE uzsakymo_id = ? FOR UPDATE');
        $stmt->execute([$uzsakymoId]);
        $dabartine = $stmt->fetchColumn();

        // Užsakymo eilutės kartu su tiekėju ir likučiu
        $stmt = $pdo->prepare(
            'SELECT up.prekes_id, up.kiekis, p.pavadinimas, p.tiekejo_id, p.kiekis AS sandelyje
             FROM uzsakymo_preke up JOIN preke p ON p.prekes_id = up.prekes_id
             WHERE up.uzsakymo_id = ? FOR UPDATE'
        );
        $stmt->execute([$uzsakymoId]);
        $eilutes = $stmt->fetchAll();

        $manos = array_filter($eilutes, fn($e) => (int)$e['tiekejo_id'] === $manoId);
        if ($dabartine === false || !$manos) {
            throw new RuntimeException('Užsakymas nerastas.');
        }
        if (count($manos) !== count($eilutes)) {
            throw new RuntimeException('Šiame užsakyme yra ir kitų tiekėjų dalių, todėl jo būsenos pakeisti negalite.');
        }
        if (!isset(PEREJIMAI[$dabartine][$nauja])) {
            throw new RuntimeException('Šio užsakymo būsenos pakeisti į pasirinktą negalima.');
        }

        // Priimant – patikrinam ir sumažinam likučius sandėlyje
        if ($nauja === 'Priimtas') {
            $mazinti = $pdo->prepare('UPDATE preke SET kiekis = kiekis - ? WHERE prekes_id = ?');
            foreach ($eilutes as $e) {
                if ((int)$e['kiekis'] > (int)$e['sandelyje']) {
                    throw new RuntimeException('Negalite priimti: „' . $e['pavadinimas'] . '“ sandėlyje yra tik '
                        . (int)$e['sandelyje'] . ' vnt., o užsakyta ' . (int)$e['kiekis'] . ' vnt. Papildykite likutį arba atmeskite užsakymą.');
                }
                $mazinti->execute([(int)$e['kiekis'], $e['prekes_id']]);
            }
        }

        $pdo->prepare('UPDATE uzsakymas SET busena = ? WHERE uzsakymo_id = ?')->execute([$nauja, $uzsakymoId]);
        $pdo->commit();

        $tekstai = ['Priimtas' => 'priimtas', 'Atsauktas' => 'atmestas', 'Ivykdytas' => 'pažymėtas įvykdytu'];
        grizti('sekme', "Užsakymas Nr. $uzsakymoId {$tekstai[$nauja]}.", $uzsakymoId);
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        grizti('klaida', $e->getMessage(), $uzsakymoId);
    }
}

// --- Peržiūrėti gautus užsakymus ---
$busenos = [
    'Pateiktas' => ['Naujas',    'bg-sky-100 text-sky-800'],
    'Priimtas'  => ['Priimtas',  'bg-amber-100 text-amber-800'],
    'Ivykdytas' => ['Įvykdytas', 'bg-green-100 text-green-800'],
    'Atsauktas' => ['Atšauktas', 'bg-slate-200 text-slate-600'],
];
$filtras = array_key_exists($_GET['busena'] ?? '', $busenos) ? $_GET['busena'] : '';

// Užsakymai, kuriuose yra mano dalių
$sql = "SELECT u.uzsakymo_id, u.pateikimo_data, u.busena,
               v.vardas, v.pavarde, v.pavadinimas AS imone, v.pastas, v.telefonas
        FROM uzsakymas u
        JOIN naudotojas v ON v.naudotojo_id = u.vadybininko_id
        WHERE EXISTS (SELECT 1 FROM uzsakymo_preke up JOIN preke p ON p.prekes_id = up.prekes_id
                      WHERE up.uzsakymo_id = u.uzsakymo_id AND p.tiekejo_id = ?)";
$params = [$manoId];
if ($filtras) {
    $sql .= ' AND u.busena = ?';
    $params[] = $filtras;
}
$sql .= " ORDER BY FIELD(u.busena, 'Pateiktas', 'Priimtas', 'Ivykdytas', 'Atsauktas'), u.pateikimo_data DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$uzsakymai = $stmt->fetchAll();

// Eilutės (visos, kad matytume, ar užsakyme yra kitų tiekėjų dalių)
$eilutes = [];
if ($uzsakymai) {
    $ids = array_column($uzsakymai, 'uzsakymo_id');
    $vietos = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT up.uzsakymo_id, up.kiekis, up.suma, p.pavadinimas, p.gamintojas, p.modelis, p.tiekejo_id, p.kiekis AS sandelyje
         FROM uzsakymo_preke up JOIN preke p ON p.prekes_id = up.prekes_id
         WHERE up.uzsakymo_id IN ($vietos) ORDER BY up.eilutes_id"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $e) {
        $eilutes[$e['uzsakymo_id']][] = $e;
    }
}

// Skaičiukai filtrams
$stmt = $pdo->prepare(
    'SELECT u.busena, COUNT(DISTINCT u.uzsakymo_id)
     FROM uzsakymas u JOIN uzsakymo_preke up ON up.uzsakymo_id = u.uzsakymo_id JOIN preke p ON p.prekes_id = up.prekes_id
     WHERE p.tiekejo_id = ? GROUP BY u.busena'
);
$stmt->execute([$manoId]);
$kiekPagalBusena = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

function eur($s): string {
    return number_format((float)$s, 2, ',', ' ') . ' €';
}

$puslapioPavadinimas = 'Gauti užsakymai';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="text-2xl font-semibold mb-6">Gauti užsakymai</h1>

<nav class="mb-6 flex flex-wrap gap-2 text-sm">
    <a href="gauti_uzsakymai.php"
       class="rounded-full px-4 py-1.5 border <?= $filtras === '' ? 'bg-slate-800 border-slate-800 text-white' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
        Visi <span class="opacity-60"><?= array_sum($kiekPagalBusena) ?></span>
    </a>
    <?php foreach ($busenos as $raktas => [$tekstas]): ?>
        <a href="?busena=<?= $raktas ?>"
           class="rounded-full px-4 py-1.5 border <?= $filtras === $raktas ? 'bg-slate-800 border-slate-800 text-white' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
            <?= $tekstas ?> <span class="opacity-60"><?= (int)($kiekPagalBusena[$raktas] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$uzsakymai): ?>
    <div class="rounded-xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
        <?= $filtras ? 'Užsakymų su šia būsena nėra.' : 'Kol kas negavote nė vieno užsakymo.' ?>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($uzsakymai as $u): ?>
            <?php
            $id     = (int)$u['uzsakymo_id'];
            $visos  = $eilutes[$id] ?? [];
            $manos  = array_values(array_filter($visos, fn($e) => (int)$e['tiekejo_id'] === $manoId));
            $misrus = count($manos) !== count($visos);
            $manoSuma = array_sum(array_column($manos, 'suma'));
            $veiksmai = $misrus ? [] : (PEREJIMAI[$u['busena']] ?? []);
            [$busTekstas, $busKlase] = $busenos[$u['busena']];
            $vadybininkas = $u['vardas'] . ' ' . $u['pavarde'];
            ?>
            <details id="u<?= $id ?>" class="group bg-white rounded-xl shadow-sm border border-slate-200 open:border-sky-300">
                <summary class="flex flex-wrap items-center gap-x-6 gap-y-2 px-5 py-4 cursor-pointer list-none select-none">
                    <span class="font-semibold w-24">Nr. <?= $id ?></span>
                    <span class="text-sm text-slate-500 w-36"><?= date('Y-m-d H:i', strtotime($u['pateikimo_data'])) ?></span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $busKlase ?>"><?= $busTekstas ?></span>
                    <span class="text-sm text-slate-600"><?= htmlspecialchars($u['imone'] ?: $vadybininkas) ?></span>
                    <span class="ml-auto font-semibold"><?= eur($manoSuma) ?></span>
                    <span class="text-slate-400 transition group-open:rotate-180">▾</span>
                </summary>

                <div class="border-t border-slate-200">
                    <!-- Užsakovas -->
                    <div class="grid gap-x-8 gap-y-1 px-5 py-4 text-sm sm:grid-cols-2 bg-slate-50">
                        <div><span class="text-slate-500">Vadybininkas:</span> <?= htmlspecialchars($vadybininkas) ?></div>
                        <div><span class="text-slate-500">Įmonė:</span> <?= htmlspecialchars($u['imone'] ?? '—') ?></div>
                        <div><span class="text-slate-500">El. paštas:</span>
                            <a href="mailto:<?= htmlspecialchars($u['pastas']) ?>" class="text-sky-700 hover:underline"><?= htmlspecialchars($u['pastas']) ?></a></div>
                        <div><span class="text-slate-500">Telefonas:</span> <?= htmlspecialchars($u['telefonas'] ?? '—') ?></div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-slate-600 border-y border-slate-200">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Dalis</th>
                                    <th class="px-5 py-3 font-medium text-right">Vnt. kaina</th>
                                    <th class="px-5 py-3 font-medium text-right">Užsakyta</th>
                                    <th class="px-5 py-3 font-medium text-right">Sandėlyje</th>
                                    <th class="px-5 py-3 font-medium text-right">Suma</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($manos as $e): ?>
                                    <?php $truksta = $u['busena'] === 'Pateiktas' && (int)$e['kiekis'] > (int)$e['sandelyje']; ?>
                                    <tr>
                                        <td class="px-5 py-3">
                                            <div class="font-medium"><?= htmlspecialchars($e['pavadinimas']) ?></div>
                                            <div class="text-xs text-slate-500"><?= htmlspecialchars($e['gamintojas'] ?? '—') ?> · <?= htmlspecialchars($e['modelis'] ?? '—') ?></div>
                                        </td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap"><?= eur((float)$e['suma'] / max(1, (int)$e['kiekis'])) ?></td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap font-medium"><?= (int)$e['kiekis'] ?> vnt.</td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap <?= $truksta ? 'text-red-600 font-medium' : 'text-slate-500' ?>">
                                            <?= (int)$e['sandelyje'] ?> vnt.<?= $truksta ? ' ⚠' : '' ?>
                                        </td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap font-medium"><?= eur($e['suma']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($misrus): ?>
                        <p class="px-5 py-3 text-xs text-amber-700 bg-amber-50 border-t border-amber-100 rounded-b-xl">
                            Šiame užsakyme yra ir kitų tiekėjų dalių, todėl rodomos tik jūsų dalys, o būsenos keisti negalima.
                        </p>
                    <?php elseif ($veiksmai): ?>
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-t border-slate-100 bg-slate-50 rounded-b-xl">
                            <p class="text-xs text-slate-500">
                                <?= $u['busena'] === 'Pateiktas'
                                    ? 'Priėmus užsakymą, dalių likutis sandėlyje sumažės. Vadybininkas jo nebegalės keisti.'
                                    : 'Kai dalys pristatytos, pažymėkite užsakymą įvykdytu.' ?>
                            </p>
                            <form method="post" class="flex gap-2">
                                <input type="hidden" name="uzsakymo_id" value="<?= $id ?>">
                                <?php foreach ($veiksmai as $nauja => $tekstas): ?>
                                    <button name="busena" value="<?= $nauja ?>"
                                            <?= $nauja === 'Atsauktas' ? 'onclick="return confirm(\'Ar tikrai norite atmesti užsakymą Nr. ' . $id . '?\')"' : '' ?>
                                            class="rounded-lg px-4 py-2 text-sm font-medium <?= $nauja === 'Atsauktas'
                                                ? 'border border-red-300 bg-white text-red-600 hover:bg-red-50'
                                                : 'bg-sky-600 text-white hover:bg-sky-700' ?>">
                                        <?= $tekstas ?>
                                    </button>
                                <?php endforeach; ?>
                            </form>
                        </div>
                    <?php else: ?>
                        <p class="px-5 py-3 text-xs text-slate-500 border-t border-slate-100">Užsakymas užbaigtas, jo būsenos keisti nebegalima.</p>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
if (location.hash) {
    const el = document.querySelector(location.hash);
    if (el && el.tagName === 'DETAILS') el.open = true;
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
