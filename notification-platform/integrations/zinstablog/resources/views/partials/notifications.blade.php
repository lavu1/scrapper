@if (config('services.notification_platform.enabled'))
  <details class="notification-dock">
    <summary aria-label="Open notification options"><i class="bi bi-bell-fill"></i></summary>
    <div class="notification-panel">
      <h2>Get the latest alerts</h2>
      <p>Receive breaking news and new job notifications from ZinstaBlog.</p>
      <button type="button" data-enable-notifications data-enabled-label="Browser notifications enabled" class="btn btn-primary w-100">Enable browser notifications</button>
      <div data-custom-push-status class="notification-status" aria-live="polite">You will only be asked for browser permission once.</div>
      <hr>
      <form data-email-notification-form>
        <label for="notification-email" class="form-label">Email alerts</label>
        <input id="notification-email" name="email" type="email" required class="form-control" placeholder="you@example.com">
        <select name="frequency" class="form-select mt-2" aria-label="Email frequency">
          <option value="immediate">Immediately</option>
          <option value="daily">Daily digest</option>
          <option value="weekly">Weekly digest</option>
        </select>
        <button type="submit" class="btn btn-dark w-100 mt-2">Subscribe by email</button>
        <div data-email-notification-status class="notification-status" aria-live="polite"></div>
      </form>
    </div>
  </details>
  <style>
    .notification-dock{position:fixed;right:20px;bottom:82px;z-index:1050}.notification-dock>summary{list-style:none;width:52px;height:52px;border-radius:50%;display:grid;place-items:center;background:#24364f;color:#fff;box-shadow:0 12px 30px rgba(15,23,42,.28);cursor:pointer}.notification-dock>summary::-webkit-details-marker{display:none}.notification-panel{position:absolute;right:0;bottom:62px;width:min(350px,calc(100vw - 32px));padding:20px;background:#fff;border:1px solid #dbe3ef;border-radius:12px;box-shadow:0 18px 45px rgba(15,23,42,.22)}.notification-panel h2{font-size:1.1rem}.notification-panel p,.notification-status{font-size:.82rem;color:#64748b}.notification-status{margin-top:8px;min-height:18px}
  </style>
  <script src="{{ rtrim(config('services.notification_platform.url'), '/') }}/assets/client.js" data-site="{{ config('services.notification_platform.site') }}" data-api="{{ rtrim(config('services.notification_platform.url'), '/') }}" defer></script>
@endif
