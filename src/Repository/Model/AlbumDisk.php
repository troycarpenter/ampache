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

namespace Ampache\Repository\Model;

use Ampache\Config\AmpConfig;
use Ampache\Module\System\Dba;
use Ampache\Repository\SongRepositoryInterface;

/**
 * This is the class responsible for handling the Album object
 * it is related to the album table in the database.
 */
class AlbumDisk extends database_object implements library_item, CatalogItemInterface
{
    protected const DB_TABLENAME = 'album_disk';

    public int $id = 0;

    public int $album_id;

    public int $disk;

    public int $disk_count = 0;

    public ?int $time = null;

    public int $catalog;

    public int $song_count;

    public int $total_count;

    public ?string $disksubtitle = null;

    /**
     * Variables from parent Album
     */

    public ?string $name;

    public ?string $prefix;

    public ?string $mbid; // MusicBrainz ID

    public ?int $year;

    public ?string $mbid_group; // MusicBrainz Release Group ID

    public ?string $release_type;

    public ?int $album_artist;

    public ?int $original_year;

    public ?string $barcode;

    public ?string $catalog_number;

    public ?string $version;

    public ?string $release_status;

    public int $addition_time;

    public int $artist_count;

    public int $song_artist_count;

    public ?string $link = null;

    public ?array $album_artists = null;

    /** @var array $tags */
    public $tags;

    /** @var null|string $f_artist_name */
    public $f_artist_name;

    /** @var null|string $f_artist_link */
    public $f_artist_link;

    /** @var null|string $f_artist */
    public $f_artist;

    /** @var null|string $f_name // Prefix + Name, generated */
    public $f_name;

    /** @var null|string $f_link */
    public $f_link;

    /** @var null|string $f_tags */
    public $f_tags;

    /** @var null|string $f_year */
    public $f_year;

    /** @var null|string $f_release_type */
    public $f_release_type;

    /** @var int $total_duration */
    public $total_duration;

    /** @var int $catalog_id */
    public $catalog_id;

    /** @var null|string $f_year_link */
    public $f_year_link;

    private ?bool $has_art = null;

    private Album $album;

    /**
     * __construct
     * Album constructor it loads everything relating
     * to this album from the database it does not
     * pull the album or thumb art by default or
     * get any of the counts.
     * @param int|null $album_disk_id
     */
    public function __construct($album_disk_id = 0)
    {
        if (!$album_disk_id) {
            $this->album = new Album();

            return;
        }

        $info = $this->get_info($album_disk_id, static::DB_TABLENAME);
        if ($info === []) {
            return;
        }

        // make sure the album is valid before going further
        $this->album = new Album($info['album_id']);
        if ($this->album->isNew()) {
            return;
        }

        foreach ($info as $key => $value) {
            $this->$key = $value;
        }

        // Little bit of formatting here
        $this->total_duration = (int)$this->time;
        // set the album variables just in case
        $this->name              = $this->album->name;
        $this->prefix            = $this->album->prefix;
        $this->mbid              = $this->album->mbid;
        $this->year              = $this->album->year;
        $this->mbid_group        = $this->album->mbid_group;
        $this->release_type      = $this->album->release_type;
        $this->album_artist      = $this->album->album_artist;
        $this->original_year     = $this->album->original_year;
        $this->barcode           = $this->album->barcode;
        $this->catalog_number    = $this->album->catalog_number;
        $this->version           = $this->album->version;
        $this->release_status    = $this->album->release_status;
        $this->addition_time     = $this->album->addition_time;
        $this->artist_count      = $this->album->artist_count;
        $this->song_artist_count = $this->album->song_artist_count;

        // finally; set up your formatted name
        $this->f_name = $this->get_fullname();
    }

    public function getId(): int
    {
        return (int)($this->id ?? 0);
    }

    public function isNew(): bool
    {
        return $this->getId() === 0;
    }

    public function getAlbumId(): int
    {
        return $this->album_id;
    }

