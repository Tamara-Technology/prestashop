(function () {
  function isTamaraPaymentOption(paymentOption) {
    var radio = paymentOption.querySelector('input[type="radio"]');
    if (radio && radio.getAttribute('data-module-name') === 'tamaraprestashop') {
      return true;
    }

    var img = paymentOption.querySelector('label img, img.tamara-payment-label__logo');
    return !!(img && img.src && img.src.indexOf('tamara') !== -1);
  }

  function findPaymentOptionWrapper(block) {
    var paymentOption = block.closest('.payment-option');
    if (paymentOption) {
      return paymentOption;
    }

    var additionalInfo = block.closest('.js-additional-information');
    if (additionalInfo && additionalInfo.previousElementSibling) {
      return additionalInfo.previousElementSibling;
    }

    return null;
  }

  function ensureLogoBeforeText(content) {
    var textNode = content.querySelector(':scope > span');
    var logoNode = content.querySelector(':scope > img');

    if (logoNode && textNode && logoNode.nextElementSibling !== textNode) {
      content.insertBefore(logoNode, textNode);
    }
  }

  function ensureTextBeforeLogo(content) {
    var textNode = content.querySelector(':scope > span');
    var logoNode = content.querySelector(':scope > img');

    if (logoNode && textNode && textNode.nextElementSibling !== logoNode) {
      content.insertBefore(textNode, logoNode);
    }
  }

  function highlightUnavailableText(textEl) {
    if (!textEl || textEl.dataset.tamaraHighlighted === '1') {
      return;
    }

    var text = textEl.textContent;
    var patterns = [/\bnot\b/i, /غير/];

    for (var i = 0; i < patterns.length; i++) {
      var match = text.match(patterns[i]);
      if (match) {
        textEl.innerHTML = text.replace(
          match[0],
          '<span class="tamara-payment-label__highlight">' + match[0] + '</span>'
        );
        textEl.dataset.tamaraHighlighted = '1';
        return;
      }
    }
  }

  function layoutTamaraPaymentLabel(paymentOption) {
    var label = paymentOption.querySelector('label');
    if (!label) {
      return;
    }

    var isUnavailable = paymentOption.classList.contains('tamara-is-unavailable');
    var content = label.querySelector('.tamara-payment-label__content');

    if (!content) {
      var textSpan = label.querySelector(':scope > span');
      var logoImg = label.querySelector(':scope > img');

      if (!textSpan || !logoImg) {
        return;
      }

      content = document.createElement('span');
      content.className = 'tamara-payment-label__content';
      label.insertBefore(content, textSpan);

      if (isUnavailable) {
        content.appendChild(textSpan);
        content.appendChild(logoImg);
      } else {
        content.appendChild(logoImg);
        content.appendChild(textSpan);
      }
    } else if (isUnavailable) {
      ensureTextBeforeLogo(content);
    } else {
      ensureLogoBeforeText(content);
    }

    content.style.display = 'inline-flex';
    content.style.alignItems = 'center';
    content.style.flexWrap = 'nowrap';
    content.style.gap = '10px';

    var textSpan = content.querySelector(':scope > span');
    if (textSpan) {
      textSpan.classList.add('tamara-payment-label__text');
    }

    var logoImg = content.querySelector('img');
    if (logoImg) {
      logoImg.classList.add('tamara-payment-label__logo');
    }
  }

  function applyUnavailableStyles(paymentOption) {
    var label = paymentOption.querySelector('label');
    if (label) {
      label.classList.add('tamara-payment-label--unavailable');
    }

    var content = paymentOption.querySelector('.tamara-payment-label__content');
    if (content) {
      content.style.setProperty('opacity', '0.3', 'important');
    }

    paymentOption.querySelectorAll('.tamara-payment-label__text').forEach(function (textEl) {
      highlightUnavailableText(textEl);
      textEl.style.setProperty('color', '#6b7280', 'important');
    });

    paymentOption.querySelectorAll('.tamara-payment-label__logo').forEach(function (imgEl) {
      imgEl.style.setProperty('filter', 'none', 'important');
    });
  }

  function disableTamaraPaymentOption(paymentOption) {
    paymentOption.classList.add('tamara-payment-option', 'tamara-is-unavailable');

    var radio = paymentOption.querySelector('input[type="radio"]');
    if (radio) {
      radio.disabled = true;
      radio.checked = false;
    }

    layoutTamaraPaymentLabel(paymentOption);
    applyUnavailableStyles(paymentOption);

    var additionalInfoBlock = paymentOption.nextElementSibling;
    if (additionalInfoBlock && additionalInfoBlock.classList.contains('js-additional-information')) {
      additionalInfoBlock.classList.add('ps-hidden');

      var payForm = additionalInfoBlock.nextElementSibling;
      if (payForm && payForm.classList.contains('js-payment-option-form')) {
        payForm.classList.add('ps-hidden');
      }
    }
  }

  function initTamaraPaymentLabelLayout() {
    document.querySelectorAll('.payment-option').forEach(function (paymentOption) {
      if (!isTamaraPaymentOption(paymentOption)) {
        return;
      }

      paymentOption.classList.add('tamara-payment-option');
      layoutTamaraPaymentLabel(paymentOption);

      if (paymentOption.classList.contains('tamara-is-unavailable')) {
        applyUnavailableStyles(paymentOption);
      }
    });
  }

  function initTamaraUnavailableOptions() {
    document.querySelectorAll('[data-tamara-unavailable="1"]').forEach(function (block) {
      var paymentOption = findPaymentOptionWrapper(block);
      if (!paymentOption) {
        return;
      }

      disableTamaraPaymentOption(paymentOption);
    });
  }

  function initTamaraCheckoutUi() {
    initTamaraUnavailableOptions();
    initTamaraPaymentLabelLayout();
  }

  document.addEventListener('DOMContentLoaded', initTamaraCheckoutUi);
  window.addEventListener('load', initTamaraCheckoutUi);

  if (window.prestashop && prestashop.on) {
    prestashop.on('updatedCheckoutStep', initTamaraCheckoutUi);
  }
})();
