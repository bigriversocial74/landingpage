# POD Agent Receptionist Routing v63.3

## Section score

- Initial audit: **4.1/10**
- Certified target: **10/10**

## Initial defects

1. Connected calls had no receptionist-routing policy.
2. Available, busy, and offline states could not route differently.
3. No connected-caller agent session existed.
4. No public-only receptionist knowledge boundary existed.
5. No receptionist citations or source links existed.
6. No agent-assisted message or callback capture existed.
7. No agent-to-human transfer decision existed.
8. No receptionist session summary or transcript existed.
9. No owner policy workspace or session review existed.
10. POD discovery did not advertise receptionist capabilities.

## Delivered

- Policy-driven connected-call routing.
- Available routes: Owner first, Agent first, Agent only.
- Busy/offline routes: Agent first, Agent only, Voicemail, Callback.
- Connected relationship and trust enforcement.
- Relationship-level Agent permission enforcement.
- Dedicated connected receptionist interface.
- Deterministic receptionist that clearly identifies itself as an automated agent.
- Approved public retrieval from:
  - Public profile/contact settings
  - Published portfolio projects
  - Published blog posts
- Source links returned with relevant answers.
- No private CRM notes, owner-only knowledge, credentials, private conversations, or unrestricted model access.
- Existing Call Center callback and message records.
- Existing CRM activity and administrator notifications.
- Human-transfer handoff to the existing connected browser-call interface.
- Session transcripts, question counts, routing state, summaries, and events.
- Administrator routing/settings/session workspace at `/portal/pod-receptionist.php`.
- Discovery capability `agent_receptionist` with `approved_public_sources_only` retrieval scope.

## Important scope boundary

v63.3 is the **Agent Receptionist Routing Foundation**. It provides real routing, screening, public-information answers, callback/message capture, and human handoff through text interaction.

It does not claim universal live speech streaming. A later voice section can add speech recognition, voice synthesis, and HomeServer voice runtime on top of these stable routing/session/action contracts.

## Call behavior

1. A connected POD contact clicks Call.
2. The scoped call link authenticates the relationship.
3. The recipient POD reads its public line status and receptionist policy.
4. `owner_first` opens the existing connected call page.
5. `agent_first`, `agent_only`, `voicemail`, or `callback` opens the receptionist interface.
6. The receptionist can answer approved public questions.
7. The caller can leave a message, request a callback, or transfer to the owner when permitted and available.
8. Actions are written to the existing Call Center, CRM, notification, and audit systems.

Anonymous public visitors continue using `/call-dave.php` without any change.

## Installation

1. Back up the database and application files.
2. Preserve live `config.php` and the complete `storage/` directory.
3. Confirm the v63, v63.1, and v63.2 migrations are installed.
4. Upload the v63.3 application files.
5. Import `database/pod_agent_receptionist_v63_3.sql` once.
6. Open `/portal/pod-receptionist.php`.
7. Configure the agent name, greeting, routes, actions, public-source permissions, question limit, and session timeout.
8. In `/portal/pod-connections.php`, confirm the test relationship is Connected and Agent access is Public or Relationship.
9. Issue/use a connected call link from `/portal/pod-contacts.php`.
10. Verify the selected routing state opens the correct owner or receptionist interface.
11. Test public portfolio/blog questions, message taking, callback capture, and human transfer.
12. Confirm `/call-dave.php`, `/connected-call.php`, local Communications, POD Messages, and feeds remain operational.

## Security model

- Requires a valid connected-call session established by the relationship-scoped call token.
- Revalidates relationship status, trust, and Agent permission.
- API requests require CSRF protection and rate limiting.
- Session expiration is owner-configurable from 5 to 120 minutes.
- Question counts are capped.
- Public-answer retrieval calls only public profile, public portfolio, and public blog functions.
- Retrieved content is treated as data, not system instruction.
- No state-changing action occurs from a free-form question.
- Message, callback, and transfer actions require explicit caller actions.
- The receptionist states that it is automated and is not the owner.

## Future voice layer

The future voice section should reuse:

- `pod_agent_receptionist_settings`
- `pod_agent_receptionist_sessions`
- `pod_agent_receptionist_messages`
- `pod_agent_receptionist_events`
- Existing transfer, callback, message, summary, and routing functions

This avoids building a separate voice-only agent or duplicating communication history.
