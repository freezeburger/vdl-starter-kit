<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925134500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the users table and its ten development records.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(180) NOT NULL,
                UNIQUE INDEX UNIQ_USERS_EMAIL (email),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );

        foreach ($this->users() as [$name, $email]) {
            $this->addSql(
                'INSERT INTO users (name, email)
                 SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = ?)',
                [$name, $email, $email]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS users');
    }

    /**
     * @return list<array{string, string}>
     */
    private function users(): array
    {
        return [
            ['Alice Martin', 'alice.martin@example.test'],
            ['Benoît Dupont', 'benoit.dupont@example.test'],
            ['Chloé Bernard', 'chloe.bernard@example.test'],
            ['David Robert', 'david.robert@example.test'],
            ['Emma Richard', 'emma.richard@example.test'],
            ['Farid Petit', 'farid.petit@example.test'],
            ['Gabrielle Durand', 'gabrielle.durand@example.test'],
            ['Hugo Leroy', 'hugo.leroy@example.test'],
            ['Inès Moreau', 'ines.moreau@example.test'],
            ['Jules Simon', 'jules.simon@example.test'],
        ];
    }
}
