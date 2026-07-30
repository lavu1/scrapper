(function () {
  'use strict';

  const script = document.currentScript;
  const site = script?.dataset.site || window.CustomNotificationSite;
  const apiBase = (script?.dataset.api || 'https://api.alphilnetworks.com/notifications').replace(/\/$/, '');
  let cachedConfig;

  function base64ToBytes(value) {
    const padded = value + '='.repeat((4 - value.length % 4) % 4);
    const raw = atob(padded.replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (character) => character.charCodeAt(0));
  }

  function sameBytes(left, right) {
    const first = left instanceof ArrayBuffer
      ? new Uint8Array(left)
      : new Uint8Array(left.buffer, left.byteOffset || 0, left.byteLength);
    const second = right instanceof ArrayBuffer
      ? new Uint8Array(right)
      : new Uint8Array(right.buffer, right.byteOffset || 0, right.byteLength);
    return first.length === second.length && first.every((value, index) => value === second[index]);
  }

  let runtimeFilters = {};

  function elementFilters() {
    const result = {};
    document.querySelectorAll('[data-notification-filter]').forEach((element) => {
      const key = element.dataset.notificationFilter;
      if (!key || element.disabled || (['checkbox', 'radio'].includes(element.type) && !element.checked)) return;
      const value = element.value?.trim();
      if (!value) return;
      if (Object.prototype.hasOwnProperty.call(result, key)) {
        result[key] = Array.isArray(result[key]) ? [...result[key], value] : [result[key], value];
      } else {
        result[key] = value;
      }
    });
    return result;
  }

  function filters() {
    try {
      return { ...JSON.parse(script?.dataset.filters || '{}'), ...elementFilters(), ...runtimeFilters };
    } catch {
      return { ...elementFilters(), ...runtimeFilters };
    }
  }

  async function configuration() {
    if (!cachedConfig) {
      const response = await fetch(`${apiBase}/v1/config?site=${encodeURIComponent(site)}`, { mode: 'cors' });
      if (!response.ok) throw new Error('Notification configuration is unavailable.');
      cachedConfig = await response.json();
    }
    return cachedConfig;
  }

  function supported() {
    return Boolean(site && window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window);
  }

  function status(message, state) {
    document.querySelectorAll('[data-custom-push-status]').forEach((element) => {
      element.textContent = message;
      element.dataset.state = state || '';
    });
    document.querySelectorAll('[data-enable-notifications]').forEach((button) => {
      button.dataset.state = state || '';
      if (state === 'enabled') button.textContent = button.dataset.enabledLabel || 'Notifications enabled';
    });
    window.dispatchEvent(new CustomEvent('custom-notifications:status', { detail: { message, state } }));
  }

  async function registration() {
    await navigator.serviceWorker.register('/custom-push-sw.js', { scope: '/' });
    return navigator.serviceWorker.ready;
  }

  async function saveSubscription(subscription, publicKey) {
    const value = subscription.toJSON();
    const response = await fetch(`${apiBase}/v1/push/subscriptions`, {
      method: 'POST',
      mode: 'cors',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ site, ...value, contentEncoding: PushManager.supportedContentEncodings?.[0] || 'aes128gcm', filters: filters() }),
    });
    if (!response.ok) throw new Error('The browser subscription could not be saved.');
    const result = await response.json();
    localStorage.setItem(`custom-push:${site}`, JSON.stringify({ id: result.id, enabledAt: new Date().toISOString() }));
    localStorage.setItem(`custom-push-vapid:${site}`, publicKey);
    return result;
  }

  async function retireSubscription(subscription) {
    try {
      await fetch(`${apiBase}/v1/push/subscriptions`, {
        method: 'DELETE', mode: 'cors', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ site, endpoint: subscription.endpoint }),
      });
    } catch (error) {
      console.warn('The old server subscription could not be retired immediately:', error);
    }
    await subscription.unsubscribe();
  }

  async function currentSubscription(serviceWorker, config) {
    const expectedKey = base64ToBytes(config.publicKey);
    let subscription = await serviceWorker.pushManager.getSubscription();
    if (subscription) {
      const currentKey = subscription.options?.applicationServerKey;
      const rememberedKey = localStorage.getItem(`custom-push-vapid:${site}`);
      const usesCurrentKey = currentKey
        ? sameBytes(currentKey, expectedKey)
        : rememberedKey === config.publicKey;
      if (!usesCurrentKey) {
        await retireSubscription(subscription);
        subscription = null;
      }
    }
    if (!subscription) {
      subscription = await serviceWorker.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: expectedKey,
      });
    }
    return subscription;
  }

  async function enable(options = {}) {
    runtimeFilters = options.filters || runtimeFilters;
    if (!supported()) throw new Error('This browser does not support website notifications.');
    if (Notification.permission === 'denied') throw new Error('Notifications are blocked. Enable them in your browser settings.');
    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    if (isIos && !standalone) throw new Error('On iPhone, first add this website to your Home Screen, then enable notifications from the installed web app.');

    const permission = Notification.permission === 'granted'
      ? 'granted'
      : await Notification.requestPermission();
    if (permission !== 'granted') {
      localStorage.setItem(`custom-push-permission:${site}`, permission);
      throw new Error('Notification permission was not granted.');
    }

    const config = await configuration();
    const serviceWorker = await registration();
    const subscription = await currentSubscription(serviceWorker, config);
    await saveSubscription(subscription, config.publicKey);
    status('Notifications enabled', 'enabled');
    return subscription;
  }

  async function disable() {
    if (!supported()) return;
    const serviceWorker = await registration();
    const subscription = await serviceWorker.pushManager.getSubscription();
    if (subscription) {
      await fetch(`${apiBase}/v1/push/subscriptions`, {
        method: 'DELETE', mode: 'cors', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ site, endpoint: subscription.endpoint }),
      });
      await subscription.unsubscribe();
    }
    localStorage.removeItem(`custom-push:${site}`);
    localStorage.removeItem(`custom-push-vapid:${site}`);
    status('Notifications disabled', 'disabled');
  }

  async function syncExisting() {
    if (!supported() || Notification.permission !== 'granted') return;
    try {
      const config = await configuration();
      const serviceWorker = await registration();
      const subscription = await currentSubscription(serviceWorker, config);
      await saveSubscription(subscription, config.publicKey);
      status('Notifications enabled', 'enabled');
    } catch (error) {
      console.warn('Could not refresh push subscription:', error);
    }
  }

  async function subscribeEmail(form) {
    const data = new FormData(form);
    const emailFilters = { ...filters() };
    form.querySelectorAll('[data-email-filter]').forEach((element) => {
      if (element.disabled || (['checkbox', 'radio'].includes(element.type) && !element.checked) || !element.value) return;
      emailFilters[element.dataset.emailFilter] = element.value;
    });
    const response = await fetch(`${apiBase}/v1/email/subscriptions`, {
      method: 'POST', mode: 'cors', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        site,
        email: data.get('email'),
        name: data.get('name') || undefined,
        frequency: data.get('frequency') || 'immediate',
        filters: emailFilters,
      }),
    });
    if (!response.ok) throw new Error('The email alert could not be saved. Please try again.');
    const result = await response.json();
    const message = result.subscribed ? 'Email alerts are active.' : 'Check your email to confirm the alert.';
    form.querySelectorAll('[data-email-notification-status]').forEach((element) => { element.textContent = message; });
    form.reset();
    return result;
  }

  document.addEventListener('click', async (event) => {
    const enableButton = event.target.closest('[data-enable-notifications]');
    const disableButton = event.target.closest('[data-disable-notifications]');
    if (!enableButton && !disableButton) return;
    event.preventDefault();
    const button = enableButton || disableButton;
    button.disabled = true;
    try {
      if (enableButton) await enable(); else await disable();
    } catch (error) {
      status(error.message, 'error');
    } finally {
      button.disabled = false;
    }
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-email-notification-form]');
    if (!form) return;
    event.preventDefault();
    const button = form.querySelector('[type="submit"]');
    if (button) button.disabled = true;
    try {
      await subscribeEmail(form);
    } catch (error) {
      form.querySelectorAll('[data-email-notification-status]').forEach((element) => { element.textContent = error.message; });
    } finally {
      if (button) button.disabled = false;
    }
  });

  window.CustomNotifications = { enable, disable, supported, syncExisting, subscribeEmail };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', syncExisting, { once: true });
  else syncExisting();
})();
