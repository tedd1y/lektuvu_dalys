// Krepšelis saugomas naršyklėje (localStorage), atskirai kiekvienam naudotojui.
// Serveriui siunčiami tik prekių ID ir kiekiai, kainas serveris ima iš DB.
const Krepselis = (() => {
    const KEY = 'krepselis_' + (document.body.dataset.naudotojas || '0');

    function gauti() {
        try {
            const d = JSON.parse(localStorage.getItem(KEY));
            return Array.isArray(d) ? d : [];
        } catch (e) {
            return [];
        }
    }

    function issaugoti(prekes) {
        try { localStorage.setItem(KEY, JSON.stringify(prekes)); } catch (e) {}
        atnaujintiZenkliuka();
        document.dispatchEvent(new CustomEvent('krepselis:pasikeite'));
    }

    function prideti(preke) {
        const prekes = gauti();
        const esama = prekes.find(p => p.id === preke.id);
        if (esama) {
            esama.kiekis = Math.min(esama.kiekis + 1, preke.sandelyje);
            Object.assign(esama, { kaina: preke.kaina, sandelyje: preke.sandelyje });
        } else {
            prekes.push({ ...preke, kiekis: 1 });
        }
        issaugoti(prekes);
    }

    function nustatytiKieki(id, kiekis) {
        const prekes = gauti();
        const p = prekes.find(p => p.id === id);
        if (!p) return;
        p.kiekis = Math.max(1, Math.min(kiekis, p.sandelyje));
        issaugoti(prekes);
    }

    function pasalinti(id) {
        issaugoti(gauti().filter(p => p.id !== id));
    }

    function isvalyti() {
        issaugoti([]);
    }

    function kiekisIsViso() {
        return gauti().reduce((s, p) => s + p.kiekis, 0);
    }

    function atnaujintiZenkliuka() {
        const n = kiekisIsViso();
        document.querySelectorAll('[data-krepselio-kiekis]').forEach(el => {
            el.textContent = n;
            el.classList.toggle('hidden', n === 0);
        });
        atnaujintiLikucius();
    }

    // Puslapyje rodomas likutis = sandėlyje − jau krepšelyje.
    // (DB nekeičiama – krepšelis tik naršyklėje; tikras likutis mažėja, kai tiekėjas priima užsakymą.)
    function atnaujintiLikucius() {
        const krepselyje = {};
        gauti().forEach(p => { krepselyje[p.id] = p.kiekis; });

        document.querySelectorAll('[data-likutis]').forEach(el => {
            const sandelyje = parseInt(el.dataset.sandelyje, 10) || 0;
            const k = krepselyje[parseInt(el.dataset.likutis, 10)] || 0;
            const liko = Math.max(0, sandelyje - k);
            el.innerHTML = (liko > 0 ? liko + ' vnt.' : '<span class="text-red-600">Nėra</span>')
                + (k > 0 ? '<span class="block text-xs text-sky-700">' + k + ' krepšelyje</span>' : '');
        });

        document.querySelectorAll('[data-i-krepseli]').forEach(btn => {
            if (btn.dataset.originalas === undefined) btn.dataset.originalas = btn.innerHTML;
            const sandelyje = parseInt(btn.dataset.sandelyje, 10) || 0;
            const k = krepselyje[parseInt(btn.dataset.id, 10)] || 0;
            const pilnas = k >= sandelyje;

            btn.disabled = pilnas;
            btn.classList.toggle('opacity-50', pilnas);
            btn.classList.toggle('cursor-not-allowed', pilnas);
            if (!btn._laikas) {
                btn.innerHTML = pilnas ? 'Visi krepšelyje' : btn.dataset.originalas;
            }
        });
    }

    // „Į krepšelį“ mygtukai: <button data-i-krepseli data-id=".." data-pavadinimas=".." ...>
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-i-krepseli]');
        if (!btn) return;
        e.preventDefault();

        const d = btn.dataset;
        const sandelyje = parseInt(d.sandelyje, 10) || 0;
        if (sandelyje <= 0) return;

        const esama = gauti().find(p => p.id === parseInt(d.id, 10));
        if (esama && esama.kiekis >= sandelyje) {
            pranesti(btn, 'Daugiau nėra');
            return;
        }

        prideti({
            id: parseInt(d.id, 10),
            pavadinimas: d.pavadinimas,
            tiekejas: d.tiekejas,
            gamintojas: d.gamintojas || '',
            modelis: d.modelis || '',
            kaina: parseFloat(d.kaina),
            sandelyje: sandelyje,
        });
        pranesti(btn, '✓ Pridėta');
    });

    function pranesti(btn, tekstas) {
        btn.innerHTML = tekstas;
        clearTimeout(btn._laikas);
        btn._laikas = setTimeout(() => {
            btn._laikas = null;
            atnaujintiLikucius();   // grąžina tinkamą mygtuko tekstą
        }, 1200);
    }

    // Kitame skirtuke pakeitus krepšelį – atnaujinam ženkliuką ir čia
    window.addEventListener('storage', e => {
        if (e.key === KEY) {
            atnaujintiZenkliuka();
            document.dispatchEvent(new CustomEvent('krepselis:pasikeite'));
        }
    });

    atnaujintiZenkliuka();

    return { gauti, nustatytiKieki, pasalinti, isvalyti };
})();
