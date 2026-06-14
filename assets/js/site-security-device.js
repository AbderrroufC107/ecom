(function () {
  var key = 'site_device_id';

  function readCookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()[\]\\/+^]/g, '\\$&') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
  }

  function writeCookie(name, value) {
    var maxAge = 60 * 60 * 24 * 365 * 2;
    document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
  }

  function hash(input) {
    var h = 2166136261;
    for (var i = 0; i < input.length; i += 1) {
      h ^= input.charCodeAt(i);
      h += (h << 1) + (h << 4) + (h << 7) + (h << 8) + (h << 24);
    }
    return (h >>> 0).toString(36);
  }

  function createId() {
    var base = [
      navigator.userAgent || '',
      navigator.language || '',
      screen.width + 'x' + screen.height,
      screen.colorDepth || '',
      new Date().getTimezoneOffset(),
      Math.random().toString(36).slice(2),
      Date.now().toString(36)
    ].join('|');
    return 'dev_' + hash(base) + '_' + Math.random().toString(36).slice(2, 10);
  }

  var deviceId = '';
  try {
    deviceId = localStorage.getItem(key) || '';
  } catch (error) {
    deviceId = '';
  }

  if (!deviceId) {
    deviceId = readCookie(key);
  }
  if (!deviceId) {
    deviceId = createId();
  }

  try {
    localStorage.setItem(key, deviceId);
  } catch (error) {}
  writeCookie(key, deviceId);
  window.siteDeviceId = deviceId;

  function attachToForms() {
    document.querySelectorAll('form').forEach(function (form) {
      var input = form.querySelector('input[name="device_id"]');
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'device_id';
        form.appendChild(input);
      }
      input.value = deviceId;
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachToForms);
  } else {
    attachToForms();
  }
})();
