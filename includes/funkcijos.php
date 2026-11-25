<?php

// Mygtukas „Į krepšelį“ (ikona, fiksuoto dydžio) – duomenis paima includes/krepselis.js
const KREPSELIO_IKONA = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>';

function krepselioMygtukas(array $preke, string $tiekejas): string
{
    $kiekis = (int)$preke['kiekis'];
    $klases = 'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-600 text-white hover:bg-sky-700 '
            . 'focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 '
            . 'disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed';

    if ($kiekis <= 0) {
        return '<button type="button" disabled aria-label="Nėra sandėlyje" class="' . $klases . '">' . KREPSELIO_IKONA . '</button>';
    }

    $attr = [
        'data-id'         => (int)$preke['prekes_id'],
        'data-pavadinimas'=> $preke['pavadinimas'],
        'data-tiekejas'   => $tiekejas,
        'data-gamintojas' => $preke['gamintojas'] ?? '',
        'data-modelis'    => $preke['modelis'] ?? '',
        'data-kaina'      => $preke['kaina'],
        'data-sandelyje'  => $kiekis,
        'aria-label'      => 'Įdėti „' . $preke['pavadinimas'] . '“ į krepšelį',
    ];
    $html = '';
    foreach ($attr as $k => $v) {
        $html .= ' ' . $k . '="' . htmlspecialchars((string)$v) . '"';
    }

    return '<button type="button" data-i-krepseli' . $html . ' class="' . $klases . '">' . KREPSELIO_IKONA . '</button>';
}

// Dalies formos laukai (pagal `preke` lentelę)
const DALIES_LAUKAI = ['pavadinimas' => '', 'gamintojas' => '', 'modelis' => '', 'kaina' => '', 'kiekis' => '', 'pristatymo_laikas' => ''];

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

    // pristatymo_laikas INT UNSIGNED – darbo dienomis
    if (!preg_match('/^\d{1,3}$/', $laukai['pristatymo_laikas']) || (int)$laukai['pristatymo_laikas'] < 1) {
        $klaidos['pristatymo_laikas'] = 'Įveskite pristatymo laiką darbo dienomis (1–999).';
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

// Likutis sandėlyje. krepselis.js atima tai, kas jau įdėta į krepšelį.
function likutis(array $preke): string
{
    $kiekis = (int)$preke['kiekis'];
    $tekstas = $kiekis > 0 ? $kiekis . ' vnt.' : '<span class="text-red-600">Nėra</span>';
    return '<span data-likutis="' . (int)$preke['prekes_id'] . '" data-sandelyje="' . $kiekis . '">' . $tekstas . '</span>';
}

// Atšaukus / atmetus užsakymą – jo dalių kiekiai grąžinami į sandėlį
function grazintiLikuti(PDO $pdo, int $uzsakymoId): void
{
    $pdo->prepare(
        'UPDATE preke p JOIN uzsakymo_preke up ON up.prekes_id = p.prekes_id
         SET p.kiekis = p.kiekis + up.kiekis
         WHERE up.uzsakymo_id = ?'
    )->execute([$uzsakymoId]);
}

// Pristatymo laikas sąraše, pvz. „5 d. d.“ (darbo dienos)
function pristatymoLaikas($dienos): string
{
    return $dienos !== null ? (int)$dienos . ' d. d.' : '—';
}

// Data po N darbo dienų (savaitgaliai praleidžiami)
function poDarboDienu(int $dienos, ?DateTimeImmutable $nuo = null): DateTimeImmutable
{
    $data = $nuo ?? new DateTimeImmutable('today');
    while ($dienos > 0) {
        $data = $data->modify('+1 day');
        if ((int)$data->format('N') <= 5) {   // 1–5 = pirmadienis–penktadienis
            $dienos--;
        }
    }
    return $data;
}
