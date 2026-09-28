(() => {
    const list = document.querySelector('[data-evaluation-sections]');
    if (!list) return;
    const addSet = document.querySelector('[data-add-section]');
    const count = document.querySelector('[data-question-count]');
    const questionTemplate = document.getElementById('evaluation-question-template');
    const sectionTemplate = document.getElementById('evaluation-section-template');
    const totalQuestions = () => list.querySelectorAll('[data-question-row]').length;

    function refresh() {
        const sections = [...list.querySelectorAll('[data-section-row]')];
        const total = totalQuestions();
        let number = 0;
        sections.forEach((section, sectionIndex) => {
            section.querySelector('[data-section-label]').textContent = `Question set ${sectionIndex + 1}`;
            ['title', 'description'].forEach(field => {
                const input = section.querySelector(`[data-section-${field}]`);
                input.name = `sections[${sectionIndex}][${field}]`;
                input.id = `evaluation-set-${field}-${sectionIndex}`;
                section.querySelector(`[data-section-${field}-label]`).htmlFor = input.id;
            });
            const removeSet = section.querySelector('[data-remove-section]');
            removeSet.disabled = sections.length === 1;
            removeSet.setAttribute('aria-label', `Remove question set ${sectionIndex + 1}`);
            section.querySelector('[data-add-question]').disabled = total >= 50;
            const rows = [...section.querySelectorAll('[data-question-row]')];
            rows.forEach((row, questionIndex) => {
                const input = row.querySelector('textarea');
                input.id = `evaluation-question-${number}`;
                input.name = `sections[${sectionIndex}][questions][${questionIndex}]`;
                const label = row.querySelector('[data-question-label]');
                label.htmlFor = input.id;
                label.textContent = `Question ${++number}`;
                const remove = row.querySelector('[data-remove-question]');
                remove.disabled = rows.length === 1;
                remove.setAttribute('aria-label', `Remove question ${number}`);
            });
        });
        addSet.disabled = sections.length >= 10 || total >= 50;
        count.textContent = `${sections.length} of 10 sets · ${total} of 50 questions · 500 characters maximum per question`;
    }

    addSet.addEventListener('click', () => {
        if (addSet.disabled) return;
        const section = sectionTemplate.content.firstElementChild.cloneNode(true);
        list.append(section);
        refresh();
        section.querySelector('[data-section-title]').focus();
    });
    list.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button || button.disabled) return;
        const section = button.closest('[data-section-row]');
        if (!section) return;
        if (button.hasAttribute('data-add-question') && totalQuestions() < 50) {
            const question = questionTemplate.content.firstElementChild.cloneNode(true);
            section.querySelector('[data-section-questions]').append(question);
            refresh();
            question.querySelector('textarea').focus();
        } else if (button.hasAttribute('data-remove-question')) {
            button.closest('[data-question-row]').remove();
            refresh();
            section.querySelector('[data-add-question]').focus();
        } else if (button.hasAttribute('data-remove-section')) {
            section.remove();
            refresh();
            addSet.focus();
        }
    });
    refresh();
})();
