<?php

use App\Support\LegacyBiffReader;

/**
 * Build a bare BIFF stream of the shape the scanner exports: a BOF record,
 * one BIFF2 LABEL record per cell, then EOF. Every cell is text, which is how
 * the device writes its exports.
 *
 * @param  list<list<string>>  $grid
 */
function biffBytes(array $grid): string
{
    $out = pack('vv', 0x0809, 6).str_repeat("\x00", 6);

    foreach ($grid as $rowIndex => $row) {
        foreach ($row as $columnIndex => $value) {
            // row, column, 3 attribute bytes, 1-byte length, then the text.
            $body = pack('vv', $rowIndex, $columnIndex)."\x00\x00\x00".chr(strlen($value)).$value;
            $out .= pack('vv', 0x0004, strlen($body)).$body;
        }
    }

    return $out.pack('vv', 0x000A, 0);
}

/**
 * @param  list<list<string>>  $grid
 */
function biffFile(array $grid): string
{
    $path = tempnam(sys_get_temp_dir(), 'biff').'.xls';
    file_put_contents($path, biffBytes($grid));

    return $path;
}

it('reads a bare BIFF stream into a matrix', function () {
    $path = biffFile([
        ['Department', 'Name', 'No.', 'Date/Time'],
        ['DIGITAL FEET', 'Danilo', '110', '23/09/2026 10:03:28 am'],
        ['DIGITAL FEET', 'Renee', '141', '23/09/2026 9:37:41 am'],
    ]);

    expect((new LegacyBiffReader)->read($path))->toBe([
        ['Department', 'Name', 'No.', 'Date/Time'],
        ['DIGITAL FEET', 'Danilo', '110', '23/09/2026 10:03:28 am'],
        ['DIGITAL FEET', 'Renee', '141', '23/09/2026 9:37:41 am'],
    ]);

    @unlink($path);
});

it('pads short rows so every row has the same width', function () {
    // The device leaves trailing columns (e.g. CardNo) empty.
    $path = biffFile([
        ['Name', 'No.', 'CardNo'],
        ['Danilo', '110'],
    ]);

    expect((new LegacyBiffReader)->read($path))->toBe([
        ['Name', 'No.', 'CardNo'],
        ['Danilo', '110', ''],
    ]);

    @unlink($path);
});

it('recognises a bare BIFF stream', function () {
    $path = biffFile([['Name'], ['Danilo']]);

    expect(LegacyBiffReader::supports($path))->toBeTrue();

    @unlink($path);
});

it('declines files PhpSpreadsheet already handles', function () {
    $xlsx = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    file_put_contents($xlsx, "PK\x03\x04 pretend zip");

    $ole = tempnam(sys_get_temp_dir(), 'o').'.xls';
    file_put_contents($ole, "\xD0\xCF\x11\xE0 pretend OLE document");

    $csv = tempnam(sys_get_temp_dir(), 'c').'.csv';
    file_put_contents($csv, "Name,No.\nDanilo,110\n");

    expect(LegacyBiffReader::supports($xlsx))->toBeFalse()
        ->and(LegacyBiffReader::supports($ole))->toBeFalse()
        ->and(LegacyBiffReader::supports($csv))->toBeFalse()
        ->and(LegacyBiffReader::supports('/no/such/file.xls'))->toBeFalse();

    @unlink($xlsx);
    @unlink($ole);
    @unlink($csv);
});

it('stops cleanly on a truncated file rather than reading past the end', function () {
    $path = tempnam(sys_get_temp_dir(), 'biff').'.xls';
    // Keep the BOF and the first cell, cut the stream mid-record.
    file_put_contents($path, substr(biffBytes([['Name'], ['Danilo']]), 0, -4));

    expect((new LegacyBiffReader)->read($path))->toBe([['Name'], ['Danilo']]);

    @unlink($path);
});

it('fails clearly when the file holds no cells', function () {
    $path = biffFile([]);

    expect(fn () => (new LegacyBiffReader)->read($path))
        ->toThrow(RuntimeException::class, 'No spreadsheet cells');

    @unlink($path);
});
