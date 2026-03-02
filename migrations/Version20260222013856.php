<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222013856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conversation (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, type VARCHAR(20) DEFAULT NULL, sujet VARCHAR(255) DEFAULT NULL, id_medecin INT NOT NULL, user_id INT NOT NULL, INDEX IDX_8A8E26E9C547FAB6 (id_medecin), INDEX IDX_8A8E26E9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE doctor_availability_exception (id INT AUTO_INCREMENT NOT NULL, doctor_id INT NOT NULL, date DATE NOT NULL, start_time TIME DEFAULT NULL, end_time TIME DEFAULT NULL, type VARCHAR(20) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE doctor_recurring_availability (id INT AUTO_INCREMENT NOT NULL, doctor_id INT NOT NULL, weekday INT NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, slot_minutes INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE doctor_time_block (id INT AUTO_INCREMENT NOT NULL, doctor_id INT NOT NULL, start_datetime DATETIME NOT NULL, end_datetime DATETIME NOT NULL, note LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT DEFAULT NULL, attachment VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, is_read TINYINT NOT NULL, ai_analysis JSON DEFAULT NULL, conversation_id INT NOT NULL, sender_id INT NOT NULL, INDEX IDX_B6BD307F9AC0396 (conversation_id), INDEX IDX_B6BD307FF624B39D (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, date_rdv DATE NOT NULL, heure_rdv TIME NOT NULL, statut VARCHAR(20) DEFAULT \'EN_COURS\' NOT NULL, type_rendez_vous_id INT NOT NULL, patient_id INT DEFAULT NULL, medecin_id INT DEFAULT NULL, INDEX IDX_65E8AA0AC72C573E (type_rendez_vous_id), INDEX IDX_65E8AA0A6B899279 (patient_id), INDEX IDX_65E8AA0A4F31A84 (medecin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE type_rendez_vous (id INT AUTO_INCREMENT NOT NULL, nom_type VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_2EF17D9B7E0E9D47 (nom_type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(180) NOT NULL, telephone VARCHAR(8) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_banned TINYINT NOT NULL, avatar VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9C547FAB6 FOREIGN KEY (id_medecin) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0AC72C573E FOREIGN KEY (type_rendez_vous_id) REFERENCES type_rendez_vous (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A6B899279 FOREIGN KEY (patient_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A4F31A84 FOREIGN KEY (medecin_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9C547FAB6');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9A76ED395');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0AC72C573E');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A6B899279');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A4F31A84');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE doctor_availability_exception');
        $this->addSql('DROP TABLE doctor_recurring_availability');
        $this->addSql('DROP TABLE doctor_time_block');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('DROP TABLE type_rendez_vous');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
