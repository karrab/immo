-- =====================================================================
--  Données initiales : permissions, rôles, administrateur, référentiels
--  Compte par défaut : admin / Admin@123  (à changer dès la 1re connexion)
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO permissions (code, module, libelle) VALUES
('dashboard.view',     'dashboard',  'Voir le tableau de bord'),
('biens.view',         'biens',      'Consulter les biens'),
('biens.create',       'biens',      'Créer un bien'),
('biens.edit',         'biens',      'Modifier un bien'),
('biens.delete',       'biens',      'Supprimer un bien'),
('biens.export',       'biens',      'Imprimer / exporter les biens'),
('categories.view',    'categories', 'Consulter les catégories'),
('categories.create',  'categories', 'Créer une catégorie'),
('categories.edit',    'categories', 'Modifier une catégorie'),
('categories.delete',  'categories', 'Supprimer une catégorie'),
('services.view',      'services',   'Consulter les services'),
('services.create',    'services',   'Créer un service'),
('services.edit',      'services',   'Modifier un service'),
('services.delete',    'services',   'Supprimer un service'),
('locaux.view',        'locaux',     'Consulter les locaux'),
('locaux.create',      'locaux',     'Créer un local'),
('locaux.edit',        'locaux',     'Modifier un local'),
('locaux.delete',      'locaux',     'Supprimer un local'),
('locaux.export',      'locaux',     'Imprimer / exporter les locaux'),
('employes.view',      'employes',   'Consulter les employés'),
('employes.create',    'employes',   'Créer un employé'),
('employes.edit',      'employes',   'Modifier un employé'),
('employes.delete',    'employes',   'Supprimer un employé'),
('employes.export',    'employes',   'Imprimer / exporter les employés'),
('transferts.view',    'transferts', 'Consulter les transferts'),
('transferts.create',  'transferts', 'Demander un transfert'),
('transferts.validate','transferts', 'Valider / rejeter un transfert'),
('transferts.delete',  'transferts', 'Supprimer un transfert'),
('transferts.export',  'transferts', 'Imprimer / exporter les transferts'),
('users.view',         'users',      'Consulter les utilisateurs'),
('users.create',       'users',      'Créer un utilisateur'),
('users.edit',         'users',      'Modifier un utilisateur'),
('users.delete',       'users',      'Supprimer un utilisateur'),
('roles.view',         'roles',      'Consulter les rôles'),
('roles.create',       'roles',      'Créer un rôle'),
('roles.edit',         'roles',      'Modifier un rôle et ses permissions'),
('roles.delete',       'roles',      'Supprimer un rôle'),
('traces.view',        'traces',     'Consulter le journal des traces'),
('traces.export',      'traces',     'Exporter le journal des traces');

INSERT INTO roles (id, nom, description, is_super) VALUES
(1, 'Administrateur', 'Accès complet à toutes les fonctionnalités', 1),
(2, 'Gestionnaire',   'Gestion du patrimoine, des locaux, des employés et des transferts', 0),
(3, 'Consultant',     'Consultation et impression uniquement', 0);

-- Gestionnaire : tout sauf utilisateurs / rôles / traces
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE module NOT IN ('users','roles','traces');

