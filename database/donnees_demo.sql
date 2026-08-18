-- ============================================================
--  Jeu de données de démonstration
--  À exécuter APRÈS schema.sql
--
--  Comptes créés :
--    admin     / admin123
--    encadrant / encadrant123
--    stagiaire / stagiaire123
-- ============================================================

USE gestion_stages;

-- ------------------------------------------------------------ Services
INSERT INTO service (nom_service, description) VALUES
('Service Informatique',   'Développement, réseaux et maintenance du système d''information'),
('Service Ressources Humaines', 'Recrutement, formation et gestion du personnel'),
('Service Financier',      'Comptabilité, budget et contrôle de gestion'),
('Service Production',     'Fabrication, qualité et suivi de production'),
('Service Commercial',     'Ventes, marketing et relation client');

-- ------------------------------------------------------------ Types de stage
INSERT INTO type_stage (libelle, duree_min) VALUES
('Stage ouvrier',      30),
('Stage technicien',   60),
('Stage PFE',         120),
('Stage d''initiation', 21),
('Stage de perfectionnement', 45);

-- ------------------------------------------------------------ Instituts
INSERT INTO institut (nom_institut, ville, telephone, email) VALUES
('ISET de Tunis',                     'Tunis',   '71 123 456', 'contact@iset-tunis.tn'),
('ENIT — École Nationale d''Ingénieurs de Tunis', 'Tunis', '71 234 567', 'contact@enit.tn'),
('FST — Faculté des Sciences de Tunis', 'Tunis', '71 345 678', 'contact@fst.tn'),
('ISIMM — Monastir',                  'Monastir', '73 456 789', 'contact@isimm.tn'),
('ESPRIT',                            'Ariana',  '70 567 890', 'contact@esprit.tn');

-- ------------------------------------------------------------ Encadrants
INSERT INTO encadrant (nom, prenom, email, telephone, fonction, id_service) VALUES
('Ben Salah', 'Karim',  'k.bensalah@entreprise.tn',  '98 111 222', 'Chef de projet informatique', 1),
('Trabelsi',  'Sonia',  's.trabelsi@entreprise.tn',  '98 222 333', 'Responsable RH',              2),
('Gharbi',    'Mohamed','m.gharbi@entreprise.tn',    '98 333 444', 'Ingénieur principal',         1),
('Jelassi',   'Amel',   'a.jelassi@entreprise.tn',   '98 444 555', 'Contrôleur de gestion',       3),
('Mansouri',  'Youssef','y.mansouri@entreprise.tn',  '98 555 666', 'Responsable production',      4);

-- ------------------------------------------------------------ Stagiaires
INSERT INTO stagiaire (nom, prenom, cin, email, telephone, etablissement, specialite, niveau, id_institut) VALUES
('Amera',    'Amine',   '11223344', 'amine.amera@email.tn',   '20 111 222', 'ISET de Tunis', 'Génie logiciel',        '3ème année', 1),
('Bouzid',   'Nour',    '22334455', 'nour.bouzid@email.tn',   '20 222 333', 'ENIT',          'Génie informatique',    'Ingénieur',  2),
('Chaabane', 'Yassine', '33445566', 'y.chaabane@email.tn',    '20 333 444', 'FST',           'Réseaux et télécoms',   'Master 2',   3),
('Dridi',    'Ines',    '44556677', 'ines.dridi@email.tn',    '20 444 555', 'ISIMM',         'Systèmes embarqués',    'Master 1',   4),
('Ferchichi','Ahmed',   '55667788', 'a.ferchichi@email.tn',   '20 555 666', 'ESPRIT',        'Génie logiciel',        'Ingénieur',  5),
('Guesmi',   'Rania',   '66778899', 'rania.guesmi@email.tn',  '20 666 777', 'ISET de Tunis', 'Gestion',               'Licence',    1),
('Hamdi',    'Skander', '77889900', 'skander.hamdi@email.tn', '20 777 888', 'ENIT',          'Génie électrique',      '2ème année', 2),
('Khelifi',  'Maryem',  '88990011', 'maryem.khelifi@email.tn','20 888 999', 'FST',           'Data science',          'Master 2',   3);

-- ------------------------------------------------------------ Comptes
-- Les hachages ci-dessous correspondent à : admin123 / encadrant123 / stagiaire123
INSERT INTO utilisateur (login, mot_de_passe, role, id_stagiaire, id_encadrant) VALUES
('admin',     '$2y$10$BkujaGOeEeX7ac8eLJO1uOa3ZHad4ig3zHevgqVbmlhRu1p59wEcC', 'admin',     NULL, NULL),
('encadrant', '$2y$10$67nsNeG6Wjj2thfxqCT.BOWE1wYpRw46T9.390H02ny2eKVPM28JC', 'encadrant', NULL, 1),
('stagiaire', '$2y$10$7WDsw/fhZcNtQ7vgmNjcqO6w3JG7Y.h/KimAjy8r6fYWElDiEHhLq', 'stagiaire', 1,    NULL);

