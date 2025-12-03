/**
 * HR Agency System - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    initDropdowns();
    initModals();
    initTabs();
    initFileInputs();
    initAlerts();
    initMobileMenu();
    initConfirmActions();
});

/**
 * Dropdown меню
 */
function initDropdowns() {
    document.querySelectorAll('.dropdown').forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        
        toggle?.addEventListener('click', (e) => {
            e.stopPropagation();
            
            // Закрываем все другие dropdown
            document.querySelectorAll('.dropdown.active').forEach(d => {
                if (d !== dropdown) d.classList.remove('active');
            });
            
            dropdown.classList.toggle('active');
        });
    });
    
    // Закрытие при клике вне dropdown
    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown.active').forEach(d => {
            d.classList.remove('active');
        });
    });
}

/**
 * Модальные окна
 */
function initModals() {
    // Открытие модального окна
    document.querySelectorAll('[data-modal]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const modalId = trigger.dataset.modal;
            const modal = document.getElementById(modalId);
            if (modal) {
                openModal(modal);
            }
        });
    });
    
    // Закрытие модального окна
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeModal(overlay);
            }
        });
    });
    
    document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) {
                closeModal(modal);
            }
        });
    });
    
    // Закрытие по Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(closeModal);
        }
    });
}

function openModal(modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

/**
 * Вкладки
 */
function initTabs() {
    document.querySelectorAll('.tabs').forEach(tabsContainer => {
        const tabs = tabsContainer.querySelectorAll('.tab-link');
        const contents = tabsContainer.parentElement.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = tab.dataset.tab;
                
                // Деактивируем все вкладки
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                
                // Активируем выбранную
                tab.classList.add('active');
                document.getElementById(targetId)?.classList.add('active');
            });
        });
    });
}

/**
 * Загрузка файлов
 */
function initFileInputs() {
    document.querySelectorAll('.file-input-wrapper').forEach(wrapper => {
        const input = wrapper.querySelector('.file-input');
        const label = wrapper.querySelector('.file-input-label');
        const nameDisplay = wrapper.querySelector('.file-name');
        
        // Drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            label?.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            label?.addEventListener(eventName, () => {
                label.classList.add('dragover');
            });
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            label?.addEventListener(eventName, () => {
                label.classList.remove('dragover');
            });
        });
        
        label?.addEventListener('drop', (e) => {
            if (input && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateFileName(input, nameDisplay);
            }
        });
        
        // Обычный выбор файла
        input?.addEventListener('change', () => {
            updateFileName(input, nameDisplay);
        });
    });
}

function updateFileName(input, nameDisplay) {
    if (input.files.length && nameDisplay) {
        nameDisplay.textContent = input.files[0].name;
        nameDisplay.style.display = 'block';
    }
}

/**
 * Автоматическое скрытие алертов
 */
function initAlerts() {
    document.querySelectorAll('.alert[data-auto-hide]').forEach(alert => {
        const delay = parseInt(alert.dataset.autoHide) || 5000;
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, delay);
    });
}

/**
 * Мобильное меню
 */
function initMobileMenu() {
    const toggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    toggle?.addEventListener('click', () => {
        sidebar?.classList.toggle('active');
    });
    
    // Закрытие при клике вне меню
    document.addEventListener('click', (e) => {
        if (sidebar?.classList.contains('active') && 
            !sidebar.contains(e.target) && 
            !toggle?.contains(e.target)) {
            sidebar.classList.remove('active');
        }
    });
}

/**
 * Подтверждение действий
 */
function initConfirmActions() {
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', (e) => {
            const message = element.dataset.confirm || 'Вы уверены?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * AJAX запросы
 */
async function fetchAPI(url, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    try {
        const response = await fetch(url, { ...defaultOptions, ...options });
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Произошла ошибка');
        }
        
        return data;
    } catch (error) {
        showNotification(error.message, 'error');
        throw error;
    }
}

/**
 * Уведомления
 */
function showNotification(message, type = 'info') {
    const container = document.getElementById('notifications') || createNotificationContainer();
    
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} animate-fade-in`;
    notification.innerHTML = `
        <span>${message}</span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    container.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

function createNotificationContainer() {
    const container = document.createElement('div');
    container.id = 'notifications';
    container.style.cssText = 'position: fixed; top: 1rem; right: 1rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem;';
    document.body.appendChild(container);
    return container;
}

/**
 * Форматирование
 */
function formatPhone(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 0) {
        if (value[0] === '8') {
            value = '7' + value.substring(1);
        }
        let formatted = '+7';
        if (value.length > 1) {
            formatted += ' (' + value.substring(1, 4);
        }
        if (value.length > 4) {
            formatted += ') ' + value.substring(4, 7);
        }
        if (value.length > 7) {
            formatted += '-' + value.substring(7, 9);
        }
        if (value.length > 9) {
            formatted += '-' + value.substring(9, 11);
        }
        input.value = formatted;
    }
}

