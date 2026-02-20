(function () {
    const navButtons = document.querySelectorAll('.nav-btn');
    const sections = document.querySelectorAll('.dashboard-section');

    function showSection(key) {
        sections.forEach((section) => section.classList.remove('active'));
        navButtons.forEach((btn) => btn.classList.remove('active'));

        const target = document.getElementById(`section-${key}`);
        const activeBtn = document.querySelector(`.nav-btn[data-section="${key}"]`);

        if (target) target.classList.add('active');
        if (activeBtn) activeBtn.classList.add('active');
    }

    navButtons.forEach((btn) => {
        btn.addEventListener('click', () => showSection(btn.dataset.section));
    });

    const tagBox = document.getElementById('techTagBox');
    const input = document.getElementById('techInput');
    const suggestions = document.getElementById('techSuggestions');
    const hidden = document.getElementById('technologiesField');

    if (!tagBox || !input || !suggestions || !hidden) {
        return;
    }

    const options = [
        'PHP', 'MySQL', 'JavaScript', 'HTML', 'CSS', 'Bootstrap', 'Tailwind', 'React', 'Node.js',
        'API', 'AJAX', 'Git', 'Docker', 'Laravel', 'jQuery', 'TypeScript', 'SQLite', 'Redis'
    ];

    const tags = [];

    function updateHiddenField() {
        hidden.value = tags.join(', ');
    }

    function renderTags() {
        [...tagBox.querySelectorAll('.tag')].forEach((el) => el.remove());
        tags.forEach((tag, index) => {
            const chip = document.createElement('span');
            chip.className = 'tag';
            chip.innerHTML = `${tag} <button type="button" aria-label="Remove">&times;</button>`;
            chip.querySelector('button').addEventListener('click', () => {
                tags.splice(index, 1);
                renderTags();
                updateHiddenField();
            });
            tagBox.insertBefore(chip, input);
        });
    }

    function addTag(value) {
        const tag = value.trim();
        if (!tag || tags.includes(tag)) return;
        tags.push(tag);
        renderTags();
        updateHiddenField();
    }

    function showSuggestions(value) {
        suggestions.innerHTML = '';
        if (!value.trim()) {
            suggestions.style.display = 'none';
            return;
        }

        const matches = options
            .filter((option) => option.toLowerCase().includes(value.toLowerCase()))
            .filter((option) => !tags.includes(option))
            .slice(0, 6);

        if (matches.length === 0) {
            suggestions.style.display = 'none';
            return;
        }

        matches.forEach((match) => {
            const item = document.createElement('div');
            item.className = 'suggestion-item';
            item.textContent = match;
            item.addEventListener('click', () => {
                addTag(match);
                input.value = '';
                suggestions.style.display = 'none';
            });
            suggestions.appendChild(item);
        });

        suggestions.style.display = 'block';
    }

    input.addEventListener('input', (event) => showSuggestions(event.target.value));

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addTag(input.value.replace(',', ''));
            input.value = '';
            suggestions.style.display = 'none';
        }
    });

    document.addEventListener('click', (event) => {
        if (!tagBox.contains(event.target)) {
            suggestions.style.display = 'none';
        }
    });

    const projectForm = document.querySelector('.project-form');
    if (projectForm) {
        projectForm.addEventListener('submit', (event) => {
            updateHiddenField();
            if (hidden.value.trim() === '') {
                event.preventDefault();
                alert('Please add at least one technology.');
            }
        });
    }

    const editToggles = document.querySelectorAll('.edit-toggle');
    editToggles.forEach((btn) => {
        btn.addEventListener('click', () => {
            const projectId = btn.getAttribute('data-project-id');
            const panel = document.getElementById(`edit-project-${projectId}`);
            if (!panel) return;

            const isHidden = panel.hasAttribute('hidden');
            document.querySelectorAll('.project-edit-wrap').forEach((item) => item.setAttribute('hidden', 'hidden'));

            if (isHidden) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', 'hidden');
            }
        });
    });
})();
