<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222122957 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alerte_nutrition (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(40) NOT NULL, severity VARCHAR(10) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, is_read TINYINT DEFAULT 0 NOT NULL, patient_id INT NOT NULL, plan_nutrition_id INT NOT NULL, nutritionniste_id INT DEFAULT NULL, INDEX IDX_B195BAA46B899279 (patient_id), INDEX IDX_B195BAA46F4325C5 (plan_nutrition_id), INDEX IDX_B195BAA4279DA68A (nutritionniste_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE plan_nutrition (id INT AUTO_INCREMENT NOT NULL, objectif VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, periode VARCHAR(100) DEFAULT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NOT NULL, user_id INT NOT NULL, nutritionniste_id INT DEFAULT NULL, INDEX IDX_71D9DE4A76ED395 (user_id), INDEX IDX_71D9DE4279DA68A (nutritionniste_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE repas (id INT AUTO_INCREMENT NOT NULL, type_repas VARCHAR(255) NOT NULL, calories INT DEFAULT NULL, proteines DOUBLE PRECISION DEFAULT NULL, glucides DOUBLE PRECISION DEFAULT NULL, lipides DOUBLE PRECISION DEFAULT NULL, portion_size VARCHAR(50) DEFAULT NULL, barcode VARCHAR(32) DEFAULT NULL, description LONGTEXT NOT NULL, date_repas DATETIME NOT NULL, plan_nutrition_id INT NOT NULL, INDEX IDX_A8D351B36F4325C5 (plan_nutrition_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE suivi_nutrition (id INT AUTO_INCREMENT NOT NULL, date_suivi DATETIME NOT NULL, poids DOUBLE PRECISION DEFAULT NULL, respect_pourcentage INT NOT NULL, humeur VARCHAR(20) DEFAULT NULL, commentaire_patient LONGTEXT DEFAULT NULL, commentaire_nutritionniste LONGTEXT DEFAULT NULL, plan_nutrition_id INT NOT NULL, patient_id INT NOT NULL, INDEX IDX_DE639B9F6F4325C5 (plan_nutrition_id), INDEX IDX_DE639B9F6B899279 (patient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE alerte_nutrition ADD CONSTRAINT FK_B195BAA46B899279 FOREIGN KEY (patient_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE alerte_nutrition ADD CONSTRAINT FK_B195BAA46F4325C5 FOREIGN KEY (plan_nutrition_id) REFERENCES plan_nutrition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE alerte_nutrition ADD CONSTRAINT FK_B195BAA4279DA68A FOREIGN KEY (nutritionniste_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE plan_nutrition ADD CONSTRAINT FK_71D9DE4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE plan_nutrition ADD CONSTRAINT FK_71D9DE4279DA68A FOREIGN KEY (nutritionniste_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE repas ADD CONSTRAINT FK_A8D351B36F4325C5 FOREIGN KEY (plan_nutrition_id) REFERENCES plan_nutrition (id)');
        $this->addSql('ALTER TABLE suivi_nutrition ADD CONSTRAINT FK_DE639B9F6F4325C5 FOREIGN KEY (plan_nutrition_id) REFERENCES plan_nutrition (id)');
        $this->addSql('ALTER TABLE suivi_nutrition ADD CONSTRAINT FK_DE639B9F6B899279 FOREIGN KEY (patient_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE alerte_nutrition DROP FOREIGN KEY FK_B195BAA46B899279');
        $this->addSql('ALTER TABLE alerte_nutrition DROP FOREIGN KEY FK_B195BAA46F4325C5');
        $this->addSql('ALTER TABLE alerte_nutrition DROP FOREIGN KEY FK_B195BAA4279DA68A');
        $this->addSql('ALTER TABLE plan_nutrition DROP FOREIGN KEY FK_71D9DE4A76ED395');
        $this->addSql('ALTER TABLE plan_nutrition DROP FOREIGN KEY FK_71D9DE4279DA68A');
        $this->addSql('ALTER TABLE repas DROP FOREIGN KEY FK_A8D351B36F4325C5');
        $this->addSql('ALTER TABLE suivi_nutrition DROP FOREIGN KEY FK_DE639B9F6F4325C5');
        $this->addSql('ALTER TABLE suivi_nutrition DROP FOREIGN KEY FK_DE639B9F6B899279');
        $this->addSql('DROP TABLE alerte_nutrition');
        $this->addSql('DROP TABLE plan_nutrition');
        $this->addSql('DROP TABLE repas');
        $this->addSql('DROP TABLE suivi_nutrition');
    }
}
