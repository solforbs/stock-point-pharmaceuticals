<?php

/*
|--------------------------------------------------------------------------
| Deployment (Part 17 — operations)
|--------------------------------------------------------------------------
| The Deployments screen reports the running version and starts the deploy
| script. `command` is the ONLY command that screen can run: it takes no
| arguments and nothing from the browser reaches it. On the server it needs
| a sudoers rule allowing www-data to run exactly that path, for example:
|
|   www-data ALL=(root) NOPASSWD: /usr/local/bin/stockpoint-deploy
|
| Set DEPLOY_ENABLED=false to hide the buttons on a machine where deploying
| from the browser is not wanted (a developer's laptop, for instance).
*/
return [
    'enabled' => (bool) env('DEPLOY_ENABLED', false),

    'root' => env('DEPLOY_ROOT', base_path()),

    'branch' => env('DEPLOY_BRANCH', 'main'),

    'command' => env('DEPLOY_COMMAND', 'sudo -n /usr/local/bin/stockpoint-deploy'),
];
