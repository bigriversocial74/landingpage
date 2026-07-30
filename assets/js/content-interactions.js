/* North Mountain Media build: 20260730-content-interactions-v66C */
(() => {
  'use strict';

  const nextReactionCount = (count, wasActive, willActive) => Math.max(0, Number(count || 0) + (willActive ? 1 : 0) - (wasActive ? 1 : 0));
  window.NMM_CONTENT_INTERACTION_UTILS = { nextReactionCount };

  const root = document.querySelector('[data-content-interactions]');
  if (!root) return;
  const api = root.dataset.api || '';
  const csrf = root.dataset.csrf || '';
  const contentType = root.dataset.contentType || 'blog_post';
  const contentId = Number(root.dataset.contentId || 0);
  const authenticated = root.dataset.authenticated === '1';
  const status = root.querySelector('[data-interaction-status]');

  const announce = (message, error = false) => {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('error', error);
  };
  const request = async (payload) => {
    if (!authenticated) throw new Error('Sign in with your portal account to participate.');
    const response = await fetch(api, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ content_type: contentType, content_id: contentId, ...payload }),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.ok !== true) throw new Error(result.message || 'The interaction could not be saved.');
    return result.result || {};
  };
  const reloadAfter = (message) => {
    announce(message);
    window.setTimeout(() => window.location.reload(), 350);
  };

  root.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-comment-form]');
    if (!form) return;
    event.preventDefault();
    const textarea = form.querySelector('textarea');
    const body = textarea?.value.trim() || '';
    if (body.length < 2) { announce('Enter a longer comment.', true); return; }
    const submit = form.querySelector('button[type="submit"]');
    if (submit) submit.disabled = true;
    request({ action: 'comment_create', parent_id: Number(form.dataset.parentId || 0), body })
      .then((result) => reloadAfter(result.message || 'Comment submitted.'))
      .catch((error) => announce(error.message, true))
      .finally(() => { if (submit) submit.disabled = false; });
  });

  root.addEventListener('click', (event) => {
    const reaction = event.target.closest('[data-content-reaction]');
    if (reaction) {
      const group = reaction.closest('[data-reaction-target]');
      const targetType = group?.dataset.reactionTarget || 'content';
      const targetId = Number(group?.dataset.targetId || contentId);
      const type = reaction.dataset.contentReaction || '';
      reaction.disabled = true;
      request({ action: 'reaction_toggle', target_type: targetType, target_id: targetId, reaction_type: type })
        .then((result) => {
          group.querySelectorAll('[data-content-reaction]').forEach((button) => {
            const buttonType = button.dataset.contentReaction || '';
            const active = result.active === buttonType;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            const count = button.querySelector(`[data-reaction-count="${CSS.escape(buttonType)}"]`);
            if (count && result.counts) count.textContent = String(result.counts[buttonType] || 0);
          });
          const total = root.querySelector('[data-total-reactions]');
          if (total && targetType === 'content') total.textContent = String(Object.values(result.counts || {}).reduce((sum, value) => sum + Number(value || 0), 0));
          announce(result.active ? 'Reaction saved.' : 'Reaction removed.');
        })
        .catch((error) => announce(error.message, true))
        .finally(() => { reaction.disabled = false; });
      return;
    }

    const toggle = event.target.closest('[data-comment-reply-toggle]');
    if (toggle) {
      const comment = toggle.closest('[data-comment-id]');
      const form = comment?.querySelector(':scope > [data-comment-form]');
      if (form) { form.hidden = !form.hidden; if (!form.hidden) form.querySelector('textarea')?.focus(); }
      return;
    }

    const edit = event.target.closest('[data-comment-edit]');
    if (edit) {
      const comment = edit.closest('[data-comment-id]');
      const commentId = Number(comment?.dataset.commentId || 0);
      const current = edit.dataset.commentBody || '';
      const body = window.prompt('Edit your comment', current);
      if (body === null) return;
      request({ action: 'comment_edit', comment_id: commentId, body })
        .then(() => reloadAfter('Comment updated and returned to moderation.'))
        .catch((error) => announce(error.message, true));
      return;
    }

    const remove = event.target.closest('[data-comment-delete]');
    if (remove) {
      const commentId = Number(remove.closest('[data-comment-id]')?.dataset.commentId || 0);
      if (!window.confirm('Delete this comment? Replies will remain attached to a deleted placeholder.')) return;
      request({ action: 'comment_delete', comment_id: commentId })
        .then(() => reloadAfter('Comment deleted.'))
        .catch((error) => announce(error.message, true));
      return;
    }

    const report = event.target.closest('[data-comment-report]');
    if (report) {
      const commentId = Number(report.closest('[data-comment-id]')?.dataset.commentId || 0);
      const reason = window.prompt('Why are you reporting this comment?', 'Inappropriate or harmful content');
      if (reason === null) return;
      request({ action: 'comment_report', comment_id: commentId, reason })
        .then(() => announce('Report submitted to moderation.'))
        .catch((error) => announce(error.message, true));
    }
  });
})();
