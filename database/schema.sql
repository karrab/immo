-- =====================================================================
--  GestImmo - Système d'information de gestion des immobilisations
--  Schéma MySQL 8 / MariaDB 10.5+  -  Moteur InnoDB, utf8mb4
-- ---------------------------------------------------------------------
--  Principes d'indexation :
--   * Toutes les listes sont paginées en "keyset" (WHERE (col,id) < (?,?)
--     ORDER BY col DESC, id DESC LIMIT n). Chaque colonne de tri / filtre
--     fréquent possède donc un index composite (col, id) afin que MySQL
--     lise directement l'index dans l'ordre, sans filesort ni OFFSET.
--   * Les clés étrangères sont indexées (obligatoire pour InnoDB et pour
--     les jointures).
--   * Un index FULLTEXT (InnoDB) sert la recherche texte libre.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS traces;
DROP TABLE IF EXISTS bien_historique;
DROP TABLE IF EXISTS transfert_lignes;
DROP TABLE IF EXISTS transferts;
DROP TABLE IF EXISTS biens;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS employes;
DROP TABLE IF EXISTS locaux;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS sequences;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Séquences (numérotation atomique : codes inventaire, n° de transfert)
-- ---------------------------------------------------------------------
CREATE TABLE sequences (
    nom     VARCHAR(60)  NOT NULL,
    valeur  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Rôles & permissions
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom         VARCHAR(80)  NOT NULL,
    description VARCHAR(255) NULL,
    is_super    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Accès total, non supprimable',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code    VARCHAR(60)  NOT NULL,
    module  VARCHAR(40)  NOT NULL,
    libelle VARCHAR(120) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_code (code),
    KEY idx_permissions_module (module, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_rp_permission (permission_id),
    CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles (id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Structure : services, locaux, employés
-- ---------------------------------------------------------------------
CREATE TABLE services (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(20)  NOT NULL,
    nom         VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_code (code),
    KEY idx_services_nom (nom, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE locaux (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    code        VARCHAR(30)   NOT NULL,
    nom         VARCHAR(150)  NOT NULL,
    type        ENUM('bureau','salle_reunion','magasin','atelier','archive','laboratoire','autre') NOT NULL DEFAULT 'bureau',
    batiment    VARCHAR(100)  NULL,
    etage       VARCHAR(20)   NULL,
    service_id  INT UNSIGNED  NULL,
    superficie  DECIMAL(8,2)  NULL,
    description VARCHAR(255)  NULL,
    actif       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_locaux_code (code),
    KEY idx_locaux_nom (nom, id),
    KEY idx_locaux_service (service_id, id),
    KEY idx_locaux_batiment (batiment, etage, id),
    KEY idx_locaux_type (type, id),
    CONSTRAINT fk_locaux_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employes (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    matricule     VARCHAR(30)  NOT NULL,
    nom           VARCHAR(80)  NOT NULL,
    prenom        VARCHAR(80)  NOT NULL,
    fonction      VARCHAR(120) NULL,
    service_id    INT UNSIGNED NULL,
    local_id      INT UNSIGNED NULL,
    email         VARCHAR(150) NULL,
    telephone     VARCHAR(30)  NULL,
    date_embauche DATE         NULL,
    type          ENUM('employe','chef_service') NOT NULL DEFAULT 'employe' COMMENT 'Chef de service : voit les biens du service et valide les transferts',
    -- Colonne générée : vaut service_id uniquement pour un chef => UN SEUL chef par service (contrainte UNIQUE)
    chef_service_id INT UNSIGNED AS (IF(type = 'chef_service', service_id, NULL)) STORED,
    actif         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employes_matricule (matricule),
    UNIQUE KEY uq_employes_chef_service (chef_service_id),
    KEY idx_employes_type (type, id),
    KEY idx_employes_nom (nom, prenom, id),
    KEY idx_employes_service (service_id, id),
    KEY idx_employes_local (local_id, id),
    KEY idx_employes_actif (actif, id),
    -- RESTRICT (et non SET NULL) : requis par la colonne générée chef_service_id ; un service ne se supprime qu'une fois vidé
    CONSTRAINT fk_employes_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE RESTRICT,
    CONSTRAINT fk_employes_local   FOREIGN KEY (local_id)   REFERENCES locaux (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Utilisateurs
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(60)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nom_complet   VARCHAR(150) NOT NULL,
    email         VARCHAR(150) NULL,
    role_id       INT UNSIGNED NOT NULL,
    employe_id    INT UNSIGNED NULL,
    actif         TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at DATETIME     NULL,
    last_login_ip VARCHAR(45)  NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id, id),
    KEY idx_users_employe (employe_id),
    CONSTRAINT fk_users_role    FOREIGN KEY (role_id)    REFERENCES roles (id)    ON DELETE RESTRICT,
    CONSTRAINT fk_users_employe FOREIGN KEY (employe_id) REFERENCES employes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Catégories comptables
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id                   INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    code                 VARCHAR(20)      NOT NULL,
    libelle              VARCHAR(150)     NOT NULL,
    compte_comptable     VARCHAR(20)      NULL COMMENT 'Compte d''immobilisation (ex: 2355)',
    compte_amortissement VARCHAR(20)      NULL COMMENT 'Compte d''amortissement (ex: 28355)',
    duree_amortissement  SMALLINT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Durée par défaut (années)',
    created_at           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_code (code),
    KEY idx_categories_libelle (libelle, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Biens (immobilisations)
-- ---------------------------------------------------------------------
CREATE TABLE biens (
    id                    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    code_inventaire       VARCHAR(40)       NOT NULL,
    designation           VARCHAR(255)      NOT NULL,
    categorie_id          INT UNSIGNED      NOT NULL,
    marque                VARCHAR(100)      NULL,
    modele                VARCHAR(100)      NULL,
    numero_serie          VARCHAR(100)      NULL,
    fournisseur           VARCHAR(150)      NULL,
    numero_facture        VARCHAR(60)       NULL,
    date_acquisition      DATE              NOT NULL,
    date_mise_service     DATE              NULL,
    valeur_acquisition    DECIMAL(15,2)     NOT NULL DEFAULT 0.00,
    valeur_residuelle     DECIMAL(15,2)     NOT NULL DEFAULT 0.00,
    duree_amortissement   SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    methode_amortissement ENUM('lineaire','degressif') NOT NULL DEFAULT 'lineaire',
    etat                  ENUM('neuf','bon','moyen','mauvais','hors_service') NOT NULL DEFAULT 'bon',
    statut                ENUM('en_service','en_stock','en_reparation','reforme','cede') NOT NULL DEFAULT 'en_service',
    local_id              INT UNSIGNED      NULL,
    employe_id            INT UNSIGNED      NULL,
    observations          TEXT              NULL,
    created_by            INT UNSIGNED      NULL,
    updated_by            INT UNSIGNED      NULL,
    created_at            DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_biens_code (code_inventaire),
    -- index composites (filtre/tri, id) pour la pagination keyset
    KEY idx_biens_categorie   (categorie_id, id),
    KEY idx_biens_local       (local_id, id),
    KEY idx_biens_employe     (employe_id, id),
    KEY idx_biens_statut      (statut, id),
    KEY idx_biens_etat        (etat, id),
    KEY idx_biens_date_acq    (date_acquisition, id),
    KEY idx_biens_valeur      (valeur_acquisition, id),
    KEY idx_biens_designation (designation, id),
    KEY idx_biens_serie       (numero_serie),
    FULLTEXT KEY ft_biens_texte (designation, marque, modele, numero_serie, fournisseur),
    CONSTRAINT fk_biens_categorie FOREIGN KEY (categorie_id) REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_biens_local     FOREIGN KEY (local_id)     REFERENCES locaux (id)     ON DELETE SET NULL,
    CONSTRAINT fk_biens_employe   FOREIGN KEY (employe_id)   REFERENCES employes (id)   ON DELETE SET NULL,
    CONSTRAINT fk_biens_created   FOREIGN KEY (created_by)   REFERENCES users (id)      ON DELETE SET NULL,
    CONSTRAINT fk_biens_updated   FOREIGN KEY (updated_by)   REFERENCES users (id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Historique de chaque bien (cycle de vie)
-- ---------------------------------------------------------------------
CREATE TABLE bien_historique (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bien_id        INT UNSIGNED    NOT NULL,
    type_evenement ENUM('creation','modification','transfert','affectation','changement_statut','changement_etat') NOT NULL,
    description    VARCHAR(255)    NOT NULL,
    donnees        TEXT            NULL COMMENT 'JSON : {champ: [ancien, nouveau]}',
    user_id        INT UNSIGNED    NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bh_bien (bien_id, id),
    KEY idx_bh_type (type_evenement, id),
    KEY idx_bh_user (user_id),
    CONSTRAINT fk_bh_bien FOREIGN KEY (bien_id) REFERENCES biens (id) ON DELETE CASCADE,
    CONSTRAINT fk_bh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Transferts de biens (bureau -> bureau)
-- ---------------------------------------------------------------------
CREATE TABLE transferts (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero                 VARCHAR(30)  NOT NULL,
    local_source_id        INT UNSIGNED NOT NULL,
    local_destination_id   INT UNSIGNED NOT NULL,
    employe_destination_id INT UNSIGNED NULL,
    date_transfert         DATE         NOT NULL,
    motif                  VARCHAR(255) NOT NULL,
    statut                 ENUM('en_attente','valide','rejete','annule') NOT NULL DEFAULT 'en_attente',
    demande_par            INT UNSIGNED NULL,
    traite_par             INT UNSIGNED NULL,
    date_traitement        DATETIME     NULL,
    commentaire            VARCHAR(255) NULL,
    created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transferts_numero (numero),
    KEY idx_tr_statut      (statut, id),
    KEY idx_tr_source      (local_source_id, id),
    KEY idx_tr_destination (local_destination_id, id),
    KEY idx_tr_date        (date_transfert, id),
    KEY idx_tr_emp_dest    (employe_destination_id),
    KEY idx_tr_demande     (demande_par),
    KEY idx_tr_traite      (traite_par),
    CONSTRAINT fk_tr_source  FOREIGN KEY (local_source_id)        REFERENCES locaux (id)   ON DELETE RESTRICT,
    CONSTRAINT fk_tr_dest    FOREIGN KEY (local_destination_id)   REFERENCES locaux (id)   ON DELETE RESTRICT,
    CONSTRAINT fk_tr_emp     FOREIGN KEY (employe_destination_id) REFERENCES employes (id) ON DELETE SET NULL,
    CONSTRAINT fk_tr_demande FOREIGN KEY (demande_par)            REFERENCES users (id)    ON DELETE SET NULL,
    CONSTRAINT fk_tr_traite  FOREIGN KEY (traite_par)             REFERENCES users (id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfert_lignes (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    transfert_id      INT UNSIGNED NOT NULL,
    bien_id           INT UNSIGNED NOT NULL,
    employe_source_id INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tl_transfert_bien (transfert_id, bien_id),
    KEY idx_tl_bien (bien_id, transfert_id),
    KEY idx_tl_emp  (employe_source_id),
    CONSTRAINT fk_tl_transfert FOREIGN KEY (transfert_id)      REFERENCES transferts (id) ON DELETE CASCADE,
    CONSTRAINT fk_tl_bien      FOREIGN KEY (bien_id)           REFERENCES biens (id)      ON DELETE CASCADE,
    CONSTRAINT fk_tl_emp       FOREIGN KEY (employe_source_id) REFERENCES employes (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Journal des traces (audit) - append only
-- ---------------------------------------------------------------------
CREATE TABLE traces (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NULL,
    username    VARCHAR(60)     NULL,
    action      VARCHAR(40)     NOT NULL,
    module      VARCHAR(40)     NOT NULL,
    entite_id   BIGINT UNSIGNED NULL,
    description VARCHAR(255)    NOT NULL,
    donnees     MEDIUMTEXT      NULL COMMENT 'JSON',
    ip          VARCHAR(45)     NULL,
    user_agent  VARCHAR(255)    NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_traces_user    (user_id, id),
    KEY idx_traces_module  (module, entite_id, id),
    KEY idx_traces_action  (action, id),
    KEY idx_traces_created (created_at, id),
    CONSTRAINT fk_traces_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
