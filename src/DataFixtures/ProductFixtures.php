<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Product\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class ProductFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $products = [
            ['Mechanical Keyboard', 'SKU-KEYBOARD-001', '120.00', 10],
            ['Wireless Mouse', 'SKU-MOUSE-001', '65.00', 20],
            ['27 Inch Monitor', 'SKU-MONITOR-001', '300.00', 5],
        ];

        foreach ($products as [$name, $sku, $price, $stock]) {
            $manager->persist(new Product($name, $sku, $price, $stock));
        }

        $manager->flush();
    }
}
