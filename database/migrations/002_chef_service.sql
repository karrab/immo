-- =====================================================================
--  Migration 002 : employés / chefs de service et périmètre de visibilité
--  À appliquer sur une base installée avant cette version :
--    mysql gestimmo < database/migrations/002_chef_service.sql
-- =====================================================================
SET NAMES utf8mb4;

-- Une colonne générée ne peut pas reposer sur une clé étrangère ON DELETE SET NULL
ALTER TABLE employes DROP FOREIGN KEY fk_employes_service;
ALTER TABLE employes ADD CONSTRAINT fk_employes_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE RESTRICT;

ALTER TABLE employes
    ADD COLUMN type ENUM('employe','chef_service') NOT NULL DEFAULT 'employe' AFTER date_embauche,
    ADD COLUMN chef_service_id INT UNSIGNED AS (IF(type = 'chef_service', service_id, NULL)) STORED AFTER type,
    ADD UNIQUE KEY uq_employes_chef_service (chef_service_id),
    ADD KEY idx_employes_type (type, id);

INSERT IGNORE INTO permissions (code, module, libelle) VALUES
('biens.view_all', 'biens', 'Voir tous les biens et tous les services (sinon : ses biens ou ceux de son service)');
UPDATE permissions SET libelle = 'Valider / rejeter / annuler un transfert (en tant que chef du service source)'
 WHERE code = 'transferts.validate';

-- Les rôles existants (hors super-admin) conservent la vision globale
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'biens.view_all' WHERE r.is_super = 0;

INSERT IGNORE INTO roles (nom, description, is_super) VALUES
('Chef de service', 'Voit les biens et employés de son service, valide les transferts de son service', 0),
('Employé',         'Voit uniquement les biens qui lui sont affectés', 0);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN
 ('dashboard.view','biens.view','biens.export','locaux.view','employes.view','employes.export','services.view',
  'transferts.view','transferts.create','transferts.validate','transferts.export')
 WHERE r.nom = 'Chef de service';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('dashboard.view','biens.view','biens.export')
 WHERE r.nom = 'Employé';
