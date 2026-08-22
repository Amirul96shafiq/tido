<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LabelBoundaryRelabelService;
use Illuminate\Console\Command;

class RelabelFurnitureBoundaryCommand extends Command
{
    protected $signature = 'labels:relabel-furniture-boundary';

    protected $description = 'Sync label descriptions and relabel consumables misclassified as Furniture & Home Appliances';

    public function handle(LabelBoundaryRelabelService $relabelService): int
    {
        $result = $relabelService->run();

        $this->info("Label descriptions updated: {$result['descriptions_updated']}");
        $this->info("Furniture → Groceries: {$result['furniture_to_groceries']}");
        $this->info("Groceries → Furniture: {$result['groceries_to_furniture']}");

        return self::SUCCESS;
    }
}