    /**
     * check
     *
     * Insert album_disk and do additional steps for data on insert
     * @param int $album_id
     * @param int $disk
     * @param int $catalog_id
     * @param null|string $disksubtitle
     * @param null|int $current_id
     */
    public static function check($album_id, $disk, $catalog_id, $disksubtitle, $current_id = null): int
    {
        // check if the album_disk exists
        $db_results = (!empty($disksubtitle))
            ? Dba::read("SELECT * FROM `album_disk` WHERE `album_id` = ? AND `disk` = ? AND `catalog` = ? AND `disksubtitle` = ?;", [$album_id, $disk, $catalog_id, $disksubtitle])
            : Dba::read("SELECT * FROM `album_disk` WHERE `album_id` = ? AND `disk` = ? AND `catalog` = ?;", [$album_id, $disk, $catalog_id]);
        $row = Dba::fetch_assoc($db_results);
        if (isset($row['id'])) {
            if ($current_id && $row['disk'] !== $disk) {
                // Update songs when you edit an album_disk object
                Dba::write("UPDATE `song` SET `disk` = ? WHERE `album` = ? AND `disk` = ?;", [$disk, $album_id, $row['disk']]);
            }

            return (int)$row['id'];
        }

        // update existing ID
        if ($current_id) {
            $db_results = Dba::read("SELECT * FROM `album_disk` WHERE `id` = ?;", [$current_id]);
            $row        = Dba::fetch_assoc($db_results);
            if (isset($row['id'])) {
                // alter the existing disk after editing
                Dba::write("UPDATE `album_disk` SET `album_id` = ?, `disk` = ?, `catalog` = ?, `disksubtitle` = ? WHERE `id` = ?;", [$album_id, $disk, $catalog_id, $current_id, $disksubtitle]);
                if ($row['disk'] !== $disk) {
                    // Update songs when you edit an album_disk object
                    Dba::write("UPDATE `song` SET `disk` = ? WHERE `album` = ? AND `disk` = ?;", [$disk, $album_id, $row['disk']]);
                }

                return $current_id;
            }
        }

        // create the album_disk (if missing)
        $db_results = Dba::write("INSERT INTO `album_disk` (`album_id`, `disk`, `catalog`) VALUES(?, ?, ?);", [$album_id, $disk, $catalog_id]);
        if (!$db_results) {
            return 0;
        }

        $album_id = Dba::insert_id();

        // count a new song on the new disk right away
        $sql = "UPDATE `album_disk` SET `song_count` = `song_count` + 1 WHERE `album_id` = ? AND `disk` = ? AND `catalog` = ?";
        Dba::write($sql, [$album_id, $disk, $catalog_id]);
        if (!empty($disksubtitle)) {
            // set the subtitle on insert too
            $sql = "UPDATE `album_disk` SET `disksubtitle` = ? WHERE `album_id` = ? AND `disk` = ? AND `catalog` = ?";
            Dba::write($sql, [$disksubtitle, $album_id, $disk, $catalog_id]);
        }

        return $album_id;
    }

    /**
     * format
     * This is the format function for this object. It sets cleaned up
     * album information with the base required
     * f_link, f_name
     *
     * @param bool $details
     * @param string $limit_threshold
     */
    public function format($details = true, $limit_threshold = ''): void
    {
        if ($this->isNew()) {
            return;
        }

        if (!isset($this->album)) {
            $this->album = new Album($this->album_id);
        }

        $this->f_release_type = $this->album->f_release_type;
        $this->album_artists  = $this->album->get_artists();

        if ($details) {
            $this->tags   = $this->album->tags;
            $this->f_tags = $this->album->f_tags;
        }

        $this->tags   = Tag::get_top_tags('album', $this->album_id);
        $this->f_tags = Tag::get_display($this->tags, true, 'album_disk');
        // set link and f_link
        $this->get_artist_fullname();
        $this->get_f_link();
        $this->get_f_artist_link();

        if (!$this->year) {
            $this->f_year = $this->album->f_year;
        } else {
            $web_path          = AmpConfig::get_web_path();
            $year              = $this->year;
            $this->f_year_link = sprintf('<a href="%s/search.php?type=album_disk&action=search&limit=0rule_1=year&rule_1_operator=2&rule_1_input=', $web_path) . $year . "\">" . $year . "</a>";
        }
    }

    /**
     * does the item have art?
     */
    public function has_art(): bool
    {
        if ($this->has_art === null) {
            $this->has_art = Art::has_db($this->album_id, 'album');
        }

        return $this->has_art;
    }

