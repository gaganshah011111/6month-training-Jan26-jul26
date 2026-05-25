(function () {
    'use strict';
    var customer = document.getElementById('payCustomer');
    var invoice = document.getElementById('payInvoice');
    var totalEl = document.getElementById('payInvTotal');
    var paidEl = document.getElementById('payInvPaid');
    var remainEl = document.getElementById('payInvRemain');
    var amountEl = document.getElementById('payAmount');
    if (!invoice) {
        return;
    }

    function money(n) {
        var x = parseFloat(n) || 0;
        return '₹' + x.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function filterInvoices() {
        if (!customer || !invoice) {
            return;
        }
        var cid = customer.value;
        Array.prototype.forEach.call(invoice.options, function (opt) {
            if (!opt.value) {
                return;
            }
            var match = !cid || opt.getAttribute('data-customer') === cid;
            opt.hidden = !match;
            opt.disabled = !match;
        });
        if (invoice.selectedOptions[0] && invoice.selectedOptions[0].disabled) {
            invoice.value = '';
        }
        syncAmounts();
    }

    function syncAmounts() {
        var opt = invoice.options[invoice.selectedIndex];
        if (!opt || !opt.value) {
            if (totalEl) totalEl.value = '';
            if (paidEl) paidEl.value = '';
            if (remainEl) remainEl.value = '';
            return;
        }
        var total = parseFloat(opt.getAttribute('data-total') || '0');
        var paid = parseFloat(opt.getAttribute('data-paid') || '0');
        var bal = parseFloat(opt.getAttribute('data-balance') || '0');
        if (totalEl) totalEl.value = money(total);
        if (paidEl) paidEl.value = money(paid);
        if (remainEl) remainEl.value = money(bal);
        if (amountEl) {
            amountEl.max = String(Math.max(0.01, bal));
            amountEl.min = '0.01';
            amountEl.step = '0.01';
            if (!amountEl.value || parseFloat(amountEl.value) > bal) {
                amountEl.value = bal > 0 ? String(bal) : '';
            }
            amountEl.placeholder = bal > 0 ? money(bal) : '0.00';
        }
    }

    if (customer) {
        customer.addEventListener('change', filterInvoices);
    }
    invoice.addEventListener('change', syncAmounts);
    filterInvoices();
    syncAmounts();
})();
