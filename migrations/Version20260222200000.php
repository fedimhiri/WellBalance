<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alter insurance_reference column to VARCHAR(255) for Document entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document MODIFY insurance_reference VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document MODIFY insurance_reference VARCHAR(100) DEFAULT NULL');
    }
}
