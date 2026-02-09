<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209011313 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activite_physique (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, type_activite VARCHAR(255) NOT NULL, niveau VARCHAR(255) NOT NULL, duree_estimee INT NOT NULL, calories_estimees INT NOT NULL, actif TINYINT NOT NULL, objectif_sportif_id INT NOT NULL, INDEX IDX_261F98FB27FDBD (objectif_sportif_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE categorie_document (id_categorie INT AUTO_INCREMENT NOT NULL, description VARCHAR(500) NOT NULL, PRIMARY KEY (id_categorie)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document (id_document INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, type_document VARCHAR(50) NOT NULL, chemin_fichier VARCHAR(500) NOT NULL, date_upload DATETIME NOT NULL, categorie_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_D8698A76BCF5E72D (categorie_id), INDEX IDX_D8698A76A76ED395 (user_id), PRIMARY KEY (id_document)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE objectif_sportif (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, type_objectif VARCHAR(255) NOT NULL, date_debut DATE NOT NULL, date_fin DATE DEFAULT NULL, statut VARCHAR(50) NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_2CC45BE1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE plan_nutrition (id INT AUTO_INCREMENT NOT NULL, objectif VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, periode VARCHAR(100) DEFAULT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_71D9DE4A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, date_rdv DATETIME NOT NULL, lieu VARCHAR(150) DEFAULT NULL, notes VARCHAR(500) DEFAULT NULL, statut VARCHAR(20) NOT NULL, type_id INT NOT NULL, INDEX IDX_65E8AA0AC54C8C93 (type_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE repas (id INT AUTO_INCREMENT NOT NULL, type_repas VARCHAR(255) NOT NULL, calories INT NOT NULL, description LONGTEXT NOT NULL, date_repas DATETIME NOT NULL, plan_nutrition_id INT NOT NULL, INDEX IDX_A8D351B36F4325C5 (plan_nutrition_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE type_rendez_vous (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(180) NOT NULL, nom VARCHAR(255) DEFAULT NULL, telephone VARCHAR(8) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE activite_physique ADD CONSTRAINT FK_261F98FB27FDBD FOREIGN KEY (objectif_sportif_id) REFERENCES objectif_sportif (id)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie_document (id_categorie)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE objectif_sportif ADD CONSTRAINT FK_2CC45BE1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE plan_nutrition ADD CONSTRAINT FK_71D9DE4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0AC54C8C93 FOREIGN KEY (type_id) REFERENCES type_rendez_vous (id)');
        $this->addSql('ALTER TABLE repas ADD CONSTRAINT FK_A8D351B36F4325C5 FOREIGN KEY (plan_nutrition_id) REFERENCES plan_nutrition (id)');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E987F4FB17 FOREIGN KEY (doctor_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite_physique DROP FOREIGN KEY FK_261F98FB27FDBD');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76BCF5E72D');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76A76ED395');
        $this->addSql('ALTER TABLE objectif_sportif DROP FOREIGN KEY FK_2CC45BE1A76ED395');
        $this->addSql('ALTER TABLE plan_nutrition DROP FOREIGN KEY FK_71D9DE4A76ED395');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0AC54C8C93');
        $this->addSql('ALTER TABLE repas DROP FOREIGN KEY FK_A8D351B36F4325C5');
        $this->addSql('DROP TABLE activite_physique');
        $this->addSql('DROP TABLE categorie_document');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE objectif_sportif');
        $this->addSql('DROP TABLE plan_nutrition');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('DROP TABLE repas');
        $this->addSql('DROP TABLE type_rendez_vous');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E987F4FB17');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9A76ED395');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
    }
}
