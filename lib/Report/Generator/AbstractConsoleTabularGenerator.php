<?php

/*
 * This file is part of the PHP Bench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Report\Generator;

use PhpBench\Benchmark\SuiteDocument;
use PhpBench\Console\OutputAwareInterface;
use PhpBench\Report\GeneratorInterface;
use PhpBench\Tabular\Dom\TableDom;
use PhpBench\Tabular\Tabular;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

require_once __DIR__ . '/tabular/xpath_functions.php';

abstract class AbstractConsoleTabularGenerator implements GeneratorInterface, OutputAwareInterface
{
    protected $tabular;
    protected $output;

    public function __construct(Tabular $tabular)
    {
        $this->tabular = $tabular;
    }

    /**
     * {@inheritdoc}
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
        $this->configureFormatters($output->getFormatter());
    }

    /**
     * Render the table.
     *
     * @param mixed $tableDom
     * @param mixed $config
     */
    private function render(TableDom $tableDom, $config)
    {
        $rows = array();
        $row = null;
        foreach ($tableDom->xpath()->query('//row') as $rowEl) {
            $row = array();
            foreach ($tableDom->xpath()->query('.//cell', $rowEl) as $cellEl) {
                $colName = $cellEl->getAttribute('name');

                // exclude cells
                if (isset($config['exclude']) && in_array($colName, $config['exclude'])) {
                    continue;
                }

                $row[$colName] = $cellEl->nodeValue;
            }

            $rows[] = $row;
        }

        $table = $this->createTable();
        $table->setHeaders(array_keys($row ?: array()));
        $table->setRows($rows);
        $this->renderTable($table);
        $this->output->writeln('');
    }

    /**
     * Create the table class. For Symfony 2.4 support.
     *
     * @return object
     */
    protected function createTable()
    {
        if (class_exists('Symfony\Component\Console\Helper\Table')) {
            return new Table($this->output);
        }

        return new \Symfony\Component\Console\Helper\TableHelper();
    }

    protected function doGenerate($definition, SuiteDocument $document, array $config, array $parameters = array())
    {
        if ($config['debug']) {
            $this->output->writeln('<info>Suite XML</info>');
            $this->output->writeln($document->saveXML());
        }

        $tableDom = $this->tabular->tabulate($document, $definition, $parameters);

        if ($config['debug']) {
            $tableDom->formatOutput = true;
            $this->output->writeln('<info>Table XML</info>');
            $this->output->writeln($tableDom->saveXML());
        }

        if ($config['title']) {
            $this->output->writeln(sprintf('<title>%s</title>', $config['title']));
        }

        if ($config['description']) {
            $this->output->writeln(sprintf('<description>%s</description>', $config['description']));
        }

        $this->render($tableDom, $config);
    }

    /**
     * Render the table. For Symfony 2.4 support.
     *
     * @param mixed $table
     */
    private function renderTable($table)
    {
        if (class_exists('Symfony\Component\Console\Helper\Table')) {
            $table->render();

            return;
        }
        $table->render($this->output);
    }

    /**
     * Adds some output formatters.
     *
     * @param OutputFormatterInterface
     */
    private function configureFormatters(OutputFormatterInterface $formatter)
    {
        $formatter->setStyle(
            'title', new OutputFormatterStyle('white', null, array('bold'))
        );
        $formatter->setStyle(
            'description', new OutputFormatterStyle(null, null, array())
        );
    }
}