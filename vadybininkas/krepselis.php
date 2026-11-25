<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';

checkRole('Vadybininkas');

function klaida(string $tekstas): never {
    $_SESSION['zinute'] = ['tipas' => 'klaida', 'tekstas' => $tekstas];
    header('Location: krepselis.php');
    exit;
}

// --- Užsakymo pateikimas ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Iš naršyklės ateina tik [{id, kiekis}, ...] – kainos ir likučiai imami iš DB
    $duomenys = json_decode($_POST['krepselis'] ?? '', true);
    if (!is_array($duomenys) || !$duomenys) {
        klaida('Krepšelis tuščias.');
    }

    $kiekiai = [];
    foreach ($duomenys as $eil) {
        $id = (int)($eil['id'] ?? 0);
        $k  = (int)($eil['kiekis'] ?? 0);
        if ($id <= 0 || $k <= 0) {
            klaida('Neteisingi krepšelio duomenys.');
        }
        $kiekiai[$id] = ($kiekiai[$id] ?? 0) + $k;
    }

    try {
        $pdo->beginTransaction();

        $vietos = implode(',', array_fill(0, count($kiekiai), '?'));
        $stmt = $pdo->prepare("SELECT prekes_id, tiekejo_id, pavadinimas, kaina, kiekis FROM preke WHERE prekes_id IN ($vietos) FOR UPDATE");
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

        $naujasUzsakymas = $pdo->prepare("INSERT INTO uzsakymas (vadybininko_id, suma, busena) VALUES (?, ?, 'Pateiktas')");
        $naujaEilute     = $pdo->prepare('INSERT INTO uzsakymo_preke (uzsakymo_id, prekes_id, kiekis, suma) VALUES (?, ?, ?, ?)');
        $sukurti = [];

        foreach ($pagalTiekeja as $eilutes) {
            $suma = 0;
            foreach ($eilutes as $id => $k) {
                $suma += round($k * (float)$prekes[$id]['kaina'], 2);
            }
            $naujasUzsakymas->execute([$_SESSION['naudotojo_id'], $suma]);
            $uzsakymoId = (int)$pdo->lastInsertId();
            $sukurti[] = $uzsakymoId;

            foreach ($eilutes as $id => $k) {
                $naujaEilute->execute([$uzsakymoId, $id, $k, round($k * (float)$prekes[$id]['kaina'], 2)]);
            }
        }

        $pdo->commit();
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        klaida($e->getMessage());
    }

    $nr = implode(', ', $sukurti);
    $_SESSION['zinute'] = ['tipas' => 'sekme', 'tekstas' => count($sukurti) === 1
        ? "Užsakymas Nr. $nr pateiktas sėkmingai."
        : 'Pateikti ' . count($sukurti) . " užsakymai (po vieną kiekvienam tiekėjui): Nr. $nr."];
    $_SESSION['isvalyti_krepseli'] = true;
    header('Location: krepselis.php');
    exit;
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

<div id="turinys" class="hidden grid gap-6 lg:grid-cols-3">
    <section class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <ul id="eilutes" class="divide-y divide-slate-100"></ul>
    </section>

    <aside class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 h-fit">
        <h2 class="font-semibold mb-4">Užsakymo suvestinė</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Dalių</dt><dd id="viso-vnt"></dd></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold">
                <dt>Iš viso</dt><dd id="viso-suma"></dd>
            </div>
        </dl>
        <p class="mt-2 text-xs text-slate-400">Galutinė suma apskaičiuojama pagal tuo metu galiojančias kainas. Jei krepšelyje yra kelių tiekėjų dalių, kiekvienam tiekėjui sukuriamas atskiras užsakymas.</p>

        <form method="post" id="uzsakymo-forma" class="mt-5">
            <input type="hidden" name="krepselis" id="krepselio-duomenys">
            <button class="w-full rounded-lg bg-sky-600 px-4 py-2.5 font-medium text-white hover:bg-sky-700">
                Pateikti užsakymą
            </button>
        </form>
        <button type="button" id="isvalyti" class="mt-2 w-full rounded-lg px-4 py-2 text-sm text-slate-500 hover:bg-slate-100">
            Išvalyti krepšelį
        </button>
    </aside>
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
            <li class="flex flex-col sm:flex-row sm:items-center gap-4 p-5">
                <div class="flex-1 min-w-0">
                    <div class="font-medium">${esc(p.pavadinimas)}</div>
                    <div class="text-sm text-slate-500">${esc(p.gamintojas || '—')} · ${esc(p.modelis || '—')}</div>
                    <div class="text-sm text-slate-500">Tiekėjas: ${esc(p.tiekejas)}</div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center rounded-lg border border-slate-300">
                        <button type="button" data-minus="${p.id}" class="px-3 py-1.5 text-lg leading-none hover:bg-slate-100 disabled:text-slate-300 disabled:hover:bg-transparent" ${p.kiekis <= 1 ? 'disabled' : ''}>−</button>
                        <span class="w-10 text-center tabular-nums">${p.kiekis}</span>
                        <button type="button" data-plius="${p.id}" class="px-3 py-1.5 text-lg leading-none hover:bg-slate-100 disabled:text-slate-300 disabled:hover:bg-transparent" ${p.kiekis >= p.sandelyje ? 'disabled' : ''}>+</button>
                    </div>
                    <div class="w-28 text-right">
                        <div class="font-semibold">${eur(p.kaina * p.kiekis)}</div>
                        <div class="text-xs text-slate-400">${eur(p.kaina)} / vnt.</div>
                    </div>
                    <button type="button" data-salinti="${p.id}" title="Pašalinti" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600">✕</button>
                </div>
            </li>`).join('');

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
