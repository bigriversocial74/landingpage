# Content Interactions v66C Setup

1. Deploy the merged `main` branch while preserving the live `config.php`, database, and complete `storage/` directory.
2. Import `database/content_interactions_v66c.sql` after the base portal and publishing migrations.
3. Open **Portal → Blog** and review the Community moderation panel.
4. Open a Blog post and configure comments, replies, reactions, moderation mode, and optional close time.
5. Test with one administrator and one active client account.
6. Confirm anonymous readers can view approved comments but cannot post or react.
7. Confirm pending comments generate administrator notifications and approved replies notify participants.

The schema is generic. Blog is the first supported content type; portfolio and music can reuse the same tables in later phases.
