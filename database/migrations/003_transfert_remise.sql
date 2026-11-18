-- =====================================================================
--  Migration 003 : bon de transfert « Remis par » / « Reçu par »
--    mysql gestimmo < database/migrations/003_transfert_remise.sql
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE transferts
    ADD COLUMN remis_par_id INT UNSIGNED NULL COMMENT 'Employé qui remet les biens (local source)' AFTER employe_destination_id,
    ADD COLUMN recu_par_id  INT UNSIGNED NULL COMMENT 'Employé qui réceptionne les biens (local destination)' AFTER remis_par_id,
    ADD KEY idx_tr_remis_par (remis_par_id),
    ADD KEY idx_tr_recu_par (recu_par_id),
    ADD CONSTRAINT fk_tr_remis FOREIGN KEY (remis_par_id) REFERENCES employes (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_tr_recu  FOREIGN KEY (recu_par_id)  REFERENCES employes (id) ON DELETE SET NULL;

-- Transferts existants : le destinataire est considéré comme réceptionnaire
UPDATE transferts SET recu_par_id = employe_destination_id WHERE recu_par_id IS NULL;
