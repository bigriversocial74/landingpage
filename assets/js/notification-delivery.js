(() => {
  'use strict';

  const root = document.querySelector('[data-notification-delivery]');
  if (!root) return;

  const status = root.querySelector('[data-push-status]');
  const enableButton = root.querySelector('[data-enable-push]');
  const disableButton = root.querySelector('[data-disable-push]');
  const testButton = root.querySelector('[data-test-local-notification]');
  const endpoint = root.dataset.pushEndpoint || '';
  const csrf = root.dataset.csrfToken || '';
  const publicKey = root.dataset.vapidPublicKey || '';

  const setStatus = (message, isError = false) => {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('is-error', isError);
  };

  const base64UrlToUint8Array = value => {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map(character => character.charCodeAt(0)));
  };

  const post = async payload => {
    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf
      },
      body: JSON.stringify(payload)
    });
    const data = await response.json().catch(() => ({ ok: false, message: 'The server returned an invalid response.' }));
    if (!response.ok || !data.ok) throw new Error(data.message || 'The notification request failed.');
    return data;
  };

  const registration = async () => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      throw new Error('This browser does not support Web Push.');
    }
    return navigator.serviceWorker.register('/notification-service-worker.js', { scope: '/' });
  };

  const refresh = async () => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      setStatus('Web Push is not supported in this browser.', true);
      if (enableButton) enableButton.disabled = true;
      if (disableButton) disableButton.disabled = true;
      return;
    }
    const reg = await registration();
    const subscription = await reg.pushManager.getSubscription();
    if (enableButton) enableButton.hidden = Boolean(subscription);
    if (disableButton) disableButton.hidden = !subscription;
    setStatus(subscription ? 'This browser is subscribed.' : 'This browser is not subscribed.');
  };

  enableButton?.addEventListener('click', async () => {
    enableButton.disabled = true;
    try {
      if (!publicKey) throw new Error('Web Push has not been initialized by an administrator.');
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') throw new Error('Notification permission was not granted.');
      const reg = await registration();
      let subscription = await reg.pushManager.getSubscription();
      if (!subscription) {
        subscription = await reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: base64UrlToUint8Array(publicKey)
        });
      }
      await post({ action: 'subscribe', subscription: subscription.toJSON() });
      setStatus('This browser is subscribed.');
      await refresh();
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'Web Push could not be enabled.', true);
    } finally {
      enableButton.disabled = false;
    }
  });

  disableButton?.addEventListener('click', async () => {
    disableButton.disabled = true;
    try {
      const reg = await registration();
      const subscription = await reg.pushManager.getSubscription();
      if (subscription) {
        await post({ action: 'unsubscribe', endpoint: subscription.endpoint });
        await subscription.unsubscribe();
      }
      setStatus('This browser is not subscribed.');
      await refresh();
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'Web Push could not be disabled.', true);
    } finally {
      disableButton.disabled = false;
    }
  });

  testButton?.addEventListener('click', async () => {
    try {
      const reg = await registration();
      await reg.showNotification('POD notification test', {
        body: 'Browser notifications are ready on this device.',
        tag: 'nmm-local-test',
        data: { url: window.location.href }
      });
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'The local notification test failed.', true);
    }
  });

  refresh().catch(error => setStatus(error instanceof Error ? error.message : 'Push status could not be loaded.', true));
})();
