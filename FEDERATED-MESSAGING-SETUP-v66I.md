# Federated Messaging, Conversation Safety & HomeServer Handoff v66I

## Deployment order

1. Deploy the latest merged `main` application files.
2. Preserve the live `config.php`.
3. Preserve the complete `storage/` directory.
4. Preserve the existing database and ActivityPub secret.
5. Import `database/federated_messaging_v66i.sql` after the v66F, v66G, and v66H migrations.
6. Keep the existing ActivityPub worker scheduled at least once per minute:

```bash
php cron/process-activitypub.php 20
```

The migration is additive and repeat-safe. It creates:

- `activitypub_message_threads`
- `activitypub_messages`
- `activitypub_message_user_state`
- `activitypub_message_events`
- `activitypub_message_assistance`

## Default policy

Federated Messages are disabled by default after migration. Enable the channel from **Federation → Federated Messages** after confirming the public HTTPS ActivityPub endpoints and worker are operating.

Recommended initial settings:

- Unknown senders: Message requests
- Retention: 180 days
- Per-actor hourly limit: 30
- Remote media: Link only
- HomeServer assistance: Owner-approved assistance

## Channel boundaries

- **Federated Messages** are ActivityPub social direct messages.
- **POD Messages** remain the trusted POD-to-POD relationship channel.
- **HomeServer** provides private summaries, translations, and proposed replies only when paired and authorized.

The HomeServer receives a bounded conversation excerpt with explicit `rss-pod` wrapper authority and `federated_message_thread` resource authority. The request denies send authority. Generated text is never automatically delivered; the POD owner must review and submit it.

## Live acceptance checklist

- Send a signed direct Note from a trusted remote actor and confirm it opens a conversation.
- Send from an unknown actor and confirm it enters Message Requests.
- Accept, reject, report, mute, archive, pin, mark unread, and block test conversations.
- Send, edit, and delete an outbound federated message.
- Confirm the remote server receives Create, Update, Delete, and Tombstone objects.
- Force a delivery failure and confirm retry restores the message state.
- Confirm attachments render only as external links with no automatic media loading.
- Confirm requests and failed deliveries appear in the Unified Social Inbox.
- Pair a HomeServer and test summary, suggested reply, and translation.
- Confirm summaries do not populate the reply editor.
- Confirm proposed replies require explicit owner submission.
- Confirm no HomeServer credentials, private source documents, hidden prompts, or unrestricted memory are stored in the POD database.

## Rollback

Disable Federated Messages in the administrator policy before rolling back application files. Do not drop the v66I tables during a routine rollback; retaining them preserves message and moderation evidence for a later redeploy.
