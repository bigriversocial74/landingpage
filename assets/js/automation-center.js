/* North Mountain Media build: 20260730-automation-center-v66K */
(() => {
  'use strict';

  const root = document.querySelector('[data-automation-center]');
  if (!root) return;

  const conditionTemplate = () => {
    const row = document.createElement('div');
    row.className = 'automation-builder-row';
    row.dataset.builderRow = 'condition';
    row.innerHTML = `
      <select name="condition_field[]" aria-label="Condition field">
        <option value="event_key">Event key</option>
        <option value="source_type">Source type</option>
        <option value="priority">Priority</option>
        <option value="category">Category</option>
        <option value="title">Title</option>
        <option value="body">Body</option>
        <option value="entity_type">Entity type</option>
        <option value="participant">Participant</option>
        <option value="workflow_status">Workflow status</option>
        <option value="assigned_user_id">Assigned user ID</option>
        <option value="needs_response">Needs response</option>
        <option value="crm_contact_id">CRM contact ID</option>
        <option value="occurred_hour">Occurred hour</option>
        <option value="occurred_weekday">Occurred weekday</option>
      </select>
      <select name="condition_operator[]" aria-label="Condition operator">
        <option value="equals">Equals</option>
        <option value="not_equals">Does not equal</option>
        <option value="contains">Contains</option>
        <option value="not_contains">Does not contain</option>
        <option value="starts_with">Starts with</option>
        <option value="in">In comma-separated list</option>
        <option value="not_in">Not in list</option>
        <option value="exists">Exists</option>
        <option value="not_exists">Does not exist</option>
        <option value="priority_at_least">Priority at least</option>
      </select>
      <input name="condition_value[]" type="text" maxlength="500" placeholder="Value">
      <button class="automation-button danger small" type="button" data-remove-row aria-label="Remove condition">Remove</button>`;
    return row;
  };

  const actionTemplate = () => {
    const row = document.createElement('div');
    row.className = 'automation-builder-row action';
    row.dataset.builderRow = 'action';
    row.innerHTML = `
      <select name="action_type[]" aria-label="Action type">
        <option value="set_priority">Set inbox priority</option>
        <option value="assign_user">Assign administrator</option>
        <option value="set_needs_response">Set needs response</option>
        <option value="set_workflow_status">Set workflow status</option>
        <option value="set_pinned">Pin or unpin</option>
        <option value="set_snooze_minutes">Snooze</option>
        <option value="archive_for_recipient">Archive for recipient</option>
        <option value="create_notification">Create in-app notification</option>
        <option value="add_crm_activity">Add CRM activity</option>
        <option value="set_crm_follow_up_days">Set CRM follow-up</option>
        <option value="homeserver_proposal">Approval-only HomeServer proposal</option>
      </select>
      <textarea name="action_parameters[]" rows="3" maxlength="3000" spellcheck="false" placeholder='JSON parameters, for example {"value":"high"}'></textarea>
      <button class="automation-button danger small" type="button" data-remove-row aria-label="Remove action">Remove</button>`;
    return row;
  };

  root.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const addButton = target.closest('[data-add-row]');
    if (addButton) {
      const type = addButton.getAttribute('data-add-row');
      const container = root.querySelector(`[data-builder="${type}"]`);
      if (!container) return;
      container.append(type === 'condition' ? conditionTemplate() : actionTemplate());
      container.querySelector(':scope > :last-child select, :scope > :last-child input, :scope > :last-child textarea')?.focus();
      return;
    }

    const removeButton = target.closest('[data-remove-row]');
    if (removeButton) {
      const row = removeButton.closest('[data-builder-row]');
      const container = row?.parentElement;
      if (!row || !container) return;
      const rows = container.querySelectorAll('[data-builder-row]');
      if (rows.length === 1) {
        row.querySelectorAll('input,textarea').forEach((field) => { field.value = ''; });
      } else {
        row.remove();
      }
      return;
    }

    const preset = target.closest('[data-action-preset]');
    if (preset) {
      const row = preset.closest('[data-builder-row="action"]');
      const textarea = row?.querySelector('textarea[name="action_parameters[]"]');
      if (textarea) textarea.value = preset.getAttribute('data-action-preset') || '{}';
    }
  });

  root.querySelectorAll('form[data-confirm-message]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const message = form.getAttribute('data-confirm-message') || 'Continue?';
      if (!window.confirm(message)) event.preventDefault();
    });
  });

  root.querySelectorAll('textarea[data-json]').forEach((textarea) => {
    textarea.addEventListener('blur', () => {
      const value = textarea.value.trim();
      if (!value) return;
      try {
        textarea.value = JSON.stringify(JSON.parse(value), null, 2);
        textarea.setCustomValidity('');
      } catch (error) {
        textarea.setCustomValidity('Enter valid JSON.');
      }
    });
  });
})();