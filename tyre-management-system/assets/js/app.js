document.addEventListener('DOMContentLoaded', function () {
    const tooltipTriggerList = document.querySelectorAll('.action-pill[title]');
    tooltipTriggerList.forEach((el) => {
        if (window.bootstrap && window.bootstrap.Tooltip) {
            new window.bootstrap.Tooltip(el);
        }
    });

    const tables = document.querySelectorAll('table');
    tables.forEach((table) => {
        table.classList.add('table-hover');
    });

    const incrementModals = document.querySelectorAll('.increment-modal');
    incrementModals.forEach((modalEl) => {
        const percentInput = modalEl.querySelector('.increment-percent');
        const amountInput = modalEl.querySelector('.increment-amount');
        const salaryPreview = modalEl.querySelector('.increment-new-salary');
        const currentSalary = parseFloat(modalEl.getAttribute('data-current-salary') || '0');
        if (!percentInput || !amountInput || !salaryPreview) return;

        const recalc = () => {
            const percent = parseFloat(percentInput.value || '0');
            const amount = (currentSalary * percent) / 100;
            const nextSalary = currentSalary + amount;
            amountInput.value = amount.toFixed(2);
            salaryPreview.textContent = nextSalary.toFixed(2);
        };

        percentInput.addEventListener('input', recalc);
        recalc();
    });
});

