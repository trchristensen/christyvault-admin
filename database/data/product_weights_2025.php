<?php

/*
|--------------------------------------------------------------------------
| Product shipping weights from "Print 2025.pdf"
|--------------------------------------------------------------------------
|
| Complete products use the catalog's assembled Total. Component product
| numbers use the matching Base, Cover, Ring, or Inner Shutter weight.
| Aliases are existing product numbers known to represent the same item.
| Rows without a weight require review and are never imported.
|
*/

return [
    // Wilbert burial vaults (catalog pages 4-8).
    ['catalog_sku' => 'W3086-B', 'weight_lbs' => 3000, 'page' => 4],
    ['catalog_sku' => 'W3086-BT', 'weight_lbs' => 2690, 'page' => 4],
    ['catalog_sku' => 'W3086-CT', 'weight_lbs' => 2690, 'page' => 4],
    ['catalog_sku' => 'W3086-SST', 'weight_lbs' => 2690, 'page' => 5],
    ['catalog_sku' => 'W3086-CAM', 'weight_lbs' => 2690, 'page' => 5],
    ['catalog_sku' => 'W3086-VET', 'weight_lbs' => 2690, 'page' => 6, 'aliases' => ['W3086-VT']],
    ['catalog_sku' => 'W3086-V', 'weight_lbs' => 2500, 'page' => 6],
    ['catalog_sku' => 'W3490-V', 'weight_lbs' => 2780, 'page' => 6],
    ['catalog_sku' => 'W3086-C', 'weight_lbs' => 2460, 'page' => 7],
    ['catalog_sku' => 'W3086-S', 'weight_lbs' => 2190, 'page' => 7],
    ['catalog_sku' => 'W3490-M', 'weight_lbs' => 2780, 'page' => 8],
    ['catalog_sku' => 'W3086-M', 'weight_lbs' => 2190, 'page' => 8],
    ['catalog_sku' => 'W2056-M', 'weight_lbs' => 1040, 'page' => 8],

    // Wilbert burial vault components.
    ['catalog_sku' => '2-3086WBT', 'weight_lbs' => 1190, 'page' => 4],
    ['catalog_sku' => '2-3086WV', 'weight_lbs' => 1050, 'page' => 6],
    ['catalog_sku' => '2-3086WC', 'weight_lbs' => 1010, 'page' => 7],
    ['catalog_sku' => '1-3086WM', 'weight_lbs' => 1450, 'page' => 8],
    ['catalog_sku' => '2-3086WM', 'weight_lbs' => 740, 'page' => 8],

    // Loved & Cherished infant/child products (catalog page 8).
    ['catalog_sku' => 'P200', 'weight_lbs' => 23.5, 'page' => 8],
    ['catalog_sku' => 'P100', 'weight_lbs' => 24, 'page' => 8],
    ['catalog_sku' => 'P50', 'weight_lbs' => 9, 'page' => 8],

    // Wilbert urn vaults (catalog pages 10-14).
    ['catalog_sku' => 'UV1212-BT', 'weight_lbs' => 104, 'page' => 10],
    ['catalog_sku' => 'UV1212-CT', 'weight_lbs' => 104, 'page' => 10],
    ['catalog_sku' => 'UV1212-SST', 'weight_lbs' => 104, 'page' => 11],
    ['catalog_sku' => 'UV1212-CAM', 'weight_lbs' => 104, 'page' => 11],
    ['catalog_sku' => 'UV1212-VET', 'weight_lbs' => 104, 'page' => 12],
    ['catalog_sku' => 'UV1212-V', 'weight_lbs' => 104, 'page' => 12],
    ['catalog_sku' => 'UV1212-VW', 'weight_lbs' => 104, 'page' => 13, 'aliases' => ['UV1212-WVS', 'UV1212-VWS']],
    ['catalog_sku' => 'UV1212-M', 'weight_lbs' => 87, 'page' => 13],
    ['catalog_sku' => 'UV712-VS', 'weight_lbs' => 76, 'page' => 14],
    ['catalog_sku' => 'UV712-VWS', 'weight_lbs' => 76, 'page' => 14, 'aliases' => ['UV712-WVS']],
    ['catalog_sku' => 'UV712-MS', 'weight_lbs' => 76, 'page' => 14],

    // Wilbert urn vault components.
    ['catalog_sku' => '1-1212UVV', 'weight_lbs' => 70, 'page' => 12],
    ['catalog_sku' => '2-1212UVVW', 'weight_lbs' => 34, 'page' => 13],
    ['catalog_sku' => '2-1212UVM', 'weight_lbs' => 25, 'page' => 13],
    ['catalog_sku' => '1-712UVM', 'weight_lbs' => 42, 'page' => 14],

    // Outer burial containers (catalog pages 17-18).
    ['catalog_sku' => 'L3086-4', 'weight_lbs' => 1175, 'page' => 17],
    ['catalog_sku' => 'L2472-4', 'weight_lbs' => 513, 'page' => 17],
    ['catalog_sku' => 'L2456-4', 'weight_lbs' => 396, 'page' => 17],
    ['catalog_sku' => 'L1849-4', 'weight_lbs' => 318, 'page' => 17],
    ['catalog_sku' => 'L1637-4', 'weight_lbs' => 250, 'page' => 17],
    ['catalog_sku' => 'L1431-4', 'weight_lbs' => 221, 'page' => 17],
    ['catalog_sku' => 'L1226-4', 'weight_lbs' => 134, 'page' => 17],
    ['catalog_sku' => 'V3290-1', 'weight_lbs' => 1644, 'page' => 17],
    ['catalog_sku' => 'V3086-1', 'weight_lbs' => 1288, 'page' => 17],
    ['catalog_sku' => 'V2464-1', 'weight_lbs' => 736, 'page' => 17],
    ['catalog_sku' => 'V1848-1', 'weight_lbs' => 500, 'page' => 17],
    ['catalog_sku' => 'V1637-1', 'weight_lbs' => 300, 'page' => 17],
    ['catalog_sku' => 'G4490-6', 'weight_lbs' => 3880, 'page' => 17, 'aliases' => ['G4490-G']],
    ['catalog_sku' => 'G3086-6', 'weight_lbs' => 1750, 'page' => 17],
    ['catalog_sku' => 'G2884-6', 'weight_lbs' => 1415, 'page' => 17],
    ['catalog_sku' => 'G3086-5', 'weight_lbs' => 2520, 'page' => 18],
    ['catalog_sku' => 'G3086-4', 'weight_lbs' => 3455, 'page' => 18],

    // Christy vault, liner, and garden crypt components.
    ['catalog_sku' => '3-3086L4', 'weight_lbs' => 600, 'page' => 17],
    ['catalog_sku' => '2-3086L4A', 'weight_lbs' => 575, 'page' => 17, 'aliases' => ['2-3086L4AS']],
    ['catalog_sku' => '2-3086V1', 'weight_lbs' => 413, 'page' => 17],
    ['catalog_sku' => '1-3086G6', 'weight_lbs' => 1175, 'page' => 17],
    ['catalog_sku' => '2-3690G5', 'weight_lbs' => 680, 'page' => 17],
    ['catalog_sku' => '1-3086G5', 'weight_lbs' => 1000, 'page' => 18],
    ['catalog_sku' => '3-3086G5', 'weight_lbs' => 640, 'page' => 18],
    ['catalog_sku' => '2-3086G5', 'weight_lbs' => 575, 'page' => 18],
    ['catalog_sku' => '4-3086G5', 'weight_lbs' => 305, 'page' => 18],
    ['catalog_sku' => '1-3086G4', 'weight_lbs' => 1535, 'page' => 18],
    ['catalog_sku' => '3-3086G4', 'weight_lbs' => 1040, 'page' => 18],

    // Concrete urn vaults and vases (catalog page 24).
    ['catalog_sku' => 'U77', 'weight_lbs' => 31, 'page' => 24],
    ['catalog_sku' => 'U7711', 'weight_lbs' => 41, 'page' => 24, 'aliases' => ['U711']],
    ['catalog_sku' => 'U1122', 'weight_lbs' => 146, 'page' => 24],
    ['catalog_sku' => 'U1020C', 'weight_lbs' => 88, 'page' => 24],
    ['catalog_sku' => 'M1A', 'weight_lbs' => 20, 'page' => 24],
    ['catalog_sku' => 'M1B', 'weight_lbs' => 10, 'page' => 24],

    // Printed totals conflict with the printed component weights; do not import.
    [
        'catalog_sku' => 'P400',
        'page' => 15,
        'review' => 'Catalog lists base 29 lb + cover 16 lb, but prints a 35 lb total.',
    ],
    [
        'catalog_sku' => 'P400WS',
        'page' => 15,
        'review' => 'Catalog lists base 29 lb + cover 16 lb, but prints a 35 lb total.',
    ],
    [
        'catalog_sku' => 'G3690-6',
        'page' => 17,
        'review' => 'Catalog lists base 1,085 lb + cover 680 lb, but prints a 2,240 lb total.',
    ],
];