-- Consultant : lecture + export des modules métier (pas d'administration)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions
 WHERE (code LIKE '%.view' OR code LIKE '%.export') AND module NOT IN ('users','roles','traces');

INSERT INTO users (username, password_hash, nom_complet, email, role_id) VALUES
('admin', '$2y$10$JxaJJDGdZbl9GIPkCcHemepT2X38rq/7BeONocmiOkgoH/nWW3yiC', 'Administrateur système', 'admin@example.local', 1);

INSERT INTO categories (code, libelle, compte_comptable, compte_amortissement, duree_amortissement) VALUES
('MOB', 'Mobilier de bureau',              '2351', '28351', 10),
('INF', 'Matériel informatique',           '2355', '28355', 4),
('MBU', 'Matériel de bureau',              '2352', '28352', 5),
('VEH', 'Matériel de transport',           '2340', '28340', 5),
('AGT', 'Agencements et installations',    '2380', '28380', 10),
('LOG', 'Logiciels',                       '2220', '28220', 3);

INSERT INTO services (code, nom) VALUES
('DG',  'Direction générale'),
('DAF', 'Direction administrative et financière'),
('DSI', 'Direction des systèmes d''information'),
('RH',  'Ressources humaines'),
('LOG', 'Logistique et moyens généraux');

INSERT INTO locaux (code, nom, type, batiment, etage, service_id) VALUES
('B-A-001', 'Bureau du Directeur général', 'bureau',        'Bâtiment A', 'RDC', 1),
('B-A-101', 'Bureau comptabilité',          'bureau',        'Bâtiment A', '1',   2),
('B-A-102', 'Bureau trésorerie',            'bureau',        'Bâtiment A', '1',   2),
('B-B-201', 'Open space informatique',      'bureau',        'Bâtiment B', '2',   3),
('B-B-202', 'Salle serveurs',               'laboratoire',   'Bâtiment B', '2',   3),
('B-A-103', 'Bureau RH',                    'bureau',        'Bâtiment A', '1',   4),
('S-A-010', 'Salle de réunion principale',  'salle_reunion', 'Bâtiment A', 'RDC', 1),
('M-C-000', 'Magasin central',              'magasin',       'Bâtiment C', 'RDC', 5);

INSERT INTO employes (matricule, nom, prenom, fonction, service_id, local_id, email, date_embauche) VALUES
('EMP001', 'BENALI',   'Karim',   'Directeur général',           1, 1, 'k.benali@example.local',   '2015-03-01'),
('EMP002', 'HADDAD',   'Samira',  'Chef comptable',              2, 2, 's.haddad@example.local',   '2017-09-15'),
('EMP003', 'MANSOURI', 'Youssef', 'Trésorier',                   2, 3, 'y.mansouri@example.local', '2019-01-07'),
('EMP004', 'AMRANI',   'Nadia',   'Responsable informatique',    3, 4, 'n.amrani@example.local',   '2018-06-11'),
('EMP005', 'TAZI',     'Omar',    'Technicien support',          3, 4, 'o.tazi@example.local',     '2021-02-01'),
('EMP006', 'CHERKAOUI','Leila',   'Responsable RH',              4, 6, 'l.cherkaoui@example.local','2016-11-21'),
('EMP007', 'IDRISSI',  'Hamid',   'Magasinier',                  5, 8, 'h.idrissi@example.local',  '2020-05-04');

INSERT INTO biens (code_inventaire, designation, categorie_id, marque, modele, numero_serie, fournisseur, numero_facture,
                   date_acquisition, date_mise_service, valeur_acquisition, duree_amortissement, etat, statut, local_id, employe_id, created_by) VALUES
('MOB-2023-00001', 'Bureau de direction en bois massif', 1, 'Bureau Plus', 'Executive 180', NULL,        'Mobilia SARL',    'FA-2023-118', '2023-01-15', '2023-01-20', 18500.00, 10, 'bon',  'en_service', 1, 1, 1),
('MOB-2023-00002', 'Fauteuil de direction cuir',         1, 'ErgoSit',     'Prestige',      NULL,        'Mobilia SARL',    'FA-2023-118', '2023-01-15', '2023-01-20',  6200.00, 10, 'bon',  'en_service', 1, 1, 1),
('INF-2024-00001', 'Ordinateur portable',                2, 'Dell',        'Latitude 5440', 'DL5440X91', 'InfoTech',        'FT-2024-031', '2024-02-10', '2024-02-12', 11900.00,  4, 'neuf', 'en_service', 4, 4, 1),
('INF-2024-00002', 'Ordinateur portable',                2, 'Dell',        'Latitude 5440', 'DL5440X92', 'InfoTech',        'FT-2024-031', '2024-02-10', '2024-02-12', 11900.00,  4, 'neuf', 'en_service', 4, 5, 1),
('INF-2024-00003', 'Serveur rack',                       2, 'HPE',         'ProLiant DL380','HPE380G11A','InfoTech',        'FT-2024-077', '2024-05-02', '2024-05-10', 98000.00,  4, 'bon',  'en_service', 5, NULL, 1),
('INF-2022-00001', 'Imprimante multifonction',           2, 'Canon',       'iR-ADV C3826',  'CN3826Z11', 'BureauTech',      'BT-2022-210', '2022-09-12', '2022-09-15', 32000.00,  4, 'moyen','en_service', 2, NULL, 1),
('MBU-2021-00001', 'Coffre-fort ignifugé',               3, 'Fichet',      'Primo 3',       'FC-77812',  'Sécurité Pro',    'SP-2021-005', '2021-04-03', '2021-04-05', 14500.00,  5, 'bon',  'en_service', 3, 3, 1),
('MOB-2022-00001', 'Table de réunion 12 places',         1, 'Bureau Plus', 'Conf 360',      NULL,        'Mobilia SARL',    'FA-2022-044', '2022-03-08', '2022-03-10', 22000.00, 10, 'bon',  'en_service', 7, NULL, 1),
('MBU-2023-00001', 'Vidéoprojecteur',                    3, 'Epson',       'EB-L520U',      'EPL520-3321','BureauTech',     'BT-2023-099', '2023-06-19', '2023-06-20',  9800.00,  5, 'bon',  'en_service', 7, NULL, 1),
('VEH-2020-00001', 'Véhicule de service',                4, 'Dacia',       'Duster',        'VF1HJD20X66','Auto Distribution','AD-2020-014','2020-10-01', '2020-10-05',185000.00,  5, 'moyen','en_service', NULL, 7, 1),
('INF-2019-00001', 'Ordinateur de bureau',               2, 'HP',          'ProDesk 400 G6','HP400G6-55','InfoTech',        'FT-2019-120', '2019-03-11', '2019-03-15',  7400.00,  4, 'mauvais','en_stock', 8, NULL, 1),
('MOB-2021-00001', 'Armoire métallique à rideaux',       1, 'Steelcase',   'Rideau 198',    NULL,        'Mobilia SARL',    'FA-2021-301', '2021-11-25', '2021-11-30',  4300.00, 10, 'bon',  'en_service', 6, 6, 1);

INSERT INTO sequences (nom, valeur) VALUES
('bien:MOB:2023', 2), ('bien:INF:2024', 3), ('bien:INF:2022', 1), ('bien:MBU:2021', 1), ('bien:MOB:2022', 1),
('bien:MBU:2023', 1), ('bien:VEH:2020', 1), ('bien:INF:2019', 1), ('bien:MOB:2021', 1);

INSERT INTO bien_historique (bien_id, type_evenement, description, user_id)
SELECT id, 'creation', CONCAT('Création du bien ', code_inventaire), 1 FROM biens;
