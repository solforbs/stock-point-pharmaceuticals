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
|   www-data ALL=(root) NOPASSWD: /usr/local/bin/stockpoint-deploy, /usr/local/bin/stockpoint-fetch
|
| Set DEPLOY_ENABLED=false to hide the buttons on a machine where deploying
| from the browser is not wanted (a developer's laptop, for instance).
*/
return [
    'enabled' => (bool) env('DEPLOY_ENABLED', false),

    'root' => env('DEPLOY_ROOT', base_path()),

    'branch' => env('DEPLOY_BRANCH', 'main'),

    'command' => env('DEPLOY_COMMAND', 'sudo -n /usr/local/bin/stockpoint-deploy'),

    /*
     | Checking for updates needs the GitHub deploy key, and the web user must
     | never hold one: it goes through the same narrow sudo door as the deploy,
     | so the key stays root-only (0600) where OpenSSH is happy with it.
     */
    'fetch_command' => env('DEPLOY_FETCH_COMMAND', 'sudo -n /usr/local/bin/stockpoint-fetch'),
];
