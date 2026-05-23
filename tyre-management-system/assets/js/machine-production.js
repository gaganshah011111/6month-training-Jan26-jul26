/**
 * Auto-fill assigned operator when machine is selected on production entry forms.
 */
(function () {
  'use strict';

  function bindMachineOperator(form) {
    var machineSelect = form.querySelector('select[name="machine_id"]');
    var operatorSelect = form.querySelector('select[name="operator_id"]');
    if (!machineSelect || !operatorSelect) {
      return;
    }

    function applyOperator(machineId) {
      if (!machineId) {
        return;
      }
      var opt = machineSelect.querySelector('option[value="' + machineId + '"]');
      var opId = opt && opt.getAttribute('data-operator-id');
      if (!opId) {
        return;
      }
      operatorSelect.value = opId;
      if (operatorSelect.tomselect) {
        operatorSelect.tomselect.setValue(opId, true);
      }
    }

    machineSelect.addEventListener('change', function () {
      applyOperator(machineSelect.value);
    });
  }

  document.querySelectorAll('.prod-page form').forEach(bindMachineOperator);
})();
