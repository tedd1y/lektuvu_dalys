<?php

// Mygtukas „Į krepšelį“ – duomenis paima includes/krepselis.js
function krepselioMygtukas(array $preke, string $tiekejas, bool $mazas = false): string
{
    $kiekis = (int)$preke['kiekis'];
    $dydis  = $mazas ? 'px-3 py-1.5 text-xs' : 'px-4 py-2 text-sm w-full';

    if ($kiekis <= 0) {
        return '<button type="button" disabled class="rounded-lg bg-slate-100 text-slate-400 cursor-not-allowed ' . $dydis . '">Nėra sandėlyje</button>';
    }

    $attr = [
        'data-id'         => (int)$preke['prekes_id'],
        'data-pavadinimas'=> $preke['pavadinimas'],
        'data-tiekejas'   => $tiekejas,
        'data-gamintojas' => $preke['gamintojas'] ?? '',
        'data-modelis'    => $preke['modelis'] ?? '',
        'data-kaina'      => $preke['kaina'],
        'data-sandelyje'  => $kiekis,
    ];
    $html = '';
    foreach ($attr as $k => $v) {
        $html .= ' ' . $k . '="' . htmlspecialchars((string)$v) . '"';
    }

    return '<button type="button" data-i-krepseli' . $html
         . ' class="rounded-lg bg-sky-600 font-medium text-white hover:bg-sky-700 whitespace-nowrap ' . $dydis . '">+ Į krepšelį</button>';
}

// Dalies formos laukai (pagal `preke` lentelę)
const DALIES_LAUKAI = ['pavadinimas' => '', 'gamintojas' => '', 'modelis' => '', 'kaina' => '', 'kiekis' => ''];

// Patikrina dalies formą. Naudojama pridedant ir redaguojant.
// Grąžina [$laukai, $klaidos, $kaina]; $isskyrusId – redaguojamos dalies ID (kad nelaikytų jos dublikatu).
function tikrintiDali(PDO $pdo, array $post, int $tiekejoId, int $isskyrusId = 0): array
{
    $laukai = [];
    foreach (DALIES_LAUKAI as $k => $_) {
        $laukai[$k] = trim((string)($post[$k] ?? ''));
    }
    $klaidos = [];

    // pavadinimas VARCHAR(100) NOT NULL
    if ($laukai['pavadinimas'] === '') {
        $klaidos['pavadinimas'] = 'Įveskite dalies pavadinimą.';
    } elseif (mb_strlen($laukai['pavadinimas']) > 100) {
        $klaidos['pavadinimas'] = 'Pavadinimas negali būti ilgesnis nei 100 simbolių.';
    }

    // gamintojas, modelis VARCHAR(100) NULL
    foreach (['gamintojas' => 'Gamintojo', 'modelis' => 'Modelio'] as $k => $pav) {
        if (mb_strlen($laukai[$k]) > 100) {
            $klaidos[$k] = "$pav pavadinimas negali būti ilgesnis nei 100 simbolių.";
        }
    }

    // kaina DECIMAL(10,2) NOT NULL – leidžiam ir kablelį (12,50)
    $kaina = str_replace([' ', ','], ['', '.'], $laukai['kaina']);
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $kaina) || (float)$kaina <= 0) {
        $klaidos['kaina'] = 'Įveskite teigiamą kainą, pvz. 1250,50 (ne daugiau 2 skaičių po kablelio).';
    }

    // kiekis INT NOT NULL
    if (!preg_match('/^\d{1,9}$/', $laukai['kiekis'])) {
        $klaidos['kiekis'] = 'Kiekis turi būti sveikas skaičius, 0 arba daugiau.';
    }

    // Tas pats tiekėjas neturėtų turėti dviejų vienodų dalių
    if (!$klaidos) {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM preke
             WHERE tiekejo_id = ? AND prekes_id <> ? AND pavadinimas = ?
               AND COALESCE(gamintojas, "") = ? AND COALESCE(modelis, "") = ?'
        );
        $stmt->execute([$tiekejoId, $isskyrusId, $laukai['pavadinimas'], $laukai['gamintojas'], $laukai['modelis']]);
        if ($stmt->fetch()) {
            $klaidos['pavadinimas'] = 'Jūs jau siūlote dalį tokiu pavadinimu, gamintoju ir modeliu.';
        }
    }

    return [$laukai, $klaidos, $kaina];
}

// Tuščią tekstą DB saugom kaip NULL
function arbaNull(string $s): ?string
{
    return $s !== '' ? $s : null;
}
