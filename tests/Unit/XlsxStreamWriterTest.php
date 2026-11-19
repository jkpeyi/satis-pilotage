<?php

use App\Reports\Rendering\XlsxStreamWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('writes a valid workbook with a bold frozen header, escaped text and unique sheet names', function () {
    $path = sys_get_temp_dir().'/xlsx-test-'.bin2hex(random_bytes(4)).'.xlsx';

    $writer = new XlsxStreamWriter;
    $writer->addSheet('Catalogue des fonctionnalités consolidé', ['Code', 'Texte'], [
        ['FCT-0001', 'A <b> & "c" '."\x07".'fin'],
        ['FCT-0002', "Ligne 1\nLigne 2"],
        ['FCT-0003', ''],
    ]);
    $writer->addSheet('Catalogue des fonctionnalités consolidé', ['Code'], [['x']]);
    $writer->addSheet('Dépôts/actifs', ['Code'], []);
    $writer->save($path);

    $spreadsheet = IOFactory::load($path);
    $sheet = $spreadsheet->getSheet(0);

    expect($spreadsheet->getSheetNames())->toBe(['Catalogue des fonctionnalités c', 'Catalogue des fonctionnalités 2', 'Dépôts actifs'])
        ->and($sheet->getCell('A1')->getFormattedValue())->toBe('Code')
        ->and($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getFreezePane())->toBe('A2')
        ->and($sheet->getCell('B2')->getFormattedValue())->toBe('A <b> & "c" fin')
        ->and($sheet->getCell('B3')->getFormattedValue())->toBe("Ligne 1\nLigne 2")
        ->and($sheet->getHighestRow())->toBe(4)
        ->and($spreadsheet->getSheet(2)->getHighestRow())->toBe(1);

    unlink($path);
});
