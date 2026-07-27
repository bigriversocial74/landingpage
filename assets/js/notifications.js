(() => {
  'use strict';

  const token =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
    document.querySelector('input[name="_token"]')?.value ||
    '';

  const apiUrl =
    document.body.dataset.notificationApi ||
    'notifications-api.php';

  const request = async (action, payload = {}) => {
    const response = await fetch(apiUrl, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': token,
      },
      body: JSON.stringify({ action, ...payload }),
    });

    const result = await response.json();

    if (!response.ok || !result.ok) {
      throw new Error(result.message || 'Notification action failed.');
    }

    return result;
  };

  const refreshBadges = (count) => {
    document.querySelectorAll('[data-notification-count]').forEach((badge) => {
      badge.textContent = String(count);
      badge.hidden = Number(count) <= 0;
    });
  };

  document.querySelectorAll('[data-notification-read]').forEach((button) => {
    button.addEventListener('click', async () => {
      const item = button.closest('[data-notification-id]');
      const notificationId = Number(item?.dataset.notificationId || 0);

      if (!notificationId) return;

      try {
        button.disabled = true;
        const result = await request('mark_read', {
          notification_id: notificationId,
        });
        item?.classList.remove('unread');
        button.remove();
        item?.querySelector('.notification-unread-label')?.remove();
        refreshBadges(result.unread_count);
      } catch (error) {
        button.disabled = false;
      }
    });
  });

  document.querySelectorAll('[data-notification-open]').forEach((link) => {
    link.addEventListener('click', () => {
      const item = link.closest('[data-notification-id]');
      const notificationId = Number(item?.dataset.notificationId || 0);

      if (notificationId) {
        navigator.sendBeacon?.(
          apiUrl,
          new URLSearchParams({
            _token: token,
            action: 'mark_read',
            notification_id: String(notificationId),
          })
        );
      }
    });
  });

  document.querySelectorAll('[data-notification-mark-all]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      try {
        await request('mark_all_read');
        document.querySelectorAll('.notification-feed-item.unread')
          .forEach((item) => item.classList.remove('unread'));
        document.querySelectorAll('[data-notification-read], .notification-unread-label')
          .forEach((node) => node.remove());
        refreshBadges(0);
        form.remove();
      } catch (error) {
        // Keep the form available for retry.
      }
    });
  });
})();
