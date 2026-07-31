# Federated Messaging, Conversation Safety & HomeServer Handoff v66I

## Initial score: 3.0/10

The POD can verify signed ActivityPub deliveries, discover remote actors, follow accounts, publish posts, receive interactions, and maintain a private federated timeline. Direct Note activities are still treated as quarantined timeline mentions rather than dedicated conversations. There is no separate message-request queue, message-thread lifecycle, edit/delete delivery bridge, per-user conversation state, sender safety controls, or owner-approved HomeServer assistance workflow.

## 10/10 target

- Keep Federated Messages separate from trusted POD Messages.
- Ingest verified direct ActivityPub Notes into dedicated conversation threads.
- Quarantine unknown senders as message requests.
- Trust accepted followers/following relationships without bypassing actor/domain blocks.
- Add read, unread, archive, mute, pin, accept, reject, block, report, and needs-response state.
- Add signed outbound replies, edits, deletes, Undo/Tombstone-compatible lifecycle, delivery receipts, and retry.
- Enforce actor/object ownership and idempotent Create, Update, Delete, and Undo processing.
- Add per-actor rate limits, risk scoring, duplicate protection, bounded content, and link-only attachments.
- Surface message requests and failed delivery actions in the Unified Social Inbox.
- Add bounded retention that preserves saved evidence and active conversations.
- Add HomeServer summary, suggested-reply, and translation handoffs using explicit wrapper/resource authority.
- Store only request hashes, bounded safe results, and receipts; never store private HomeServer source material.
- Never automatically send HomeServer-generated text; require explicit owner submission.
- Preserve standalone POD operation when no HomeServer is paired or online.
- Add fresh/additive schema support and live MySQL 8.4/MariaDB 11.4 certification.

## Current score: 3.0/10

Final 10/10 requires complete implementation, privacy/security review, clean temporary-file removal, retained portal compatibility, exact-head certification, PR promotion, and merge.