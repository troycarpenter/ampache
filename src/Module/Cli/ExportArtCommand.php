<?php

declare(strict_types=1);

/**
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright Ampache.org, 2001-2024
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace Ampache\Module\Cli;

use Ahc\Cli\Input\Command;
use Ampache\Module\Art\Export\ArtExporterInterface;
use Ampache\Module\Art\Export\Exception\ArtExportException;
use Ampache\Module\Art\Export\Writer\MetadataWriter;
use Ampache\Module\System\LegacyLogger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class ExportArtCommand extends Command
{
    private LoggerInterface $logger;

    private ArtExporterInterface $artExporter;

    private ContainerInterface $dic;

    protected function defaults(): self
    {
        $this->option('-h, --help', T_('Help'))->on([$this, 'showHelp']);

        $this->onExit(static fn ($exitCode = 0) => exit($exitCode));

        return $this;
    }

    public function __construct(
        LoggerInterface $logger,
        ArtExporterInterface $artExporter,
        ContainerInterface $dic
    ) {
        parent::__construct('export:databaseArt', T_('Export all database art to local_metadata_dir'));

        $this->logger      = $logger;
        $this->artExporter = $artExporter;
        $this->dic         = $dic;

        $this
            ->option('-c|--clear', T_('Clear the database image when the local file exists'), 'boolval', false)
            ->usage('<bold>  export:databaseArt</end> ## ' . T_('Export all database art to local_metadata_dir') . '<eol/>');
    }

    public function execute(): void
    {
        if ($this->app() === null) {
            return;
        }

        $interactor = $this->io();
        $interactor->info(
            T_('Start Art Dump'),
            true
        );
        $clearData = $this->values()['clear'] === true;

        try {
            $this->artExporter->export(
                $interactor,
                $this->dic->get(MetadataWriter::class),
                $clearData
            );
        } catch (ArtExportException $error) {
            $this->logger->error(
                $error->getMessage(),
                [LegacyLogger::CONTEXT_TYPE => self::class]
            );

            $interactor->error(
                $error->getMessage(),
                true
            );
        }

        $interactor->info(
            T_('Art Dump Complete'),
            true
        );
    }
}
