<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222192137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE categorie_document (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, type_document VARCHAR(100) NOT NULL, chemin_fichier VARCHAR(500) NOT NULL, date_upload DATETIME NOT NULL, resume_ai LONGTEXT DEFAULT NULL, mots_cles JSON DEFAULT NULL, type_detecte VARCHAR(100) DEFAULT NULL, insurance_reference VARCHAR(100) DEFAULT NULL, categorie_id INT DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_D8698A76BCF5E72D (categorie_id), INDEX IDX_D8698A76A76ED395 (user_id), INDEX idx_document_date_upload (date_upload), INDEX idx_document_type (type_document), INDEX idx_document_type_detecte (type_detecte), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie_document (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76BCF5E72D');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76A76ED395');
        $this->addSql('DROP TABLE categorie_document');
        $this->addSql('DROP TABLE document');
    }
}
