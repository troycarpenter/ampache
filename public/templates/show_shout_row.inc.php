<?php

declare(strict_types=0);

/**
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright Ampache.org, 2001-2024
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

use Ampache\Repository\Model\library_item;
use Ampache\Repository\Model\Shoutbox;
use Ampache\Module\Util\Ui;
use Ampache\Repository\Model\User;

/** @var Shoutbox $libitem */
/** @var library_item $object */
/** @var User $client */
/** @var string $web_path */
/** @var string $admin_path */
/** @var string $t_edit */
/** @var string $t_delete */
/** @var string $t_yes */
/** @var string $t_no */
?>
<tr id="flagged_<?php echo $libitem->getId(); ?>">
    <td class="cel_object"><?php echo $object->get_f_link(); ?></td>
    <td class="cel_username"><?php echo $client->get_f_link(); ?></td>
    <td class="cel_sticky"><?php echo $libitem->isSticky() ? $t_yes : $t_no; ?></td>
    <td class="cel_comment"><?php echo scrub_out($libitem->getText()); ?></td>
    <td class="cel_date"><?php echo get_datetime($libitem->getDate()); ?></td>
    <td class="cel_action">
        <a href="<?php echo $admin_path; ?>/shout.php?action=show_edit&shout_id=<?php echo $libitem->getId(); ?>">
            <?php echo Ui::get_material_symbol('edit', $t_edit); ?>
        </a>
        <a href="<?php echo $admin_path; ?>/shout.php?action=delete&shout_id=<?php echo $libitem->getId(); ?>">
            <?php echo Ui::get_material_symbol('close', $t_delete); ?>
        </a>
    </td>
</tr>
