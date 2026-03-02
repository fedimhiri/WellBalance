<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222185910 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alerte_nutrition (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(40) NOT NULL, severity VARCHAR(10) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, is_read TINYINT DEFAULT 0 NOT NULL, patient_id INT NOT NULL, plan_nutrition_id INT NOT NULL, nutritionniste_id INT DEFAULT NULL, INDEX IDX_B195BAA46B899279 (patient_id), INDEX IDX_B195BAA46F4325C5 (plan_nutrition_id), INDEX IDX_B195BAA4279DA68A (nutritionniste_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE doctor_availability_exception (id INT AUTO_INCREMENT NOT NULL, doctor_id INT NOT NULL, date DATE NOT NULL, start_time TIME DEFAULT NULL, end_time TIME DEFAULT NULL, type VARCHAR(20) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE doctor_recurring_availability (id INT AUTO_INCREMENT NOT NULL, doctor_id INT NOT NULL, weekday INT NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, slot_minutes INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE doctor_time_block (id INT AUTO_INCREMENT NOT NULL, doctor_id INT NOT NULL, start_datetime DATETIME NOT NULL, end_datetime DATETIME NOT NULL, note LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE suivi_nutrition (id INT AUTO_INCREMENT NOT NULL, date_suivi DATETIME NOT NULL, poids DOUBLE PRECISION DEFAULT NULL, respect_pourcentage INT NOT NULL, humeur VARCHAR(20) DEFAULT NULL, commentaire_patient LONGTEXT DEFAULT NULL, commentaire_nutritionniste LONGTEXT DEFAULT NULL, plan_nutrition_id INT NOT NULL, patient_id INT NOT NULL, INDEX IDX_DE639B9F6F4325C5 (plan_nutrition_id), INDEX IDX_DE639B9F6B899279 (patient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE alerte_nutrition ADD CONSTRAINT FK_B195BAA46B899279 FOREIGN KEY (patient_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE alerte_nutrition ADD CONSTRAINT FK_B195BAA46F4325C5 FOREIGN KEY (plan_nutrition_id) REFERENCES plan_nutrition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE alerte_nutrition ADD CONSTRAINT FK_B195BAA4279DA68A FOREIGN KEY (nutritionniste_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE suivi_nutrition ADD CONSTRAINT FK_DE639B9F6F4325C5 FOREIGN KEY (plan_nutrition_id) REFERENCES plan_nutrition (id)');
        $this->addSql('ALTER TABLE suivi_nutrition ADD CONSTRAINT FK_DE639B9F6B899279 FOREIGN KEY (patient_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE activite_physique DROP FOREIGN KEY `FK_261F98FB27FDBD`');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY `FK_D8698A76A76ED395`');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY `FK_D8698A76BCF5E72D`');
        $this->addSql('ALTER TABLE objectif_sportif DROP FOREIGN KEY `FK_2CC45BE1A76ED395`');
        $this->addSql('DROP TABLE activite_physique');
        $this->addSql('DROP TABLE categorie_document');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE objectif_sportif');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY `FK_8A8E26E987F4FB17`');
        $this->addSql('DROP INDEX IDX_8A8E26E987F4FB17 ON conversation');
        $this->addSql('ALTER TABLE conversation CHANGE doctor_id id_medecin INT NOT NULL');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9C547FAB6 FOREIGN KEY (id_medecin) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_8A8E26E9C547FAB6 ON conversation (id_medecin)');
        $this->addSql('ALTER TABLE plan_nutrition DROP FOREIGN KEY `FK_71D9DE4A76ED395`');
        $this->addSql('ALTER TABLE plan_nutrition ADD nutritionniste_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE plan_nutrition ADD CONSTRAINT FK_71D9DE4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_nutrition ADD CONSTRAINT FK_71D9DE4279DA68A FOREIGN KEY (nutritionniste_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_71D9DE4279DA68A ON plan_nutrition (nutritionniste_id)');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY `FK_65E8AA0AC54C8C93`');
        $this->addSql('DROP INDEX IDX_65E8AA0AC54C8C93 ON rendez_vous');
        $this->addSql('ALTER TABLE rendez_vous ADD description LONGTEXT DEFAULT NULL, ADD heure_rdv TIME NOT NULL, ADD patient_id INT DEFAULT NULL, ADD medecin_id INT DEFAULT NULL, DROP lieu, DROP notes, CHANGE titre titre VARCHAR(120) NOT NULL, CHANGE date_rdv date_rdv DATE NOT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'EN_COURS\' NOT NULL, CHANGE type_id type_rendez_vous_id INT NOT NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0AC72C573E FOREIGN KEY (type_rendez_vous_id) REFERENCES type_rendez_vous (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A6B899279 FOREIGN KEY (patient_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A4F31A84 FOREIGN KEY (medecin_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_65E8AA0AC72C573E ON rendez_vous (type_rendez_vous_id)');
        $this->addSql('CREATE INDEX IDX_65E8AA0A6B899279 ON rendez_vous (patient_id)');
        $this->addSql('CREATE INDEX IDX_65E8AA0A4F31A84 ON rendez_vous (medecin_id)');
        $this->addSql('ALTER TABLE repas ADD proteines DOUBLE PRECISION DEFAULT NULL, ADD glucides DOUBLE PRECISION DEFAULT NULL, ADD lipides DOUBLE PRECISION DEFAULT NULL, ADD portion_size VARCHAR(50) DEFAULT NULL, ADD barcode VARCHAR(32) DEFAULT NULL, CHANGE calories calories INT DEFAULT NULL');
        $this->addSql('ALTER TABLE type_rendez_vous CHANGE description description LONGTEXT DEFAULT NULL, CHANGE libelle nom_type VARCHAR(100) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2EF17D9B7E0E9D47 ON type_rendez_vous (nom_type)');
        $this->addSql('ALTER TABLE user ADD is_banned TINYINT NOT NULL, CHANGE nom avatar VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activite_physique (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, type_activite VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, niveau VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, duree_estimee INT NOT NULL, calories_estimees INT NOT NULL, actif TINYINT NOT NULL, objectif_sportif_id INT NOT NULL, INDEX IDX_261F98FB27FDBD (objectif_sportif_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE categorie_document (id_categorie INT AUTO_INCREMENT NOT NULL, description VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY (id_categorie)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE document (id_document INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, type_document VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, chemin_fichier VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_upload DATETIME NOT NULL, categorie_id INT NOT NULL, user_id INT NOT NULL, ai_summary LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, ai_keywords JSON DEFAULT NULL, ai_detected_type VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, ai_analyzed_at DATETIME DEFAULT NULL, ai_is_analyzed TINYINT DEFAULT 0 NOT NULL, insurance_reference VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX IDX_D8698A76A76ED395 (user_id), INDEX IDX_D8698A76BCF5E72D (categorie_id), PRIMARY KEY (id_document)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE objectif_sportif (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, type_objectif VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_debut DATE NOT NULL, date_fin DATE DEFAULT NULL, statut VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, user_id INT DEFAULT NULL, INDEX IDX_2CC45BE1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE activite_physique ADD CONSTRAINT `FK_261F98FB27FDBD` FOREIGN KEY (objectif_sportif_id) REFERENCES objectif_sportif (id)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT `FK_D8698A76A76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT `FK_D8698A76BCF5E72D` FOREIGN KEY (categorie_id) REFERENCES categorie_document (id_categorie)');
        $this->addSql('ALTER TABLE objectif_sportif ADD CONSTRAINT `FK_2CC45BE1A76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE alerte_nutrition DROP FOREIGN KEY FK_B195BAA46B899279');
        $this->addSql('ALTER TABLE alerte_nutrition DROP FOREIGN KEY FK_B195BAA46F4325C5');
        $this->addSql('ALTER TABLE alerte_nutrition DROP FOREIGN KEY FK_B195BAA4279DA68A');
        $this->addSql('ALTER TABLE suivi_nutrition DROP FOREIGN KEY FK_DE639B9F6F4325C5');
        $this->addSql('ALTER TABLE suivi_nutrition DROP FOREIGN KEY FK_DE639B9F6B899279');
        $this->addSql('DROP TABLE alerte_nutrition');
        $this->addSql('DROP TABLE doctor_availability_exception');
        $this->addSql('DROP TABLE doctor_recurring_availability');
        $this->addSql('DROP TABLE doctor_time_block');
        $this->addSql('DROP TABLE suivi_nutrition');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9C547FAB6');
        $this->addSql('DROP INDEX IDX_8A8E26E9C547FAB6 ON conversation');
        $this->addSql('ALTER TABLE conversation CHANGE id_medecin doctor_id INT NOT NULL');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT `FK_8A8E26E987F4FB17` FOREIGN KEY (doctor_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_8A8E26E987F4FB17 ON conversation (doctor_id)');
        $this->addSql('ALTER TABLE plan_nutrition DROP FOREIGN KEY FK_71D9DE4A76ED395');
        $this->addSql('ALTER TABLE plan_nutrition DROP FOREIGN KEY FK_71D9DE4279DA68A');
        $this->addSql('DROP INDEX IDX_71D9DE4279DA68A ON plan_nutrition');
        $this->addSql('ALTER TABLE plan_nutrition DROP nutritionniste_id');
        $this->addSql('ALTER TABLE plan_nutrition ADD CONSTRAINT `FK_71D9DE4A76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0AC72C573E');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A6B899279');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A4F31A84');
        $this->addSql('DROP INDEX IDX_65E8AA0AC72C573E ON rendez_vous');
        $this->addSql('DROP INDEX IDX_65E8AA0A6B899279 ON rendez_vous');
        $this->addSql('DROP INDEX IDX_65E8AA0A4F31A84 ON rendez_vous');
        $this->addSql('ALTER TABLE rendez_vous ADD lieu VARCHAR(150) DEFAULT NULL, ADD notes VARCHAR(500) DEFAULT NULL, DROP description, DROP heure_rdv, DROP patient_id, DROP medecin_id, CHANGE titre titre VARCHAR(150) NOT NULL, CHANGE date_rdv date_rdv DATETIME NOT NULL, CHANGE statut statut VARCHAR(20) NOT NULL, CHANGE type_rendez_vous_id type_id INT NOT NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT `FK_65E8AA0AC54C8C93` FOREIGN KEY (type_id) REFERENCES type_rendez_vous (id)');
        $this->addSql('CREATE INDEX IDX_65E8AA0AC54C8C93 ON rendez_vous (type_id)');
        $this->addSql('ALTER TABLE repas DROP proteines, DROP glucides, DROP lipides, DROP portion_size, DROP barcode, CHANGE calories calories INT NOT NULL');
        $this->addSql('DROP INDEX UNIQ_2EF17D9B7E0E9D47 ON type_rendez_vous');
        $this->addSql('ALTER TABLE type_rendez_vous CHANGE description description VARCHAR(255) DEFAULT NULL, CHANGE nom_type libelle VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE user DROP is_banned, CHANGE avatar nom VARCHAR(255) DEFAULT NULL');
    }
}
