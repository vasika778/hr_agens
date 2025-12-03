-- HR Agency System - Database Schema
-- Перед импортом выберите нужную базу данных в phpMyAdmin

-- Таблица администраторов (HR)
CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'hr_manager', 'recruiter') DEFAULT 'recruiter',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Таблица должностей
CREATE TABLE IF NOT EXISTS positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица групп кандидатов
CREATE TABLE IF NOT EXISTS candidate_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_system BOOLEAN DEFAULT FALSE,
    color VARCHAR(7) DEFAULT '#6366f1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица кандидатов
CREATE TABLE IF NOT EXISTS candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    position_id INT,
    resume_file VARCHAR(255),
    comment TEXT,
    status ENUM('new', 'reviewing', 'interview', 'testing', 'approved', 'rejected', 'hired') DEFAULT 'new',
    group_id INT DEFAULT 1,
    access_token VARCHAR(64) UNIQUE,
    test_attempts_used INT DEFAULT 0,
    test_passed BOOLEAN DEFAULT FALSE,
    test_score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE SET NULL,
    FOREIGN KEY (group_id) REFERENCES candidate_groups(id) ON DELETE SET NULL
);

-- Таблица истории статусов
CREATE TABLE IF NOT EXISTS status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- Таблица тестов
CREATE TABLE IF NOT EXISTS tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    time_limit INT DEFAULT 0,
    max_attempts INT DEFAULT 1,
    passing_score INT DEFAULT 70,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Таблица вопросов
CREATE TABLE IF NOT EXISTS questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    test_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('single', 'multiple', 'text') DEFAULT 'single',
    points INT DEFAULT 1,
    order_num INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
);

-- Таблица вариантов ответов
CREATE TABLE IF NOT EXISTS answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    answer_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    order_num INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Таблица результатов тестирования
CREATE TABLE IF NOT EXISTS test_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id INT NOT NULL,
    test_id INT NOT NULL,
    score INT NOT NULL,
    max_score INT NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    passed BOOLEAN NOT NULL,
    answers_json JSON,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
);

-- Таблица материалов "О компании"
CREATE TABLE IF NOT EXISTS about_materials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    type ENUM('text', 'image', 'video', 'pdf', 'youtube') NOT NULL,
    file_path VARCHAR(255),
    youtube_url VARCHAR(255),
    order_num INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Таблица настроек системы
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Вставка начальных данных

-- ВАЖНО: Администратор создаётся через install.php
-- После импорта базы запустите: php install.php
-- Это создаст админа с логином admin и паролем admin123

-- Группы кандидатов
INSERT INTO candidate_groups (name, description, is_system, color) VALUES
('Кандидаты', 'Люди, подавшие резюме (до прохождения теста)', TRUE, '#6366f1'),
('Постоянные сотрудники', 'Сотрудники после успешного оформления', TRUE, '#10b981');

-- Должности
INSERT INTO positions (name, description) VALUES
('Менеджер по продажам', 'Работа с клиентами, продажи товаров и услуг'),
('Программист', 'Разработка программного обеспечения'),
('Дизайнер', 'Создание визуального контента'),
('Бухгалтер', 'Ведение бухгалтерского учёта'),
('HR-менеджер', 'Управление персоналом'),
('Маркетолог', 'Продвижение товаров и услуг'),
('Администратор', 'Административная работа'),
('Другое', 'Другая должность');

-- Тестовый тест
INSERT INTO tests (title, description, time_limit, max_attempts, passing_score, is_active) VALUES
('Входное тестирование', 'Базовый тест для всех кандидатов', 30, 2, 70, TRUE);

-- Тестовые вопросы
INSERT INTO questions (test_id, question_text, question_type, points, order_num) VALUES
(1, 'Что для вас важнее всего в работе?', 'single', 10, 1),
(1, 'Как вы справляетесь со стрессом?', 'single', 10, 2),
(1, 'Опишите ваш подход к командной работе', 'single', 10, 3),
(1, 'Какие ваши сильные стороны?', 'single', 10, 4),
(1, 'Почему вы хотите работать в нашей компании?', 'text', 20, 5);

-- Варианты ответов
INSERT INTO answers (question_id, answer_text, is_correct, order_num) VALUES
-- Вопрос 1
(1, 'Карьерный рост', TRUE, 1),
(1, 'Высокая зарплата', FALSE, 2),
(1, 'Стабильность', FALSE, 3),
(1, 'Интересные задачи', TRUE, 4),
-- Вопрос 2
(2, 'Делаю перерывы и переключаюсь', TRUE, 1),
(2, 'Работаю ещё усерднее', FALSE, 2),
(2, 'Обсуждаю с коллегами', TRUE, 3),
(2, 'Игнорирую стресс', FALSE, 4),
-- Вопрос 3
(3, 'Предпочитаю работать самостоятельно', FALSE, 1),
(3, 'Активно взаимодействую с командой', TRUE, 2),
(3, 'Беру на себя роль лидера', TRUE, 3),
(3, 'Избегаю конфликтов любой ценой', FALSE, 4),
-- Вопрос 4
(4, 'Коммуникабельность', TRUE, 1),
(4, 'Ответственность', TRUE, 2),
(4, 'Перфекционизм', FALSE, 3),
(4, 'Умение работать в команде', TRUE, 4);

-- Материалы "О компании"
INSERT INTO about_materials (title, content, type, order_num, is_active) VALUES
('Добро пожаловать в нашу команду!', 'Мы рады, что вы успешно прошли тестирование и готовы присоединиться к нашей команде. Здесь вы найдёте всю необходимую информацию о компании.', 'text', 1, TRUE),
('Наша миссия', 'Мы стремимся создавать инновационные решения, которые делают жизнь людей лучше. Наша команда — это профессионалы своего дела, объединённые общей целью.', 'text', 2, TRUE),
('Корпоративная культура', 'В нашей компании ценятся открытость, честность и командная работа. Мы поддерживаем инициативу и создаём условия для профессионального роста каждого сотрудника.', 'text', 3, TRUE);

-- Настройки системы
INSERT INTO settings (setting_key, setting_value, description) VALUES
('company_name', 'HR Agency', 'Название компании'),
('company_email', 'hr@company.ru', 'Email для связи'),
('company_phone', '+996 555 000 000', 'Телефон компании'),
('test_enabled', '1', 'Включено ли тестирование'),
('default_test_attempts', '2', 'Количество попыток по умолчанию'),
('email_notifications', '1', 'Email уведомления включены');

-- =====================================================
-- ТАБЛИЦЫ ДЛЯ ХРАНЕНИЯ ДОКУМЕНТОВ HR
-- =====================================================

-- Категории (папки) документов HR
CREATE TABLE IF NOT EXISTS hr_doc_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#6366f1',
    parent_id INT DEFAULT NULL,
    order_num INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES hr_doc_categories(id) ON DELETE SET NULL
);

-- Документы HR
CREATE TABLE IF NOT EXISTS hr_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    category_id INT DEFAULT NULL,
    candidate_id INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    is_visible_to_candidate BOOLEAN DEFAULT FALSE,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES hr_doc_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE SET NULL
);

-- Индексы для быстрого поиска
CREATE INDEX idx_hr_docs_admin ON hr_documents(admin_id);
CREATE INDEX idx_hr_docs_category ON hr_documents(category_id);
CREATE INDEX idx_hr_docs_candidate ON hr_documents(candidate_id);

-- Примечание: Стандартные категории документов создаются автоматически 
-- при первом входе в раздел "Документы" или вручную через интерфейс
