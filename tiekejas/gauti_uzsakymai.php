<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/funkcijos.php';

tikrintiRole('Tiekejas');

$manoId = (int)$_SESSION['naudotojo_id'];

// Leidžiami būsenos perėjimai: dabartinė => [nauja => mygtuko tekstas]
const PEREJIMAI = [
    'Pateiktas' => ['Priimtas' => 'Priimti', 'Atsauktas' => 'Atmesti'],
    'Priimtas'  => ['Ivykdytas' => 'Pažymėti įvykdytu'],
];

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

        // Likutis sumažintas jau pateikiant užsakymą – atmetus jį grąžinam į sandėlį
        if ($nauja === 'Atsauktas') {
            grazintiLikuti($pdo, $uzsakymoId);
        }

        $pdo->prepare('UPDATE uzsakymas SET busena = ? WHERE uzsakymo_id = ?')->execute([$nauja, $uzsakymoId]);
        $pdo->commit();

        $tekstai = ['Priimtas' => 'priimtas', 'Atsauktas' => 'atmestas', 'Ivykdytas' => 'pažymėtas įvykdytu'];
        grizti('sekme', "Užsakymas Nr. $uzsakymoId {$tekstai[$nauja]}.", "u$uzsakymoId");
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        grizti('klaida', $e->getMessage(), "u$uzsakymoId");
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
$sql = "SELECT u.uzsakymo_id, u.pateikimo_data, u.pristatymo_data, u.busena,
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
    <!-- Gautų užsakymų lentelė -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-5 py-3 font-medium">Nr.</th>
                    <th class="px-5 py-3 font-medium">Pateikta</th>
                    <th class="px-5 py-3 font-medium">Pristatymas</th>
                    <th class="px-5 py-3 font-medium">Užsakovas</th>
                    <th class="px-5 py-3 font-medium">Būsena</th>
                    <th class="px-5 py-3 font-medium text-right">Suma</th>
                    <th class="px-5 py-3 w-16"><span class="sr-only">Detalės</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($uzsakymai as $u): ?>
                    <?php
                    $id    = (int)$u['uzsakymo_id'];
                    $manos = array_values(array_filter($eilutes[$id] ?? [], fn($e) => (int)$e['tiekejo_id'] === $manoId));
                    [$busTekstas, $busKlase] = $busenos[$u['busena']];
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-semibold"><?= $id ?></td>
                        <td class="px-5 py-3 whitespace-nowrap text-slate-600"><?= date('Y-m-d H:i', strtotime($u['pateikimo_data'])) ?></td>
                        <td class="px-5 py-3 whitespace-nowrap text-slate-600"><?= htmlspecialchars($u['pristatymo_data'] ?? '—') ?></td>
                        <td class="px-5 py-3"><?= htmlspecialchars($u['imone'] ?: $u['vardas'] . ' ' . $u['pavarde']) ?></td>
                        <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $busKlase ?>"><?= $busTekstas ?></span></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap font-semibold tabular-nums"><?= eur(array_sum(array_column($manos, 'suma'))) ?></td>
                        <td class="px-5 py-3 text-right">
                            <button type="button" data-atidaryti="u<?= $id ?>"
                                    title="Užsakymo detalės" aria-label="Užsakymo Nr. <?= $id ?> detalės"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Popup langai su užsakymo detalėmis -->
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
        <dialog id="u<?= $id ?>" class="w-full max-w-4xl rounded-xl p-0 shadow-xl backdrop:bg-slate-900/50">
            <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-slate-200">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">Užsakymas Nr. <?= $id ?></h2>
                    <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-slate-500">
                        <span>Pateikta <?= date('Y-m-d H:i', strtotime($u['pateikimo_data'])) ?></span>
                        <?php if ($u['pristatymo_data']): ?><span>Pristatymas <?= htmlspecialchars($u['pristatymo_data']) ?></span><?php endif; ?>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $busKlase ?>"><?= $busTekstas ?></span>
                    </div>
                </div>
                <form method="dialog">
                    <button aria-label="Uždaryti" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </form>
            </div>

            <!-- Užsakovas -->
            <div class="grid gap-x-8 gap-y-1 px-6 py-4 text-sm sm:grid-cols-2 bg-slate-50 border-b border-slate-200">
                <div><span class="text-slate-500">Vadybininkas:</span> <?= htmlspecialchars($vadybininkas) ?></div>
                <div><span class="text-slate-500">Įmonė:</span> <?= htmlspecialchars($u['imone'] ?? '—') ?></div>
                <div><span class="text-slate-500">El. paštas:</span>
                    <a href="mailto:<?= htmlspecialchars($u['pastas']) ?>" class="text-sky-700 hover:underline"><?= htmlspecialchars($u['pastas']) ?></a></div>
                <div><span class="text-slate-500">Telefonas:</span> <?= htmlspecialchars($u['telefonas'] ?? '—') ?></div>
            </div>

            <div class="max-h-[50vh] overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-white text-left text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 font-medium">Dalis</th>
                            <th class="px-4 py-3 font-medium text-right">Vnt. kaina</th>
                            <th class="px-4 py-3 font-medium text-center">Užsakyta</th>
                            <th class="px-4 py-3 font-medium text-right">Liko sandėlyje</th>
                            <th class="px-6 py-3 font-medium text-right">Suma</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($manos as $e): ?>
                            <tr>
                                <td class="px-6 py-3">
                                    <div class="font-medium"><?= htmlspecialchars($e['pavadinimas']) ?></div>
                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($e['gamintojas'] ?? '—') ?> · <?= htmlspecialchars($e['modelis'] ?? '—') ?></div>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap"><?= eur((float)$e['suma'] / max(1, (int)$e['kiekis'])) ?></td>
                                <td class="px-4 py-3 text-center whitespace-nowrap"><?= (int)$e['kiekis'] ?> vnt.</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap text-slate-500"><?= (int)$e['sandelyje'] ?> vnt.</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap font-medium"><?= eur($e['suma']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="border-t border-slate-200">
                        <tr>
                            <td colspan="4" class="px-6 py-3 text-right text-slate-500">Iš viso</td>
                            <td class="px-6 py-3 text-right whitespace-nowrap font-semibold"><?= eur($manoSuma) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if ($misrus): ?>
                <p class="px-6 py-3 text-xs text-amber-700 bg-amber-50 border-t border-amber-100">
                    Šiame užsakyme yra ir kitų tiekėjų dalių, todėl rodomos tik jūsų dalys, o būsenos keisti negalima.
                </p>
            <?php elseif ($veiksmai): ?>
                <form method="post" class="flex justify-end gap-2 px-6 py-4 border-t border-slate-200 bg-slate-50">
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
            <?php else: ?>
                <p class="px-6 py-3 text-xs text-slate-500 border-t border-slate-200 bg-slate-50">Užsakymas užbaigtas, jo būsenos keisti nebegalima.</p>
            <?php endif; ?>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>

<script>
// Atidaryti popup paspaudus ikoną
document.addEventListener('click', e => {
    const btn = e.target.closest('[data-atidaryti]');
    if (btn) document.getElementById(btn.dataset.atidaryti)?.showModal();
});
// Uždaryti paspaudus šalia lango
document.querySelectorAll('dialog').forEach(d => {
    d.addEventListener('click', e => { if (e.target === d) d.close(); });
});
// Po būsenos pakeitimo (#u12) atidarom tą užsakymą
if (location.hash) {
    const el = document.querySelector(location.hash);
    if (el && el.tagName === 'DIALOG') el.showModal();
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
