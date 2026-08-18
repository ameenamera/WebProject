-- ============================================================
--  Gestion des Stages - Schéma de la base de données
--  SGBD : MySQL / MariaDB
-- ============================================================

DROP DATABASE IF EXISTS gestion_stages;
CREATE DATABASE gestion_stages
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE gestion_stages;

-- ------------------------------------------------------------
-- Table : service
-- ------------------------------------------------------------
CREATE TABLE service (
    id_service   INT AUTO_INCREMENT PRIMARY KEY,
    nom_service  VARCHAR(100) NOT NULL,
    description  VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uk_service_nom (nom_service)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table : type_stage  (Gestion type de stage)
-- ------------------------------------------------------------
CREATE TABLE type_stage (
    id_type      INT AUTO_INCREMENT PRIMARY KEY,
    libelle      VARCHAR(50) NOT NULL,
    duree_min    INT DEFAULT NULL COMMENT 'Durée minimale conseillée en jours',
    UNIQUE KEY uk_type_libelle (libelle)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table : institut  (Gestion institut de stage)
-- ------------------------------------------------------------
CREATE TABLE institut (
    id_institut  INT AUTO_INCREMENT PRIMARY KEY,
    nom_institut VARCHAR(150) NOT NULL,
    ville        VARCHAR(100) DEFAULT NULL,
    telephone    VARCHAR(20)  DEFAULT NULL,
    email        VARCHAR(100) DEFAULT NULL,
    UNIQUE KEY uk_institut_nom (nom_institut)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table : encadrant
-- ------------------------------------------------------------
CREATE TABLE encadrant (
    id_encadrant INT AUTO_INCREMENT PRIMARY KEY,
    nom          VARCHAR(50)  NOT NULL,
    prenom       VARCHAR(50)  NOT NULL,
    email        VARCHAR(100) NOT NULL,
    telephone    VARCHAR(20)  DEFAULT NULL,
    fonction     VARCHAR(100) DEFAULT NULL,
    id_service   INT DEFAULT NULL,
    UNIQUE KEY uk_encadrant_email (email),
    CONSTRAINT fk_encadrant_service FOREIGN KEY (id_service)
        REFERENCES service (id_service) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table : stagiaire
-- ------------------------------------------------------------
CREATE TABLE stagiaire (
    id_stagiaire  INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(50)  NOT NULL,
    prenom        VARCHAR(50)  NOT NULL,
    cin           VARCHAR(20)  NOT NULL,
    email         VARCHAR(100) NOT NULL,
    telephone     VARCHAR(20)  DEFAULT NULL,
    etablissement VARCHAR(100) DEFAULT NULL,
    specialite    VARCHAR(100) DEFAULT NULL,
    niveau        VARCHAR(50)  DEFAULT NULL,
    id_institut   INT DEFAULT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_stagiaire_cin (cin),
    UNIQUE KEY uk_stagiaire_email (email),
    CONSTRAINT fk_stagiaire_institut FOREIGN KEY (id_institut)
        REFERENCES institut (id_institut) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table : utilisateur  (comptes de connexion)
--   role : admin | encadrant | stagiaire
--   Un compte encadrant  pointe vers encadrant.id_encadrant
--   Un compte stagiaire  pointe vers stagiaire.id_stagiaire
-- ------------------------------------------------------------
CREATE TABLE utilisateur (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    login          VARCHAR(50)  NOT NULL,
    mot_de_passe   VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt',
    role           ENUM('admin','encadrant','stagiaire') NOT NULL,
    id_stagiaire   INT DEFAULT NULL,
    id_encadrant   INT DEFAULT NULL,
    actif          TINYINT(1) NOT NULL DEFAULT 1,
    derniere_connexion DATETIME DEFAULT NULL,
    date_creation  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_utilisateur_login (login),
    CONSTRAINT fk_user_stagiaire FOREIGN KEY (id_stagiaire)
        REFERENCES stagiaire (id_stagiaire) ON DELETE CASCADE,
    CONSTRAINT fk_user_encadrant FOREIGN KEY (id_encadrant)
        REFERENCES encadrant (id_encadrant) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table : stage
-- ------------------------------------------------------------
CREATE TABLE stage (
    id_stage     INT AUTO_INCREMENT PRIMARY KEY,
    id_stagiaire INT NOT NULL,
    id_service   INT DEFAULT NULL,
    id_encadrant INT DEFAULT NULL,
    id_type      INT DEFAULT NULL,
    sujet        VARCHAR(255) NOT NULL,
    description  TEXT DEFAULT NULL,
    date_debut   DATE NOT NULL,
    date_fin     DATE NOT NULL,
    type_stage   VARCHAR(50) DEFAULT NULL,
    statut       VARCHAR(30) NOT NULL DEFAULT 'En cours',
    CONSTRAINT fk_stage_stagiaire FOREIGN KEY (id_stagiaire)
        REFERENCES stagiaire (id_stagiaire) ON DELETE CASCADE,
    CONSTRAINT fk_stage_service FOREIGN KEY (id_service)
        REFERENCES service (id_service) ON DELETE SET NULL,
    CONSTRAINT fk_stage_encadrant FOREIGN KEY (id_encadrant)
        REFERENCES encadrant (id_encadrant) ON DELETE SET NULL,
    CONSTRAINT fk_stage_type FOREIGN KEY (id_type)
        REFERENCES type_stage (id_type) ON DELETE SET NULL,
    CONSTRAINT ck_stage_dates CHECK (date_fin >= date_debut),
    INDEX idx_stage_statut (statut)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table : tache
--   etat : En attente | Validee | Rejetee
-- ------------------------------------------------------------
CREATE TABLE tache (
    id_tache    INT AUTO_INCREMENT PRIMARY KEY,
    id_stage    INT NOT NULL,
    titre       VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    date_tache  DATE NOT NULL,
    etat        VARCHAR(20) NOT NULL DEFAULT 'En attente',
    commentaire TEXT DEFAULT NULL COMMENT 'Commentaire de l''encadrant',
    date_validation DATETIME DEFAULT NULL,
    CONSTRAINT fk_tache_stage FOREIGN KEY (id_stage)
        REFERENCES stage (id_stage) ON DELETE CASCADE,
    INDEX idx_tache_etat (etat),
    INDEX idx_tache_date (date_tache)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table : presence
-- ------------------------------------------------------------
CREATE TABLE presence (
    id_presence   INT AUTO_INCREMENT PRIMARY KEY,
    id_stage      INT NOT NULL,
    date_presence DATE NOT NULL,
    heure_entree  TIME DEFAULT NULL,
    heure_sortie  TIME DEFAULT NULL,
    statut        VARCHAR(20) NOT NULL DEFAULT 'Present',
    CONSTRAINT fk_presence_stage FOREIGN KEY (id_stage)
        REFERENCES stage (id_stage) ON DELETE CASCADE,
    UNIQUE KEY uk_presence_jour (id_stage, date_presence)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table : document
--   type_document : Convention | Rapport | Attestation | Autre
-- ------------------------------------------------------------
CREATE TABLE document (
    id_document   INT AUTO_INCREMENT PRIMARY KEY,
    id_stage      INT NOT NULL,
    type_document VARCHAR(30)  NOT NULL,
    nom_fichier   VARCHAR(255) NOT NULL COMMENT 'Nom d''origine',
    chemin        VARCHAR(255) NOT NULL COMMENT 'Nom stocké sur disque',
    taille        INT DEFAULT NULL,
    date_depot    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    depose_par    INT DEFAULT NULL,
    CONSTRAINT fk_document_stage FOREIGN KEY (id_stage)
        REFERENCES stage (id_stage) ON DELETE CASCADE,
    CONSTRAINT fk_document_user FOREIGN KEY (depose_par)
        REFERENCES utilisateur (id_utilisateur) ON DELETE SET NULL,
    INDEX idx_document_type (type_document)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table : evaluation
-- ------------------------------------------------------------
CREATE TABLE evaluation (
    id_evaluation   INT AUTO_INCREMENT PRIMARY KEY,
    id_stage        INT NOT NULL,
    note_technique  DECIMAL(4,2) DEFAULT NULL,
    note_comportement DECIMAL(4,2) DEFAULT NULL,
    observations    TEXT DEFAULT NULL,
    decision        VARCHAR(20) DEFAULT NULL COMMENT 'Valide | Non valide',
    date_evaluation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_encadrant    INT DEFAULT NULL,
    UNIQUE KEY uk_evaluation_stage (id_stage),
    CONSTRAINT fk_evaluation_stage FOREIGN KEY (id_stage)
        REFERENCES stage (id_stage) ON DELETE CASCADE,
    CONSTRAINT fk_evaluation_encadrant FOREIGN KEY (id_encadrant)
        REFERENCES encadrant (id_encadrant) ON DELETE SET NULL,
    CONSTRAINT ck_note_tech CHECK (note_technique IS NULL OR (note_technique >= 0 AND note_technique <= 20)),
    CONSTRAINT ck_note_comp CHECK (note_comportement IS NULL OR (note_comportement >= 0 AND note_comportement <= 20))
) ENGINE=InnoDB;
