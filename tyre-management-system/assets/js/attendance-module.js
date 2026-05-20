(function () {
    function parseJson(id, fallback) {
        var el = document.getElementById(id);
        if (!el) return fallback;
        try { return JSON.parse(el.textContent || ''); } catch (e) { return fallback; }
    }

    var EMP = parseJson('attEmpData', []);
    var ATT_DATE = parseJson('attMarkDate', '');

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function shiftWindowTs(dateYmd, emp) {
        var sc = (emp.shift_clock_start || '09:00') + ':00';
        var ec = (emp.shift_clock_end || '18:00') + ':00';
        var startTs = new Date(dateYmd + 'T' + sc).getTime();
        var endSame = new Date(dateYmd + 'T' + ec).getTime();
        var endTs = endSame;
        if (endSame <= startTs) {
            var d = new Date(dateYmd + 'T12:00:00');
            d.setDate(d.getDate() + 1);
            var nd = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            endTs = new Date(nd + 'T' + ec).getTime();
        }
        return [startTs, endTs];
    }

    function statusClass(val) {
        var map = {
            'Present': 'att-st-present',
            'Half Day': 'att-st-half',
            'Late': 'att-st-late',
            'Absent': 'att-st-absent',
            'Holiday': 'att-st-holiday',
            'Leave': 'att-st-leave'
        };
        return map[val] || 'att-st-default';
    }

    function applyStatusStyle(sel) {
        if (!sel) return;
        sel.className = 'form-select att-status-select att-status ' + statusClass(sel.value);
    }

    function statusNeedsNoPunch(val) {
        return val === 'Absent' || val === 'Holiday' || val === 'Leave';
    }

    function syncPunchFieldsForRow(tr) {
        var stSel = tr.querySelector('.att-status');
        var vin = tr.querySelector('.att-in');
        var vout = tr.querySelector('.att-out');
        var elO = tr.querySelector('.att-calc-ot');
        if (!stSel || !vin || !vout) return;
        var noPunch = statusNeedsNoPunch(stSel.value);
        if (noPunch) {
            vin.value = '';
            vout.value = '';
            vin.readOnly = true;
            vout.readOnly = true;
            vin.classList.add('bg-light');
            vout.classList.add('bg-light');
            if (elO) elO.textContent = '—';
        } else {
            vin.readOnly = false;
            vout.readOnly = false;
            vin.classList.remove('bg-light');
            vout.classList.remove('bg-light');
        }
    }

    function punchValuesForSubmit(tr) {
        var vin = tr.querySelector('.att-in');
        var vout = tr.querySelector('.att-out');
        var st = tr.querySelector('.att-status');
        var pi = vin ? vin.value : '';
        var po = vout ? vout.value : '';
        if (st && statusNeedsNoPunch(st.value)) {
            return { punch_in: '', punch_out: '' };
        }
        if (pi === '00:00' || pi === '00:00:00') pi = '';
        if (po === '00:00' || po === '00:00:00') po = '';
        return { punch_in: pi, punch_out: po };
    }

    document.querySelectorAll('[data-status-class]').forEach(function (sel) {
        applyStatusStyle(sel);
        var tr = sel.closest('.att-row');
        if (tr) syncPunchFieldsForRow(tr);
        sel.addEventListener('change', function () {
            sel.dataset.manual = '1';
            applyStatusStyle(sel);
            if (tr) syncPunchFieldsForRow(tr);
        });
    });

    function recalcRow(tr) {
        var idx = parseInt(tr.getAttribute('data-row-index'), 10);
        var emp = EMP[idx];
        if (!emp || !ATT_DATE) return;
        var vin = tr.querySelector('.att-in');
        var vout = tr.querySelector('.att-out');
        var stSel = tr.querySelector('.att-status');
        var elO = tr.querySelector('.att-calc-ot');
        if (!vin || !vout || !elO) return;
        if (!vin.value || !vout.value) {
            elO.textContent = '—';
            return;
        }
        var inTs = new Date(ATT_DATE + 'T' + vin.value + ':00').getTime();
        var outSame = new Date(ATT_DATE + 'T' + vout.value + ':00').getTime();
        var outTs = outSame;
        if (outSame <= inTs) {
            var d2 = new Date(ATT_DATE + 'T12:00:00');
            d2.setDate(d2.getDate() + 1);
            var nd2 = d2.getFullYear() + '-' + pad(d2.getMonth() + 1) + '-' + pad(d2.getDate());
            outTs = new Date(nd2 + 'T' + vout.value + ':00').getTime();
        }
        if (!(outTs > inTs)) {
            elO.textContent = '—';
            return;
        }
        var sw = shiftWindowTs(ATT_DATE, emp);
        var et = sw[1];
        var ot = Math.max(0, Math.round(((outTs - et) / 3600000) * 100) / 100);
        elO.textContent = String(ot);
        if (stSel && !stSel.dataset.manual) {
            var sv = stSel.value;
            if (sv === 'Holiday' || sv === 'Absent' || sv === 'Leave') return;
            var st = sw[0];
            var late = inTs > st;
            var worked = Math.round(((outTs - inTs) / 3600000) * 100) / 100;
            var schedH = Math.max(4, (et - sw[0]) / 3600000);
            var minHalf = Math.min(4, Math.max(2, schedH * 0.5));
            if (worked < minHalf) stSel.value = 'Half Day';
            else if (late) stSel.value = 'Late';
            else stSel.value = 'Present';
            applyStatusStyle(stSel);
        }
    }

    var batchForm = document.getElementById('att-batch-form');
    if (batchForm) {
        batchForm.addEventListener('submit', function () {
            document.querySelectorAll('.att-row').forEach(function (tr) {
                var st = tr.querySelector('.att-status');
                if (!st || !st.value) return;
                var punches = punchValuesForSubmit(tr);
                var vin = tr.querySelector('.att-in');
                var vout = tr.querySelector('.att-out');
                if (vin) {
                    vin.readOnly = false;
                    vin.value = punches.punch_in;
                }
                if (vout) {
                    vout.readOnly = false;
                    vout.value = punches.punch_out;
                }
            });
        });
    }

    document.querySelectorAll('.att-row').forEach(function (tr) {
        syncPunchFieldsForRow(tr);
        tr.querySelectorAll('.att-in, .att-out').forEach(function (inp) {
            inp.addEventListener('input', function () { recalcRow(tr); });
            inp.addEventListener('change', function () { recalcRow(tr); });
        });
        recalcRow(tr);
    });

    document.querySelectorAll('.att-row-save').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var eid = btn.getAttribute('data-employee-id');
            var tr = btn.closest('.att-row');
            if (!tr || !eid) return;
            var pi = tr.querySelector('.att-in');
            var po = tr.querySelector('.att-out');
            var st = tr.querySelector('.att-status');
            if (!st || !st.value) {
                alert('Select a status first.');
                return;
            }
            var punches = punchValuesForSubmit(tr);
            document.getElementById('att-single-emp-id').value = eid;
            document.getElementById('att-single-pi').value = punches.punch_in;
            document.getElementById('att-single-po').value = punches.punch_out;
            document.getElementById('att-single-st').value = st.value;
            document.getElementById('att-single-save-form').submit();
        });
    });

    var dateForm = document.getElementById('att-date-form');
    if (dateForm) {
        var dateInp = document.querySelector('[form="att-date-form"][name="att_date"]');
        if (dateInp) {
            dateInp.addEventListener('change', function () { dateForm.submit(); });
        }
    }

    var regMode = document.getElementById('reg_mode_select');
    var dailyFields = document.querySelectorAll('.reg-field-daily');
    var monthlyFields = document.querySelectorAll('.reg-field-monthly');
    function syncRegMode() {
        if (!regMode) return;
        var m = regMode.value === 'monthly';
        dailyFields.forEach(function (el) { el.hidden = m; });
        monthlyFields.forEach(function (el) { el.hidden = !m; });
    }
    if (regMode) {
        syncRegMode();
        regMode.addEventListener('change', syncRegMode);
    }
})();
