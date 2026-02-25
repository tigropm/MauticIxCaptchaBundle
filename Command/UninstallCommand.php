<?php

declare(strict_types=1);

namespace MauticPlugin\MauticIxCaptchaBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes all ixCaptcha field references from Mautic forms and cleans up the
 * integration settings before the plugin folder is deleted.
 *
 * Usage:
 *   php bin/console mautic:ixcaptcha:uninstall
 *   php bin/console mautic:ixcaptcha:uninstall --force   # skip confirmation prompt
 *
 * Run this command BEFORE deleting the plugin folder, then:
 *   rm -rf plugins/MauticIxCaptchaBundle
 *   php bin/console cache:clear
 *   php bin/console mautic:plugins:reload
 */
class UninstallCommand extends Command
{
    public const COMMAND_NAME = 'mautic:ixcaptcha:uninstall';

    /** Field type identifier used in Mautic's form_fields table */
    private const FIELD_TYPE = 'plugin.ixcaptcha';

    /** Integration name used in plugin_integration_settings */
    private const INTEGRATION_NAME = 'IxCaptcha';

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Removes all ixCaptcha fields from Mautic forms and cleans up integration settings.')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Skip the confirmation prompt'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('ixCaptcha — Uninstall');

        // Count affected form fields
        $fieldCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM form_fields WHERE type = ?',
            [self::FIELD_TYPE]
        );

        // Count affected forms (distinct)
        $formCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(DISTINCT form_id) FROM form_fields WHERE type = ?',
            [self::FIELD_TYPE]
        );

        if ($fieldCount === 0) {
            $io->success('No ixCaptcha fields found in any form — nothing to clean up.');
        } else {
            $io->warning(sprintf(
                'Found %d ixCaptcha field(s) across %d form(s). These will be permanently removed.',
                $fieldCount,
                $formCount
            ));

            if (!$input->getOption('force')) {
                $confirmed = $io->confirm('Continue and remove all ixCaptcha fields?', false);
                if (!$confirmed) {
                    $io->comment('Aborted — no changes made.');
                    return Command::SUCCESS;
                }
            }

            // Delete all ixCaptcha fields from all forms
            $deleted = $this->connection->executeStatement(
                'DELETE FROM form_fields WHERE type = ?',
                [self::FIELD_TYPE]
            );

            $io->success(sprintf('Removed %d ixCaptcha field(s) from %d form(s).', $deleted, $formCount));
        }

        // Remove integration settings
        $settingsDeleted = $this->connection->executeStatement(
            'DELETE FROM plugin_integration_settings WHERE name = ?',
            [self::INTEGRATION_NAME]
        );

        if ($settingsDeleted > 0) {
            $io->writeln('<info>✓ Integration settings removed from database.</info>');
        }

        $io->section('Next steps');
        $io->listing([
            'rm -rf plugins/MauticIxCaptchaBundle',
            'php bin/console cache:clear',
            'php bin/console mautic:plugins:reload',
        ]);

        return Command::SUCCESS;
    }

    protected static $defaultDescription = 'Removes all ixCaptcha fields from Mautic forms before uninstalling the plugin.';
}
