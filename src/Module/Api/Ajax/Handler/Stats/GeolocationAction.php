<?php
/*
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
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
 */

declare(strict_types=0);

namespace Ampache\Module\Api\Ajax\Handler\Stats;

use Ampache\Config\AmpConfig;
use Ampache\Module\Api\Ajax\Handler\ActionInterface;
use Ampache\Module\Statistics\Stats;
use Ampache\Module\System\Core;
use Ampache\Module\System\Session;
use Ampache\Repository\Model\Plugin;
use Ampache\Repository\Model\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class GeolocationAction implements ActionInterface
{
    public function handle(
        ServerRequestInterface $request,
        ResponseInterface $response,
        User $user
    ): array {
        if (AmpConfig::get('geolocation')) {
            if (Core::get_global('user')->id) {
                $latitude  = (float) $_REQUEST['latitude'];
                $longitude = (float) $_REQUEST['longitude'];
                $name      = $_REQUEST['name'];
                if (empty($name)) {
                    // First try to get from local cache (avoid external api requests)
                    $name = Stats::get_cached_place_name($latitude, $longitude);
                    if (empty($name)) {
                        foreach (Plugin::get_plugins('get_location_name') as $plugin_name) {
                            $plugin = new Plugin($plugin_name);
                            if ($plugin->load(Core::get_global('user'))) {
                                $name = $plugin->_plugin->get_location_name($latitude, $longitude);
                                if (!empty($name)) {
                                    break;
                                }
                            }
                        }
                    }
                }

                // Better to check for bugged values here and keep previous user good location
                // Someone listing music at 0.0,0.0 location would need a waterproof music player btw
                if ($latitude > 0 && $longitude > 0) {
                    Session::update_geolocation((string) session_id(), $latitude, $longitude, $name);
                }
            }
        } else {
            debug_event('stats.ajax', 'Geolocation not enabled for the user.', 3);
        }

        return [];
    }
}
