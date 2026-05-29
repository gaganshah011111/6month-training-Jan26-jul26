(function () {
    'use strict';

    var cfg = window.payrollDashboardConfig || {};

    function inr(n) {
        return '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) {
            el.textContent = val;
        }
    }

    function resolveApiUrl(url) {
        if (!url) {
            return '';
        }
        if (/^https?:\/\//i.test(url)) {
            return url;
        }
        if (url.charAt(0) === '/') {
            return window.location.origin + url;
        }
        var path = window.location.pathname || '/';
        var base = path.substring(0, path.lastIndexOf('/') + 1);
        return base + url.replace(/^\//, '');
    }

    function parseJsonResponse(r) {
        return r.text().then(function (text) {
            var data;
            try {
                data = text ? JSON.parse(text) : {};
            } catch (e) {
                throw new Error('Server returned an invalid response. Check you are logged in and try again.');
            }
            if (!r.ok) {
                throw new Error((data && data.error) ? data.error : ('Request failed (' + r.status + ')'));
            }
            return data;
        });
    }

    function loadPreview(modal) {
        var empId = document.getElementById('payrollEmpId').value;
        var month = modal.getAttribute('data-month');
        var url = resolveApiUrl(modal.getAttribute('data-calc-url') || cfg.calcApiUrl || '');
        var extraOt = document.getElementById('pmExtraOt').value || '0';
        var manual = document.getElementById('pmManualDed').value || '0';
        var loading = document.getElementById('payrollModalLoading');
        var body = document.getElementById('payrollModalBody');

        if (!empId || !url) {
            return;
        }

        loading.classList.remove('d-none');
        body.classList.add('d-none');

        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        var full = url + sep + 'employee_id=' + encodeURIComponent(empId)
            + '&month_year=' + encodeURIComponent(month)
            + '&overtime_hours=' + encodeURIComponent(extraOt)
            + '&deductions=' + encodeURIComponent(manual);

        fetch(full, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
            .then(parseJsonResponse)
            .then(function (data) {
                loading.classList.add('d-none');
                body.classList.remove('d-none');
                if (!data.ok) {
                    alert(data.error || 'Calculation failed');
                    return;
                }
                var c = data.calc;
                var att = c.attendance || {};
                var leave = c.leave || {};
                var emp = data.employee || {};

                setText('pmName', emp.full_name || '—');
                setText('pmCode', emp.employee_code || '—');
                setText('pmDept', emp.department || '—');
                setText('pmDesig', emp.designation || '—');
                setText('pmFixedGross', inr(emp.fixed_gross));

                setText('pmPresent', att.present_days);
                setText('pmHalf', att.half_days);
                setText('pmAbsent', att.absent_days);
                setText('pmLate', att.late_days);
                setText('pmOtH', c.overtime_hours);
                setText('pmPL', leave.paid_leave_days);
                setText('pmUL', leave.unpaid_leave_days);

                setText('pmBasic', inr(c.basic));
                setText('pmHra', inr(c.hra_amount));
                setText('pmDa', inr(c.dearness_allowance));
                setText('pmMed', inr(c.medical_allowance));
                setText('pmTravel', inr(c.travel_allowance));
                setText('pmSpecial', inr(c.special_allowance));
                setText('pmPf', inr(c.pf_amount));
                setText('pmEsi', inr(c.esi_employee_amount));
                setText('pmOtAmt', inr(c.overtime_amount));
                setText('pmGross', inr(c.gross_salary));
                setText('pmDed', inr(c.total_deduction));
                setText('pmNet', inr(c.net_salary));
            })
            .catch(function () {
                loading.classList.add('d-none');
                body.classList.remove('d-none');
                alert('Could not load payroll preview. Check your connection.');
            });
    }

    function showPayrollResult(notice) {
        if (!notice || notice.step !== 'payroll') {
            return;
        }
        var modalEl = document.getElementById('payrollResultModal');
        if (!modalEl || typeof bootstrap === 'undefined') {
            return;
        }

        var name = notice.employee_name || '';
        var code = notice.employee_code || '';
        setText('prEmpLine', name + (code ? ' (' + code + ')' : ''));
        setText('prGross', inr(notice.gross_salary));
        setText('prDed', inr(notice.total_deduction));
        setText('prNet', inr(notice.net_salary));
        setText('prBasic', inr(notice.basic));
        setText('prPf', inr(notice.pf_amount));
        setText('prEsi', inr(notice.esi_employee_amount));
        setText('prOtAmt', inr(notice.overtime_amount));
        setText('prOtH', notice.overtime_hours != null ? String(notice.overtime_hours) : '—');
        setText('prPresent', notice.present_days != null ? String(notice.present_days) : '—');

        var slip = document.getElementById('prPayslipLink');
        if (slip && notice.salary_id && cfg.payslipBase) {
            slip.href = cfg.payslipBase + '&id=' + encodeURIComponent(notice.salary_id);
            slip.classList.remove('d-none');
        }

        var row = document.getElementById('payroll-emp-row-' + (notice.employee_id || ''));
        if (row) {
            row.classList.add('payroll-row-highlight');
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    document.querySelectorAll('.payroll-gen-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('payrollEmpId').value = btn.getAttribute('data-employee-id');
            document.getElementById('payrollGenModalLabel').textContent = 'Generate payroll — ' + btn.getAttribute('data-employee-name');
            setText('pmName', btn.getAttribute('data-employee-name'));
            setText('pmCode', btn.getAttribute('data-employee-code'));
            setText('pmDept', btn.getAttribute('data-department'));
            setText('pmDesig', btn.getAttribute('data-designation'));
            setText('pmFixedGross', inr(btn.getAttribute('data-gross')));
            document.getElementById('pmExtraOt').value = '0';
            document.getElementById('pmManualDed').value = '0';
            document.getElementById('payrollFormAction').value = 'generate';
        });
    });

    var modalEl = document.getElementById('payrollGenModal');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function () {
            loadPreview(modalEl);
        });
        var recalc = document.getElementById('pmRecalcBtn');
        if (recalc) {
            recalc.addEventListener('click', function () {
                loadPreview(modalEl);
            });
        }
    }

    var testModal = document.getElementById('payrollTestDataModal');
    if (testModal) {
        testModal.addEventListener('show.bs.modal', function () {
            document.body.classList.add('payroll-test-modal-open');
        });
        testModal.addEventListener('hidden.bs.modal', function () {
            document.body.classList.remove('payroll-test-modal-open');
        });
    }

    var nextCard = document.getElementById('payrollNextStepCard');
    if (cfg.highlightEmployeeId && !nextCard) {
        var rowOnly = document.getElementById('payroll-emp-row-' + cfg.highlightEmployeeId);
        if (rowOnly) {
            rowOnly.classList.add('payroll-row-highlight');
            rowOnly.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    if (cfg.notice) {
        showPayrollResult(cfg.notice);
    }
})();
