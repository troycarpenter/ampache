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

use Ampache\Config\AmpConfig;
use Ampache\Module\Authorization\AccessLevelEnum;
use Ampache\Module\Authorization\AccessTypeEnum;
use Ampache\Repository\Model\Live_Stream;
use Ampache\Repository\Model\User;
use Ampache\Module\Authorization\Access;
use Ampache\Module\Api\Ajax;
use Ampache\Module\Util\Ui;

/** @var Ampache\Repository\Model\Browse $browse */
/** @var list<int> $object_ids */

$is_table     = !$browse->is_grid_view();
$show_ratings = (User::is_registered() && AmpConfig::get('ratings'));
//mashup and grid view need different css
$cel_cover = ($is_table) ? "cel_cover" : 'grid_cover';
$css_class = ($is_table) ? '' : ' gridview';
if (Access::check(AccessTypeEnum::INTERFACE, AccessLevelEnum::CONTENT_MANAGER)) { ?>
<?php Ui::show_box_top(T_('Manage'), 'info-box'); ?>
<div id="information_actions">
<ul>
<li>
    <a href="<?php echo AmpConfig::get_web_path(); ?>/radio.php?action=show_create">
        <?php echo Ui::get_material_symbol('add_circle', T_('Add')); ?>
        <?php echo T_('Add Radio Station'); ?>
    </a>
</li>
</ul>
</div>
<?php Ui::show_box_bottom(); ?>
<?php } ?>
<?php if ($browse->is_show_header()) {
    require Ui::find_template('list_header.inc.php');
} ?>
<table class="tabledata striped-rows <?php echo $css_class; ?>" data-objecttype="live_stream">
    <thead>
        <tr class="th-top">
            <th class="cel_play essential"></th>
            <th class="<?php echo $cel_cover; ?> optional"><?php echo T_('Art'); ?></th>
            <th class="cel_streamname essential persist"><?php echo Ajax::text('?page=browse&action=set_sort&browse_id=' . $browse->id . '&sort=name', T_('Name'), 'live_stream_sort_name'); ?></th>
            <th class="cel_add essential"></th>
            <th class="cel_siteurl optional"><?php echo Ajax::text('?page=browse&action=set_sort&browse_id=' . $browse->id . '&sort=site_url', T_('Website'), 'live_stream_sort_site_url'); ?></th>
            <th class="cel_codec optional"><?php echo Ajax::text('?page=browse&action=set_sort&browse_id=' . $browse->id . '&sort=codec', T_('Codec'), 'live_stream_codec'); ?></th>
            <?php if ($show_ratings) { ?>
            <th class="cel_ratings optional"><?php echo Ajax::text('?page=browse&action=set_sort&browse_id=' . $browse->id . '&sort=rating', T_('Rating'), 'live_stream_sort_rating'); ?></th>
            <?php } ?>
            <th class="cel_action essential"><?php echo T_('Action'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($object_ids as $radio_id) {
            $libitem = new Live_Stream($radio_id);
            if ($libitem->isNew()) {
                continue;
            } ?>
        <tr id="live_stream_<?php echo $libitem->id; ?>">
                <?php require Ui::find_template('show_live_stream_row.inc.php'); ?>
        </tr>
        <?php } ?>
        <?php if (!count($object_ids)) { ?>
        <tr>
            <td colspan="6"><span class="nodata"><?php echo T_('No live stream found'); ?></span></td>
        </tr>
        <?php } ?>
    </tbody>
    <tfoot>
        <tr class="th-bottom">
            <th class="cel_play"></th>
            <th class="<?php echo $cel_cover; ?>"><?php echo T_('Art'); ?></th>
            <th class="cel_streamname"></th>
            <th class="cel_add essential"></th>
            <th class="cel_siteurl"><?php echo T_('Website'); ?></th>
            <th class="cel_codec"></th>
            <?php if ($show_ratings) { ?>
            <th class="cel_ratings optional"><?php echo T_('Rating'); ?></th>
            <?php } ?>
            <th class="cel_action"><?php echo T_('Action'); ?> </th>
        </tr>
    </tfoot>
</table>
<?php if ($browse->is_show_header()) {
    require Ui::find_template('list_header.inc.php');
} ?>
