<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208134151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plan_nutrition (id INT AUTO_INCREMENT NOT NULL, objectif VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, periode VARCHAR(100) DEFAULT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_71D9DE4A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE repas (id INT AUTO_INCREMENT NOT NULL, type_repas VARCHAR(255) NOT NULL, calories INT NOT NULL, description LONGTEXT NOT NULL, date_repas DATETIME NOT NULL, plan_nutrition_id INT NOT NULL, INDEX IDX_A8D351B36F4325C5 (plan_nutrition_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE plan_nutrition ADD CONSTRAINT FK_71D9DE4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE repas ADD CONSTRAINT FK_A8D351B36F4325C5 FOREIGN KEY (plan_nutrition_id) REFERENCES plan_nutrition (id)');
        $this->addSql('ALTER TABLE user ADD nom VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE plan_nutrition DROP FOREIGN KEY FK_71D9DE4A76ED395');
        $this->addSql('ALTER TABLE repas DROP FOREIGN KEY FK_A8D351B36F4325C5');
        $this->addSql('DROP TABLE plan_nutrition');
        $this->addSql('DROP TABLE repas');
        $this->addSql('ALTER TABLE user DROP nom');
    }
}
