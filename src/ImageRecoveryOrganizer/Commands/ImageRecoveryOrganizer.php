<?php

namespace ImageRecoveryOrganizer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Image Recovery Organizer Application
 * 
 * @See Symfony/Console doc from:
 *   http://symfony.com/doc/master/components/console/introduction.html
 */
class ImageRecoveryOrganizer extends Command
{
    /**
     * The console command data.
     *
     * @var string
     */
    protected $name = 'iro';
    protected $description = 'Image Recovery Organizer';
    
    
    protected $destination_path = 'iro';

    
    protected $search_started = false;
    

    /** @var OutputInterface */
    protected $output = null;

    /** @var ProgressBar */
    protected $progress = null;
    
    /**
     * Cache of destination directories created for saving images files
     *
     * @var string[]
     */
    protected $cache_dst_path = [];


    /**
     * Configure Symfony Console Command
     */
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
        $this->output = $output;
        
        $this->validateParameters($input);
        $this->configureProgressBar();

        // step #1: find all jpg files
        $this->progress->start();
        $jpg_files = $this->searchJpgFiles($this->path, false);
        $this->progress->finish();
        $this->output->writeln(' --> Done.');
        $this->output->writeln(" Found " . count($jpg_files) . " JPG files");
        
        // step #2: analyze and process jpgs
        $this->output->writeln(' Analysing files ');
        $this->configureProgressBar(count($jpg_files));
        $this->progress->start();
        $this->processJpgFiles($jpg_files, $this->path);
        $this->progress->finish();
        $this->output->writeln(' --> Done.');

        $output->writeln("\n --\n"
                ." Files are saved on {$this->destination_path}\n"
                . " --Done--");
    }
    
    private function processJpgFiles($files, $path)
    {
        foreach ($files as $fullname) {
            if ($path) {
                $fullname = preg_replace("#^.#", $path, $fullname, 1);
            }

            $exif_data = exif_read_data($fullname);
            if (! $exif_data) {
                $this->progress->advance();
                continue;
            }
            
            $dst_path = $this->getStorageDirectory($exif_data);
            $this->saveFile($fullname, $dst_path);
            $this->progress->advance();
        }
    }
    
    /**
     * Save image
     * 
     * @param string $target
     * @param string $dst_path
     */
    private function saveFile($target, $dst_path)
    {
        $dst_file = $dst_path . DS . basename($target);
        copy($target, $dst_file);
    }


    /**
     * 
     * @param array[] $exif_data
     */
    private function getStorageDirectory($exif_data)
    {
        if (isset($exif_data['FileDateTime'])) {
            $filetime = date("Y", $exif_data['FileDateTime']) . DS
                      . date("m", $exif_data['FileDateTime']) . DS
                      . date("d", $exif_data['FileDateTime']);
        } else {
            $filetime = 'unknown';
        }
        
        $dst_path = $this->destination_path . DS . $filetime;
        if (! isset($this->cache_dst_path[$filetime])) {
            if (file_exists($dst_path)) {
                if (! is_writable($dst_path)) {
                    throw new \Exception("Cannot write on destination "
                            . "directory: {$dst_path}");
                }
            } else {
                if (! mkdir($dst_path, 0755, true)) {
                    throw new \Exception("Cannot create destination "
                            . "directory: {$dst_path}");
                }
            }
            $this->cache_dst_path[$filetime] = true;
        }
        
        return $dst_path;
    }

    /**
     * 
     * @param int $steps
     */
    private function configureProgressBar($steps = 0)
    {
        $progress = new ProgressBar($this->output, $steps);
        $progress->setFormat('%current:10s% [%bar%]  %elapsed:8s% %memory:6s%');
        $freq = ($steps) ? sqrt($steps) : 100;
        $progress->setRedrawFrequency($freq);
        
        $this->progress = $progress;
    }
    
    /**
     * 
     * @param string $path
     * @param boolean $fullpath
     * 
     * @return string[]
     */
    private function searchJpgFiles($path, $fullpath = false)
    {
        // $countdown = 10;
        $files = [];
        
        $flags =  \FilesystemIterator::KEY_AS_PATHNAME
                | \FilesystemIterator::CURRENT_AS_FILEINFO
                | \FilesystemIterator::SKIP_DOTS
                | \FilesystemIterator::UNIX_PATHS;
        
        $display_path = ($fullpath)
                        ? $path
                        : preg_replace("#^{$this->path}#", '.', $path, 1);

        $iterator = new \RecursiveDirectoryIterator($path, $flags);
        foreach ($iterator as $entry) {
            // process only directories and regular files
            if ($entry->isDir()) {
                $dirname = $path . DS . $entry->getFilename();
                $subfiles = $this->searchJpgFiles($dirname, $fullpath);
                $files = array_merge($files, $subfiles);
            } elseif ($entry->isFile()) {
                $filename = $this->filterEntry($entry);
                if ($filename) {
                    $files[] = sprintf("%s%s%s", $display_path, DS, $filename);
                }
            }
            
            $this->progress->advance();
            
            // if (! $countdown) {
            //    break;
            // }
            // $countdown--;
        }
        
        return $files;
    }
    
    /**
     * 
     * @param \SplFileInfo $entry
     */
    private function filterEntry(\SplFileInfo $entry)
    {
        $filename = $entry->getFilename();
        $is_jpg = strrpos(strtolower($filename), '.jpg', -4) !== false;
        
        return ($is_jpg) ? $filename : null;
    }
    
    /**
     * Validate source and destination paths.
     * 
     * @param InputInterface $input
     * 
     * @throws \Exception
     */
    private function validateParameters(InputInterface $input)
    {
        $path = $input->getArgument('recover_path');
        if (! is_dir($path) || ! is_readable($path)) {
            throw new \Exception("recover_path must be a readable directory");
        }
        
        $destination_path = $input->getArgument('destination_path');
        // generate default destination path
        if (empty($destination_path)) {
            $destination_path = getcwd() . DS . 'saved' . DS;
        } else {
            $destination_path = rtrim($destination_path, DS) . DS . 'iro' . DS;
        }
        $destination_path .= date('Ymd');
        
        // Destination Path not exists -> try to create it
        if (! file_exists($destination_path)) {
            if (mkdir($destination_path, 0750, true) === false) {
                $msg = sprintf("Unable create destination_path directory: %s",
                                $destination_path);
                throw new \Exception($msg);
            }
        } elseif (! is_dir($path) || ! is_writable($destination_path)) {
            throw new \Exception("destination_path must be a writable directory");
        }
        
        $this->path = rtrim($path, DS) ;
        $this->destination_path = rtrim($destination_path, DS);
    }

    /**
     * Return text for help
     * 
     * @return type
     */
    protected function help()
    {
        return <<< END
Image Recovery Organizer (a.k.a. {$this->name})
---
Tools that search, organize and save images recovered with Photorec.

END;
   }
    
}
