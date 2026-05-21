-- ============================================================
-- setup_db.sql — Logycab
-- À exécuter dans SQL Server Management Studio
-- sur chaque machine (PC cabinet ou laptop)
-- après avoir sélectionné la base : USE Logycab;
-- ============================================================

USE Logycab;
GO

-- ============================================================
-- 1. T_Config — configuration générale (ex: NbrMax)
-- ============================================================
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_NAME = 'T_Config'
)
BEGIN
    CREATE TABLE T_Config (
        Cle    NVARCHAR(50)  NOT NULL PRIMARY KEY,
        Valeur NVARCHAR(255) NOT NULL
    );
    -- Valeur par défaut : 20 patients max par jour
    INSERT INTO T_Config (Cle, Valeur) VALUES ('NbrMax', '20');
    PRINT 'T_Config créée et initialisée.';
END
ELSE
    PRINT 'T_Config existe déjà — ignorée.';
GO

-- ============================================================
-- 2. T_JourFeries — jours fériés et jours de fermeture
-- ============================================================
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_NAME = 'T_JourFeries'
)
BEGIN
    CREATE TABLE T_JourFeries (
        id        INT IDENTITY(1,1) PRIMARY KEY,
        DateFerie DATE          NOT NULL,
        Label     NVARCHAR(100) NOT NULL DEFAULT ''
    );
    PRINT 'T_JourFeries créée.';
END
ELSE
BEGIN
    -- Vérifier si la colonne Label existe, sinon l'ajouter
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'T_JourFeries' AND COLUMN_NAME = 'Label'
    )
    BEGIN
        ALTER TABLE T_JourFeries ADD Label NVARCHAR(100) NOT NULL DEFAULT '';
        PRINT 'Colonne Label ajoutée à T_JourFeries.';
    END
    ELSE
        PRINT 'T_JourFeries existe déjà — ignorée.';
END
GO

-- ============================================================
-- 3. LE_BILAN — bilans biologiques des patients
-- ============================================================
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_NAME = 'LE_BILAN'
)
BEGIN
    CREATE TABLE LE_BILAN (
        n_bilan     INT IDENTITY(1,1) PRIMARY KEY,
        id          INT           NOT NULL,   -- FK → ID.[N°PAT]
        date_bilan  DATE          NOT NULL,
        observation NVARCHAR(500) NULL
    );
    PRINT 'LE_BILAN créée.';
END
ELSE
    PRINT 'LE_BILAN existe déjà — ignorée.';
GO

-- ============================================================
-- 4. analyses — détail des bilans (lignes d'analyse)
-- ============================================================
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_NAME = 'analyses'
)
BEGIN
    CREATE TABLE analyses (
        N_analyse INT IDENTITY(1,1) PRIMARY KEY,
        N_bilan   INT           NOT NULL,   -- FK → LE_BILAN.n_bilan
        bilan     INT           NOT NULL,   -- FK → C_ANALYSE.[N°TypeAnalyse]
        résultat  NVARCHAR(100) NULL
    );
    PRINT 'analyses créée.';
END
ELSE
    PRINT 'analyses existe déjà — ignorée.';
GO

-- ============================================================
-- 5. C_ANALYSE — catalogue des analyses (référentiel)
-- ============================================================
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_NAME = 'C_ANALYSE'
)
BEGIN
    CREATE TABLE C_ANALYSE (
        [N°TypeAnalyse] INT IDENTITY(1,1) PRIMARY KEY,
        analyse         NVARCHAR(150) NOT NULL,
        rubrique        NVARCHAR(100) NULL
    );
    PRINT 'C_ANALYSE créée (vide — à remplir avec le catalogue).';
END
ELSE
    PRINT 'C_ANALYSE existe déjà — ignorée.';
GO

-- ============================================================
-- Vérification finale — liste toutes les tables Logycab
-- ============================================================
PRINT '--- Tables présentes dans la base Logycab ---';
SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;
GO
