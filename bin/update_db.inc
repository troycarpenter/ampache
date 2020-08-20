<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright 2001 - 2020 Ampache.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

define('NO_SESSION', '1');
$path   = dirname(__FILE__);
$prefix = realpath($path . '/../');
$a_root = realpath(__DIR__ . "/../");
require_once $a_root . '/lib/init.php';

$options = getopt("x");

// will not change anything if debug is true
// Require "-x" to disable debug mode
$test_mode = !array_key_exists('x', $options);

echo "\n-----------------\n" .
    "bin/update_db.inc" .
    "\n-----------------\n";

$translated_charset = Dba::translate_to_mysqlcharset(AmpConfig::get('site_charset'));
$target_charset     = $translated_charset['charset'];
$engine_sql         = ($translated_charset['charset'] == 'utf8mb4') ? 'ENGINE=InnoDB' : '';
$target_collation   = $translated_charset['collation'];
$table_engine       = ($target_charset == 'utf8mb4') ? 'InnoDB' : 'MYISAM';

echo T_('This script makes changes to your database based on your config settings') . "\n\n";
echo T_('Target charset') . ":   $target_charset\n";
echo T_('Target collation') . ": $target_collation\n";
echo T_('Table engine') . ":     $table_engine\n\n";


if ($test_mode) {
    echo "\n" . T_('Running in Test Mode. Use -x to execute') . "\n\n";
    echo "***" . T_('No changes have been made') . "***\n\n";
} else {
    echo "\n***" . T_("WARNING") . "*** " . T_("Running in Write Mode. Make sure you've tested first!") . "\n\n";
    Dba::reset_db_charset();
}