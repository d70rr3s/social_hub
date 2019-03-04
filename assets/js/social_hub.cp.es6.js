/**
 * @file
 * JS utilities for Social Hub module.
 */

/**
 * Fallback for browsers with no Clipboard API support.
 *
 * @param {string} text Text to copy.
 * @param {?Function} popUpCallback Callback function to show notification
 *     (optional).
 */
function fallbackCopyTextToClipboard(text, popUpCallback) {
  const clipboard = document.createElement('textarea');
  clipboard.value = text;
  document.body.appendChild(clipboard);
  clipboard.focus();
  clipboard.select();

  try {
    document.execCommand('copy');
    popUpCallback(Drupal.t('Copied!'));
  } catch (err) {
    popUpCallback(
      Drupal.t(`Error copying text to clipboard: ${err}`),
      Drupal.t('Error'),
    );
    console.error('Error copying text to clipboard', err);
  }

  document.body.removeChild(clipboard);
}

/**
 * Close popup trigger.
 *
 * @param {string} id The popup id.
 */
function closePopup(id) {
  const el = document.querySelector(`#${id}`);
  el.parentNode.removeChild(el);
}

/**
 * Generate unique ID.
 *
 * @param {number} length ID length (defaults: 12).
 *
 * @return {string} The generated ID.
 */
function uniqueid(length = 12) {
  let id = '';

  do {
    id = Math.random()
      .toString(36)
      .substr(2, length);
  } while (id.length < length);

  return id;
}

/**
 * Fallback popup function.
 *
 * @param {string} content The popup content.
 * @param {?string} title The popup title (optional).
 */
function fallbackPopUp(content, title = null) {
  const id = uniqueid();
  const alStart = Drupal.t('Begins %string.', { '%string': Drupal.t('popup') });
  const alEnd = Drupal.t('Ends %string.', { '%string': Drupal.t('popup') });
  const alClose = Drupal.t('Click to close popup.');
  const template = `
<div class="popup">
  <span class="element-invisible">${alStart}</span>
  <h2>${title}</h2>
  <a class="close" href="#" onclick="closePopup('${id}')" aria-label="${alClose}">&times;</a>
  <div class="content">${content}</div>
  <span class="element-invisible">${alEnd}</span>
</div>
`;
  const overlay = document.createElement('div');
  overlay.id = id;
  overlay.classList.add('overlay');
  overlay.innerHTML = template;
  document.body.appendChild(overlay);
}

/**
 * Copy text to clipboard.
 *
 * @param {string} text Text to copy.
 * @param {?Function} popUpCallback Callback function to show notification
 *     (optional).
 */
function copyTextToClipboard(text, popUpCallback = null) {
  if (popUpCallback === null || !(typeof popUpCallback === 'function')) {
    popUpCallback = fallbackPopUp;
  }

  if (!navigator.clipboard) {
    fallbackCopyTextToClipboard(text, popUpCallback);
    return;
  }

  navigator.clipboard.writeText(text).then(
    () => {
      popUpCallback(Drupal.t('Copied!'));
    },
    err => {
      popUpCallback(
        Drupal.t(`Error copying text to clipboard: ${err}`),
        Drupal.t('Error'),
      );
      console.error('Error copying text to clipboard: ', err);
    },
  );
}
