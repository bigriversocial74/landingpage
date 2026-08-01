<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-agent-chat-workspace-v66Q4 */

function agent_chat_render(array $user): void
{
    ?>
<section
    class="agent-chat-page"
    data-agent-chat-page
    aria-label="North Mountain agent conversation"
>
    <div class="agent-chat-empty" data-agent-chat-empty>
        <div class="agent-chat-mark" aria-hidden="true">N</div>
        <h2>How can I help?</h2>
        <p>
            Ask about calls, messages, relationships, projects, publishing,
            visitors, or anything available to your connected agent.
        </p>
    </div>
</section>
<?php
}
