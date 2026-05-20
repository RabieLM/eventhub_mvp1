-- ╔══════════════════════════════════════════════════════════════╗
-- ║  EventHub Pro — database/schema.sql                         ║
-- ║  Schéma de la base de données                               ║
-- ║  ENSA Marrakech — Examen PHP Avancé                         ║
-- ╚══════════════════════════════════════════════════════════════╝
--
-- STATUT : ✅ Complété — Partie 1.1
--
-- FOURNI :
--   ✅  Table users
--   ✅  Table categories
--   ✅  Table events (structure de base)
--   ✅  Table mail_logs
--   ✅  Données de test pour users et categories
--
-- COMPLÉTÉ (Partie 1.1) :
--   ✅  Table registrations
--   ✅  Contraintes FK
--   ✅  Index de performance        → sur event_date et category
--   ✅  Colonne alert_sent          → dans events (pour Partie 2.2)
--   ✅  Données de test complètes   → 3 événements, 5 inscrits
--

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Base de données ────────────────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS eventhub_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE eventhub_db;

-- ══════════════════════════════════════════════════════════════════════════
-- TABLE : users
-- ══════════════════════════════════════════════════════════════════════════
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(150)  NOT NULL,
    email        VARCHAR(255)  NOT NULL UNIQUE,
    password     VARCHAR(255)  NOT NULL,           -- bcrypt hash
    role         ENUM('organizer', 'participant') NOT NULL DEFAULT 'participant',
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════════════════════
-- TABLE : categories
-- ══════════════════════════════════════════════════════════════════════════
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug         VARCHAR(50)   NOT NULL UNIQUE,    -- 'tech', 'design', etc.
    label        VARCHAR(100)  NOT NULL,
    color_primary VARCHAR(7)   NOT NULL DEFAULT '#2563EB',
    color_light   VARCHAR(7)   NOT NULL DEFAULT '#DBEAFE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════════════════════
