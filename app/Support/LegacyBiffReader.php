<?php

namespace App\Support;

use RuntimeException;

/**
 * Reads the raw-BIFF ".xls" files the biometric scanner exports.
 *
 * These are not the OLE compound documents PhpSpreadsheet understands — the
 * device writes a bare stream of Excel 4.0-era BIFF records with no container,
 * which PhpSpreadsheet cannot identify and Excel itself refuses to open under
 * its default Trust Center file-block policy.
 *
 * The format is simple enough to walk directly: a flat sequence of
 * `[id:uint16][length:uint16][body]` records, of which only the cell records
 * matter. Everything else (fonts, formats, page setup) is skipped, and cells
 * are returned as a plain row/column matrix for the caller to interpret.
 */
class LegacyBiffReader
{
    /** Beginning-of-file record ids across BIFF versions. */
    private const BOF_RECORDS = [0x0009, 0x0209, 0x0409, 0x0809];

    /** Cell records carrying a string, by BIFF generation. */
    private const LABEL_BIFF2 = 0x0004;

    private const LABEL_BIFF5 = 0x0204;

    /** Cell records carrying a float. */
    private const NUMBER_BIFF2 = 0x0003;

    private const NUMBER_BIFF5 = 0x0203;

    /** Cell record carrying an RK-encoded number. */
    private const RK = 0x027E;

    /**
     * Whether this file looks like a bare BIFF stream we can read — i.e. it
     * starts with a BOF record and is neither an OLE document (.xls proper)
     * nor a zip archive (.xlsx), both of which PhpSpreadsheet handles better.
     */
    public static function supports(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $head = (string) fread($handle, 8);
        fclose($handle);

        if (strlen($head) < 4) {
            return false;
        }

        // OLE compound document, or a zip (xlsx) — not ours.
        if (str_starts_with($head, "\xD0\xCF\x11\xE0") || str_starts_with($head, 'PK')) {
            return false;
        }

        /** @var array{id: int, length: int} $header */
        $header = unpack('vid/vlength', substr($head, 0, 4));

        return in_array($header['id'], self::BOF_RECORDS, true);
    }

    /**
     * Read the file into a row/column matrix of cell values. Gaps are filled
     * with empty strings so every row has the same width.
     *
     * @return list<list<mixed>>
     *
     * @throws RuntimeException When the file cannot be read or holds no cells.
     */
    public function read(string $path): array
    {
        $data = @file_get_contents($path);

        if ($data === false) {
            throw new RuntimeException("Unable to read {$path}.");
        }

        $cells = [];
        $maxRow = -1;
        $maxCol = -1;
        $offset = 0;
        $length = strlen($data);

        while ($offset + 4 <= $length) {
            /** @var array{id: int, length: int} $header */
            $header = unpack('vid/vlength', substr($data, $offset, 4));
            $body = substr($data, $offset + 4, $header['length']);
            $offset += 4 + $header['length'];

            // A truncated trailing record means the stream ended mid-write.
            if (strlen($body) < $header['length']) {
                break;
            }

            $cell = $this->decodeCell($header['id'], $body);

            if ($cell === null) {
                continue;
            }

            [$row, $column, $value] = $cell;

            $cells[$row][$column] = $value;
            $maxRow = max($maxRow, $row);
            $maxCol = max($maxCol, $column);
        }

        if ($maxRow < 0) {
            throw new RuntimeException('No spreadsheet cells were found in this file.');
        }

        $matrix = [];

        for ($row = 0; $row <= $maxRow; $row++) {
            $line = [];

            for ($column = 0; $column <= $maxCol; $column++) {
                $line[] = $cells[$row][$column] ?? '';
            }

            $matrix[] = $line;
        }

        return $matrix;
    }

    /**
     * Decode one record into [row, column, value], or null when the record is
     * not a cell we care about.
     *
     * @return array{0: int, 1: int, 2: mixed}|null
     */
    protected function decodeCell(int $id, string $body): ?array
    {
        if (strlen($body) < 6) {
            return null;
        }

        /** @var array{row: int, column: int} $position */
        $position = unpack('vrow/vcolumn', substr($body, 0, 4));

        return match ($id) {
            // BIFF2: 3 attribute bytes, then a single-byte string length.
            self::LABEL_BIFF2 => [
                $position['row'],
                $position['column'],
                $this->decodeString($body, 8, ord($body[7])),
            ],
            // BIFF3-5: a 2-byte format index, then a 2-byte string length.
            self::LABEL_BIFF5 => [
                $position['row'],
                $position['column'],
                $this->decodeString($body, 8, $this->uint16($body, 6)),
            ],
            self::NUMBER_BIFF2 => [$position['row'], $position['column'], $this->decodeDouble($body, 7)],
            self::NUMBER_BIFF5 => [$position['row'], $position['column'], $this->decodeDouble($body, 6)],
            self::RK => [$position['row'], $position['column'], $this->decodeRk($body, 6)],
            default => null,
        };
    }

    protected function decodeString(string $body, int $offset, int $length): string
    {
        $raw = substr($body, $offset, $length);

        // The device writes single-byte text; normalise it to UTF-8 so names
        // with accented characters survive.
        return mb_convert_encoding($raw, 'UTF-8', 'CP1252');
    }

    protected function decodeDouble(string $body, int $offset): float|string
    {
        $raw = substr($body, $offset, 8);

        if (strlen($raw) < 8) {
            return '';
        }

        /** @var array{value: float} $unpacked */
        $unpacked = unpack('evalue', $raw);

        return $unpacked['value'];
    }

    /**
     * Decode an RK value: a 30-bit number that is optionally integer-encoded
     * and optionally scaled down by 100.
     */
    protected function decodeRk(string $body, int $offset): float|string
    {
        $raw = substr($body, $offset, 4);

        if (strlen($raw) < 4) {
            return '';
        }

        /** @var array{value: int} $unpacked */
        $unpacked = unpack('Vvalue', $raw);
        $encoded = $unpacked['value'];

        if ($encoded & 0x02) {
            // Integer: the value sits in the top 30 bits, signed.
            $number = (float) ($encoded >> 2);

            if ($encoded & 0x80000000) {
                $number = (float) (($encoded >> 2) - 0x40000000);
            }
        } else {
            // Float: the top 30 bits are the high word of an IEEE 754 double.
            /** @var array{value: float} $asDouble */
            $asDouble = unpack('evalue', pack('V2', 0, $encoded & 0xFFFFFFFC));
            $number = $asDouble['value'];
        }

        return $encoded & 0x01 ? $number / 100 : $number;
    }

    protected function uint16(string $body, int $offset): int
    {
        /** @var array{value: int} $unpacked */
        $unpacked = unpack('vvalue', substr($body, $offset, 2));

        return $unpacked['value'];
    }
}
