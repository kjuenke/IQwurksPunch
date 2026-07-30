<?php
declare(strict_types=1);

return [
    'delivery_retry' => [
        /*
         * The original scheduled delivery counts as attempt 1.
         * A value of 3 permits two subsequent retry attempts.
         */
        'scheduled_max_attempts' =>
            3,

        /*
         * Maximum number of eligible failures processed during one
         * automatic or manually requested retry run.
         */
        'batch_limit' =>
            10,

        /*
         * Failed deliveries must remain failed for at least this many
         * minutes before becoming eligible for automatic retry.
         */
        'delay_minutes' =>
            5
    ]
];
