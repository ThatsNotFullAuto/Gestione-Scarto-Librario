<?php
declare(strict_types=1);

final class ScartoPdfMemoryStream {
    public static string $data = '';
    private int $position = 0;

    public function stream_open($path, $mode, $options, &$opened_path): bool {
        $this->position = 0;
        if (str_contains((string) $mode, 'w')) self::$data = '';
        return true;
    }

    public function stream_write($data): int {
        $length = strlen((string) $data);
        self::$data = substr(self::$data, 0, $this->position) . $data . substr(self::$data, $this->position + $length);
        $this->position += $length;
        return $length;
    }

    public function stream_read($count): string {
        $result = substr(self::$data, $this->position, (int) $count);
        $this->position += strlen($result);
        return $result;
    }

    public function stream_eof(): bool {
        return $this->position >= strlen(self::$data);
    }

    public function stream_stat(): array {
        return self::stat();
    }

    public function url_stat($path, $flags): array {
        return self::stat();
    }

    public function unlink($path): bool {
        self::$data = '';
        return true;
    }

    public function stream_metadata($path, $option, $value): bool {
        return true;
    }

    private static function stat(): array {
        return ['mode' => 0100666, 'size' => strlen(self::$data)];
    }
}

stream_wrapper_register('scartotest', ScartoPdfMemoryStream::class);

function scarto_create_temp_reservation_pdf_path($code) {
    return 'scartotest://reservation.pdf';
}

function scarto_delete_temp_reservation_pdf($path): void {
    if (is_string($path) && file_exists($path)) unlink($path);
}

function scarto_get_settings(): array {
    return [
        'library_name' => 'Biblioteca Statale Stelio Crise',
        'library_address' => 'Largo Papa Giovanni XXIII, 6 - 34123 Trieste',
        'library_phone' => '040 300725',
        'email_from' => 'bs-scts@cultura.gov.it',
    ];
}

function wp_date($format, $timestamp): string {
    return gmdate((string) $format, (int) $timestamp);
}

function scarto_reservation_pdf_footer($settings): array {
    return [
        (string) $settings['library_name'],
        (string) $settings['library_address'],
        'Tel. ' . $settings['library_phone'] . ' - email: ' . $settings['email_from'],
    ];
}

$source = file_get_contents(dirname(__DIR__) . '/gestione-scarto-librario.php');
$start = strpos($source, 'function scarto_generate_reservation_pdf(');
$end = strpos($source, "\nfunction scarto_generate_pdf_content", $start ?: 0);
if ($start === false || $end === false) {
    fwrite(STDERR, "FAIL: generatore PDF non trovato\n");
    exit(1);
}
eval(substr($source, $start, $end - $start));

$books = [];
for ($index = 1; $index <= 70; $index++) {
    $books[] = [
        'titolo' => "Titolo completo del volume {$index} con informazioni bibliografiche estese",
        'autore' => "Autore Cognome {$index}",
        'inventario' => 'INV-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT),
    ];
}

$path = scarto_generate_reservation_pdf(
    'GAJY4B',
    ['nome' => 'Valerio', 'cognome' => 'Melucci', 'email' => 'utente@example.org', 'indirizzo' => ''],
    $books,
    1787219820000,
    7
);
$pdf = is_string($path) && file_exists($path) ? file_get_contents($path) : false;

function pdf_assert($condition, $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

pdf_assert(is_string($pdf) && str_starts_with($pdf, '%PDF-1.4'), 'file PDF non valido');
pdf_assert(str_contains($pdf, '(Riepilogo Prenotazione Scarto Librario)'), 'titolo strutturato assente');
pdf_assert(str_contains($pdf, '/F2 24 Tf') && str_contains($pdf, '0.145 0.388 0.922 rg'), 'codice evidenziato assente');
pdf_assert(str_contains($pdf, '(GAJY4B)') && str_contains($pdf, 'INV-00070'), 'codice o ultimo inventario assente');
pdf_assert(str_contains($pdf, '(ISTRUZIONI PER IL RITIRO)'), 'istruzioni assenti');
pdf_assert(str_contains($pdf, '(Biblioteca Statale Stelio Crise)'), 'footer configurabile assente');
pdf_assert(substr_count($pdf, '/Type /Page ') > 1, 'paginazione non attivata con molti volumi');
pdf_assert(str_contains($pdf, '(Pagina 2 di '), 'numerazione multipagina assente');

pdf_assert((bool) preg_match('/xref\n0 (\d+)\n(.*?)trailer/s', $pdf, $xref), 'tabella xref assente');
$xref_lines = preg_split('/\r?\n/', trim($xref[2]));
for ($object = 1; $object < (int) $xref[1]; $object++) {
    $line = $xref_lines[$object] ?? '';
    pdf_assert((bool) preg_match('/^(\d{10}) 00000 n/', $line, $match), "offset xref {$object} non valido");
    $offset = (int) $match[1];
    pdf_assert(substr($pdf, $offset, strlen((string) $object) + 6) === $object . ' 0 obj', "oggetto PDF {$object} non raggiungibile");
}

scarto_delete_temp_reservation_pdf($path);
fwrite(STDOUT, "OK PDF prenotazione strutturato, completo e multipagina\n");
