<?php

return [
    0 => [
        'name' => 'Person',
        'attributes' => [
            0 => [
                'name' => 'name',
                'type' => 'string',
            ],
            1 => [
                'name' => 'phonenumber',
                'type' => 'string',
            ],
            2 => [
                'name' => 'emailaddress',
                'type' => 'string',
            ],
            3 => [
                'name' => 'address',
                'type' => 'Address',
            ],
        ],
    ],
    1 => [
        'name' => 'Student',
        'attributes' => [
            0 => [
                'name' => 'studentnumber',
                'type' => 'integer',
            ],
            1 => [
                'name' => 'averagemark',
                'type' => 'integer',
            ],
        ],
    ],
    2 => [
        'name' => 'Professor',
        'attributes' => [
            0 => [
                'name' => 'name',
                'type' => 'void',
            ],
            1 => [
                'name' => 'staffnumber',
                'type' => 'integer',
            ],
            2 => [
                'name' => 'yearsofservice',
                'type' => 'integer',
            ],
            3 => [
                'name' => 'numberofclasses',
                'type' => 'integer',
            ],
        ],
    ],
    3 => [
        'name' => 'Address',
        'attributes' => [
            0 => [
                'name' => 'street',
                'type' => 'string',
            ],
            1 => [
                'name' => 'city',
                'type' => 'string',
            ],
            2 => [
                'name' => 'state',
                'type' => 'string',
            ],
            3 => [
                'name' => 'postalcode',
                'type' => 'integer',
            ],
            4 => [
                'name' => 'country',
                'type' => 'string',
            ],
        ],
    ],
    4 => [
        'name' => 'Adresses',
        'attributes' => [
            0 => [
                'name' => 'city',
                'type' => 'string',
            ],
            1 => [
                'name' => 'state',
                'type' => 'string',
            ],
            2 => [
                'name' => 'postalcode',
                'type' => 'integer',
            ],
            3 => [
                'name' => 'country',
                'type' => 'string',
            ],
        ],
    ],
];
