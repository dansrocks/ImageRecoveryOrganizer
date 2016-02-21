<?php

namespace ImageRecoveryOrganizer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Image Recovery Organizer Application
 */
class ImageRecoveryOrganizer extends Command
{
    protected $input = null;
    protected $output = null;
    
    /**
     * The console command name.
     *
     * @var string
     */
 
    protected $name = 'iro';
    protected $description = 'Image Recovery Organizer';
    
    protected $destination_path = 'iro';


    protected function configure()
    {
        $this
            ->setName($this->name)
            ->setDescription($this->description)
            ->addArgument(
                'recover_path',
                InputArgument::REQUIRED,
                'Path to directory that contains recovered images'
            )
            ->addArgument(
                'destination_path',
                InputArgument::OPTIONAL,
                'Path where images will be saved (default <iro>)'
            )
            ->setHelp($this->help());
            /*
             * 
            ->addOption(
               'yell',
               null,
               InputOption::VALUE_NONE,
               'If set, the task will yell in uppercase letters'
            )
             *
             */
        ;
    }



    /**
     * 
     * @param InputInterface $input
     * @param OutputInterface $output
     * 
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('path');
        if (! $name) {
            throw new \Exception("Path required to recovered file's folder is required");
        } else {
            $text = 'Hello';
        }

        /*
         * 
        if ($input->getOption('yell')) {
            $text = strtoupper($text);
        }
         * 
         */

        $output->writeln($text);
    }
    
    protected function help()
    {
        return <<< END
Image Recovery Organizer (a.k.a. {$this->name})
---
Tools that search, organize and save images recovered with Photorec.

END;
   }
    
}
