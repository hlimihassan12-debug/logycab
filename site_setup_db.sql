-- ============================================================
-- site_setup_db.sql — Logycab, site public
-- À exécuter dans SQL Server Management Studio, ou via sqlcmd :
--   sqlcmd -S <serveur> -d Logycab -E -f 65001 -i site_setup_db.sql
-- (le -f 65001 est important : le fichier est encodé en UTF-8)
-- ============================================================

USE Logycab;
GO

-- ============================================================
-- 1. T_DemandesRDV — demandes de rendez-vous envoyées depuis le site public
-- ============================================================
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_NAME = 'T_DemandesRDV'
)
BEGIN
    CREATE TABLE T_DemandesRDV (
        id              INT IDENTITY(1,1) PRIMARY KEY,
        nom             NVARCHAR(150)  NOT NULL,
        telephone       NVARCHAR(30)   NOT NULL,
        email           NVARCHAR(150)  NULL,
        motif           NVARCHAR(200)  NULL,
        date_souhaitee  DATE           NULL,
        heure_souhaitee NVARCHAR(5)    NULL,
        message         NVARCHAR(500)  NULL,
        statut          NVARCHAR(20)   NOT NULL DEFAULT 'en_attente', -- en_attente / confirme / refuse
        date_creation   DATETIME       NOT NULL DEFAULT GETDATE()
    );
    PRINT 'T_DemandesRDV creee.';
END
ELSE
    PRINT 'T_DemandesRDV existe deja - ignoree.';
GO

-- ============================================================
-- 2. T_Config — clés pour les infos du cabinet et les horaires
--    (T_Config existe déjà, voir setup_db.sql — on ajoute juste des lignes)
--    NOTE : Valeur est NVARCHAR(100) sur cette base -> rester sous 100 caractères,
--    et toujours préfixer les littéraux texte par N'...' pour l'unicode.
-- ============================================================
IF NOT EXISTS (SELECT 1 FROM T_Config WHERE Cle = N'Cabinet_Nom')
    INSERT INTO T_Config (Cle, Valeur) VALUES (N'Cabinet_Nom', N'Cabinet du Docteur Hassan');

IF NOT EXISTS (SELECT 1 FROM T_Config WHERE Cle = N'Cabinet_Adresse')
    INSERT INTO T_Config (Cle, Valeur) VALUES (N'Cabinet_Adresse', N'12 Avenue Mohammed V, Casablanca');

IF NOT EXISTS (SELECT 1 FROM T_Config WHERE Cle = N'Cabinet_Telephone')
    INSERT INTO T_Config (Cle, Valeur) VALUES (N'Cabinet_Telephone', N'05 22 00 00 00');

IF NOT EXISTS (SELECT 1 FROM T_Config WHERE Cle = N'Cabinet_Email')
    INSERT INTO T_Config (Cle, Valeur) VALUES (N'Cabinet_Email', N'contact@cabinet-hassan.ma');

IF NOT EXISTS (SELECT 1 FROM T_Config WHERE Cle = N'Cabinet_Description')
    INSERT INTO T_Config (Cle, Valeur) VALUES (N'Cabinet_Description', N'Cabinet medical moderne et accueillant, suivi personnalise de chaque patient.');

IF NOT EXISTS (SELECT 1 FROM T_Config WHERE Cle = N'Horaire_Lundi')
    INSERT INTO T_Config (Cle, Valeur) VALUES (N'Horaire_Lundi', N'09:00-16:00');

IF NOT EXISTS (SELECT 1 FROM T_Config WHERE Cle = N'Horaire_Mardi')
    INSERT INTO T_Config (Cle, Valeur) VALUES (N'Horaire_Mardi', N'09:00-16:00');

IF NOT EXISTS (SELECT 1 FROM T_Config WHERE Cle = N'Horaire_Mercredi')
    INSERT INTO T_Config (Cle, Valeur) VALUES (N'Horaire_Mercredi', N'09:00-16:00');

IF NOT EXISTS (SELECT 1 FROM T_Config WHERE Cle = N'Horaire_Jeudi')
    INSERT INTO T_Config (Cle, Valeur) VALUES (N'Horaire_Jeudi', N'09:00-16:00');

IF NOT EXISTS (SELECT 1 FROM T_Config WHERE Cle = N'Horaire_Vendredi')
    INSERT INTO T_Config (Cle, Valeur) VALUES (N'Horaire_Vendredi', N'09:00-16:00');

IF NOT EXISTS (SELECT 1 FROM T_Config WHERE Cle = N'Horaire_Samedi')
    INSERT INTO T_Config (Cle, Valeur) VALUES (N'Horaire_Samedi', N'09:00-13:00');

IF NOT EXISTS (SELECT 1 FROM T_Config WHERE Cle = N'Horaire_Dimanche')
    INSERT INTO T_Config (Cle, Valeur) VALUES (N'Horaire_Dimanche', N'Ferme');

PRINT 'Cles T_Config du site public verifiees/inserees.';
GO

-- ============================================================
-- Vérification finale
-- ============================================================
PRINT '--- T_DemandesRDV + cles Cabinet_/Horaire_ dans T_Config ---';
SELECT Cle, Valeur FROM T_Config WHERE Cle LIKE 'Cabinet_%' OR Cle LIKE 'Horaire_%' ORDER BY Cle;
GO
