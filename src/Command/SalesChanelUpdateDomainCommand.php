<?php declare(strict_types=1);

namespace SwagPaas\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SalesChanelUpdateDomainCommand extends Command
{

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelDomainRepository;

    public function __construct(EntityRepositoryInterface $salesChannelDomainRepository)
    {
        parent::__construct();
        $this->salesChannelDomainRepository = $salesChannelDomainRepository;
    }

    protected function configure()
    {
        $this->setName('sales-channel:update-domain');
        $this->addOption('only-storefront', null, InputOption::VALUE_OPTIONAL, 'Only update sales channel with type storefront', true);
        $this->addArgument('domain', InputArgument::REQUIRED, 'URL of the new sales channel');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!filter_var($input->getArgument('domain'), FILTER_VALIDATE_DOMAIN)) {
            $output->writeln('<error>Invalid domain</error>');
            return 1;
        }

        $host = parse_url($input->getArgument('domain'), PHP_URL_HOST);
        
        $criteria = new Criteria();
        if ($input->getOption('only-storefront')) {
            $criteria->addFilter(new EqualsFilter('salesChannel.type.id', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        }

        $context = Context::createDefaultContext();
        $domains = $this->salesChannelDomainRepository->search($criteria, $context);

        $payload = [];
        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            $newDomain = $this->replaceDomain($domain->getUrl(), $host);
            if (!filter_var($newDomain, FILTER_VALIDATE_URL)) {
                $output->writeln(sprintf('<error>Unable to generate new domain. %s is invalid.</error>', $newDomain));
                return 1;
            }

            $payload[] = [
                'id' => $domain->getId(),
                'url' => $this->replaceDomain($domain->getUrl(), $host),
            ];
        }

        $this->salesChannelDomainRepository->update($payload, $context);
    }

    private function replaceDomain(string $url, string $newDomain): string
    {
        $components = parse_url($url);
        $components['host'] = $newDomain;

        return $this->build_url($components);

    }

    private function build_url(array $parts): string {
        return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '') .
            ((isset($parts['user']) || isset($parts['host'])) ? '//' : '') .
            (isset($parts['user']) ? "{$parts['user']}" : '') .
            (isset($parts['pass']) ? ":{$parts['pass']}" : '') .
            (isset($parts['user']) ? '@' : '') .
            (isset($parts['host']) ? "{$parts['host']}" : '') .
            (isset($parts['port']) ? ":{$parts['port']}" : '') .
            (isset($parts['path']) ? "{$parts['path']}" : '') .
            (isset($parts['query']) ? "?{$parts['query']}" : '') .
            (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
    }

}