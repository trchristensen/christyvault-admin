<?php

/*
|--------------------------------------------------------------------------
| Confirmed product loading-profile assignments
|--------------------------------------------------------------------------
|
| These mappings intentionally contain only products whose physical loading
| behavior is known well enough to support planning. Components, companion
| crypt assemblies, and uncertain liners remain unassigned for review.
|
*/

return [
    // Standard full-size burial vaults and single vault/crypt products.
    ['sku' => 'W3086-B', 'profile_code' => 'regular_burial_vault'],
    ['sku' => 'W3086-BT', 'profile_code' => 'regular_burial_vault_triune'],
    ['sku' => 'W3086-CT', 'profile_code' => 'regular_burial_vault_triune'],
    ['sku' => 'W3086-SST', 'profile_code' => 'regular_burial_vault_triune'],
    ['sku' => 'W3086-CAM', 'profile_code' => 'regular_burial_vault_triune'],
    ['sku' => 'W3086-VET', 'profile_code' => 'regular_burial_vault_triune'],
    ['sku' => 'W3086-VT', 'profile_code' => 'regular_burial_vault_triune'],
    ['sku' => 'W3086-V', 'profile_code' => 'regular_burial_vault'],
    ['sku' => 'W3086-V (WG)', 'profile_code' => 'regular_burial_vault'],
    ['sku' => 'W3086-C', 'profile_code' => 'regular_burial_vault'],
    ['sku' => 'W3086-S', 'profile_code' => 'regular_burial_vault'],
    ['sku' => 'W3086-M', 'profile_code' => 'regular_burial_vault'],
    ['sku' => 'G2884-6', 'profile_code' => 'standard_rack_box'],
    ['sku' => 'G3086-6', 'profile_code' => 'standard_three_high_box'],
    ['sku' => 'V3086-1', 'profile_code' => 'standard_three_high_box'],
    ['sku' => 'L3086-4', 'profile_code' => 'standard_three_high_box'],
    ['sku' => 'L2472-4', 'profile_code' => 'ring_liner_three_high'],
    ['sku' => 'G3086-4', 'profile_code' => 'double_garden_crypt'],
    ['sku' => 'G3086-5', 'profile_code' => 'double_garden_crypt'],
    ['sku' => '2-3690G5', 'profile_code' => 'garden_crypt_cover_4_high'],
    ['sku' => '2-3086G5', 'profile_code' => 'garden_crypt_cover_6_lower_bays'],
    ['sku' => 'V1637-1', 'profile_code' => 'christy_1637_vault_lower_bays_flatbed'],
    ['sku' => 'V2464-1', 'profile_code' => 'christy_2464_vault_rack_flatbed'],
    ['sku' => '2-1637V1', 'profile_code' => 'christy_1637_cover_4_per_pallet'],

    // Products explicitly identified as oversized or grande.
    ['sku' => 'W3490-M', 'profile_code' => 'oversized_single_rack'],
    ['sku' => 'W3490-V', 'profile_code' => 'oversized_single_rack'],
    ['sku' => 'G3290-6', 'profile_code' => 'oversized_single_rack'],
    ['sku' => 'G3690-6', 'profile_code' => 'oversized_single_rack'],
    ['sku' => 'G3696-6', 'profile_code' => 'oversized_single_rack'],
    ['sku' => 'G4490-6', 'profile_code' => 'oversized_single_rack'],
    ['sku' => 'G4490-G', 'profile_code' => 'oversized_single_rack'],
    ['sku' => 'V3290-1', 'profile_code' => 'oversized_single_rack'],

    // Complete Wilbert urn vaults: four products per pallet.
    ['sku' => 'UV1212-BT', 'profile_code' => 'wilbert_urn_vault_pallet'],
    ['sku' => 'UV1212-CT', 'profile_code' => 'wilbert_urn_vault_pallet'],
    ['sku' => 'UV1212-SST', 'profile_code' => 'wilbert_urn_vault_pallet'],
    ['sku' => 'UV1212-CAM', 'profile_code' => 'wilbert_urn_vault_pallet'],
    ['sku' => 'UV1212-VET', 'profile_code' => 'wilbert_urn_vault_pallet'],
    ['sku' => 'UV1212-V', 'profile_code' => 'wilbert_urn_vault_pallet'],
    ['sku' => 'UV1212-VWS', 'profile_code' => 'wilbert_urn_vault_pallet'],
    ['sku' => 'UV1212-WVS', 'profile_code' => 'wilbert_urn_vault_pallet'],
    ['sku' => 'UV712-VS', 'profile_code' => 'wilbert_urn_vault_pallet'],
    ['sku' => 'UV712-WVS', 'profile_code' => 'wilbert_urn_vault_pallet'],
    ['sku' => 'UV712-MS', 'profile_code' => 'wilbert_urn_vault_pallet'],
    ['sku' => 'UV1212-M', 'profile_code' => 'wilbert_urn_vault_pallet'],

    // Lightweight boxed urn products. All profiles in this family may share a mixed pallet.
    ['sku' => 'P300', 'profile_code' => 'boxed_urn_products_18_per_pallet'],
    ['sku' => 'P310', 'profile_code' => 'boxed_urn_products_18_per_pallet'],
    ['sku' => 'P300P', 'profile_code' => 'boxed_urn_products_18_per_pallet'],
    ['sku' => 'P310P', 'profile_code' => 'boxed_urn_products_18_per_pallet'],
    ['sku' => 'P300WS', 'profile_code' => 'boxed_urn_products_18_per_pallet'],
    ['sku' => 'P310WS', 'profile_code' => 'boxed_urn_products_18_per_pallet'],
    ['sku' => 'P400', 'profile_code' => 'boxed_urn_products_9_per_pallet'],
    ['sku' => 'P410', 'profile_code' => 'boxed_urn_products_9_per_pallet'],
    ['sku' => 'P400WS', 'profile_code' => 'boxed_urn_products_9_per_pallet'],

    // Granite marker bases: three products per pallet.
    ['sku' => 'G1814V2-3.75', 'profile_code' => 'granite_marker_bases_3_per_pallet'],
    ['sku' => 'G2412V2-4', 'profile_code' => 'granite_marker_bases_3_per_pallet'],
    ['sku' => 'G2412V2-3.75', 'profile_code' => 'granite_marker_bases_3_per_pallet'],
    ['sku' => 'G2412V2-3.75 (deep)', 'profile_code' => 'granite_marker_bases_3_per_pallet'],
    ['sku' => 'G1814SV2-3.75', 'profile_code' => 'granite_marker_bases_3_per_pallet'],
    ['sku' => 'G2412NV', 'profile_code' => 'granite_marker_bases_3_per_pallet'],
    ['sku' => 'G2816NV', 'profile_code' => 'granite_marker_bases_3_per_pallet'],
    ['sku' => 'G2412SV-4', 'profile_code' => 'granite_marker_bases_3_per_pallet'],
    ['sku' => 'G1810V1-4', 'profile_code' => 'granite_marker_bases_3_per_pallet'],
    ['sku' => 'G2412V1-5', 'profile_code' => 'granite_marker_bases_3_per_pallet'],
    ['sku' => 'G2412V1-4', 'profile_code' => 'granite_marker_bases_3_per_pallet'],

    // Bronze marker bases: six products per pallet.
    ['sku' => 'B2211NV', 'profile_code' => 'bronze_marker_bases_6_per_pallet'],
    ['sku' => 'B2413NV', 'profile_code' => 'bronze_marker_bases_6_per_pallet'],
    ['sku' => 'B2414NV', 'profile_code' => 'bronze_marker_bases_6_per_pallet'],
    ['sku' => 'B3613V1-6', 'profile_code' => 'bronze_marker_bases_6_per_pallet'],
    ['sku' => 'B2412V1-5SL', 'profile_code' => 'bronze_marker_bases_6_per_pallet'],
    ['sku' => 'B2416V1-6', 'profile_code' => 'bronze_marker_bases_6_per_pallet'],
    ['sku' => 'B2412V2-3.75', 'profile_code' => 'bronze_marker_bases_6_per_pallet'],
    ['sku' => 'B2412V2-4', 'profile_code' => 'bronze_marker_bases_6_per_pallet'],
    ['sku' => 'B2816V2-4', 'profile_code' => 'bronze_marker_bases_6_per_pallet'],
    ['sku' => 'B2412V1-4', 'profile_code' => 'bronze_marker_bases_6_per_pallet'],
    ['sku' => 'B2412V1-5', 'profile_code' => 'bronze_marker_bases_6_per_pallet'],
    ['sku' => 'B2816V1-4', 'profile_code' => 'bronze_marker_bases_6_per_pallet'],
];