    /**
     * Get item keywords for metadata searches.
     */
    public function get_keywords(): array
    {
        return [
            'mb_albumid' => [
                'important' => false,
                'label' => T_('Album MusicBrainzID'),
                'value' => $this->mbid,
            ],
            'mb_albumid_group' => [
                'important' => false,
                'label' => T_('Release Group MusicBrainzID'),
                'value' => $this->mbid_group,
            ],
            'artist' => [
                'important' => true,
                'label' => T_('Artist'),
                'value' => ($this->get_artist_fullname()),
            ],
            'album' => [
                'important' => true,
                'label' => T_('Album'),
                'value' => $this->get_fullname(true),
            ],
            'year' => [
                'important' => false,
                'label' => T_('Year'),
                'value' => $this->year,
            ],
        ];
    }

    /**
     * Get item fullname.
     * @param bool $simple
     * @param bool $force_year
     */
    public function get_fullname($simple = false, $force_year = false): string
    {
        // return the basic name without all the wild formatting
        if ($simple) {
            return trim(trim($this->album->prefix ?? '') . ' ' . trim($this->album->name ?? ''));
        }

        if ($force_year) {
            $f_name = trim(trim($this->album->prefix ?? '') . ' ' . trim($this->album->name ?? ''));
            if ($this->album->year > 0) {
                $f_name .= " (" . $this->album->year . ")";
            }

            if ($this->disk_count > 1) {
                $f_name .= " [" . T_('Disk') . " " . $this->disk . "]";
            }

            return $f_name;
        }

        // don't do anything if it's formatted
        if ($this->f_name === null) {
            $this->f_name = trim(trim($this->album->prefix ?? '') . ' ' . trim($this->album->name ?? ''));
            // Album pages should show a year and looking if we need to display the release year
            if ($this->album->original_year && AmpConfig::get('use_original_year') && $this->album->original_year != $this->album->year && $this->album->year > 0) {
                $this->f_name .= " (" . $this->album->year . ")";
            }

            if ($this->disk_count > 1) {
                $this->f_name .= " [" . T_('Disk') . " " . $this->disk . "]";
            }
        }

        return $this->f_name;
    }

    /**
     * Get item link.
     */
    public function get_link(): string
    {
        // don't do anything if it's formatted
        if ($this->link === null) {
            $web_path   = AmpConfig::get_web_path();
            $this->link = $web_path . '/albums.php?action=show_disk&album_disk=' . $this->id;
        }

        return $this->link;
    }

    /**
     * Get item f_link.
     */
    public function get_f_link(): string
    {
        // don't do anything if it's formatted
        if ($this->f_link === null) {
            $this->f_link = "<a href=\"" . $this->get_link() . "\" title=\"" . scrub_out($this->get_fullname()) . "\">" . scrub_out($this->get_fullname()) . "</a>";
        }

        return $this->f_link;
    }

    /**
     * Return a formatted link to the parent object (if appliccable)
     */
    public function get_f_parent_link(): ?string
    {
        return $this->get_f_artist_link();
    }

    /**
     * getYear
     */
    public function getYear(): string
    {
        return (string)($this->year ?: '');
    }

    /**
     * Get item f_artist_link.
     */
    public function get_f_artist_link(): ?string
    {
        // don't do anything if it's formatted
        if ($this->f_artist_link === null) {
            $this->f_artist_link = $this->album->get_f_artist_link();
        }

        return $this->f_artist_link;
    }

    /**
     * Get item album_artist fullname.
     */
    public function get_artist_fullname(): ?string
    {
        if ($this->f_artist_name === null) {
            $this->f_artist_name = $this->album->get_artist_fullname();
        }

        return $this->f_artist_name;
    }

    /**
     * @return null|array{object_type: LibraryItemEnum, object_id: int}
     */
    public function get_parent(): ?array
    {
        if ($this->album_id !== 0) {
            return [
                'object_type' => LibraryItemEnum::ARTIST,
                'object_id' => $this->album_id
            ];
        }

        return null;
    }

    /**
     * Get item children.
     */
    public function get_childrens(): array
    {
        return $this->get_medias();
    }

    /**
     * Search for direct children of an object
     * @param string $name
     */
    public function get_children($name): array
    {
        debug_event(self::class, 'get_children ' . $name, 5);

        return [];
    }

