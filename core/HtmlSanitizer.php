<?php
/**
 * HtmlSanitizer — čisti HTML (blog, CMS stranice, opisi artikala) od XSS-a.
 *
 * Dopušta SAMO sigurne tagove/atribute; briše <script>/<iframe>/<style>/<form>…,
 * sve on* atribute (onclick…) i opasne URL sheme (javascript:, data:text/html).
 * Vanjski linkovi dobivaju rel="noopener noreferrer nofollow" + target="_blank".
 * Bez vanjskih biblioteka — koristi DOMDocument (ext-dom, standardno prisutan).
 */

class HtmlSanitizer
{
    /** Dopušteni tagovi → dopušteni atributi za taj tag. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'span' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'small' => [], 'mark' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [],
        'blockquote' => [], 'pre' => [], 'code' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
    ];

    /** Tagovi koji se brišu ZAJEDNO sa sadržajem (ne samo odmotaju). */
    private const STRIP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'link', 'meta', 'base', 'svg', 'math', 'noscript', 'template',
    ];

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') return '';
        if (!class_exists('DOMDocument')) return self::fallback($html);

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // <?xml encoding> trik → loadHTML tretira ulaz kao UTF-8 (hrvatski znakovi ostaju)
        $doc->loadHTML(
            '<?xml encoding="UTF-8"?><body>' . $html . '</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) return '';
        self::cleanNode($body);

        $out = '';
        foreach (iterator_to_array($body->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    /** Depth-first: prvo se očiste poddrva, pa se onda obrade direktna djeca. */
    private static function cleanNode(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) self::cleanNode($child);
        }
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) continue;
            if (!($child instanceof DOMElement)) { // komentari, CDATA, PI → makni
                $child->parentNode->removeChild($child);
                continue;
            }
            $tag = strtolower($child->tagName);
            if (!isset(self::ALLOWED[$tag])) {
                if (in_array($tag, self::STRIP_WITH_CONTENT, true)) {
                    $child->parentNode->removeChild($child);          // opasan tag — briši sve
                } else {
                    self::unwrap($child);                              // npr. div/font — zadrži (već čistu) djecu
                }
                continue;
            }
            self::cleanAttributes($child, $tag);                       // dopušten tag — očisti atribute
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];
        $names = [];
        foreach ($el->attributes as $a) $names[] = $a->name;
        foreach ($names as $name) {
            $lname = strtolower($name);
            if (!in_array($lname, $allowed, true)) {                   // briše i sve on* atribute, style, class…
                $el->removeAttribute($name);
                continue;
            }
            $val = $el->getAttribute($name);
            if ($lname === 'href' && !self::safeUrl($val, true))  $el->removeAttribute($name);
            if ($lname === 'src'  && !self::safeUrl($val, false)) $el->removeAttribute($name);
        }
        if ($tag === 'a') {
            $href = $el->getAttribute('href');
            if ($href !== '' && preg_match('#^https?://#i', $href)) {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener noreferrer nofollow');
            } else {
                $el->removeAttribute('target');
                $el->removeAttribute('rel');
            }
        }
        if ($tag === 'img') $el->setAttribute('loading', 'lazy');
    }

    /** Dopuštene URL sheme: relativno, http(s), mailto/tel (linkovi), data:image (slike). */
    private static function safeUrl(string $url, bool $allowMailTel): bool
    {
        $u = trim($url);
        if ($u === '') return false;
        if (str_starts_with($u, '//')) return false;                  // protokol-relativno (//evil.com) → odbij
        if (preg_match('#^(/|\./|\.\./|\#|\?)#', $u)) return true;
        if (preg_match('#^https?://#i', $u)) return true;
        if ($allowMailTel && preg_match('#^(mailto:|tel:)#i', $u)) return true;
        if (!$allowMailTel && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $u)) return true;
        return false; // javascript:, vbscript:, data:text/html, …
    }

    /** Zamijeni element njegovom (već očišćenom) djecom. */
    private static function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    /** Krajnji fallback ako ext-dom nije dostupan (rijetko): agresivno čišćenje. */
    private static function fallback(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form|svg|math)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|link|meta|base)\b[^>]*/?>#is', '', $html);
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);   // on* atributi
        $html = preg_replace('#(href|src)\s*=\s*("\s*javascript:[^"]*"|\'\s*javascript:[^\']*\')#i', '', $html);
        return strip_tags($html, '<p><br><hr><span><strong><b><em><i><u><s><ul><ol><li><h2><h3><h4><blockquote><pre><code><a><img><figure><figcaption><table><thead><tbody><tr><th><td>');
    }
}
