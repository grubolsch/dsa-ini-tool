<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema for the Turn Tracker (MySQL 8).
 */
final class Version20260629000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: hero, monster_template, encounter, encounter_monster, live_encounter, combatant, status_effect';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hero (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            picture VARCHAR(255) DEFAULT NULL,
            initiative INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE monster_template (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            picture VARCHAR(255) DEFAULT NULL,
            initiative INT NOT NULL,
            le INT NOT NULL,
            description LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE encounter (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            atmosphere_picture VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_encounter_name (name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE encounter_monster (
            id INT AUTO_INCREMENT NOT NULL,
            encounter_id INT NOT NULL,
            monster_template_id INT DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            picture VARCHAR(255) DEFAULT NULL,
            initiative INT NOT NULL,
            le INT NOT NULL,
            description LONGTEXT DEFAULT NULL,
            INDEX IDX_em_encounter (encounter_id),
            INDEX IDX_em_template (monster_template_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE live_encounter (
            id INT AUTO_INCREMENT NOT NULL,
            encounter_id INT NOT NULL,
            code VARCHAR(8) NOT NULL,
            round INT NOT NULL,
            active_index INT NOT NULL,
            phase VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_live_code (code),
            INDEX IDX_live_encounter (encounter_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE combatant (
            id INT AUTO_INCREMENT NOT NULL,
            live_encounter_id INT NOT NULL,
            side VARCHAR(20) NOT NULL,
            name VARCHAR(255) NOT NULL,
            picture VARCHAR(255) DEFAULT NULL,
            initiative INT NOT NULL,
            le INT DEFAULT NULL,
            max_le INT DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            is_dead TINYINT(1) NOT NULL,
            is_out_of_combat TINYINT(1) NOT NULL,
            sort_order INT NOT NULL,
            pending_initiative INT DEFAULT NULL,
            ini_changed_this_round TINYINT(1) NOT NULL,
            INDEX IDX_combatant_live (live_encounter_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE status_effect (
            id INT AUTO_INCREMENT NOT NULL,
            combatant_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            duration_rounds INT NOT NULL,
            trigger_at_round_end TINYINT(1) NOT NULL,
            group_tag VARCHAR(20) DEFAULT NULL,
            INDEX IDX_se_combatant (combatant_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE encounter_monster ADD CONSTRAINT FK_em_encounter FOREIGN KEY (encounter_id) REFERENCES encounter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE encounter_monster ADD CONSTRAINT FK_em_template FOREIGN KEY (monster_template_id) REFERENCES monster_template (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE live_encounter ADD CONSTRAINT FK_live_encounter FOREIGN KEY (encounter_id) REFERENCES encounter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE combatant ADD CONSTRAINT FK_combatant_live FOREIGN KEY (live_encounter_id) REFERENCES live_encounter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE status_effect ADD CONSTRAINT FK_se_combatant FOREIGN KEY (combatant_id) REFERENCES combatant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE status_effect DROP FOREIGN KEY FK_se_combatant');
        $this->addSql('ALTER TABLE combatant DROP FOREIGN KEY FK_combatant_live');
        $this->addSql('ALTER TABLE live_encounter DROP FOREIGN KEY FK_live_encounter');
        $this->addSql('ALTER TABLE encounter_monster DROP FOREIGN KEY FK_em_template');
        $this->addSql('ALTER TABLE encounter_monster DROP FOREIGN KEY FK_em_encounter');
        $this->addSql('DROP TABLE status_effect');
        $this->addSql('DROP TABLE combatant');
        $this->addSql('DROP TABLE live_encounter');
        $this->addSql('DROP TABLE encounter_monster');
        $this->addSql('DROP TABLE encounter');
        $this->addSql('DROP TABLE monster_template');
        $this->addSql('DROP TABLE hero');
    }
}
