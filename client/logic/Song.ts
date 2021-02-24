import { AuthKey } from './Auth';
import flagItem from '~logic/Methods/Flag';

type Song = {
    id: string;
    title: string;
    name: string;
    artist: {
        id: string;
        name: string;
    };
    album: {
        id: string;
        name: string;
    };
    genre: [{ id: number; name: string }];
    albumartist: {
        id: string;
        name: string;
    };
    filename: string;
    track: number;
    playlisttrack: number;
    time: number;
    year: number;
    bitrate: number;
    rate: number;
    mode: string;
    mime: string;
    url: string;
    size: number;
    mbid: string;
    album_mbid: string;
    artist_mbid: string;
    albumartist_mbid: string;
    art: string;
    flag: boolean;
    preciserating: number;
    rating: number;
    averagerating: number;
    playcount: number;
    catalog: number;
    composer: string;
    channels: number;
    comment: string;
    license?: string;
    publiser: string;
    language: string;
    replaygain_album_gain: string;
    replaygain_album_peak: string;
    replaygain_track_gain: string;
    replaygain_track_peak: string;
    r128_album_gain: string;
    r128_track_gain: string;
};

const flagSong = (songID: string, favorite: boolean, authKey: AuthKey) => {
    return flagItem('song', songID, favorite, authKey);
};

export { Song, flagSong };
