(function () {
  'use strict';

  const MODAL_IDS = [
    'treasuryOpeningModal',
    'treasuryLoanModal',
    'treasuryRepayModal',
    'treasuryAdjustModal',
  ];

  function moveModalsToBody() {
    MODAL_IDS.forEach((id) => {
      const el = document.getElementById(id);
      if (el && el.parentElement !== document.body) {
        document.body.appendChild(el);
      }
    });
  }

  function bindModalTriggers() {
    document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target^="#treasury"]').forEach((btn) => {
      const target = btn.getAttribute('data-bs-target');
      if (!target) {
        return;
      }
      btn.addEventListener('click', (e) => {
        const modalEl = document.querySelector(target);
        if (!modalEl || typeof bootstrap === 'undefined') {
          return;
        }
        e.preventDefault();
        e.stopPropagation();
        bootstrap.Modal.getOrCreateInstance(modalEl, { focus: true }).show();
      });
    });
  }

  function resetFormState(form) {
    delete form.dataset.submitting;
    form.classList.remove('acc-treasury-form--busy');
    const modal = form.closest('.modal');
    if (modal) {
      modal.classList.remove('acc-treasury-modal--busy');
    }
    form.querySelectorAll('.acc-treasury-submit-btn, button[type="submit"]').forEach((btn) => {
      btn.disabled = false;
      if (btn.dataset.labelDefault) {
        btn.innerHTML = btn.dataset.labelDefault;
      }
    });
  }

  function parseAmountInput(raw) {
    const cleaned = String(raw || '').replace(/,/g, '').trim();
    if (cleaned === '') {
      return NaN;
    }
    return Number(cleaned);
  }

  function bindAmountInputs() {
    document.querySelectorAll('.acc-treasury-amount-input').forEach((input) => {
      input.addEventListener('blur', () => {
        const n = parseAmountInput(input.value);
        if (!Number.isFinite(n)) {
          return;
        }
        input.value = n.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
      });
      input.addEventListener('focus', () => {
        input.value = String(input.value).replace(/,/g, '');
      });
    });
  }

  function bindFormSubmitLock() {
    document.querySelectorAll('.acc-treasury-post-form').forEach((form) => {
      form.addEventListener('submit', (e) => {
        if (form.dataset.submitting === '1') {
          e.preventDefault();
          return;
        }
        form.querySelectorAll('.acc-treasury-amount-input').forEach((input) => {
          input.value = String(input.value).replace(/,/g, '');
        });
        const amtInput = form.querySelector('.acc-treasury-amount-input');
        if (amtInput) {
          const max = parseFloat(amtInput.getAttribute('data-max-amount') || '0');
          const val = parseAmountInput(amtInput.value);
          if (Number.isFinite(max) && max > 0 && Number.isFinite(val) && val > max) {
            e.preventDefault();
            alert('Amount is too large. Maximum allowed is ' + max.toLocaleString('en-IN') + '.');
            return;
          }
        }
        form.dataset.submitting = '1';
        form.classList.add('acc-treasury-form--busy');
        const modal = form.closest('.modal');
        if (modal) {
          modal.classList.add('acc-treasury-modal--busy');
        }
        form.querySelectorAll('.acc-treasury-submit-btn, button[type="submit"]').forEach((btn) => {
          if (!btn.dataset.labelDefault) {
            btn.dataset.labelDefault = btn.innerHTML;
          }
          btn.disabled = true;
          btn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving…';
        });
      });

      form.addEventListener('invalid', () => {
        resetFormState(form);
      }, true);
    });

    MODAL_IDS.forEach((id) => {
      const el = document.getElementById(id);
      if (!el) {
        return;
      }
      el.addEventListener('hidden.bs.modal', () => {
        const form = el.querySelector('.acc-treasury-post-form');
        if (form) {
          resetFormState(form);
          if (form.classList.contains('acc-treasury-quick-form')) {
            form.reset();
            const today = new Date().toISOString().slice(0, 10);
            const loanDate = form.querySelector('[name="loan_date"]');
            const payDate = form.querySelector('[name="payment_date"]');
            if (loanDate) {
              loanDate.value = today;
            }
            if (payDate) {
              payDate.value = today;
            }
            const ir = form.querySelector('[name="interest_rate"]');
            if (ir) {
              ir.value = '0';
            }
          }
        }
      });
    });
  }

  function bindTableHorizontalSlider() {
    const wrap = document.getElementById('accTreasuryTableScroll');
    const slider = document.getElementById('accTreasuryHSlider');
    const btnL = document.getElementById('accTreasuryScrollLeft');
    const btnR = document.getElementById('accTreasuryScrollRight');
    if (!wrap || !slider) {
      return;
    }

    const sliderWrap = slider.closest('.acc-treasury-h-slider-wrap');
    const hint = document.querySelector('.acc-treasury-scroll-hint');

    const syncSliderFromScroll = () => {
      const max = Math.max(0, wrap.scrollWidth - wrap.clientWidth);
      const needsScroll = max > 4;
      if (sliderWrap) {
        sliderWrap.style.display = needsScroll ? '' : 'none';
      }
      if (hint) {
        hint.style.display = needsScroll ? '' : 'none';
      }
      slider.disabled = !needsScroll;
      slider.value = max > 0 ? String(Math.round((wrap.scrollLeft / max) * 100)) : '0';
    };

    const scrollFromSlider = () => {
      const max = Math.max(0, wrap.scrollWidth - wrap.clientWidth);
      wrap.scrollLeft = (parseInt(slider.value, 10) / 100) * max;
    };

    slider.addEventListener('input', scrollFromSlider);
    wrap.addEventListener('scroll', syncSliderFromScroll, { passive: true });
    window.addEventListener('resize', syncSliderFromScroll);

    if (btnL) {
      btnL.addEventListener('click', () => {
        wrap.scrollBy({ left: -220, behavior: 'smooth' });
      });
    }
    if (btnR) {
      btnR.addEventListener('click', () => {
        wrap.scrollBy({ left: 220, behavior: 'smooth' });
      });
    }

    syncSliderFromScroll();
  }

  function init() {
    if (typeof bootstrap === 'undefined') {
      return;
    }
    moveModalsToBody();
    bindModalTriggers();
    bindAmountInputs();
    bindFormSubmitLock();
    bindTableHorizontalSlider();
    MODAL_IDS.forEach((id) => {
      const el = document.getElementById(id);
      if (!el) {
        return;
      }
      el.addEventListener('shown.bs.modal', () => {
        const first = el.querySelector(
          'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])'
        );
        if (first) {
          first.focus();
        }
      });
    });
  }

  if (document.readyState === 'complete') {
    init();
  } else {
    window.addEventListener('load', init);
  }
})();