-- TABLE : events
-- ══════════════════════════════════════════════════════════════════════════
DROP TABLE IF EXISTS events;
CREATE TABLE events (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(255)  NOT NULL,
    description      TEXT          NOT NULL,
    event_date       DATETIME      NOT NULL,
    location         VARCHAR(255)  NOT NULL,
    capacity         SMALLINT UNSIGNED NOT NULL CHECK (capacity > 0),
    category         VARCHAR(50)   NOT NULL,
    organizer_email  VARCHAR(255)  NOT NULL,
    organizer_id     INT UNSIGNED  NULL,
    alert_sent       TINYINT(1)    NOT NULL DEFAULT 0,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- La catégorie référence le slug pour garder l'API simple côté filtres.
    CONSTRAINT fk_events_category
        FOREIGN KEY (category) REFERENCES categories(slug)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    -- Si un organisateur est supprimé, ses événements restent consultables.
    CONSTRAINT fk_events_organizer
        FOREIGN KEY (organizer_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    INDEX idx_events_date_category (event_date, category),
    INDEX idx_events_organizer (organizer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════════════════════
-- TABLE : registrations
-- ══════════════════════════════════════════════════════════════════════════
DROP TABLE IF EXISTS registrations;
CREATE TABLE registrations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id      INT UNSIGNED NOT NULL,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    token         VARCHAR(64)  NOT NULL,
    registered_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status        ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    cancelled_at  DATETIME NULL,

    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE KEY uq_registrations_event_email (event_id, email),
    UNIQUE KEY uq_registrations_token (token),
    INDEX idx_registrations_event_registered_at (event_id, registered_at),
    INDEX idx_registrations_event_status (event_id, status),
    INDEX idx_registrations_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ══════════════════════════════════════════════════════════════════════════
-- TABLE : mail_logs
-- ══════════════════════════════════════════════════════════════════════════
DROP TABLE IF EXISTS mail_logs;
CREATE TABLE mail_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type          ENUM('confirmation', 'capacity_alert', 'ticket', 'other') NOT NULL,
    recipient     VARCHAR(255) NOT NULL,
    event_id      INT UNSIGNED NULL,
    error_message TEXT         NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mail_logs_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    INDEX idx_mail_logs_event_type (event_id, type),
    INDEX idx_mail_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════════════════════
-- INDEX DE PERFORMANCE
-- ══════════════════════════════════════════════════════════════════════════
-- Index composé ajouté dans la définition de events :
--   idx_events_date_category (event_date, category)
-- Il aide searchEvents() quand les filtres de date et catégorie sont combinés :
-- MySQL peut réduire rapidement la plage de dates, puis filtrer/ordonner par
-- catégorie sans parcourir toute la table events.


-- ══════════════════════════════════════════════════════════════════════════
-- DONNÉES DE TEST
-- ══════════════════════════════════════════════════════════════════════════
INSERT INTO categories (slug, label, color_primary, color_light) VALUES
    ('tech',     'Tech',     '#2563EB', '#DBEAFE'),
    ('design',   'Design',   '#7C3AED', '#EDE9FE'),
    ('business', 'Business', '#EA580C', '#FEF3C7'),
    ('science',  'Science',  '#16A34A', '#DCFCE7');

-- Mot de passe : "password123" hashé avec bcrypt
INSERT INTO users (name, email, password, role) VALUES
    ('Organisateur ENSA',   'orga@ensa.ma',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'organizer'),
    ('Yassine El Fassi',    'yassine@example.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant'),
    ('Salma Benali',        'salma@example.ma',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant'),
    ('Mehdi Khalil',        'mehdi@example.ma',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant'),
    ('Zineb Moussaoui',     'zineb@example.ma',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant'),
    ('Participant 1',       'lamjidrabie+participant1@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant'),
    ('Participant 2',       'lamjidrabie+participant2@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant'),
    ('Participant 3',       'lamjidrabie+participant3@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant'),
    ('Participant 4',       'lamjidrabie+participant4@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant'),
    ('Participant 5',       'lamjidrabie+participant5@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant');

INSERT INTO events (title, description, event_date, location, capacity, category, organizer_email, organizer_id) VALUES
    (
        'DevFest Marrakech 2025',
        'La grande conférence tech de Marrakech. Talks, ateliers pratiques et networking avec les professionnels du secteur.',
        '2025-09-20 09:00:00',
        'ENSA Marrakech — Grand Amphi',
        200,
        'tech',
        'orga@ensa.ma',
        1
    ),
    (
        'UX Design Workshop',
        'Atelier intensif de design UX : prototypage Figma, tests utilisateurs, design systems. Places très limitées.',
        '2025-07-28 14:00:00',
        'École Nationale des Arts, Marrakech',
        30,
        'design',
        'orga@ensa.ma',
        1
    ),
    (
        'PHP & MVC Day',
        'Journée dédiée à PHP 8.x, architecture MVC native, bonnes pratiques PDO et sécurité des applications web.',
        '2025-11-08 09:30:00',
        'ENSA Marrakech — Salle TP Informatique',
        5,
        'tech',
        'orga@ensa.ma',
        1
    ),
    (
        'DevFest Marrakech 2026',
        'Scenario bout-en-bout : evenement de test avec cinq places pour valider inscriptions, alerte 80%, PDF et dashboard.',
        '2026-09-20 09:00:00',
        'ENSA Marrakech',
        5,
        'tech',
        'walid.bouarifi@gmail.com',
        1
    );

INSERT INTO registrations (event_id, name, email, token, registered_at) VALUES
    (1, 'Yassine El Fassi',  'yassine@example.ma', 'b75b7e4d0df64f4aa3bd6f38db83c89d7d62e6b564b16af52c8d9e86b7ef0001', '2025-09-15 10:15:00'),
    (1, 'Salma Benali',      'salma@example.ma',   'b75b7e4d0df64f4aa3bd6f38db83c89d7d62e6b564b16af52c8d9e86b7ef0002', '2025-09-16 11:30:00'),
    (2, 'Mehdi Khalil',      'mehdi@example.ma',   'b75b7e4d0df64f4aa3bd6f38db83c89d7d62e6b564b16af52c8d9e86b7ef0003', '2025-07-21 09:45:00'),
    (3, 'Zineb Moussaoui',   'zineb@example.ma',   'b75b7e4d0df64f4aa3bd6f38db83c89d7d62e6b564b16af52c8d9e86b7ef0004', '2025-11-01 14:10:00'),
    (3, 'Nora El Amrani',    'nora@example.ma',    'b75b7e4d0df64f4aa3bd6f38db83c89d7d62e6b564b16af52c8d9e86b7ef0005', '2025-11-02 16:25:00');

SET FOREIGN_KEY_CHECKS = 1;
