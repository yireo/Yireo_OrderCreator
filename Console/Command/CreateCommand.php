<?php
declare(strict_types = 1);

namespace Yireo\OrderCreator\Console\Command;

use InvalidArgumentException;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Yireo\OrderCreator\Generator\Order as OrderGenerator;

class CreateCommand extends Command
{
    /**
     * @var
     */
    private $orderGenerator;

    public function __construct(
        AppState $state,
        OrderGenerator $orderGenerator,
        $name = null
    ) {
        $state->setAreaCode(Area::AREA_ADMINHTML);
        $this->orderGenerator = $orderGenerator;
        return parent::__construct($name);
    }

    /**
     * Configure this command
     */
    protected function configure()
    {
        $this->setName('yireo_ordercreator:create');
        $this->setDescription('Create a new order with specific products for a specific customer');

        $this->addOption(
            'email',
            null,
            InputOption::VALUE_REQUIRED,
            'Customer email');

        $this->addOption(
            'sku',
            null,
            InputOption::VALUE_REQUIRED,
            'List of SKUs (comma-separated)');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int|null|void
     * @throws LocalizedException
     * @throws \Exception
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Yireo\OrderCreator\Exception\NoCustomerObject
     */
    protected function execute(Input $input, Output $output)
    {
        try {
            $email = trim($input->getOption('email'));
            $sku = trim($input->getOption('sku'));
        } catch(\Error $e) {
            throw new InvalidArgumentException('Unable to initialize options');
        }

        $skus = $this->fromStringToArray($sku);
        foreach ($skus as $sku) {
            $this->orderGenerator->addProductBySku($sku);
        }

        $this->orderGenerator->setCustomerEmail($email);
        $this->orderGenerator->generate();
    }

    /**
     * @param string $string
     * @return string[]
     */
    private function fromStringToArray(string $string): array
    {
        $return = [];
        $strings = explode(',', $string);

        foreach ($strings as $string) {
            $string = trim($string);
            if (empty($string)) {
                continue;
            }

            $return[] = $string;
        }

        return $return;
    }
}