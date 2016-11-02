<?php

return [
    'events' => [
        // @TODO get rid of default count dependency
        'defaultCount' => 4,
        'defaultTimeframe' => '+ 1 month',
        'dateRanges' => [
            [
                'description' => 'Previous Year',
                'rangeStart'  => -12,
                'rangeEnd'    => -4,
                'cacheMaxAge' => 3600 * 24 * 30
            ],
            [
                'description' => 'Previous 3 Months',
                'rangeStart'  => -3,
                'rangeEnd'    => -1,
                'cacheMaxAge' => 3600 * 24 * 7
            ],
            [
                'description' => 'Current 3 Months',
                'rangeStart'  => 0,
                'rangeEnd'    => 3,
                'cacheMaxAge' => 3600
            ],
            [
                'description' => 'Next Year',
                'rangeStart'  => 4,
                'rangeEnd'    => 12,
                'cacheMaxAge' => 3600 * 24 * 7
            ],
            [
                'description' => 'Next 2 Years',
                'rangeStart'  => 13,
                'rangeEnd'    => 24,
                'cacheMaxAge' => 3600 * 24 * 30
            ]
        ],
    ],
    'bible' => [
        'defaultReference' => 'John+3:16',
    ],
];
