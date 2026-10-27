<?php

namespace DockerHostManager;

use Docker\API\Model\ContainerInspectResponse;
use DockerHostManager\Docker\Docker;
use DockerHostManager\Docker\Event;
use Symfony\Component\Console\Output\OutputInterface;

class Synchronizer
{
    const START_TAG = '## docker-hostmanager-start';
    const END_TAG = '## docker-hostmanager-end';

    /** @var Docker  */
    private $docker;
    /** @var string */
    private $hostsFile;
    /** @var string */
    private $tld;

    /** @var ContainerInspectResponse[] */
    private $activeContainers = [];
    /** @var OutputInterface */
    private $output = false;
    private $oldHostsLines = [];

    /**
     * @param Docker $docker
     * @param string $hostsFile
     * @param string $tld
     */
    public function __construct(Docker $docker, $hostsFile, $tld)
    {
        $this->docker = $docker;
        $this->hostsFile = $hostsFile;
        $this->tld = $tld;
    }

    public function run()
    {
        if (!is_writable($this->hostsFile)) {
            throw new \RuntimeException(sprintf('File "%s" is not writable.', $this->hostsFile));
        }

        $this->init();
        $this->listen();
    }

    private function init()
    {
        foreach ($this->docker->containerList() as $container) {
            $this->configureContainer($container->getId());
        }

        $this->write();
    }

    private function configureContainer(string $containerId)
    {
        try {
            $container = $this->docker->containerInspect($containerId);
        } catch (\Exception $e) {
            return;
        }

        if (null === $container) {
            return;
        }

        if ($this->isExposed($container)) {
            $this->activeContainers[$container->getId()] = $container;
        } else {
            unset($this->activeContainers[$container->getId()]);
        }
    }

    private function listen()
    {
        $this->docker->listenEvents(function (Event $event) {
            if (null === $event->getId()) {
                return;
            }

            $this->configureContainer($event->getId());

            $this->write();
        });
    }

    private function write()
    {
        $hostsLines = array_map(
            function ($container) {
                return implode("\n", $this->getHostsLines($container));
            },
            $this->activeContainers
        );
        if ($this->output !== false) {
            $removed = array_diff($this->oldHostsLines, $hostsLines);
            $added = array_diff($hostsLines,$this->oldHostsLines);
            if(count($removed)) {
                $this->output->write('removed: ');
                $this->output->writeln($removed);
            }
            if(count($added)) {
                $this->output->write('added: ');
                $this->output->writeln($added);
            }

        }
        $this->oldHostsLines = $hostsLines;

        $content = array_map('trim', file($this->hostsFile));
        $res = preg_grep('/^'.self::START_TAG.'/', $content);
        $start = count($res) ? key($res) : count($content) + 1;
        $res = preg_grep('/^'.self::END_TAG.'/', $content);
        $end = count($res) ? key($res) : count($content) + 1;
        $hosts = array_merge(
            [self::START_TAG],
            $hostsLines,
            [self::END_TAG]
        );
        array_splice($content, $start, $end - $start + 1, $hosts);
        file_put_contents($this->hostsFile, implode("\n", $content));
    }

    private function getHostsLines(ContainerInspectResponse $container): array
    {
        $lines = [];

        $bridgeIp = $this->getDefaultNetworkIp($container);
        if (null !== $bridgeIp) {
            $lines[$bridgeIp] = implode(' ', $this->getContainerHosts($container));
        }

        $networkSettings = $container->getNetworkSettings();
        $networks = $networkSettings?->getNetworks();
        if (!is_iterable($networks)) {
            $networks = [];
        }

        // Networks
        foreach ($networks as $networkName => $conf) {
            $ip = $conf->getIPAddress();
            $aliases = is_array($conf->getAliases()) ? $conf->getAliases() : [];
            $aliases[] = substr($container->getName(), 1);

            $hosts = [];
            foreach (array_unique($aliases) as $alias) {
                $hosts[] = $alias.'.'.$networkName;
            }

            $hostList = implode(' ', $this->uniqueHostnames($hosts));
            $lines[$ip] = sprintf('%s%s', isset($lines[$ip]) ? $lines[$ip].' ' : '', $hostList);
        }

        array_walk($lines, function (&$host, $ip) {
            $host = $ip.' '.$host;
        });

        return $lines;
    }

    private function getDefaultNetworkIp(ContainerInspectResponse $container): ?string
    {
        $networkSettings = $container->getNetworkSettings();
        if (null === $networkSettings) {
            return null;
        }

        $networks = $networkSettings->getNetworks();
        if (!is_iterable($networks)) {
            return null;
        }

        foreach ($networks as $networkName => $conf) {
            if ('bridge' === $networkName) {
                $ip = $conf->getIPAddress();
                if (!empty($ip)) {
                    return $ip;
                }
            }
        }

        foreach ($networks as $conf) {
            $ip = $conf->getIPAddress();
            if (!empty($ip)) {
                return $ip;
            }
        }

        return null;
    }

    private function getContainerHosts(ContainerInspectResponse $container): array
    {
        $hosts = [substr($container->getName(), 1).$this->tld];
        if (is_array($container->getConfig()->getEnv())) {
            $env = $container->getConfig()->getEnv();
            foreach (preg_grep('/DOMAIN_NAME=/', $env) as $row) {
                $row = substr($row, strlen('DOMAIN_NAME='));
                $hosts = array_merge($hosts, explode(',', $row));
            }
        }

        return $this->uniqueHostnames($hosts);
    }

    /**
     * @return string[]
     */
    private function hostnameVariants(string $hostname): array
    {
        $variants = [$hostname];
        $dashed = str_replace('_', '-', $hostname);
        if ($dashed !== $hostname) {
            $variants[] = $dashed;
        }

        return $variants;
    }

    /**
     * @param string[] $hostnames
     *
     * @return string[]
     */
    private function uniqueHostnames(array $hostnames): array
    {
        $unique = [];
        foreach ($hostnames as $hostname) {
            foreach ($this->hostnameVariants($hostname) as $variant) {
                $unique[$variant] = true;
            }
        }

        return array_keys($unique);
    }

    private function isExposed(ContainerInspectResponse $container): bool
    {
        $ports = $container->getNetworkSettings()?->getPorts();
        if (empty($ports) || empty($container->getState()?->getRunning())) {
            return false;
        }

        return true === $container->getState()->getRunning();
    }

    public function setOutputInterface(OutputInterface $output) {
        $this->output=$output;
    }
}
