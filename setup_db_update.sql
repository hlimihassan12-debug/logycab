-- ============================================================
-- Logycab — Script de mise à jour structure base de données
-- À exécuter sur chaque PC en cas de divergence
-- Date : 01/06/2026
-- ============================================================

-- LE_BILAN : ajouter colonne observation si absente
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME='LE_BILAN' AND COLUMN_NAME='observation'
)
BEGIN
    ALTER TABLE LE_BILAN ADD observation NVARCHAR(500);
    PRINT 'LE_BILAN.observation ajoutée';
END
ELSE
    PRINT 'LE_BILAN.observation déjà présente';

-- T_JourFeries : vérifier structure
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_NAME='T_JourFeries'
)
BEGIN
    CREATE TABLE T_JourFeries (
        id INT IDENTITY(1,1) PRIMARY KEY,
        DateFerie DATE NOT NULL,
        Label NVARCHAR(100)
    );
    PRINT 'T_JourFeries créée';
END
ELSE
    PRINT 'T_JourFeries déjà présente';

-- T_Config : vérifier présence NbrMax
IF NOT EXISTS (
    SELECT 1 FROM T_Config WHERE Cle='NbrMax'
)
BEGIN
    INSERT INTO T_Config (Cle, Valeur) VALUES ('NbrMax', '20');
    PRINT 'T_Config.NbrMax ajouté';
END
ELSE
    PRINT 'T_Config.NbrMax déjà présent';

PRINT '=== Mise à jour terminée ===';