-- ------------------------------------------------------------ Stages
INSERT INTO stage (id_stagiaire, id_service, id_encadrant, id_type, sujet, description, date_debut, date_fin, type_stage, statut) VALUES
(1, 1, 1, 3, 'Développement d''une application web de gestion des stages',
    'Conception et réalisation d''une plateforme PHP/MySQL couvrant la gestion des stagiaires, le suivi des tâches et l''évaluation.',
    '2026-02-01', '2026-06-30', 'Stage PFE', 'En cours'),

(2, 1, 3, 3, 'Mise en place d''une API REST pour le système d''information',
    'Développement d''une API sécurisée et documentation technique.',
    '2026-02-15', '2026-07-15', 'Stage PFE', 'En cours'),

(3, 1, 1, 2, 'Audit et optimisation du réseau interne',
    'Analyse de l''infrastructure existante et propositions d''amélioration.',
    '2026-01-05', '2026-03-05', 'Stage technicien', 'Terminé'),

(4, 4, 5, 2, 'Automatisation d''une ligne de production',
    'Programmation d''automates et supervision.',
    '2026-03-01', '2026-05-31', 'Stage technicien', 'En cours'),

(5, 1, 3, 3, 'Application mobile de suivi des interventions',
    'Développement d''une application mobile connectée au back-office.',
    '2025-09-01', '2026-01-31', 'Stage PFE', 'Terminé'),

(6, 2, 2, 1, 'Numérisation des dossiers du personnel',
    'Classement et indexation des dossiers RH.',
    '2026-02-10', '2026-03-10', 'Stage ouvrier', 'Terminé'),

(7, 3, 4, 4, 'Découverte du contrôle de gestion',
    'Participation au suivi budgétaire mensuel.',
    '2026-04-01', '2026-04-30', 'Stage d''initiation', 'En cours'),

(8, 1, 1, 3, 'Tableau de bord décisionnel pour la direction',
    'Collecte, traitement et visualisation des indicateurs de l''entreprise.',
    '2026-02-01', '2026-06-30', 'Stage PFE', 'En cours');

-- ------------------------------------------------------------ Tâches
INSERT INTO tache (id_stage, titre, description, date_tache, etat, commentaire, date_validation) VALUES
-- Stage 1 (Amine Amera)
(1, 'Étude du cahier des charges', 'Lecture et analyse des besoins fonctionnels avec l''encadrant.', '2026-02-03', 'Validée', 'Bonne compréhension du besoin.', '2026-02-04 09:00:00'),
(1, 'Conception de la base de données', 'Modèle conceptuel et schéma relationnel des huit tables.', '2026-02-07', 'Validée', 'Modèle cohérent et bien normalisé.', '2026-02-08 10:30:00'),
(1, 'Maquettes des interfaces', 'Réalisation des maquettes des écrans principaux.', '2026-02-14', 'Validée', 'Interfaces claires.', '2026-02-15 11:00:00'),
(1, 'Module d''authentification', 'Développement de la connexion multi-rôles.', '2026-02-21', 'Validée', NULL, '2026-02-22 09:15:00'),
(1, 'Gestion des stagiaires (CRUD)', 'Ajout, modification, suppression et recherche.', '2026-03-02', 'Validée', 'Fonctionnel, penser à la validation des champs.', '2026-03-03 14:00:00'),
(1, 'Suivi des tâches et avancement', 'Saisie quotidienne et validation par l''encadrant.', '2026-03-10', 'En attente', NULL, NULL),
(1, 'Module de gestion documentaire', 'Dépôt et téléchargement des documents.', '2026-03-18', 'En attente', NULL, NULL),
(1, 'Rédaction du rapport', 'Première version du rapport de stage.', '2026-03-25', 'Rejetée', 'Plan à revoir : ajouter une partie sur les tests.', '2026-03-26 16:00:00'),

-- Stage 2 (Nour Bouzid)
(2, 'Analyse des besoins de l''API', 'Recensement des points d''entrée nécessaires.', '2026-02-18', 'Validée', 'Bon travail d''analyse.', '2026-02-19 09:00:00'),
(2, 'Choix de l''architecture', 'Comparatif des solutions techniques.', '2026-02-25', 'Validée', NULL, '2026-02-26 10:00:00'),
(2, 'Développement des routes principales', 'Implémentation des opérations CRUD.', '2026-03-05', 'En attente', NULL, NULL),
(2, 'Sécurisation par jetons', 'Mise en place de l''authentification.', '2026-03-12', 'En attente', NULL, NULL),

-- Stage 3 (Yassine Chaabane) — terminé
(3, 'Cartographie du réseau', 'Inventaire des équipements et schéma général.', '2026-01-08', 'Validée', 'Très complet.', '2026-01-09 09:00:00'),
(3, 'Analyse des performances', 'Mesures de débit et identification des goulets.', '2026-01-20', 'Validée', NULL, '2026-01-21 09:00:00'),
(3, 'Propositions d''optimisation', 'Rapport de recommandations.', '2026-02-10', 'Validée', 'Recommandations pertinentes.', '2026-02-11 15:00:00'),
(3, 'Présentation finale', 'Soutenance devant l''équipe technique.', '2026-03-03', 'Validée', 'Excellente présentation.', '2026-03-04 11:00:00'),

