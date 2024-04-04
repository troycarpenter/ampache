<?php

declare(strict_types=0);

/**
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright Ampache.org, 2001-2023
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

namespace Ampache\Gui\Song;

use Ampache\Config\ConfigContainerInterface;
use Ampache\Config\ConfigurationKeyEnum;
use Ampache\Module\Authorization\AccessTypeEnum;
use Ampache\Repository\Model\Catalog;
use Ampache\Repository\Model\ModelFactoryInterface;
use Ampache\Repository\Model\Rating;
use Ampache\Repository\Model\Share;
use Ampache\Repository\Model\Song;
use Ampache\Repository\Model\User;
use Ampache\Repository\Model\Userflag;
use Ampache\Module\Api\Ajax;
use Ampache\Module\Application\Song\DeleteAction;
use Ampache\Module\Authorization\AccessLevelEnum;
use Ampache\Module\Authorization\GuiGatekeeperInterface;
use Ampache\Module\Playback\Stream_Playlist;
use Ampache\Module\System\Core;
use Ampache\Module\Util\Ui;

final class SongViewAdapter implements SongViewAdapterInterface
{
    private ConfigContainerInterface $configContainer;

    private ModelFactoryInterface $modelFactory;

    private GuiGatekeeperInterface $gatekeeper;

    private Song $song;

    public function __construct(
        ConfigContainerInterface $configContainer,
        ModelFactoryInterface $modelFactory,
        GuiGatekeeperInterface $gatekeeper,
        Song $song
    ) {
        $this->configContainer = $configContainer;
        $this->modelFactory    = $modelFactory;
        $this->gatekeeper      = $gatekeeper;
        $this->song            = $song;
    }

    public function getId(): int
    {
        return $this->song->getId();
    }

    public function getRating(): string
    {
        return Rating::show($this->song->getId(), 'song');
    }

    public function getAverageRating(): string
    {
        $rating = $this->modelFactory->createRating(
            $this->song->getId(),
            'song'
        );

        return (string) $rating->get_average_rating();
    }

    public function canAutoplayNext(): bool
    {
        return Stream_Playlist::check_autoplay_next();
    }

    public function canAppendNext(): bool
    {
        return Stream_Playlist::check_autoplay_append();
    }

    public function getUserFlags(): string
    {
        return Userflag::show($this->song->getId(), 'song');
    }

    public function getWaveformUrl(): string
    {
        return sprintf(
            '%s/waveform.php?song_id=%d',
            $this->configContainer->getWebPath(),
            $this->song->getId()
        );
    }

    public function getDirectplayButton(): string
    {
        $songId = $this->song->getId();

        return Ajax::button(
            '?page=stream&action=directplay&object_type=song&object_id=' . $songId,
            'play',
            T_('Play'),
            'play_song_' . $songId
        );
    }

    public function getAutoplayNextButton(): string
    {
        $songId = $this->song->getId();

        return Ajax::button(
            '?page=stream&action=directplay&object_type=song&object_id=' . $songId . '&playnext=true',
            'play_next',
            T_('Play next'),
            'nextplay_song_' . $songId
        );
    }

    public function getAppendNextButton(): string
    {
        $songId = $this->song->getId();

        return Ajax::button(
            '?page=stream&action=directplay&object_type=song&object_id=' . $songId . '&append=true',
            'play_add',
            T_('Play last'),
            'addplay_song_' . $songId
        );
    }

    public function getCustomPlayActions(): string
    {
        $actions = Song::get_custom_play_actions();

        $buttons = '';
        foreach ($actions as $action) {
            $buttons .= Ajax::button(
                '?page=stream&action=directplay&object_type=song&object_id=' . $this->song->getId() . '&custom_play_action=' . $action['index'],
                $action['icon'],
                T_($action['title']),
                $action['icon'] . '_song_' . $this->song->getId()
            );
        }

        return $buttons;
    }

    public function getTemporaryPlaylistButton(): string
    {
        $songId = $this->song->getId();

        return Ajax::button(
            '?action=basket&type=song&id=' . $songId,
            'add',
            T_('Add to Temporary Playlist'),
            'add_song_' . $songId
        );
    }

    public function canPostShout(): bool
    {
        return (
            $this->configContainer->isAuthenticationEnabled() === false ||
            $this->gatekeeper->mayAccess(AccessTypeEnum::INTERFACE, AccessLevelEnum::USER) === true
        ) &&
            $this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::SOCIABLE);
    }

    public function getPostShoutUrl(): string
    {
        return sprintf(
            '%s/shout.php?action=show_add_shout&type=song&id=%d',
            $this->configContainer->getWebPath(),
            $this->song->getId()
        );
    }

    public function getPostShoutIcon(): string
    {
        return Ui::get_icon('comment', T_('Post Shout'));
    }

    public function canShare(): bool
    {
        return $this->gatekeeper->mayAccess(AccessTypeEnum::INTERFACE, AccessLevelEnum::USER) &&
            $this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::SHARE);
    }

    public function getShareUi(): string
    {
        return Share::display_ui('song', $this->song->getId(), false);
    }

    public function canDownload(): bool
    {
        return $this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::DOWNLOAD);
    }

    public function getExternalPlayUrl(): string
    {
        return $this->song->play_url(
            '&action=download&cache=1&format=raw',
            '',
            false,
            Core::get_global('user')->id ?? 0
        );
    }

    public function getExternalPlayIcon(): string
    {
        return Ui::get_icon('link', T_('Link'));
    }

    public function getDownloadUrl(): string
    {
        return sprintf(
            '%s/stream.php?action=download&song_id=%d',
            $this->configContainer->getWebPath(),
            $this->song->getId()
        );
    }

    public function getDownloadIcon(): string
    {
        return Ui::get_icon('download', T_('Download'));
    }

    public function canDisplayStats(): bool
    {
        $owner_id = $this->song->get_user_owner();

        return (
            (($owner_id !== null && !empty($GLOBALS['user'])) && $owner_id == $GLOBALS['user']->id) ||
            $this->gatekeeper->mayAccess(AccessTypeEnum::INTERFACE, AccessLevelEnum::CONTENT_MANAGER)
        ) &&
        $this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::STATISTICAL_GRAPHS) &&
        is_dir(__DIR__ . '/../../../vendor/szymach/c-pchart/src/Chart/');
    }

    public function getDisplayStatsUrl(): string
    {
        return sprintf(
            '%s/stats.php?action=graph&object_type=song&object_id=%d',
            $this->configContainer->getWebPath(),
            $this->song->getId()
        );
    }

    public function getUpdateFromTagsUrl(): string
    {
        return sprintf(
            '%s/song.php?action=update_from_tags&song_id=%d',
            $this->configContainer->getWebPath(),
            $this->song->getId()
        );
    }

    public function getDisplayStatsIcon(): string
    {
        return Ui::get_icon('statistics', T_('Graphs'));
    }
    public function getRefreshIcon(): string
    {
        return Ui::get_icon('file_refresh', T_('Update from tags'));
    }

    public function isEditable(): bool
    {
        return $this->gatekeeper->mayAccess(AccessTypeEnum::INTERFACE, AccessLevelEnum::CONTENT_MANAGER) ||
            ((Core::get_global('user') instanceof User && $this->song->get_user_owner() == Core::get_global('user')->id) && $this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::UPLOAD_ALLOW_EDIT) === true);
    }

    public function getEditButtonTitle(): string
    {
        return T_('Song Edit');
    }

    public function getEditIcon(): string
    {
        return Ui::get_icon('edit', T_('Edit'));
    }

    public function canToggleState(): bool
    {
        return $this->gatekeeper->mayAccess(AccessTypeEnum::INTERFACE, AccessLevelEnum::MANAGER) ||
            ((Core::get_global('user') instanceof User && $this->song->get_user_owner() == Core::get_global('user')->id) && $this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::UPLOAD_ALLOW_EDIT) === true);
    }

    public function getToggleStateButton(): string
    {
        $songId = $this->song->getId();

        if ($this->song->enabled) {
            $icon     = 'disable';
            $icontext = T_('Disable');
        } else {
            $icon     = 'enable';
            $icontext = T_('Enable');
        }

        return Ajax::button(
            '?page=song&action=flip_state&song_id=' . $songId,
            $icon,
            $icontext,
            'flip_song_' . $songId
        );
    }

    public function getDeletionUrl(): string
    {
        return sprintf(
            '%s/song.php?action=%s&song_id=%d',
            $this->configContainer->getWebPath(),
            DeleteAction::REQUEST_KEY,
            $this->song->getId()
        );
    }

    public function getDeletionIcon(): string
    {
        return Ui::get_icon('delete', T_('Delete'));
    }

    public function canBeDeleted(): bool
    {
        return Catalog::can_remove($this->song);
    }

    public function getProperties(): array
    {
        $songprops = [];

        $songprops[T_('Title')]        = scrub_out($this->song->title);
        $songprops[T_('Song Artist')]  = $this->song->get_f_artist_link();
        $songprops[T_('Album Artist')] = $this->song->get_f_albumartist_link();
        if ($this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::ALBUM_GROUP) === true) {
            $songprops[T_('Album')] = $this->song->get_f_album_link();
        } else {
            $songprops[T_('Album')] = $this->song->get_f_album_disk_link();
        }
        $songprops[T_('Composer')]      = scrub_out($this->song->composer);
        $songprops[T_('Genres')]        = $this->song->f_tags;
        $songprops[T_('Track')]         = $this->song->track;
        $songprops[T_('Disk')]          = $this->song->disk;
        $songprops[T_('Disk Subtitle')] = $this->song->disksubtitle ?? '';
        $songprops[T_('Year')]          = $this->song->year;
        $songprops[T_('Original Year')] = $this->song->get_album_original_year($this->song->album);
        $songprops[T_('Length')]        = scrub_out($this->song->f_time);
        $songprops[T_('Links')]         = "<a href=\"http://www.google.com/search?q=%22" . rawurlencode((string)$this->song->f_artist) . "%22+%22" . rawurlencode((string)$this->song->f_name) . "%22\" target=\"_blank\">" . UI::get_icon('google', T_('Search on Google ...')) . "</a>" .
            "&nbsp;<a href=\"https://www.duckduckgo.com/?q=%22" . rawurlencode((string)$this->song->f_artist) . "%22+%22" . rawurlencode((string)$this->song->f_name) . "%22\" target=\"_blank\">" . UI::get_icon('duckduckgo', T_('Search on DuckDuckGo ...')) . "</a>" .
            "&nbsp;<a href=\"http://www.last.fm/search?q=%22" . rawurlencode((string)$this->song->f_artist) . "%22+%22" . rawurlencode((string)$this->song->f_name) . "%22&type=track\" target=\"_blank\">" . UI::get_icon('lastfm', T_('Search on Last.fm ...')) . "</a>";
        if ($this->song->mbid) {
            $songprops[T_('Links')] .= "&nbsp;<a href=\"https://musicbrainz.org/recording/" . $this->song->mbid . "\" target=\"_blank\">" . UI::get_icon('musicbrainz', T_('Search on Musicbrainz ...')) . "</a>";
        } else {
            $songprops[T_('Links')] .= "&nbsp;<a href=\"https://musicbrainz.org/taglookup?tag-lookup.artist=%22" . rawurlencode((string)$this->song->f_artist) . "%22&tag-lookup.track=%22" . rawurlencode((string)$this->song->f_name) . "%22\" target=\"_blank\">" . UI::get_icon('musicbrainz', T_('Search on Musicbrainz ...')) . "</a>";
        }
        $songprops[T_('Comment')] = scrub_out($this->song->comment ?? '');
        if ($this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::LABEL)) {
            $web_path     = $this->configContainer->getWebPath();
            $label_string = '';
            foreach (array_map('trim', explode(';', (string)$this->song->label)) as $label_name) {
                $label_string .= "<a href=\"" . $web_path . "/labels.php?action=show&name=" . scrub_out($label_name) . "\">" . scrub_out($label_name) . "</a>, ";
            }
            $songprops[T_('Label')] = rtrim($label_string, ', ');
        } else {
            $songprops[T_('Label')] = scrub_out($this->song->label ?? '');
        }
        if (isset($this->song->language)) {
            $songprops[T_('Song Language')] = scrub_out($this->song->language);
        }
        $songprops[T_('Catalog Number')] = scrub_out($this->song->get_album_catalog_number($this->song->album));
        $songprops[T_('Barcode')]        = scrub_out($this->song->get_album_barcode($this->song->album));
        $songprops[T_('Bitrate')]        = scrub_out($this->song->f_bitrate);
        $songprops[T_('Channels')]       = $this->song->channels;
        $songprops[T_('Song MBID')]      = scrub_out($this->song->mbid);
        $songprops[T_('Album MBID')]     = scrub_out($this->song->album_mbid);
        $songprops[T_('Artist MBID')]    = scrub_out($this->song->artist_mbid);
        if (isset($this->song->replaygain_track_gain) && $this->song->replaygain_track_gain !== null) {
            $songprops[T_('ReplayGain Track Gain')] = $this->song->replaygain_track_gain;
        }
        if (isset($this->song->replaygain_album_gain) && $this->song->replaygain_album_gain !== null) {
            $songprops[T_('ReplayGain Album Gain')] = $this->song->replaygain_album_gain;
        }
        if (isset($this->song->r128_track_gain) && $this->song->r128_track_gain !== null) {
            $songprops[T_('R128 Track Gain')] = $this->song->r128_track_gain;
        }
        if (isset($this->song->r128_album_gain) && $this->song->r128_album_gain !== null) {
            $songprops[T_('R128 Album Gain')] = $this->song->r128_album_gain;
        }
        if ($this->gatekeeper->mayAccess(AccessTypeEnum::INTERFACE, AccessLevelEnum::MANAGER) && $this->song->file !== null) {
            $data                      = pathinfo($this->song->file);
            $songprops[T_('Path')]     = scrub_out((string)($data['dirname'] ?? ''));
            $songprops[T_('Filename')] = scrub_out($data['filename'] . "." . ($data['extension'] ?? ''));
            $songprops[T_('Size')]     = $this->song->f_size;
        }
        if ($this->song->update_time) {
            $songprops[T_('Last Updated')] = get_datetime((int) $this->song->update_time);
        }
        $songprops[T_('Added')] = get_datetime($this->song->getAdditionTime());
        if ($this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::SHOW_PLAYED_TIMES)) {
            $songprops[T_('Played')] = $this->song->total_count;
        }
        if ($this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::SHOW_SKIPPED_TIMES)) {
            $songprops[T_('Skipped')] = $this->song->total_skip;
        }

        if ($this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::SHOW_LYRICS)) {
            $songprops[T_('Lyrics')] = $this->song->f_lyrics;
        }

        $license = $this->song->getLicense();
        if ($license !== null) {
            $songprops[T_('Licensing')] = $license->getLinkFormatted();
        }

        $owner_id = $this->song->get_user_owner();
        if ($this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::SOCIABLE) && $owner_id > 0) {
            $owner                        = $this->modelFactory->createUser($owner_id);
            $songprops[T_('Uploaded by')] = $owner->get_f_link();
        }

        return $songprops;
    }

    public function canEditPlaylist(): bool
    {
        return $this->gatekeeper->mayAccess(AccessTypeEnum::INTERFACE, AccessLevelEnum::USER);
    }

    public function getAddToPlaylistIcon(): string
    {
        return Ui::get_icon('playlist_add', T_('Add to playlist'));
    }

    public function canBeReordered(): bool
    {
        return $this->gatekeeper->mayAccess(AccessTypeEnum::INTERFACE, AccessLevelEnum::CONTENT_MANAGER);
    }

    public function getReorderIcon(): string
    {
        return Ui::get_icon('drag', T_('Reorder'));
    }

    public function getPreferencesIcon(): string
    {
        return Ui::get_icon('preferences', T_('Song Information'));
    }

    public function getTrackNumber(): string
    {
        return (string)$this->song->f_track;
    }

    public function getSongUrl(): string
    {
        return $this->song->get_link();
    }

    public function getSongLink(): string
    {
        return (string)$this->song->get_f_link();
    }

    public function getArtistLink(): string
    {
        return (string)$this->song->get_f_artist_link();
    }

    public function getAlbumLink(): string
    {
        return $this->song->get_f_album_link();
    }

    public function getAlbumDiskLink(): string
    {
        return $this->song->get_f_album_disk_link();
    }

    public function getYear(): int
    {
        return $this->song->year;
    }

    public function getGenre(): string
    {
        return (string)$this->song->f_tags;
    }

    public function getPlayDuration(): string
    {
        return (string)$this->song->f_time;
    }

    public function getLicenseLink(): string
    {
        $license = $this->song->getLicense();

        if ($license !== null) {
            return $license->getLinkFormatted();
        }

        return '';
    }

    public function getNumberPlayed(): int
    {
        return $this->song->total_count;
    }

    public function getNumberSkipped(): int
    {
        return $this->song->total_skip;
    }
}
