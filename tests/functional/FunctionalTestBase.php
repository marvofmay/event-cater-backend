<?php

declare(strict_types=1);

namespace App\tests\functional;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

abstract class FunctionalTestBase extends WebTestCase
{
    private static bool $templateReady = false;

    protected EntityManagerInterface $em;
    protected KernelBrowser $client;
    protected MessageService $messageService;

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->messageService = static::getContainer()->get(MessageService::class);

        if (self::$templateReady) {
            $this->restoreTemplate($this->em->getConnection());

            return;
        }

        $this->resetDatabase();
        $this->initializeData();
        $this->storeTemplate($this->em->getConnection());
        self::$templateReady = true;
    }

    private function resetDatabase(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();

        $schemaTool->dropDatabase();
        if (!empty($metadata)) {
            $schemaTool->createSchema($metadata);
        }
    }

    private function initializeData(): void
    {
        $application = new Application(static::$kernel);
        $command = $application->find('app:initialize-system-default-data');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    private function storeTemplate(Connection $connection): void
    {
        $source = $this->quoteIdentifier($connection->getDatabase());
        $template = $this->quoteIdentifier($connection->getDatabase().'_seed');
        $connection->executeStatement(sprintf('DROP DATABASE IF EXISTS %s', $template));
        $connection->executeStatement(sprintf('CREATE DATABASE %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $template));
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($connection->fetchFirstColumn('SHOW TABLES') as $table) {
            $table = (string) $table;
            $quoted = $this->quoteIdentifier($table);
            $create = $connection->fetchAssociative(sprintf('SHOW CREATE TABLE %s.%s', $source, $quoted));
            $statement = $create['Create Table'] ?? null;
            if (!is_string($statement)) {
                throw new \RuntimeException(sprintf('Brak definicji tabeli %s.', $table));
            }
            $qualified = preg_replace('/^CREATE TABLE '.$quoted.'/', 'CREATE TABLE '.$template.'.'.$quoted, $statement, 1);
            if (!is_string($qualified) || !str_starts_with($qualified, 'CREATE TABLE '.$template.'.')) {
                throw new \RuntimeException(sprintf('Nie udało się przenieść definicji tabeli %s do szablonu testów.', $table));
            }
            $connection->executeStatement($qualified);
            $connection->executeStatement(sprintf('INSERT INTO %s.%s SELECT * FROM %s.%s', $template, $quoted, $source, $quoted));
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function restoreTemplate(Connection $connection): void
    {
        $source = $this->quoteIdentifier($connection->getDatabase());
        $template = $this->quoteIdentifier($connection->getDatabase().'_seed');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($connection->fetchFirstColumn('SHOW TABLES') as $table) {
            $connection->executeStatement(sprintf('DROP TABLE %s', $this->quoteIdentifier((string) $table)));
        }
        foreach ($connection->fetchFirstColumn(sprintf('SHOW TABLES FROM %s', $template)) as $table) {
            $quoted = $this->quoteIdentifier((string) $table);
            $create = $connection->fetchAssociative(sprintf('SHOW CREATE TABLE %s.%s', $template, $quoted));
            $statement = $create['Create Table'] ?? null;
            if (!is_string($statement)) {
                throw new \RuntimeException(sprintf('Brak definicji tabeli %s w szablonie testów.', $table));
            }
            $connection->executeStatement($statement);
            $connection->executeStatement(sprintf('INSERT INTO %s.%s SELECT * FROM %s.%s', $source, $quoted, $template, $quoted));
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new \RuntimeException(sprintf('Niedozwolona nazwa identyfikatora SQL: %s', $identifier));
        }

        return '`'.$identifier.'`';
    }

    protected function getAuthenticatedClient(string $email = 'admin.ec@gmail.com', string $password = 'Admin123!'): KernelBrowser
    {
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => $email,
            'password' => $password,
        ]));

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $token = $data['token'] ?? null;
        if (!$response->isSuccessful() || !$token) {
            throw new \RuntimeException('Nie udało się zalogować użytkownika testowego.');
        }

        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$token);

        return $this->client;
    }

    protected function tearDown(): void
    {
        if (null !== static::$kernel) {
            $container = static::getContainer();
            if ($container->has('doctrine')) {
                foreach ($container->get('doctrine')->getConnections() as $connection) {
                    if ($connection->isConnected()) {
                        $connection->close();
                    }
                }
            }
        }
        parent::tearDown();
    }
}
