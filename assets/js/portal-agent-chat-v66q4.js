/* North Mountain Media build: 20260731-agent-chat-workspace-v66Q4 */
(() => {
  'use strict';

  const installStyles = () => {
    if (document.querySelector('[data-agent-chat-v66q4-style]')) return;

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '../assets/css/portal-agent-chat-v66q4.css?v=20260731-v66Q4';
    link.dataset.agentChatV66q4Style = '';
    document.head.appendChild(link);
  };

  const integrateAgentWorkspace = () => {
    if (document.body.dataset.portalActive !== 'agent') return;

    const page = document.querySelector('[data-agent-chat-page]');
    const empty = document.querySelector('[data-agent-chat-empty]');
    const chat = document.querySelector('[data-admin-assistant-chat]');
    const messages = document.querySelector('[data-admin-assistant-messages]');
    const loading = document.querySelector('[data-admin-assistant-loading]');
    const footer = document.querySelector('[data-admin-assistant-footer]');
    const input = document.querySelector('[data-admin-assistant-input]');

    if (!page || !chat || !messages || !footer) return;

    page.dataset.agentChatIntegrated = '1';
    chat.classList.add('agent-chat-conversation');
    footer.classList.add('agent-chat-composer-dock');

    if (loading) chat.appendChild(loading);
    page.append(chat, footer);

    if (input) {
      input.placeholder = 'Message your North Mountain agent…';
      input.setAttribute('aria-label', 'Message your North Mountain agent');
    }

    const enforceWorkspaceState = () => {
      chat.hidden = false;
      footer.hidden = false;
      document.body.classList.remove(
        'admin-assistant-active',
        'admin-assistant-querying'
      );
    };

    const syncConversationState = () => {
      const hasMessages = messages.childElementCount > 0;
      if (empty) empty.hidden = hasMessages;
      page.classList.toggle('agent-chat-has-messages', hasMessages);
      enforceWorkspaceState();

      if (hasMessages) {
        window.requestAnimationFrame(() => {
          messages.scrollTop = messages.scrollHeight;
        });
      }
    };

    enforceWorkspaceState();
    syncConversationState();

    new MutationObserver(syncConversationState).observe(messages, {
      childList: true,
    });

    new MutationObserver(enforceWorkspaceState).observe(chat, {
      attributes: true,
      attributeFilter: ['hidden'],
    });

    new MutationObserver(() => {
      if (
        document.body.classList.contains('admin-assistant-active')
        || document.body.classList.contains('admin-assistant-querying')
      ) {
        enforceWorkspaceState();
      }
    }).observe(document.body, {
      attributes: true,
      attributeFilter: ['class'],
    });
  };

  const initialize = () => {
    installStyles();
    integrateAgentWorkspace();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
