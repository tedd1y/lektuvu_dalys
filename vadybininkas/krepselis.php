<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/funkcijos.php';

tikrintiRole('Vadybininkas');

// --- Užsakymo pateikimas ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Iš naršyklės ateina tik [{id, kiekis}, ...] – kainos ir likučiai imami iš DB
    $duomenys = json_decode($_POST['krepselis'] ?? '', true);
    if (!is_array($duomenys) || !$duomenys) {
        grizti('klaida', 'Krepšelis tuščias.');
    }

    $kiekiai = [];
    foreach ($duomenys as $eil) {
        $id = (int)($eil['id'] ?? 0);
        $k  = (int)($eil['kiekis'] ?? 0);
        if ($id <= 0 || $k <= 0) {
            grizti('klaida', 'Neteisingi krepšelio duomenys.');
        }
        $kiekiai[$id] = ($kiekiai[$id] ?? 0) + $k;
    }

    try {
        $pdo->beginTransaction();

        $vietos = implode(',', array_fill(0, count($kiekiai), '?'));
        $stmt = $pdo->prepare("SELECT prekes_id, tiekejo_id, pavadinimas, kaina, kiekis, pristatymo_laikas FROM preke WHERE prekes_id IN ($vietos) FOR UPDATE");
        $stmt->execute(array_keys($kiekiai));
        $prekes = array_column($stmt->fetchAll(), null, 'prekes_id');

        // Kiekvienam tiekėjui – atskiras užsakymas (būseną keičia tas tiekėjas)
        $pagalTiekeja = [];
        foreach ($kiekiai as $id => $k) {
            if (!isset($prekes[$id])) {
                throw new RuntimeException('Viena iš krepšelio dalių nebeegzistuoja. Pašalinkite ją ir bandykite dar kartą.');
            }
            if ($k > (int)$prekes[$id]['kiekis']) {
                throw new RuntimeException('„' . $prekes[$id]['pavadinimas'] . '“ sandėlyje liko tik ' . (int)$prekes[$id]['kiekis'] . ' vnt.');
            }
            $pagalTiekeja[$prekes[$id]['tiekejo_id']][$id] = $k;
        }

        $naujasUzsakymas = $pdo->prepare("INSERT INTO uzsakymas (vadybininko_id, suma, pristatymo_data, busena) VALUES (?, ?, ?, 'Pateiktas')");
        $naujaEilute     = $pdo->prepare('INSERT INTO uzsakymo_preke (uzsakymo_id, prekes_id, kiekis, suma) VALUES (?, ?, ?, ?)');
        $mazintiLikuti   = $pdo->prepare('UPDATE preke SET kiekis = kiekis - ? WHERE prekes_id = ?');
        $sukurti = [];

        foreach ($pagalTiekeja as $eilutes) {
            $suma = 0;
            foreach ($eilutes as $id => $k) {
                $suma += round($k * (float)$prekes[$id]['kaina'], 2);
            }
            // Užsakymas pristatomas, kai atkeliauja ilgiausiai keliaujanti dalis:
            // max(pristatymo laikų) darbo dienomis nuo šiandien
            $laikai = array_filter(array_map(fn($id) => $prekes[$id]['pristatymo_laikas'], array_keys($eilutes)), fn($l) => $l !== null);
            $pristatymoData = $laikai ? poDarboDienu((int)max($laikai))->format('Y-m-d') : null;

            $naujasUzsakymas->execute([$_SESSION['naudotojo_id'], $suma, $pristatymoData]);
            $uzsakymoId = (int)$pdo->lastInsertId();
            $sukurti[] = $uzsakymoId;

            foreach ($eilutes as $id => $k) {
                $naujaEilute->execute([$uzsakymoId, $id, $k, round($k * (float)$prekes[$id]['kaina'], 2)]);
                $mazintiLikuti->execute([$k, $id]);   // dalys rezervuojamos iškart pateikus
            }
        }

        $pdo->commit();
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        grizti('klaida', $e->getMessage());
    }

    $nr = implode(', ', $sukurti);
    $_SESSION['isvalyti_krepseli'] = true;
    grizti('sekme', count($sukurti) === 1
        ? "Užsakymas Nr. $nr pateiktas sėkmingai."
        : 'Pateikti ' . count($sukurti) . " užsakymai (po vieną kiekvienam tiekėjui): Nr. $nr.");
}

$isvalyti = !empty($_SESSION['isvalyti_krepseli']);
unset($_SESSION['isvalyti_krepseli']);

$puslapioPavadinimas = 'Krepšelis';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="text-2xl font-semibold mb-6">Krepšelis</h1>

<div id="tuscias" class="hidden rounded-xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
    Krepšelis tuščias.
    <a href="daliu_paieska.php" class="text-sky-600 hover:underline">Ieškoti dalių →</a>
</div>

