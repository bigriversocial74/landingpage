# North Mountain Media Music Dashboard and Demo Mode v49

Build: `20260726-publishing-workflow-v56`

## SQL

There is no new v49 migration.

Existing requirements remain:

- `database/music_library_v44.sql`
- `database/visitor_intelligence_v43.sql`

Demo Mode and its optional banner switch use the existing `settings` table.

## Original public sidebar

The public Music Library and album/playlist pages use the same sidebar as the resume/portfolio workspace. The Visitor Type section has been removed:

- Conversation
  - Home
  - Music Library
  - Blog
  - Call Us
- Portfolio project links
- Public profile card

There is no Music-specific sidebar.

## Administrator Demo Mode

Open:

**Operations → Music Library → Demo Mode**

Controls:

- **Enable Demo Music Mode**
- **Display the demo featured banner**

### Demo Mode off

The public Music Library uses the live active catalog from:

- `music_tracks`
- `music_albums`
- `music_playlists`
- `music_playlist_tracks`
- `knowledge_assets`

### Demo Mode on

The public Music Library uses the packaged playable sample catalog.

The live database remains unchanged.

## Demo catalog

Included:

- 8 albums
- 10 playable MP3 demos
- 4 playlists
- Featured playlist
- New Songs
- Top Songs
- Recently Played
- Trending Now
- Featured Songs
- All Songs

The demo audio is original synthesized material produced for the UI demonstration.

## Featured banner

The existing Banner tab remains the custom production banner control.

Priority:

1. Enabled custom banner
2. Enabled demo banner while Demo Mode is active
3. No banner markup

The banner is never displayed merely because text settings exist.

## Public dashboard order

Above the fold:

1. Optional featured banner
2. Albums
3. New Songs
4. Top Songs
5. Recently Played
6. Trending Now
7. Fixed player

Below the first viewport:

- Featured Songs
- All Songs

## Player controls

The fixed player includes:

- Cover artwork
- Song and artist
- Favorite
- Shuffle
- Previous
- Play/pause
- Next
- Repeat
- Progress scrubber
- Current time
- Duration
- Volume
- Queue

Recently Played is stored in the visitor browser with localStorage.

## Demo audio security

Raw files are stored in:

`assets/demo-music/audio/`

Direct Apache access is denied by:

`assets/demo-music/audio/.htaccess`

Playback uses:

`demo-music.php?id={demo-track-id}`

The endpoint:

- Requires Demo Music Mode
- Uses an internal whitelist
- Serves MP3 only
- Supports HTTP byte ranges
- Supports browser seeking
- Does not reveal filesystem paths

## Analytics

### Music Library page

Event:

`music_library_view`

### Album page

Event:

`music_album_view`

### Playlist page

Event:

`music_playlist_view`

### Actual playback

Event:

`music_track_play`

A track play is reported after browser playback begins.

Live plays increment `music_tracks.play_count`.

Demo plays do not modify live music tables.

### CRM and Visitor Intelligence

The existing visitor identity model attaches events to:

- Visitor profile
- Visitor session
- CRM contact when identified
- CRM opportunity when attributed

CRM contact summaries include:

- Music plays
- Unique tracks

The CRM relationship timeline displays:

- Track title
- Artist
- Album
- Genre
- Demo/live status
- Page path
- Timestamp

## Deployment

Upload v49 over v48 while preserving:

- Active `config.php`
- `storage/`
- Uploaded MP3/audio
- Music covers
- Custom banner files
- Portfolio media
- Profile photos
- Call Center recordings and greetings
- Live Knowledge Center data

New packaged demo assets may be uploaded normally.

## Verification

1. Open Operations → Music Library → Demo Mode.
2. Confirm the status says Live catalog.
3. Enable Demo Music Mode.
4. Enable the demo featured banner.
5. Save.
6. Open `music-library.php?v=49`.
7. Confirm the original public sidebar menu is visible.
8. Confirm no special Music sidebar exists.
9. Confirm the banner, Albums, New Songs, Top Songs, Recently Played, and Trending Now layout.
10. Play Take It Slow.
11. Test pause/resume.
12. Test previous and next.
13. Test shuffle.
14. Test repeat.
15. Scrub the progress control.
16. Adjust volume.
17. Open the queue.
18. Play a second track and confirm Recently Played updates.
19. Open Visitor Intelligence and confirm Music plays.
20. Identify a visitor through the public contact/call workflow.
21. Play another track.
22. Open that CRM contact and confirm the Music track played event.
23. Disable Demo Music Mode.
24. Confirm the live catalog returns without losing live content.
