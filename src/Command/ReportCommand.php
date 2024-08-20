<?php

namespace App\Command;

use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:report',
    description: 'Report on consumption and costs between two given dates.',
)]
class ReportCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%env(float:BUSINESS_PORTION_OF_FLOOR_AREA)%')] private readonly float $businessAreaRatio = 1.0
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('start', InputArgument::REQUIRED, 'The starting date of the reporting period')
            ->addArgument('end', InputArgument::REQUIRED, 'The end date of the reporting period')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $start = new CarbonImmutable($input->getArgument('start'));
        $end = new CarbonImmutable($input->getArgument('end'));

        $data = $this->connection->executeQuery("
            SELECT
                date_trunc('month', consumption.timestamp)::date AS month,
                accessory.name,
                ROUND(SUM(consumption_wh / 1000 * rate.rate_per_kwh * accessory.business_use)::numeric, 2) AS cost,
                ROUND(SUM(consumption_wh / 1000 * rate.rate_per_kwh)) AS consumption,
                rate.rate_per_kwh,
                rate.name AS rate_name
            FROM consumption
                JOIN rate ON consumption.rate_id = rate.id
                JOIN accessory ON consumption.accessory_id = accessory.id
            WHERE consumption.consumption_wh > 0
              AND timestamp::date BETWEEN :start::date
                AND :end::date
            GROUP BY date_trunc('month', consumption.timestamp)::date, accessory.name, rate.id
            ORDER BY 1, accessory.name, rate.effective_since, rate.time_of_day_start NULLS LAST
        ", [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ])->fetchAllAssociative();

        $io->table(['Month', 'Accessory', 'Cost', 'Consumption', 'Rate per kWh', 'Rate Name'], $data);

        $totalConsumptionCost = round(array_sum(array_column($data, 'cost')), 2);

        $io->writeln([
            'Total consumption: '.round(array_sum(array_column($data, 'consumption')), 2),
            'Total cost for consumed electricity: '.$totalConsumptionCost,
        ]);

        $supplyCharge = $this->connection->executeQuery("
            SELECT
                SUM(rate.rate_per_day)
            FROM
                generate_series (:start::timestamp, :end::timestamp, '1 day'::interval) dates
            LEFT JOIN
                rate ON date_trunc('day', dates)::date
                    BETWEEN rate.effective_since::date
                        AND rate.effective_until::date
        ", [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ])->fetchFirstColumn()[0];

        $totalSupplyCharge = round($supplyCharge * $this->businessAreaRatio, 2);

        $io->writeln([
            'Total supply charge: '.round($supplyCharge, 2),
            'Supply charge adjusted for '.(100 * $this->businessAreaRatio).'% of business use: '.$totalSupplyCharge,
        ]);

        $io->success(sprintf('Total deductible for period between %s and %s: %s',
            $start->format('j F Y'),
            $end->format('j F Y'),
            $totalSupplyCharge + $totalConsumptionCost
        ));

        return Command::SUCCESS;
    }
}