    /**
     * Get all children and sub-childrens media.
     *
     * @return list<array{object_type: LibraryItemEnum, object_id: int}>
     */
    public function get_medias(?string $filter_type = null): array
    {
        $medias = [];
        if (!$filter_type || $filter_type === 'song') {
            $songs = $this->getSongRepository()->getByAlbumDisk($this->id);
            foreach ($songs as $song_id) {
                $medias[] = ['object_type' => LibraryItemEnum::SONG, 'object_id' => $song_id];
            }
        }

        return $medias;
    }

    /**
     * Returns the id of the catalog the item is associated to
     */
    public function getCatalogId(): int
    {
        return $this->catalog;
    }

    /**
     * Get item's owner.
     */
    public function get_user_owner(): ?int
    {
        if (!$this->album->album_artist) {
            return null;
        }

        $artist = new Artist($this->album->album_artist);

        return $artist->get_user_owner();
    }

    /**
     * Get default art kind for this item.
     */
    public function get_default_art_kind(): string
    {
        return 'default';
    }

    /**
     * get_songs
     *
     * Get each song id for the album_disk
     * @return int[]
     */
    public function get_songs(): array
    {
        $results = [];
        $params  = [$this->album_id, $this->disk];
        $sql     = (AmpConfig::get('catalog_disable'))
            ? "SELECT DISTINCT `song`.`id` FROM `song` LEFT JOIN `catalog` ON `catalog`.`id` = `song`.`catalog` WHERE `song`.`album` = ? AND `song`.`disk` = ? AND `catalog`.`enabled` = '1'"
            : "SELECT DISTINCT `song`.`id` FROM `song` WHERE `song`.`album` = ? AND `song`.`disk` = ?";
        $db_results = Dba::read($sql, $params);

        while ($row = Dba::fetch_assoc($db_results, false)) {
            $results[] = (int)$row['id'];
        }

        return $results;
    }

    /**
     * get_description
     */
    public function get_description(): string
    {
        // Album description is not supported yet, always return artist description
        $artist = new Artist($this->album->album_artist);

        return $artist->get_description();
    }

    /**
     * display_art
     * @param int $thumb
     * @param bool $force
     */
    public function display_art($thumb = 2, $force = false): void
    {
        $album_id = null;
        $type     = null;

        if (Art::has_db($this->album_id, 'album')) {
            $album_id = $this->album_id;
            $type     = 'album';
        } elseif (
            $this->album->album_artist &&
            (
                Art::has_db($this->album->album_artist, 'artist') ||
                $force
            )
        ) {
            $album_id = $this->album->album_artist;
            $type     = 'artist';
        }

        if ($album_id !== null && $type !== null) {
            $title = '[' . ($this->get_artist_fullname() ?? $this->get_artist_fullname()) . '] ' . $this->get_fullname();
            Art::display($type, $album_id, $title, $thumb, $this->get_link());
        }
    }

    /**
     * update
     * This function takes a key'd array of data and updates this object
     * as needed
     */
    public function update(array $data): int
    {
        $album_id     = $this->album->update($data);
        $disk         = (int)($data['disk'] ?? $this->disk);
        $catalog      = $data['catalog'] ?? $this->catalog;
        $disksubtitle = $data['disksubtitle'] ?? $this->disksubtitle;

        return self::check($album_id, $disk, $catalog, $disksubtitle, $this->id);
    }

    /**
     * does the item have a single album artist and song artist?
     */
    public function get_artist_count(): int
    {
        $sql        = "SELECT COUNT(DISTINCT(`object_id`)) AS `artist_count` FROM `album_map` WHERE `album_id` = ?;";
        $db_results = Dba::read($sql, [$this->id]);
        $row        = Dba::fetch_assoc($db_results);
        if ($row !== []) {
            return (int)$row['artist_count'];
        }

        return 0;
    }

    public function getMediaType(): LibraryItemEnum
    {
        return LibraryItemEnum::ALBUM_DISK;
    }

    /**
     * @deprecated
     */
    private function getSongRepository(): SongRepositoryInterface
    {
        global $dic;

        return $dic->get(SongRepositoryInterface::class);
    }
}
