<?php

namespace App\Command;

use App\Entity\Accessory;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\RowCellIterator;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:import',
    description: 'Imports all new CSV files found in %kernel.project_dir%/import',
)]
class ImportCommand extends Command
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')] private readonly string $projectDir,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $file = $this->projectDir.'/import/rates.xlsx';
        if (is_readable($file)) {
            $this->importRates($file);
            rename($file, $this->projectDir.'/import/imported/'.date('Y-m-d H:i:s').'-'.basename($file));
        }

        foreach (glob($this->projectDir.'/import/*.xlsx') as $file) {
            $io->title(sprintf('Importing %s', $file));

            $pbar = $io->createProgressBar();

            try {
                foreach ($this->importConsumption($file) as $tick) {
                    $pbar->advance();
                }
            } catch (\Throwable $exception) {
                $io->error($exception->getMessage());
            }

            $pbar->finish();

            $io->newLine(2);

            $io->success('Done.');

            rename($file, $this->projectDir.'/import/imported/'.date('Y-m-d H:i:s').'-'.basename($file));
        }

        $connection = $this->entityManager->getConnection();

        $connection->executeStatement('
            UPDATE consumption SET rate_id = (
                    SELECT id FROM rate
                    WHERE
                        consumption.timestamp
                            BETWEEN rate.effective_since
                                AND rate.effective_until
                        AND consumption.timestamp::time
                            BETWEEN rate.time_of_day_start
                                AND rate.time_of_day_end
            ) WHERE rate_id IS NULL
        ');

        $connection->executeStatement('
            UPDATE consumption SET rate_id = (
                    SELECT id FROM rate
                    WHERE
                        consumption.timestamp
                            BETWEEN rate.effective_since
                                AND rate.effective_until
                        AND rate.time_of_day_start IS NULL
                        AND rate.time_of_day_end IS NULL
            ) WHERE rate_id IS NULL
        ');

        return Command::SUCCESS;
    }

    /**
     * @return \Generator<true>
     */
    private function importConsumption(string $file): \Generator
    {
        $spreadsheet = IOFactory::load($file);

        if (!$worksheet = $spreadsheet->getSheetByName('Total Consumption')) {
            throw new \RuntimeException('Total Consumption worksheet doesn\'t exist.');
        }

        $accessory = $this->getAccessory($worksheet);

        $connection = $this->entityManager->getConnection();

        foreach ($worksheet->getRowIterator(startRow: 5) as $row) {
            /** @var RowCellIterator $cellIterator */
            $cellIterator = $row->getCellIterator(startColumn: 'A', endColumn: 'B');
            ['A' => $timestampCell, 'B' => $consumptionCell] = iterator_to_array($cellIterator, true);

            $timestamp = CarbonImmutable::createFromInterface(Date::excelToDateTimeObject($timestampCell->getValue()));

            $connection->executeStatement('
                INSERT INTO consumption
                    (id, accessory_id, timestamp, consumption_wh)
                VALUES
                    (:id, :accessory_id, :timestamp, :consumption_wh)
                ON CONFLICT (timestamp, accessory_id) DO NOTHING
            ',
                [
                    'id' => Uuid::v7(),
                    'accessory_id' => $accessory->getId()->toRfc4122(),
                    'timestamp' => $timestamp->format('Y-m-d H:i:s'),
                    'consumption_wh' => $consumptionCell->getValue(),
                ]
            );

            unset($cellIterator, $timestampCell, $timestampCell, $consumptionCell);

            yield true;
        }
    }

    private function importRates(string $file): void
    {
        $data = IOFactory::load($file)->getWorksheetIterator()->current()->toArray();

        $header = array_shift($data);

        $expectedHeader = [
            'name',
            'effective_since',
            'effective_until',
            'time_of_day_start',
            'time_of_day_end',
            'rate_per_kwh',
            'rate_per_day',
        ];

        if (!empty(array_diff($expectedHeader, $header))) {
            throw new \RuntimeException(sprintf('The Rates worksheet must contain all of these columns: %s. The order doesn\'t matter, but the columns must be there.', implode(', ', $expectedHeader)));
        }

        $connection = $this->entityManager->getConnection();

        foreach ($data as $row) {
            if (empty(array_filter($row))) {
                continue;
            }

            $row = array_combine($header, $row);

            $row['effective_since'] = (new CarbonImmutable($row['effective_since']))->setTime(0, 0, 0)->format('Y-m-d H:i:s');
            $row['effective_until'] = (new CarbonImmutable($row['effective_until']))->setTime(23, 59, 59)->format('Y-m-d H:i:s');

            $connection->executeStatement('
                INSERT INTO rate
                    (id, name, effective_since, effective_until, time_of_day_start, time_of_day_end, rate_per_kwh, rate_per_day)
                VALUES
                    (:id, :name, :effective_since, :effective_until, :time_of_day_start, :time_of_day_end, :rate_per_kwh, :rate_per_day) 
                ON CONFLICT (name, effective_since, effective_until) DO NOTHING
            ',
                [
                    'id' => Uuid::v7(),
                    ...$row,
                ]
            );
        }
    }

    private function getAccessory(Worksheet $worksheet): Accessory
    {
        $accessoryName = $worksheet->getCell('A1')->getValue();
        if (!$accessoryName || !preg_match('/Accessory: (.*)/', $accessoryName, $matches)) {
            throw new \RuntimeException('Accessory name can\'t be extracted from cell A2.');
        }

        $accessoryName = $matches[1];

        if (!$accessory = $this->entityManager->getRepository(Accessory::class)->findOneBy(['name' => $accessoryName])) {
            $accessory = new Accessory();
            $accessory->name = $accessoryName;
            $this->entityManager->persist($accessory);
            $this->entityManager->flush();
        }

        return $accessory;
    }
}
