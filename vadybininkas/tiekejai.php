<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/funkcijos.php';

checkRole('Vadybininkas');

$q = trim($_GET['q'] ?? '');

// Tiekėjai su jų prekių skaičiumi (jei įvesta – filtruojam pagal pavadinimą)
$sql = "SELECT n.naudotojo_id, n.vardas, n.pavarde, n.pavadinimas, n.pastas, n.telefonas,
               COUNT(p.prekes_id) AS prekiu_sk
        FROM naudotojas n
        LEFT JOIN preke p ON p.tiekejo_id = n.naudotojo_id
        WHERE n.tipas = 'Tiekejas'";
$params = [];
if ($q !== '') {
    // Įmonės pavadinimas, o jei jo nėra – vardas ir pavardė
    $sql .= " AND (n.pavadinimas LIKE ? OR CONCAT(n.vardas, ' ', n.pavarde) LIKE ?)";
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $params = [$like, $like];
}
$sql .= ' GROUP BY n.naudotojo_id ORDER BY COALESCE(n.pavadinimas, n.pavarde)';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tiekejai = $stmt->fetchAll();

// «extend» Peržiūrėti tiekėjo siūlomas dalis – visų tiekėjų dalys viena užklausa,
// kad popup atsidarytų iškart, be puslapio perkrovimo
$dalysPagalTiekeja = [];
foreach ($pdo->query(
    "SELECT p.prekes_id, p.tiekejo_id, p.pavadinimas, p.gamintojas, p.modelis, p.kaina, p.kiekis, p.pristatymo_laikas
     FROM preke p JOIN naudotojas n ON n.naudotojo_id = p.tiekejo_id
     WHERE n.tipas = 'Tiekejas'
     ORDER BY p.pavadinimas"
) as $p) {
    $dalysPagalTiekeja[$p['tiekejo_id']][] = $p;
}

// ?id=.. (pvz. nuoroda iš dalių paieškos) – to tiekėjo popup atidaromas automatiškai
$atidarytiId = (int)($_GET['id'] ?? 0);
$ids = array_map('intval', array_column($tiekejai, 'naudotojo_id'));
$nerastas = $atidarytiId && !in_array($atidarytiId, $ids, true);

function tiekejoPavadinimas(array $t): string {
    return $t['pavadinimas'] ?: $t['vardas'] . ' ' . $t['pavarde'];
}

$puslapioPavadinimas = 'Tiekėjai';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="text-2xl font-semibold mb-6">Tiekėjai</h1>

<form method="get" class="mb-6 flex gap-2 max-w-xl">
    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ieškoti tiekėjo pagal pavadinimą…"
           class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500">
    <button class="rounded-lg bg-sky-600 px-5 py-2 text-sm font-medium text-white hover:bg-sky-700">Ieškoti</button>
    <?php if ($q !== ''): ?>
        <a href="tiekejai.php" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-200">Rodyti visus</a>
    <?php endif; ?>
</form>

<?php if ($nerastas): ?>
    <div class="mb-6 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">Toks tiekėjas nerastas.</div>
<?php endif; ?>

<?php if (!$tiekejai): ?>
    <div class="rounded-xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
        <?= $q !== '' ? 'Pagal „' . htmlspecialchars($q) . '“ tiekėjų nerasta.' : 'Tiekėjų kol kas nėra.' ?>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-5 py-3 font-medium">Tiekėjas</th>
                    <th class="px-5 py-3 font-medium">El. paštas</th>
                    <th class="px-5 py-3 font-medium">Telefonas</th>
                    <th class="px-5 py-3 font-medium text-center">Siūlomų dalių</th>
                    <th class="px-5 py-3 w-16"><span class="sr-only">Dalys</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($tiekejai as $t): ?>
                    <?php $id = (int)$t['naudotojo_id']; $sk = (int)$t['prekiu_sk']; ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <div class="font-medium text-slate-800"><?= htmlspecialchars(tiekejoPavadinimas($t)) ?></div>
                            <?php if ($t['pavadinimas']): ?>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($t['vardas'] . ' ' . $t['pavarde']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3">
                            <a href="mailto:<?= htmlspecialchars($t['pastas']) ?>" class="text-sky-700 hover:underline"><?= htmlspecialchars($t['pastas']) ?></a>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap text-slate-600"><?= htmlspecialchars($t['telefonas'] ?? '—') ?></td>
                        <td class="px-5 py-3 text-center tabular-nums"><?= $sk ?></td>
                        <td class="px-5 py-3 text-right">
                            <button type="button" data-atidaryti="tiekejas-<?= $id ?>"
                                    title="Peržiūrėti siūlomas dalis" aria-label="Peržiūrėti <?= htmlspecialchars(tiekejoPavadinimas($t)) ?> siūlomas dalis"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Popup langai su tiekėjų dalimis -->
    <?php foreach ($tiekejai as $t): ?>
        <?php $id = (int)$t['naudotojo_id']; $prekes = $dalysPagalTiekeja[$id] ?? []; ?>
        <dialog id="tiekejas-<?= $id ?>" <?= $id === $atidarytiId ? 'data-atidaryti-iskart' : '' ?>
                class="w-full max-w-5xl rounded-xl p-0 shadow-xl backdrop:bg-slate-900/50">
            <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-slate-200">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800"><?= htmlspecialchars(tiekejoPavadinimas($t)) ?></h2>
                </div>
                <form method="dialog">
                    <button aria-label="Uždaryti" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </form>
            </div>

            <?php if (!$prekes): ?>
                <p class="px-6 py-10 text-center text-slate-500">Šis tiekėjas dalių kol kas nesiūlo.</p>
            <?php else: ?>
                <div class="max-h-[70vh] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-left text-slate-600">
                            <tr>
                                <th class="px-6 py-3 font-medium">Pavadinimas</th>
                                <th class="px-4 py-3 font-medium">Gamintojas</th>
                                <th class="px-4 py-3 font-medium">Modelis</th>
                                <th class="px-4 py-3 font-medium text-right">Pristatymas</th>
                                <th class="px-4 py-3 font-medium text-right">Kaina</th>
                                <th class="px-4 py-3 font-medium text-right">Sandėlyje</th>
                                <th class="px-6 py-3"><span class="sr-only">Veiksmai</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($prekes as $p): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-3 font-medium"><?= htmlspecialchars($p['pavadinimas']) ?></td>
                                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($p['gamintojas'] ?? '—') ?></td>
                                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($p['modelis'] ?? '—') ?></td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap text-slate-600">
                                        <?= pristatymoLaikas($p['pristatymo_laikas']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap"><?= number_format((float)$p['kaina'], 2, ',', ' ') ?> €</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap"><?= likutis($p) ?></td>
                                    <td class="px-6 py-3 text-right"><?= krepselioMygtukas($p, tiekejoPavadinimas($t)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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

// Uždaryti paspaudus šalia lango (ant tamsaus fono)
document.querySelectorAll('dialog').forEach(d => {
    d.addEventListener('click', e => { if (e.target === d) d.close(); });
});

// ?id=.. – atidarom iškart
document.querySelector('dialog[data-atidaryti-iskart]')?.showModal();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
