(() => {
    const limits = {
        email: 254,
        ms_account: 254,
        login: 150,
        password: 128,
        password_confirmation: 128,
        firstname: 100,
        lastname: 100,
        title: 200,
        search: 100,
        message: 2000,
        remarks: 3000,
        description: 3000,
        instructions: 3000,
    };

    document.querySelectorAll('input[name], textarea[name]').forEach(field => {
        if (['hidden', 'file', 'checkbox', 'radio', 'submit', 'button'].includes(field.type)) return;
        const rootName = field.name.replace(/\[.*$/, '');
        const limit = limits[rootName] || (field.tagName === 'TEXTAREA' ? 3000 : 255);
        if (!field.hasAttribute('maxlength')) field.maxLength = limit;
    });

    const formatStudentId = field => {
        if (!/^[\d\s-]*$/.test(field.value)) return;
        const digits = field.value.replace(/\D/g, '').slice(0, 8);
        field.value = digits.length > 4 ? `${digits.slice(0, 4)}-${digits.slice(4)}` : digits;
    };

    document.querySelectorAll('input[name="student_id"]:not([type="hidden"])').forEach(field => {
        field.title = 'Format: 2000-1234';
        if (field.getAttribute('pattern') === '\\d{4}-\\d{4}') {
            field.maxLength = 9;
            field.inputMode = 'numeric';
        }
        field.addEventListener('input', () => formatStudentId(field));
        formatStudentId(field);
    });
})();