<div id="turinys" class="hidden">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-5 py-3 font-medium">Dalis</th>
                    <th class="px-5 py-3 font-medium">Tiekėjas</th>
                    <th class="px-5 py-3 font-medium text-right">Vnt. kaina</th>
                    <th class="px-5 py-3 font-medium text-center">Kiekis</th>
                    <th class="px-5 py-3 font-medium text-right">Suma</th>
                    <th class="px-5 py-3 w-12"><span class="sr-only">Pašalinti</span></th>
                </tr>
            </thead>
            <tbody id="eilutes" class="divide-y divide-slate-100"></tbody>
            <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                <tr>
                    <td colspan="3" class="px-5 py-3 font-semibold">Iš viso</td>
                    <td id="viso-vnt" class="px-5 py-3 text-center font-semibold tabular-nums"></td>
                    <td id="viso-suma" class="px-5 py-3 text-right font-semibold tabular-nums whitespace-nowrap"></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="mt-4 flex justify-end">
        <div class="flex gap-2">
            <button type="button" id="isvalyti" class="rounded-lg px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-200">
                Išvalyti krepšelį
            </button>
            <form method="post" id="uzsakymo-forma">
                <input type="hidden" name="krepselis" id="krepselio-duomenys">
                <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-medium text-white hover:bg-sky-700">
                    Pateikti užsakymą
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    <?php if ($isvalyti): ?>
    Krepselis.isvalyti();
    <?php endif; ?>

    const eur = n => n.toLocaleString('lt-LT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function piesti() {
        const prekes = Krepselis.gauti();
        document.getElementById('tuscias').classList.toggle('hidden', prekes.length > 0);
        document.getElementById('turinys').classList.toggle('hidden', prekes.length === 0);

        document.getElementById('eilutes').innerHTML = prekes.map(p => `
            <tr class="hover:bg-slate-50">
                <td class="px-5 py-3">
                    <div class="font-medium text-slate-800">${esc(p.pavadinimas)}</div>
                    <div class="text-xs text-slate-500">${esc(p.gamintojas || '—')} · ${esc(p.modelis || '—')}</div>
                </td>
                <td class="px-5 py-3 text-slate-600">${esc(p.tiekejas)}</td>
                <td class="px-5 py-3 text-right whitespace-nowrap tabular-nums">${eur(p.kaina)}</td>
                <td class="px-5 py-3">
                    <div class="mx-auto flex w-fit items-center rounded-lg border border-slate-300">
                        <button type="button" data-minus="${p.id}" aria-label="Mažinti" class="px-3 py-1 text-lg leading-none hover:bg-slate-100 disabled:text-slate-300 disabled:hover:bg-transparent" ${p.kiekis <= 1 ? 'disabled' : ''}>−</button>
                        <span class="w-10 text-center tabular-nums">${p.kiekis}</span>
                        <button type="button" data-plius="${p.id}" aria-label="Didinti" class="px-3 py-1 text-lg leading-none hover:bg-slate-100 disabled:text-slate-300 disabled:hover:bg-transparent" ${p.kiekis >= p.sandelyje ? 'disabled' : ''}>+</button>
                    </div>
                </td>
                <td class="px-5 py-3 text-right whitespace-nowrap tabular-nums font-medium">${eur(p.kaina * p.kiekis)}</td>
                <td class="px-5 py-3 text-right">
                    <button type="button" data-salinti="${p.id}" aria-label="Pašalinti" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-600 hover:border-red-300 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </td>
            </tr>`).join('');

        document.getElementById('viso-vnt').textContent = prekes.reduce((s, p) => s + p.kiekis, 0) + ' vnt.';
        document.getElementById('viso-suma').textContent = eur(prekes.reduce((s, p) => s + p.kaina * p.kiekis, 0));
    }

    document.getElementById('eilutes').addEventListener('click', e => {
        const b = e.target.closest('button');
        if (!b) return;
        const p = id => Krepselis.gauti().find(x => x.id === parseInt(id, 10));
        if (b.dataset.plius)   Krepselis.nustatytiKieki(parseInt(b.dataset.plius, 10), p(b.dataset.plius).kiekis + 1);
        if (b.dataset.minus)   Krepselis.nustatytiKieki(parseInt(b.dataset.minus, 10), p(b.dataset.minus).kiekis - 1);
        if (b.dataset.salinti) Krepselis.pasalinti(parseInt(b.dataset.salinti, 10));
    });

    document.getElementById('isvalyti').addEventListener('click', () => {
        if (confirm('Išvalyti visą krepšelį?')) Krepselis.isvalyti();
    });

    document.getElementById('uzsakymo-forma').addEventListener('submit', e => {
        const prekes = Krepselis.gauti();
        if (!prekes.length) { e.preventDefault(); return; }
        document.getElementById('krepselio-duomenys').value =
            JSON.stringify(prekes.map(p => ({ id: p.id, kiekis: p.kiekis })));
    });

    document.addEventListener('krepselis:pasikeite', piesti);
    piesti();
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
