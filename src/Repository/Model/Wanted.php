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
 *
 */

declare(strict_types=0);

namespace Ampache\Repository\Model;

use Ampache\Config\AmpConfig;
use Ampache\Module\Api\Ajax;
use Ampache\Module\System\Core;
use Ampache\Module\System\Dba;
use Ampache\Module\Wanted\Gui\MusicBrainzResultWantedGuiItem;
use Ampache\Module\Wanted\Gui\WantedUiItem;
use Ampache\Module\Wanted\MissingArtistLookupInterface;
use Ampache\Repository\AlbumRepositoryInterface;
use Ampache\Repository\WantedRepositoryInterface;
use Exception;
use MusicBrainz\MusicBrainz;

final class Wanted extends database_object implements WantedInterface
{
    protected const DB_TABLENAME = 'wanted';

    private WantedRepositoryInterface $wantedRepository;

    private MusicBrainz $musicBrainz;

    private MissingArtistLookupInterface $missingArtistLookup;

    public int $id;

    /** @var array<Song_Preview>|null */
    private ?array $songs = null;

    /** @var array<string, mixed>|null */
    private ?array $dbData = null;

    private ModelFactoryInterface $modelFactory;

    public function __construct(
        WantedRepositoryInterface $wantedRepository,
        MusicBrainz $musicBrainz,
        MissingArtistLookupInterface $missingArtistLookup,
        ModelFactoryInterface $modelFactory,
        int $id
    ) {
        $this->wantedRepository    = $wantedRepository;
        $this->musicBrainz         = $musicBrainz;
        $this->id                  = $id;
        $this->modelFactory        = $modelFactory;
        $this->missingArtistLookup = $missingArtistLookup;
    }

