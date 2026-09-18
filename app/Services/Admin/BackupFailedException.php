<?php

namespace App\Services\Admin;

/**
 * A database backup could not be taken — mysqldump is missing, refused the
 * credentials or wrote nothing. The message is shown to the administrator
 * as-is, so it says what to fix.
 */
class BackupFailedException extends \RuntimeException {}
