-- ╔══════════════════════════════════════════════════════════════╗
-- ║  EventHub Pro — database/schema.sql                         ║
-- ║  Schéma de la base de données                               ║
-- ║  ENSA Marrakech — Examen PHP Avancé                         ║
-- ╚══════════════════════════════════════════════════════════════╝
--
-- STATUT : ⚠️ Partiel — Partie 1.1
--
-- FOURNI :
--   ✅  Table users
--   ✅  Table categories
--   ✅  Table events (structure de base)
--   ✅  Table mail_logs
--   ✅  Données de test pour users et categories
--
-- À COMPLÉTER (Partie 1.1) :
--   🔴  Table registrations         → définissez la structure optimale
--   🔴  Contraintes FK              → intégrité référentielle
--   🔴  Index de performance        → sur event_date, category_id
--   🔴  Colonne alert_sent          → dans events (pour Partie 2.2)
--   🔴  Données de test complètes   → 3+ événements, 5+ inscrits
-- ╔══════════════════════════════════════════════════════════════╗
-- ║  EventHub Pro — database/schema.sql                         ║
-- ║  Schéma de la base de données                               ║
-- ║  ENSA Marrakech — Examen PHP Avancé                         ║
-- ╚══════════════════════════════════════════════════════════════╝

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
    password     VARCHAR(255)  NOT NULL,
    role         ENUM('organizer', 'participant') NOT NULL DEFAULT 'participant',
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════════════════════
-- TABLE : categories
-- ══════════════════════════════════════════════════════════════════════════
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(50)   NOT NULL UNIQUE,
    label         VARCHAR(100)  NOT NULL,
    color_primary VARCHAR(7)    NOT NULL DEFAULT '#2563EB',
    color_light   VARCHAR(7)    NOT NULL DEFAULT '#DBEAFE'
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
    -- ✅ AJOUT 1.1 — Colonne alert_sent pour éviter les doublons d'email (Partie 2.2)
    alert_sent       TINYINT(1)    NOT NULL DEFAULT 0,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- ✅ AJOUT 1.1 — Contrainte FK sur organizer_id
    CONSTRAINT fk_events_organizer
        FOREIGN KEY (organizer_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════════════════════
-- TABLE : registrations
-- ══════════════════════════════════════════════════════════════════════════
-- ✅ AJOUT 1.1 — Structure complète de la table registrations
DROP TABLE IF EXISTS registrations;
CREATE TABLE registrations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id      INT UNSIGNED  NOT NULL,
    name          VARCHAR(150)  NOT NULL,
    email         VARCHAR(255)  NOT NULL,
    token         VARCHAR(64)   NOT NULL UNIQUE,   -- lien de désinscription sécurisé
    registered_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Empêcher qu'un même email s'inscrive deux fois au même événement
    UNIQUE KEY uq_registration_event_email (event_id, email),

    -- Intégrité référentielle : suppression cascade si l'événement est supprimé
    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE ON UPDATE CASCADE
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
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════════════════════
-- INDEX DE PERFORMANCE
-- ══════════════════════════════════════════════════════════════════════════
-- ✅ AJOUT 1.1 — Index composé (event_date, category)
--
-- JUSTIFICATION : La requête searchEvents() filtre typiquement par date
-- (ex: événements futurs) ET par catégorie en même temps.
-- Un index composé permet à MySQL de satisfaire les deux conditions
-- sans faire de full table scan — MySQL lit uniquement les pages d'index
-- correspondantes, ce qui est O(log n) au lieu de O(n).
-- L'ordre (event_date en premier) est optimal car c'est le filtre
-- le plus sélectif dans la plupart des requêtes de ce type.
CREATE INDEX idx_events_date_category ON events (event_date, category);

-- ✅ AJOUT 1.1 — Index sur registrations pour accélérer le comptage des inscrits
CREATE INDEX idx_registrations_event ON registrations (event_id);

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
    ('Zineb Moussaoui',     'zineb@example.ma',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant');

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
    );

-- ✅ AJOUT 1.1 — Données de test pour registrations (5 inscrits, tokens SHA2 uniques)
INSERT INTO registrations (event_id, name, email, token) VALUES
    (1, 'Yassine El Fassi',  'yassine@example.ma', SHA2('reg-1-yassine@example.ma-salt42',   256)),
    (1, 'Salma Benali',      'salma@example.ma',   SHA2('reg-1-salma@example.ma-salt42',     256)),
    (1, 'Mehdi Khalil',      'mehdi@example.ma',   SHA2('reg-1-mehdi@example.ma-salt42',     256)),
    (2, 'Zineb Moussaoui',   'zineb@example.ma',   SHA2('reg-2-zineb@example.ma-salt42',     256)),
    (3, 'Yassine El Fassi',  'yassine@example.ma', SHA2('reg-3-yassine@example.ma-salt42',   256));

SET FOREIGN_KEY_CHECKS = 1;