    private function getDbData(): array
    {
        if ($this->dbData === null) {
            $this->dbData = $this->wantedRepository->getById($this->id);
        }

        return $this->dbData;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function IsNew(): bool
    {
        return $this->getDbData() === [];
    }

    public function getUserId(): int
    {
        return (int) ($this->getDbData()['user'] ?? 0);
    }

    public function getReleaseMusicBrainzId(): string
    {
        return $this->getDbData()['release_mbid'] ?? '';
    }

    public function getAccepted(): bool
    {
        return (bool) ($this->getDbData()['accepted'] ?? 0);
    }

    public function setAccepted(bool $value): WantedInterface
    {
        $this->getDbData();

        $this->dbData['accepted'] = (int) $value;

        return $this;
    }

    public function getYear(): int
    {
        return (int) ($this->getDbData()['year'] ?? 0);
    }

    public function setYear(int $value): WantedInterface
    {
        $this->getDbData();

        $this->dbData['year'] = $value;

        return $this;
    }

    public function getName(): string
    {
        return $this->getDbData()['name'] ?? '';
    }

    public function setName(string $value): WantedInterface
    {
        $this->getDbData();

        $this->dbData['name'] = $value;

        return $this;
    }

    public function getArtistMusicBrainzId(): string
    {
        return $this->getDbData()['artist_mbid'] ?? '';
    }

    public function setArtistMusicBrainzId(string $value): WantedInterface
    {
        $this->getDbData();

        $this->dbData['artist_mbid'] = $value;

        return $this;
    }

    public function getArtistId(): int
    {
        return (int) ($this->getDbData()['artist'] ?? 0);
    }

    public function setArtistId(int $value): WantedInterface
    {
        $this->getDbData();

        $this->dbData['artist'] = $value;

        return $this;
    }

    public function getMusicBrainzId(): string
    {
        return $this->getDbData()['mbid'] ?? '';
    }

    public function setMusicBrainzId(string $value): WantedInterface
    {
        $this->getDbData();

        $this->dbData['mbid'] = $value;

        return $this;
    }

    public function getSongs(): array
    {
        if ($this->songs === null) {
            $this->load_all();
        }

        return $this->songs;
    }

    public function getArtistLink(): string
    {
        if ($this->getArtistId()) {
            $artist = $this->modelFactory->createArtist($this->getArtistId());
            $artist->format();

            return $artist->f_link;
        } else {
            return $this->missingArtistLookup->lookup($this->getArtistMusicBrainzId())['link'];
        }
    }

    public function getLinkFormatted(): string
    {
        return sprintf(
            "<a href=\"%s\">%s</a>",
            AmpConfig::get('web_path') . "/albums.php?action=show_missing&mbid=" . $this->getMusicBrainzId() . "&artist=" . $this->getArtistId() . "&artist_mbid=" . $this->getArtistMusicBrainzId() . "\" title=\"" . $this->getName(),
            $this->getName()
        );
    }

    /**
     * get_missing_albums
     * Get list of library's missing albums from MusicBrainz
     * @param Artist|null $artist
     * @param string $mbid
     * @return array
     * @throws \MusicBrainz\Exception
     */
    public static function get_missing_albums($artist, $mbid = '')
    {
        $mbrainz  = static::getMusicBrainz();
        $includes = array('release-groups');
        $types    = explode(',', AmpConfig::get('wanted_types'));

        try {
            $martist = $mbrainz->lookup('artist', $artist ? $artist->mbid : $mbid, $includes);
        } catch (Exception $error) {
            debug_event(self::class, 'get_missing_albums ERROR: ' . $error, 3);

            return null;
        }

        $owngroups = array();
        $wartist   = array();
        if ($artist) {
            $albums = static::getAlbumRepository()->getByArtist($artist->id);
            foreach ($albums as $albumid) {
                $album = new Album($albumid);
                if (trim((string)$album->mbid_group)) {
                    $owngroups[] = $album->mbid_group;
                } else {
                    if (trim((string)$album->mbid)) {
                        $malbum = $mbrainz->lookup('release', $album->mbid, array('release-groups'));
                        if ($malbum->{'release-group'}) {
                            if (!in_array($malbum->{'release-group'}->id, $owngroups)) {
                                $owngroups[] = $malbum->{'release-group'}->id;
                            }
                        }
                    }
                }
            }
        } else {
            $wartist['mbid'] = $mbid;
            $wartist['name'] = $martist->name;

            $wartist = static::getMissingArtistLookup()->lookup($mbid);
        }

        $modelFactory = static::getModelFactory();

        $results = array();
        foreach ($martist->{'release-groups'} as $group) {
            if (in_array(strtolower((string)$group->{'primary-type'}), $types)) {
                $add     = true;
                $g_count = count($group->{'secondary-types'});

                for ($i = 0; $i < $g_count && $add; ++$i) {
                    $add = in_array(strtolower((string)$group->{'secondary-types'}[$i]), $types);
                }

                if ($add) {
                    debug_event(self::class, 'get_missing_albums ADDING: ' . $group->title, 5);
                    if (!in_array($group->id, $owngroups)) {
                        $wantedid = static::getWantedRepository()->getByMusicbrainzId($group->id);
                        $wanted   = $modelFactory->createWanted($wantedid);
                        if ($wanted->id) {
                            $results[] = new WantedUiItem(
                                static::getWantedRepository(),
                                Core::get_global('user'),
                                $wanted
                            );
                        } else {
                            $results[] = new MusicBrainzResultWantedGuiItem(
                                $artist,
                                $group,
                                $mbid,
                                $wartist['link']
                            );
                        }
                    }
                }
            }
        }

        return $results;
    } // get_missing_albums

    /**
     * Delete a wanted release by mbid.
     * @param string $mbid
     * @throws \MusicBrainz\Exception
     */
    public static function delete_wanted_release($mbid)
    {
        if (static::getWantedRepository()->getAcceptedCount() > 0) {
            $mbrainz = static::getMusicBrainz();
            $malbum  = $mbrainz->lookup('release', $mbid, array('release-groups'));
            if ($malbum->{'release-group'}) {
                $userId = Core::get_global('user')->has_access('75') ? null : Core::get_global('user')->id;
                static::getWantedRepository()->deleteByMusicbrainzId(
                    print_r($malbum->{'release-group'}, true),
                    $userId
                );
            }
        }
    }

    /**
     * Accept a wanted request.
     */
    public function accept()
    {
        if (Core::get_global('user')->has_access('75')) {
            $sql = "UPDATE `wanted` SET `accepted` = '1' WHERE `mbid` = ?";
            Dba::write($sql, array($this->getMusicBrainzId()));

            $this->setAccepted(true);

            foreach (Plugin::get_plugins('process_wanted') as $plugin_name) {
                $plugin = new Plugin($plugin_name);
                if ($plugin->load(Core::get_global('user'))) {
                    debug_event(self::class, 'Using Wanted Process plugin: ' . $plugin_name, 5);
                    $plugin->_plugin->process_wanted($this);
                }
            }
        }
    }

    /**
     * Show action buttons.
     */
    public function show_action_buttons()
    {
        if ($this->id) {
            if (!$this->getAccepted()) {
                if (Core::get_global('user')->has_access('75')) {
                    echo Ajax::button('?page=index&action=accept_wanted&mbid=' . $this->getMusicBrainzId(), 'enable', T_('Accept'),
                        'wanted_accept_' . $this->getMusicBrainzId());
                }
            }
            if (
                Core::get_global('user')->has_access('75') ||
                (
                    static::getWantedRepository()->find($this->getMusicBrainzId(), Core::get_global('user')->id) &&
                    $this->getAccepted() != '1'
                )
            ) {
                echo " " . Ajax::button('?page=index&action=remove_wanted&mbid=' . $this->getMusicBrainzId(), 'disable', T_('Remove'),
                        'wanted_remove_' . $this->getMusicBrainzId());
            }
        } else {
            echo Ajax::button('?page=index&action=add_wanted&mbid=' . $this->getMusicBrainzId() . ($this->getArtistId() ? '&artist=' . $this->getArtistId() : '&artist_mbid=' . $this->getArtistMusicBrainzId()) . '&name=' . urlencode($this->getName()) . '&year=' . $this->getYear(), 'add_wanted', T_('Add to wanted list'), 'wanted_add_' . $this->getMusicBrainzId());
        }
    }

    /**
     * Load wanted release data.
     */
    private function load_all()
    {
        $this->songs = array();

        try {
            $group = $this->musicBrainz->lookup('release-group', $this->getMusicBrainzId(), array('releases'));
            // Set fresh data
            $this->setName($group->title);
            $this->setYear((int) date("Y", strtotime($group->{'first-release-date'})));

            // Load from database if already cached
            $this->songs = Song_Preview::get_song_previews($this->getMusicBrainzId());
            if (count($group->releases) > 0) {
                if (count($this->songs) == 0) {
                    // Use the first release as reference for track content
                    $release = $this->musicBrainz->lookup('release', $group->releases[0]->id, array('recordings'));
                    foreach ($release->media as $media) {
                        foreach ($media->tracks as $track) {
                            $song          = array();
                            $song['disk']  = Album::sanitize_disk($media->position);
                            $song['track'] = $track->number;
                            $song['title'] = $track->title;
                            $song['mbid']  = $track->id;
                            if ($this->getArtistId()) {
                                $song['artist'] = $this->getArtistId();
                            }
                            $song['artist_mbid'] = $this->getArtistMusicBrainzId();
                            $song['session']     = session_id();
                            $song['album_mbid']  = $this->getMusicBrainzId();

                            if ($this->getArtistId()) {
                                $artist      = $this->modelFactory->createArtist($this->getArtistId());
                                $artist_name = $artist->name;
                            } else {
                                $wartist     = static::getMissingArtistLookup()->lookup($this->getArtistMusicBrainzId());
                                $artist_name = $wartist['name'];
                            }

                            $song['file'] = null;
                            foreach (Plugin::get_plugins('get_song_preview') as $plugin_name) {
                                $plugin = new Plugin($plugin_name);
                                if ($plugin->load(Core::get_global('user'))) {
                                    $song['file'] = $plugin->_plugin->get_song_preview($track->id, $artist_name,
                                        $track->title);
                                    if ($song['file'] != null) {
                                        break;
                                    }
                                }
                            }

                            if ($song != null) {
                                $this->songs[] = new Song_Preview(Song_Preview::insert($song));
                            }
                        }
                    }
                }
            }
        } catch (Exception $error) {
            $this->songs = array();
        }

        foreach ($this->songs as $song) {
            $song->f_album = $this->getName();
            $song->format();
        }
    }

    public function getUser(): User
    {
        $user = new User($this->getUserId());
        $user->format();

        return $user;
    }

    private static function getAlbumRepository(): AlbumRepositoryInterface
    {
        global $dic;

        return $dic->get(AlbumRepositoryInterface::class);
    }

    private static function getWantedRepository(): WantedRepositoryInterface
    {
        global $dic;

        return $dic->get(WantedRepositoryInterface::class);
    }

    private static function getModelFactory(): ModelFactoryInterface
    {
        global $dic;

        return $dic->get(ModelFactoryInterface::class);
    }

    /**
     * @deprecated Inject by constructor
     */
    private static function getMissingArtistLookup(): MissingArtistLookupInterface
    {
        global $dic;

        return $dic->get(MissingArtistLookupInterface::class);
    }

    /**
     * @deprecated Inject by constructor
     */
    private static function getMusicBrainz(): MusicBrainz
    {
        global $dic;

        return $dic->get(MusicBrainz::class);
    }
}