/**
 * Валидация форм
 */
function validateForm(form) {
    let isValid = true;
    const errors = [];
    
    form.querySelectorAll('[required]').forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('error');
            errors.push(`Поле "${field.dataset.label || field.name}" обязательно`);
        } else {
            field.classList.remove('error');
        }
    });
    
    // Email validation
    form.querySelectorAll('[type="email"]').forEach(field => {
        if (field.value && !isValidEmail(field.value)) {
            isValid = false;
            field.classList.add('error');
            errors.push('Некорректный email');
        }
    });
    
    return { isValid, errors };
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/**
 * Debounce функция
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Тестирование
 */
class TestManager {
    constructor(container, questions) {
        this.container = container;
        this.questions = questions;
        this.currentIndex = 0;
        this.answers = {};
        this.init();
    }
    
    init() {
        this.render();
    }
    
    render() {
        const question = this.questions[this.currentIndex];
        const progress = ((this.currentIndex + 1) / this.questions.length) * 100;
        
        this.container.innerHTML = `
            <div class="test-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${progress}%"></div>
                </div>
                <div class="progress-text">
                    <span>Вопрос ${this.currentIndex + 1} из ${this.questions.length}</span>
                    <span>${Math.round(progress)}%</span>
                </div>
            </div>
            
            <div class="question-card">
                <div class="question-number">Вопрос ${this.currentIndex + 1}</div>
                <div class="question-text">${question.text}</div>
                <div class="answer-options">
                    ${question.answers.map((answer, i) => `
                        <label class="answer-option ${this.answers[question.id] === answer.id ? 'selected' : ''}">
                            <input type="radio" name="answer" value="${answer.id}" 
                                   ${this.answers[question.id] === answer.id ? 'checked' : ''}>
                            <span class="answer-radio"></span>
                            <span class="answer-text">${answer.text}</span>
                        </label>
                    `).join('')}
                </div>
            </div>
            
            <div class="test-navigation">
                <button class="btn btn-secondary" ${this.currentIndex === 0 ? 'disabled' : ''} onclick="testManager.prev()">
                    ← Назад
                </button>
                ${this.currentIndex === this.questions.length - 1 ? `
                    <button class="btn btn-primary" onclick="testManager.submit()">
                        Завершить тест
                    </button>
                ` : `
                    <button class="btn btn-primary" onclick="testManager.next()">
                        Далее →
                    </button>
                `}
            </div>
        `;
        
        // Event listeners для ответов
        this.container.querySelectorAll('.answer-option').forEach(option => {
            option.addEventListener('click', () => {
                const input = option.querySelector('input');
                this.answers[question.id] = parseInt(input.value);
                this.container.querySelectorAll('.answer-option').forEach(o => o.classList.remove('selected'));
                option.classList.add('selected');
            });
        });
    }
    
    next() {
        if (this.currentIndex < this.questions.length - 1) {
            this.currentIndex++;
            this.render();
        }
    }
    
    prev() {
        if (this.currentIndex > 0) {
            this.currentIndex--;
            this.render();
        }
    }
    
    async submit() {
        if (Object.keys(this.answers).length < this.questions.length) {
            if (!confirm('Вы не ответили на все вопросы. Завершить тест?')) {
                return;
            }
        }
        
        try {
            const response = await fetchAPI('submit-test.php', {
                method: 'POST',
                body: JSON.stringify({ answers: this.answers })
            });
            
            if (response.success) {
                window.location.href = 'result.php';
            }
        } catch (error) {
            console.error('Error submitting test:', error);
        }
    }
}

// Экспорт для использования
window.TestManager = TestManager;
window.fetchAPI = fetchAPI;
window.showNotification = showNotification;
window.formatPhone = formatPhone;
window.validateForm = validateForm;
window.openModal = openModal;
window.closeModal = closeModal;

// =====================================================
// Theme Switcher
// =====================================================

/**
 * Переключение темы
 */
function toggleTheme() {
    var html = document.documentElement;
    var currentTheme = html.getAttribute('data-theme') || 'dark';
    var newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('hr-theme', newTheme);
    
    // Обновляем иконку
    updateThemeIcon(newTheme);
}

/**
 * Обновление иконки темы
 */
function updateThemeIcon(theme) {
    var icon = document.getElementById('themeIcon');
    if (icon) {
        icon.textContent = theme === 'dark' ? '🌙' : '☀️';
    }
}

/**
 * Загрузка сохранённой темы
 */
function loadTheme() {
    var savedTheme = localStorage.getItem('hr-theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);
}

// Экспортируем функцию
window.toggleTheme = toggleTheme;

// Загружаем тему при загрузке страницы
document.addEventListener('DOMContentLoaded', loadTheme);