-- Stage 4 (Ines Dridi)
(4, 'Prise en main des automates', 'Formation aux outils de programmation.', '2026-03-04', 'Validée', NULL, '2026-03-05 09:00:00'),
(4, 'Programmation du cycle de base', 'Développement du programme principal.', '2026-03-15', 'En attente', NULL, NULL),

-- Stage 5 (Ahmed Ferchichi) — terminé
(5, 'Spécifications de l''application', 'Rédaction du cahier des charges fonctionnel.', '2025-09-05', 'Validée', NULL, '2025-09-06 09:00:00'),
(5, 'Développement des écrans', 'Interfaces mobiles principales.', '2025-10-15', 'Validée', 'Interface soignée.', '2025-10-16 09:00:00'),
(5, 'Connexion au back-office', 'Intégration des services distants.', '2025-11-20', 'Validée', NULL, '2025-11-21 09:00:00'),
(5, 'Tests et corrections', 'Campagne de tests et corrections des anomalies.', '2025-12-18', 'Validée', 'Bonne rigueur.', '2025-12-19 09:00:00'),
(5, 'Rapport final', 'Rédaction et remise du rapport.', '2026-01-25', 'Validée', 'Rapport de qualité.', '2026-01-26 09:00:00'),

-- Stage 6 (Rania Guesmi) — terminé
(6, 'Tri des dossiers papier', 'Classement par service et par année.', '2026-02-12', 'Validée', NULL, '2026-02-13 09:00:00'),
(6, 'Numérisation', 'Scan et nommage des documents.', '2026-02-20', 'Validée', 'Travail méthodique.', '2026-02-21 09:00:00'),
(6, 'Indexation', 'Saisie des métadonnées dans le tableur.', '2026-03-05', 'Validée', NULL, '2026-03-06 09:00:00'),

-- Stage 7 (Skander Hamdi)
(7, 'Découverte du service', 'Présentation des missions et des outils.', '2026-04-02', 'Validée', NULL, '2026-04-03 09:00:00'),
(7, 'Suivi budgétaire mensuel', 'Participation au rapprochement des écritures.', '2026-04-10', 'En attente', NULL, NULL),

-- Stage 8 (Maryem Khelifi)
(8, 'Recensement des indicateurs', 'Entretiens avec les responsables de service.', '2026-02-05', 'Validée', 'Bonne démarche.', '2026-02-06 09:00:00'),
(8, 'Extraction des données', 'Scripts d''extraction depuis les bases métier.', '2026-02-18', 'Validée', NULL, '2026-02-19 09:00:00'),
(8, 'Premier tableau de bord', 'Maquette des graphiques principaux.', '2026-03-08', 'En attente', NULL, NULL);

-- ------------------------------------------------------------ Présences
INSERT INTO presence (id_stage, date_presence, heure_entree, heure_sortie, statut) VALUES
(1, '2026-03-02', '08:30:00', '17:00:00', 'Present'),
(1, '2026-03-03', '08:45:00', '17:00:00', 'Retard'),
(1, '2026-03-04', '08:30:00', '17:00:00', 'Present'),
(1, '2026-03-05', NULL,       NULL,       'Absent'),
(1, '2026-03-06', '08:30:00', '16:30:00', 'Present'),
(1, '2026-03-09', '08:30:00', '17:00:00', 'Present'),
(1, '2026-03-10', '08:30:00', '17:00:00', 'Present'),
(2, '2026-03-02', '09:00:00', '17:30:00', 'Present'),
(2, '2026-03-03', '09:00:00', '17:30:00', 'Present'),
(2, '2026-03-04', NULL,       NULL,       'Congé'),
(4, '2026-03-04', '08:00:00', '16:00:00', 'Present'),
(4, '2026-03-05', '08:00:00', '16:00:00', 'Present'),
(8, '2026-03-02', '08:30:00', '17:00:00', 'Present'),
(8, '2026-03-03', '08:30:00', '17:00:00', 'Present');

-- ------------------------------------------------------------ Évaluations
INSERT INTO evaluation (id_stage, note_technique, note_comportement, observations, decision, id_encadrant) VALUES
(3, 16.50, 17.00,
   'Stagiaire sérieux et autonome. L''audit réseau a été mené avec rigueur et les recommandations ont été retenues par le service.',
   'Validé', 1),
(5, 18.00, 17.50,
   'Excellent stage de fin d''études. L''application livrée est en production. Très bonne capacité d''adaptation et esprit d''initiative.',
   'Validé', 3),
(6, 13.00, 15.50,
   'Travail méthodique sur la numérisation des dossiers. Bonne intégration dans l''équipe RH.',
   'Validé', 2);
