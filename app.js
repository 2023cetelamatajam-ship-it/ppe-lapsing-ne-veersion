document.querySelectorAll('[data-confirm-delete]').forEach((button) => {
    button.addEventListener('click', (event) => {
        const message = button.getAttribute('data-confirm-delete') || 'Delete this record?';

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});

const themeToggleButtons = document.querySelectorAll('[data-theme-toggle]');

const getActiveTheme = () => document.documentElement.dataset.theme === 'light' ? 'light' : 'dark';

const updateThemeToggleButtons = (theme) => {
    const nextThemeLabel = theme === 'dark' ? 'Light mode' : 'Dark mode';
    const iconClass = theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-stars-fill';

    themeToggleButtons.forEach((button) => {
        const icon = button.querySelector('[data-theme-toggle-icon]');
        const label = button.querySelector('[data-theme-toggle-label]');

        button.setAttribute('title', 'Switch to ' + nextThemeLabel.toLowerCase());
        button.setAttribute('aria-label', 'Switch to ' + nextThemeLabel.toLowerCase());

        if (icon) {
            icon.className = 'bi ' + iconClass;
        }

        if (label) {
            label.textContent = nextThemeLabel;
        }
    });
};

const applyTheme = (theme) => {
    document.documentElement.dataset.theme = theme;

    try {
        window.localStorage.setItem('ppe-theme', theme);
    } catch (error) {
        // Ignore storage failures and still apply the theme for this page.
    }

    updateThemeToggleButtons(theme);
};

updateThemeToggleButtons(getActiveTheme());

themeToggleButtons.forEach((button) => {
    button.addEventListener('click', () => {
        const nextTheme = getActiveTheme() === 'dark' ? 'light' : 'dark';
        applyTheme(nextTheme);
    });
});
