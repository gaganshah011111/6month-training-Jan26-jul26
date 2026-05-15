/**
 * Client-side mirror of indian_split_monthly_gross() — uses live Payroll Settings from the server.
 */
(function (global) {
    'use strict';

    function round2(n) {
        return Math.round(n * 100) / 100;
    }

    function allowanceAmount(enabled, mode, basic, fixed, pctOfBasic) {
        if (!enabled) {
            return 0;
        }
        var pct = parseFloat(pctOfBasic) || 0;
        var fix = parseFloat(fixed) || 0;
        if (mode === 'percent' && pct > 0) {
            return round2(basic * pct / 100);
        }
        return round2(Math.max(0, fix));
    }

    /**
     * @param {number} G - gross monthly salary
     * @param {boolean} metro
     * @param {boolean} isWorker
     * @param {object} s - payroll settings (snake_case keys from API)
     */
    function split(G, metro, isWorker, s) {
        s = s || {};
        if (G <= 0) {
            return {
                basic: 0,
                da: 0,
                hra: 0,
                hra_pct: 0,
                medical: 0,
                travel: 0,
                special: 0,
                gratuity_monthly: 0,
                daily_wage: 0,
                hourly_rate: 0,
                ot_rate: 0,
            };
        }

        var basicPct = parseFloat(s.basic_pct_of_gross) || 50;
        var basic = round2(G * basicPct / 100);

        var daEnabled = !!s.da_enabled;
        var daPct = parseFloat(s.da_pct_of_basic) || 0;
        var da = (daEnabled && daPct > 0) ? round2(basic * daPct / 100) : 0;

        var hraPct = 0;
        if (!isWorker) {
            hraPct = metro
                ? (parseFloat(s.hra_pct_metro) || 50)
                : (parseFloat(s.hra_pct_non_metro) || 40);
        }
        var hra = isWorker ? 0 : round2(basic * hraPct / 100);

        var medical = allowanceAmount(
            !!s.medical_enabled,
            s.medical_mode || 'fixed',
            basic,
            s.medical_fixed,
            s.medical_pct_of_basic
        );

        var travel = allowanceAmount(
            !!s.travel_enabled,
            s.travel_mode || 'fixed',
            basic,
            s.travel_fixed,
            s.travel_pct_of_basic
        );

        var fixedSum = basic + da + hra + medical + travel;
        var special = round2(Math.max(0, G - fixedSum));

        var gPct = parseFloat(s.gratuity_pct_of_basic) || 4.81;
        var gratuityMonthly = round2(basic * gPct / 100);

        var wd = Math.max(1, parseFloat(s.working_days_default) || 26);
        var sh = Math.max(0.5, parseFloat(s.shift_hours_default) || 8);
        var dailyWage = round2(G / wd);
        var hourlyRate = round2(dailyWage / sh);
        var otMult = Math.max(0.5, parseFloat(s.ot_multiplier) || 1);
        var otRate = round2(hourlyRate * otMult);

        return {
            basic: basic,
            da: da,
            hra: hra,
            hra_pct: hraPct,
            medical: medical,
            travel: travel,
            special: special,
            gratuity_monthly: gratuityMonthly,
            daily_wage: dailyWage,
            hourly_rate: hourlyRate,
            ot_rate: otRate,
        };
    }

    global.IndianPayrollSplit = { split: split, round2: round2 };
})(typeof window !== 'undefined' ? window : this);
