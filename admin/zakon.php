<?php
/** Zakonska usklađenost — vodič za vlasnika + izjava o odgovornosti. */
require_once __DIR__ . '/templates/init.php';

$withdrawals = (int) $db->fetchColumn('SELECT COUNT(*) FROM orders WHERE withdrawal_requested_at IS NOT NULL');

$pageTitle = 'Zakonska usklađenost';
require __DIR__ . '/templates/header.php';
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">
  <div style="display:grid;gap:20px">
    <div class="acard">
      <h3>✅ Što trgovina radi automatski za vas</h3>
      <table class="atable" style="font-size:13px">
        <tr><td><strong>Gumb za jednostrani raskid</strong><br><span style="color:#6b7280">obveza od 19. 6. 2026.</span></td>
            <td>Vidljiv link u podnožju svake stranice + online obrazac (<a target="_blank" href="<?= e(url('raskid-ugovora.php')) ?>">pogledaj ↗</a>).
            Kupac dobiva <strong>automatsku e-mail potvrdu s točnim datumom i vremenom</strong> (zakonski trajni medij).
            Zahtjev se bilježi na narudžbi, a vi dobivate obavijest. Bez "dark patterna" — bez nagovaranja i skrivenih koraka.
            <?php if ($withdrawals): ?><br><span class="badge amber">Zaprimljeno zahtjeva: <?= $withdrawals ?></span><?php endif; ?></td></tr>
        <tr><td><strong>Informacija o pravu na raskid</strong></td>
            <td>U e-mail potvrdi svake narudžbe i na stranici potvrde — time vrijedi rok 14 dana (bez te informacije zakon ga produljuje na 12 mjeseci i 14 dana).</td></tr>
        <tr><td><strong>Pravo na popravak</strong><br><span style="color:#6b7280">obveza od 31. 7. 2026.</span></td>
            <td>Pripremljena stranica <a target="_blank" href="<?= e(url('s/pravo-na-popravak')) ?>">"Pravo na popravak" ↗</a> u podnožju —
            prilagodite tekst svojoj ponudi (vrijedi za hladnjake, perilice, uređaje s baterijama, mobitele, tablete i sl.).</td></tr>
        <tr><td><strong>Pravila o cijenama i sniženjima</strong></td>
            <td>Trgovina <strong>ne mijenja cijene ni PDV</strong> — sve dolazi iz vašeg MojaĐurđa računa i nema "akcijskih" precrtanih cijena.
            Time pravilo najniže cijene u 30 dana nije primjenjivo dok god akcije ne radite izvan sustava.</td></tr>
        <tr><td><strong>Fiskalizacija</strong></td>
            <td>Svaki naplaćeni račun fiskalizira se u Poreznoj upravi kroz MojaĐurđa (uklj. zakonski 48-satni rok s automatskim ponavljanjem).</td></tr>
      </table>
    </div>

    <div class="acard">
      <h3>📅 Vaše obveze — kalendar 2026.</h3>
      <table class="atable" style="font-size:13px">
        <tr><td style="white-space:nowrap"><strong>19. 6. 2026.</strong></td>
            <td>Gumb za raskid mora biti aktivan — <span class="badge green">trgovina to već ima ✓</span>. Vaše: obraditi zahtjeve i vratiti novac u zakonskom roku (14 dana).</td></tr>
        <tr><td style="white-space:nowrap"><strong>31. 7. 2026.</strong></td>
            <td>Ako prodajete bijelu tehniku/elektroniku s baterijama: dopunite stranicu "Pravo na popravak" svojim uvjetima i ne odbijajte popravak zbog ranijeg neovlaštenog servisa.</td></tr>
        <tr><td style="white-space:nowrap"><strong>27. 9. 2026.</strong></td>
            <td>Zabrana greenwashinga: tvrdnje "eko", "zeleno", "klimatski neutralno" u opisima proizvoda smijete koristiti SAMO uz provjerljiv certifikat ovlaštenog tijela. Bez dokaza — prekršaj. Pregledajte svoje opise!</td></tr>
        <tr><td style="white-space:nowrap"><strong>Trajno</strong></td>
            <td>GDPR: vi ste voditelj obrade podataka svojih kupaca. Održavajte stranicu
            <a target="_blank" href="<?= e(url('s/zastita-privatnosti')) ?>">Zaštita privatnosti ↗</a> točnom (tko ste, koje podatke
            obrađujete — ime/adresa/e-mail za izvršenje ugovora, koliko ih čuvate, prava ispitanika). Podaci se čuvaju isključivo u VAŠOJ bazi na VAŠEM serveru.</td></tr>
      </table>
      <p class="sub" style="margin-top:10px">B2B napomena: ove obveze štite potrošače (fizičke osobe). Vaša trgovina nema obveznu provjeru poslovnog statusa pri kupnji, pa se s pravne strane tretira kao B2C — obveze vrijede.</p>
    </div>
  </div>

  <div class="acard" style="border-color:#fde68a;background:#fffdf5">
    <h3>⚠️ Izjava o odgovornosti (pročitajte!)</h3>
    <div style="font-size:13px;line-height:1.8;color:#374151">
      <p><strong>ĐurđaShop se pruža "kakav jest" (as-is), besplatno i u dobroj vjeri.</strong>
      Tvrtka <strong>Fork</strong> (izdavatelj sustava MojaĐurđa i ove programske podrške) uložila je razuman trud
      da trgovina sadrži tehničke alate koji OLAKŠAVAJU usklađenost s propisima Republike Hrvatske i EU
      (gumb za jednostrani raskid ugovora, automatske potvrde na trajnom mediju, fiskalizacija računa,
      informativne stranice). Ti alati su pomoć — <strong>ne pravni savjet i ne jamstvo usklađenosti</strong>.</p>
      <p><strong>Isključivu odgovornost za zakonitost poslovanja web trgovine snosi njezin vlasnik (trgovac)</strong>, uključujući ali ne ograničavajući se na:</p>
      <ul style="margin:6px 0;padding-left:18px;line-height:1.9">
        <li>usklađenost sa Zakonom o zaštiti potrošača (uklj. izmjene 2026.: raskid ugovora, pravo na popravak, zabranu greenwashinga, pravila o cijenama), Zakonom o trgovini, Zakonom o elektroničkoj trgovini i poreznim propisima;</li>
        <li><strong>GDPR / Zakon o provedbi Opće uredbe o zaštiti podataka</strong>: vlasnik trgovine je voditelj obrade osobnih podataka svojih kupaca; podaci se pohranjuju isključivo na poslužitelju vlasnika; Fork nema pristup tim podacima, ne obrađuje ih i nije ni voditelj ni izvršitelj obrade;</li>
        <li>točnost i zakonitost objavljenih sadržaja, opisa proizvoda, tvrdnji (uklj. ekološke tvrdnje), cijena i općih uvjeta;</li>
        <li>pravovremenu obradu zahtjeva kupaca (raskidi, povrati, reklamacije, popravci) i povrat sredstava u zakonskim rokovima;</li>
        <li>sigurnost vlastitog poslužitelja, ažuriranje programske podrške i čuvanje pristupnih podataka.</li>
      </ul>
      <p>Fork ne odgovara za bilo kakvu izravnu ili neizravnu štetu, izgubljenu dobit, prekršajne ili upravne
      sankcije nastale korištenjem ili nemogućnošću korištenja ove trgovine, niti za neusklađenost trgovine
      s propisima koji se mijenjaju nakon objave programske podrške. Korištenjem trgovine vlasnik potvrđuje
      da je ovu izjavu pročitao i prihvatio.</p>
      <p style="color:#9ca3af;font-size:12px">Savjet: za konačnu provjeru usklađenosti svojeg poslovanja konzultirajte odvjetnika ili knjigovođu. Ova stranica se ažurira s novim verzijama trgovine, ali praćenje propisa je vaša obveza.</p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
