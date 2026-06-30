import './bootstrap.js';
import JustValidate from 'just-validate';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */


// console.log removed in production

function enableNextButton(form, isValid) {
    const nextBtn = form.querySelector('button[type="submit"]');
    if (nextBtn) {
        nextBtn.disabled = !isValid;
        nextBtn.classList.toggle('opacity-50', !isValid);
    }
}

function initValidation(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    const validation = new JustValidate(form);
    form.querySelectorAll('input[required], select[required], textarea[required]').forEach((el) => {
        if (el.id) {
            validation.addField(`#${el.id}`, [{ rule: 'required' }]);
        }
    });
    validation.onValidate((isValid) => enableNextButton(form, isValid));
}

document.addEventListener('DOMContentLoaded', () => {
    const form1 = document.getElementById('form-step1');
    if (form1) {
        const validation1 = new JustValidate(form1);
        validation1
            .addField('#step1_gender', [{ rule: 'required' }])
            .addField('#step1_firstName', [{ rule: 'required' }])
            .addField('#step1_lastName', [{ rule: 'required' }]);
    }

    ['form-step2', 'form-step3', 'form-step4', 'form-step5'].forEach(initValidation);
});
