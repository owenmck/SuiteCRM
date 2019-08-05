<?php
$viewdefs['Accounts'] = [
    'EditView' => [
        'templateMeta' => [
            'form' => [
                'buttons' => [
                    0 => 'SAVE',
                    1 => 'CANCEL',
                ],
            ],
            'maxColumns' => '2',
            'widths' => [
                0 => [
                    'label' => '10',
                    'field' => '30',
                ],
                1 => [
                    'label' => '10',
                    'field' => '30',
                ],
            ],
            'includes' => [
                0 => [
                    'file' => 'modules/Accounts/Account.js',
                ],
            ],
            'useTabs' => false,
            'tabDefs' => [
                'LBL_ACCOUNT_INFORMATION' => [
                    'newTab' => false,
                    'panelDefault' => 'expanded',
                ],
                'LBL_PANEL_ADVANCED' => [
                    'newTab' => false,
                    'panelDefault' => 'expanded',
                ],
            ],
            'syncDetailEditViews' => true,
        ],
        'panels' => [
            'lbl_account_information' => [
                0 => [
                    0 => [
                        'name' => 'abn_c',
                        'label' => 'LBL_ABN',
                    ],
                    1 => [
                        'name' => 'abn_details_hash_c',
                        'label' => 'LBL_ABN_DETAILS_HASH',
                    ],
                ],
                1 => [
                    0 => [
                        'name' => 'name',
                        'label' => 'LBL_NAME',
                        'displayParams' => [
                            'required' => true,
                        ],
                    ],
                    1 => [
                        'name' => 'phone_office',
                        'label' => 'LBL_PHONE_OFFICE',
                    ],
                ],
                2 => [
                    0 => [
                        'name' => 'website',
                        'type' => 'link',
                        'label' => 'LBL_WEBSITE',
                    ],
                    1 => [
                        'name' => 'phone_fax',
                        'label' => 'LBL_FAX',
                    ],
                ],
                3 => [
                    0 => [
                        'name' => 'email1',
                        'studio' => 'false',
                        'label' => 'LBL_EMAIL',
                    ],
                ],
                4 => [
                    0 => [
                        'name' => 'billing_address_street',
                        'hideLabel' => true,
                        'type' => 'address',
                        'displayParams' => [
                            'key' => 'billing',
                            'rows' => 2,
                            'cols' => 30,
                            'maxlength' => 150,
                        ],
                    ],
                    1 => [
                        'name' => 'shipping_address_street',
                        'hideLabel' => true,
                        'type' => 'address',
                        'displayParams' => [
                            'key' => 'shipping',
                            'copy' => 'billing',
                            'rows' => 2,
                            'cols' => 30,
                            'maxlength' => 150,
                        ],
                    ],
                ],
                5 => [
                    0 => [
                        'name' => 'description',
                        'label' => 'LBL_DESCRIPTION',
                    ],
                ],
                6 => [
                    0 => [
                        'name' => 'assigned_user_name',
                        'label' => 'LBL_ASSIGNED_TO',
                    ],
                ],
            ],
            'LBL_PANEL_ADVANCED' => [
                0 => [
                    0 => 'account_type',
                    1 => 'industry',
                ],
                1 => [
                    0 => 'annual_revenue',
                    1 => 'employees',
                ],
                2 => [
                    0 => 'parent_name',
                ],
                3 => [
                    0 => 'campaign_name',
                ],
            ],
        ],
    ],
];
