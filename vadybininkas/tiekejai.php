<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/funkcijos.php';

checkRole('Vadybininkas');

// Visi tiekėjai su jų prekių skaičiumi
$tiekejai = $pdo->query(
    "SELECT n.naudotojo_id, n.vardas, n.pavarde, n.pavadinimas, n.pastas, n.telefonas,
            COUNT(p.prekes_id) AS prekiu_sk
     FROM naudotojas n
     LEFT JOIN preke p ON p.tiekejo_id = n.naudotojo_id
     WHERE n.tipas = 'Tiekejas'
     GROUP BY n.naudotojo_id
     ORDER BY COALESCE(n.pavadinimas, n.pavarde)"
)->fetchAll();

// «extend»: pasirinktas tiekėjas ir jo siūlomos dalys
$pasirinktoId = (int)($_GET['id'] ?? 0);
$pasirinktas  = null;
$prekes       = [];

foreach ($tiekejai as $t) {
    if ((int)$t['naudotojo_id'] === $pasirinktoId) {
        $pasirinktas = $t;
        break;
    }
}

if ($pasirinktas) {
    $stmt = $pdo->prepare(
        'SELECT prekes_id, pavadinimas, gamintojas, modelis, kaina, kiekis
         FROM preke WHERE tiekejo_id = ? ORDER BY pavadinimas'
    );
    $stmt->execute([$pasirinktoId]);
    $prekes = $stmt->fetchAll();
}

function tiekejoPavadinimas(array $t): string {
    return $t['pavadinimas'] ?: $t['vardas'] . ' ' . $t['pavarde'];
}

$puslapioPavadinimas = 'Tiekėjai';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="text-2xl font-semibold mb-6">Tiekėjai</h1>

<?php if ($pasirinktoId && !$pasirinktas): ?>
    <div class="mb-6 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
        Toks tiekėjas nerastas.
    </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-5">

    <!-- Tiekėjų sąrašas -->
    <section class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <?php if (!$tiekejai): ?>
                <p class="p-5 text-slate-500">Tiekėjų kol kas nėra.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($tiekejai as $t): ?>
                        <?php $aktyvus = (int)$t['naudotojo_id'] === $pasirinktoId; ?>
                        <li>
                            <a href="?id=<?= (int)$t['naudotojo_id'] ?>"
                               class="block px-5 py-4 <?= $aktyvus ? 'bg-sky-50 border-l-4 border-sky-500' : 'hover:bg-slate-50 border-l-4 border-transparent' ?>">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="font-medium <?= $aktyvus ? 'text-sky-800' : 'text-slate-800' ?>">
                                        <?= htmlspecialchars(tiekejoPavadinimas($t)) ?>
                                    </span>
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                                        <?= (int)$t['prekiu_sk'] ?> dal.
                                    </span>
                                </div>
                                <div class="mt-1 text-sm text-slate-500 space-y-0.5">
                                    <div>✉ <?= htmlspecialchars($t['pastas']) ?></div>
                                    <div>☎ <?= htmlspecialchars($t['telefonas'] ?? '—') ?></div>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <!-- Pasirinkto tiekėjo siūlomos dalys -->
    <section class="lg:col-span-3">
        <?php if (!$pasirinktas): ?>
            <div class="rounded-xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
                Pasirinkite tiekėją iš sąrašo, kad pamatytumėte jo siūlomas dalis.
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200">
                <div class="px-5 py-4 border-b border-slate-200">
                    <h2 class="text-lg font-semibold"><?= htmlspecialchars(tiekejoPavadinimas($pasirinktas)) ?></h2>
                    <p class="text-sm text-slate-500">Siūlomos dalys</p>
                </div>

                <?php if (!$prekes): ?>
                    <p class="p-5 text-slate-500">Šis tiekėjas dalių kol kas nesiūlo.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-slate-600">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Pavadinimas</th>
                                    <th class="px-5 py-3 font-medium">Gamintojas</th>
                                    <th class="px-5 py-3 font-medium">Modelis</th>
                                    <th class="px-5 py-3 font-medium text-right">Kaina</th>
                                    <th class="px-5 py-3 font-medium text-right">Sandėlyje</th>
                                    <th class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($prekes as $p): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-5 py-3 font-medium"><?= htmlspecialchars($p['pavadinimas']) ?></td>
                                        <td class="px-5 py-3"><?= htmlspecialchars($p['gamintojas'] ?? '—') ?></td>
                                        <td class="px-5 py-3"><?= htmlspecialchars($p['modelis'] ?? '—') ?></td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap"><?= number_format((float)$p['kaina'], 2, ',', ' ') ?> €</td>
                                        <td class="px-5 py-3 text-right">
                                            <?= likutis($p) ?>
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <?= krepselioMygtukas($p, tiekejoPavadinimas($pasirinktas), true) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
