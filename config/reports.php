<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduled report archive (Part 20.3)
    |--------------------------------------------------------------------------
    |
    | Every scheduled-report run keeps its CSV in the app for review. Runs
    | older than this many days are removed, with their files, every night.
    |
    */

    'run_retention_days' => (int) env('REPORT_RUN_RETENTION_DAYS', 180),

];
