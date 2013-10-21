<?php
namespace Francodacosta\SupervisordBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Parser;

class SupervisordConfigCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('supervisord:setup');
        $this->setDescription('creates supervisord config files based on supervisord.yml files');
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'the name of the file to use', 'supervisord.conf');
        $this->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'the name of the configuration file to search for', 'supervisord.yml');
    }

    private function getTokensToReplace($content)
    {
        $matches = array();
        preg_match_all('/\%.*?\%/', $content, $matches);

        return $matches[0];
    }

    private function replaceParameters($content)
    {
        $tokens = $this->getTokensToReplace($content);
        foreach ($tokens as $token) {
            $tokenParam = trim($token, '%');
            $content = str_replace($token, $this->getContainer()->getParameter($tokenParam), $content);
        }

        return $content;
    }

    /**
     * gets the amqp.yml files as an array
     *
     * @return array
     */
    private function getConfig($name)
    {

        $finder = new Finder();
        $files = $finder
            ->files()
            ->in($this->getContainer()->getParameter('kernel.root_dir').'/../src/')
            ->name($name)
            ->files();

        $yml = new Parser;
        $config = array();
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            $contents = $this->replaceParameters($contents);
            $config = array_merge_recursive($config, $yml->parse($contents));
        }

        return $config;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $supervisorFile = $input->getOption('output');
        $configFile = $input->getOption('input');

        $output->writeln('gathering configuration');
        $configArray = $this->getConfig($configFile);

        $configLoader = $this->getContainer()->get('francodacosta.supervisord.loader.array');
        $configLoader->setSource($configArray);
        $confurationGenerator = $configLoader->load();

        file_put_contents($supervisorFile, $confurationGenerator->generate());
        $output->writeln("\n" . $supervisorFile . ' created');
    }
}
