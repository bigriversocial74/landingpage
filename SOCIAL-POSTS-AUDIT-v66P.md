# POD Social Post Publisher Audit v66P

## Initial score: 4.0/10

The POD already had a complete ActivityPub actor, approved followers, accepted Following relationships, signed delivery, blog Article federation, a private federated timeline, social interactions, follower Stories, public blog publishing, and RSS. It did not have a permanent local social-post publisher comparable to a normal social network.

## Gaps identified

- no permanent local ActivityPub Note publisher
- no public versus followers-only social-post visibility control
- no social drafts or editable published Notes
- no signed Update lifecycle for local social posts
- no dedicated HTML and ActivityPub object pages for public social posts
- no public POD social profile/feed
- no simple remote “Follow this POD” handoff
- no landing-page choice between blogs, social posts, or a tabbed combination
- no visual-builder landing integration for the same content settings
- no local-publishing section in the federated timeline workspace
- no direct administrator navigation to social publishing
- no Social Posts migration, retained evidence, or MySQL/MariaDB certification
- the inherited public outbox could expose follower-only payloads without audience filtering

## 10/10 target

The completed feature must reuse the canonical ActivityPub identity and approved-follower graph, publish permanent Notes with correct audiences, support drafts and signed Create/Update/Delete activities, protect followers-only content from public pages, restrict local media to protected same-origin storage, preserve blog and RSS behavior, render configurable landing content in both landing systems, provide a normal public social profile, and pass exact-head source plus live MySQL 8.4 and MariaDB 11.4 certification.

## Authority and privacy boundary

- no second ActivityPub actor or follower table
- no fake local users
- no RSS replacement or blog schema rewrite
- no arbitrary remote-media fetching
- no remote server credentials collected by the POD
- remote follow handoff validates the domain and redirects the visitor to their own server
- public Notes may be indexed and redistributed
- followers-only Notes are audience-restricted but are not end-to-end encrypted
- recipients can retain content delivered to them
- protected media remains same-origin; external links require HTTPS
- the public outbox contains only verified Public-audience activities
