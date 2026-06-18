<?php
/**
 * Vizualni (WYSIWYG) editor + HTML način — zajednički za stranice i blog.
 * Ulaz: $wzName (ime polja, npr. 'content'), $wzContent (sirovi HTML sadržaj),
 *       $wzRows (neobavezno). JS koji ovo oživi je u templates/footer.php
 *       (radi za proizvoljan broj .wz editora na stranici).
 */
$wzName    = $wzName ?? 'content';
$wzContent = $wzContent ?? '';
$wzRows    = $wzRows ?? 16;
?>
<div class="wz" data-mode="visual">
  <div class="wz-tabs">
    <button type="button" class="wz-tab is-active" data-mode="visual">✏️ Vizualno</button>
    <button type="button" class="wz-tab" data-mode="html">&lt;/&gt; HTML</button>
  </div>
  <div class="wz-tools">
    <button type="button" class="wz-b" data-cmd="bold" title="Podebljano"><b>B</b></button>
    <button type="button" class="wz-b" data-cmd="italic" title="Kurziv"><i>I</i></button>
    <button type="button" class="wz-b" data-cmd="underline" title="Podcrtano"><u>U</u></button>
    <span class="wz-sep"></span>
    <button type="button" class="wz-b" data-block="h2" title="Naslov">H2</button>
    <button type="button" class="wz-b" data-block="h3" title="Podnaslov">H3</button>
    <button type="button" class="wz-b" data-block="p" title="Običan tekst">¶</button>
    <span class="wz-sep"></span>
    <button type="button" class="wz-b" data-cmd="insertUnorderedList" title="Lista s točkama">• Lista</button>
    <button type="button" class="wz-b" data-cmd="insertOrderedList" title="Numerirana lista">1. Lista</button>
    <button type="button" class="wz-b" data-block="blockquote" title="Citat">❝</button>
    <span class="wz-sep"></span>
    <button type="button" class="wz-b" data-link="1" title="Dodaj poveznicu">🔗</button>
    <button type="button" class="wz-b" data-cmd="unlink" title="Ukloni poveznicu">⛓</button>
    <button type="button" class="wz-b" data-cmd="removeFormat" title="Očisti oblikovanje">✕</button>
  </div>
  <div class="wz-visual" contenteditable="true" spellcheck="true" data-ph="Upišite sadržaj…"><?= $wzContent /* HTML vlasnika */ ?></div>
  <textarea class="ainput wz-html" name="<?= e($wzName) ?>" rows="<?= (int) $wzRows ?>"><?= e($wzContent) ?></textarea>
  <p class="wz-hint">Napredni način — čisti HTML. Promjene se primjenjuju u vizualnom prikazu kad se vratite na karticu „Vizualno".</p>
</div>
