<?php
/**
 * DVCSHOW Editor — password configuration
 *
 * Copy this file to editor-config.php on the server, then replace the hash
 * below with a real one generated for your chosen password.
 *
 * GENERATE A HASH:
 *   On the VPS, run:
 *     php -r "echo password_hash('CHANGE-THIS-TO-YOUR-PASSWORD', PASSWORD_BCRYPT) . PHP_EOL;"
 *   Copy the entire output (starts with $2y$10$...) into the line below.
 *
 * NEVER commit editor-config.php to source control.
 */

$EDITOR_PASSWORD_HASH = '$2y$10$REPLACE_THIS_WITH_GENERATED_HASH';
