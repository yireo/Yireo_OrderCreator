<?php
declare(strict_types=1);

namespace Yireo\OrderCreator\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * Installs DB schema for a module
     *
     * @param SchemaSetupInterface $installation
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(SchemaSetupInterface $installation, ModuleContextInterface $context)
    {
        $connection = $installation->getConnection();
    }
}