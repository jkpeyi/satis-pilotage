<?php

use App\Reports\Builders\L1Builder;
use App\Reports\Builders\L2Builder;
use App\Reports\Builders\L3Builder;
use App\Reports\Builders\L4Builder;
use App\Reports\Builders\L5Builder;
use App\Reports\Builders\L6Builder;

/*
 * Deliverables of the DTI plan (SPEC-001). A deliverable without builder is not exportable yet.
 * Templates live in resources/report-templates; bump template_version when a template changes.
 */
return [
    'missing_value' => 'À confirmer',

    // Above this number of rows, the DOCX shows the first rows and refers to the XLSX annex.
    'docx_max_rows' => 500,

    'disk' => 'local',

    'deliverables' => [
        'L1' => ['title' => 'Cartographie du patrimoine applicatif', 'template' => 'L1.docx', 'template_version' => '1.0', 'builder' => L1Builder::class],
        'L2' => ['title' => 'Référentiel fonctionnel consolidé', 'template' => 'L2.docx', 'template_version' => '1.0', 'builder' => L2Builder::class],
        'L3' => ['title' => 'Cartographie technique', 'template' => 'L3.docx', 'template_version' => '1.0', 'builder' => L3Builder::class],
        'L4' => ['title' => 'Rapport de diagnostic par dépôt', 'template' => 'L4.docx', 'template_version' => '1.0', 'builder' => L4Builder::class],
        'L5' => ['title' => 'Registre consolidé de dette technique', 'template' => 'L5.docx', 'template_version' => '1.0', 'builder' => L5Builder::class],
        'L6' => ['title' => 'Tableau de bord DTI', 'template' => 'L6.docx', 'template_version' => '1.0', 'builder' => L6Builder::class],
        'L7' => ['title' => 'Stratégie de refactoring', 'template' => 'L7.docx', 'template_version' => '1.0', 'builder' => null],
        'L8' => ['title' => 'Feuille de route de remédiation', 'template' => 'L8.docx', 'template_version' => '1.0', 'builder' => null],
    ],
];
