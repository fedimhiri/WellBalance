<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208150117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE objectif_sportif ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE objectif_sportif ADD CONSTRAINT FK_2CC45BE1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_2CC45BE1A76ED395 ON objectif_sportif (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE objectif_sportif DROP FOREIGN KEY FK_2CC45BE1A76ED395');
        $this->addSql('DROP INDEX IDX_2CC45BE1A76ED395 ON objectif_sportif');
        $this->addSql('ALTER TABLE objectif_sportif DROP user_id');
    }
}
