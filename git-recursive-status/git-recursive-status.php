<?php
class GitRecursiveStatus
{
    const REPO_PART_STAGED = 'staged';
    const REPO_PART_NOT_STAGED = 'not_staged';
    const REPO_PART_UNTRACKED = 'untracked';

    protected $basePath;

    /**
     * @var Repo[]
     */
    protected $repos = array();

    protected $stagedChanges = array();
    protected $notStagedChanges = array();
    protected $untrackedFiles = array();

    protected $reposWithFiles = array();

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }

    public function run()
    {
        $this->printRepoStatus();
        $this->checkFileStatus();
        $this->printFileStatus();
    }

    /**
     * @return Repo[]
     */
    protected function getRepos()
    {
        if(!$this->repos)
        {
            $repoPaths = array();
            exec("find {$this->basePath} -type d -name .git | sed -e \"s/\.git//\"", $repoPaths);

            $repos = array();
            foreach($repoPaths as $repo)
            {
                $repos[] = new Repo($repo);
            }
            $this->repos = $repos;
        }
        return $this->repos;
    }

    private function printRepoStatus()
    {
        foreach ($this->getRepos() as $repo)
        {
            $status = $repo->getStatus();
            if (false == strpos($status, 'nothing to commit')) {
                if(!$repo->isRoot())
                {
                    echo "\033[1;33m{$repo->name}\033[0m  ({$repo->path})\n" . str_repeat('-', strlen($repo->name)) . "\n";
                }
                echo "$status\n";
            }
        }
    }

    private function checkFileStatus()
    {
        foreach($this->getRepos() as $repo)
        {
            $this->readFileStatusForRepo($repo);
        }
    }

    private function readFileStatusForRepo(Repo $repo)
    {
        $content = preg_replace("/\x1B\[([0-9]{1,2}(;[0-9]{1,2})?)?[m|K]/i", "", $repo->getStatus());
        $parts = preg_split('/^#\s*$/ims', $content);

        array_shift($parts);

        $repoGroup = null;
        $readNext = false;
        $files = array();
        foreach($parts as $part)
        {
            if(stripos($part, "Changes to be committed"))
            {
                $repoGroup = self::REPO_PART_STAGED;
                $readNext = true;
            }
            else if(stripos($part, "Changes not staged for commit"))
            {
                $repoGroup = self::REPO_PART_NOT_STAGED;
                $readNext = true;
            }
            else if(stripos($part, "Untracked files"))
            {
                $repoGroup = self::REPO_PART_UNTRACKED;
                $readNext = true;
            }
            else if($readNext)
            {
                $fileState = 'untracked';
                $matches = array();
                preg_match_all('/^#\t(([a-z ]+):)?\s*([a-z0-9-_.\/]+)/ims', trim($part), $matches);
                if(isset($matches[3]))
                {
                    foreach($matches[3] as $index => $file)
                    {
                        if(!empty($matches[2][$index]))
                        {
                            $fileState = $matches[2][$index];
                        }
                        if(!isset($files[$repoGroup][$fileState]))
                        {
                            $files[$repoGroup][$fileState] = array();
                        }
                        $files[$repoGroup][$fileState][] = $file;

//                        = array(
//                        self::FILE_STATE_STAGED => $staged,
//                        self::FILE_STATE_NOT_STAGED => $notStaged,
//                        self::FILE_STATE_UNTRACKED => $untracked
//                    );
                    }
                }
            }
            else
            {
                $readNext = false;
            }
        }

        if(!empty($files))
        {
            $this->reposWithFiles[$repo->name] = $files;
        }
    }

    private function printFileStatus()
    {
        $this->printStagedFiles();
        $this->printNotStagedFiles();
        $this->printUntrackedFiles();

        $this->lintPHPFiles();
    }

    private function getFileCount($dataSet)
    {
        $count = 0;
        foreach($dataSet as $set)
        {
            $count += count($set);
        }
        return $count;
    }

    private function printStagedFiles()
    {
        $stagedData = $this->getFilesByRepoGroup(self::REPO_PART_STAGED);
        if(!empty($stagedData))
        {
            $repos = $stagedData['repos'];
            $stagedRepoCount = count($repos);
            $stagedFileCount = 0;

            foreach($stagedData['files'] as $index => $fileStates)
            {
                foreach($fileStates as $fileState => $files)
                {
                    $stagedFileCount += count($files);
                }
            }
            $reposAsText = implode(", ", $repos);
            echo "$stagedFileCount staged files in $stagedRepoCount repos ($reposAsText)\n";
        }
    }

    private function printNotStagedFiles()
    {
        $notStagedData = $this->getFilesByRepoGroup(self::REPO_PART_NOT_STAGED);
        if(!empty($notStagedData))
        {
            $repos = $notStagedData['repos'];
            $notStagedRepoCount = count($repos);
            $notStagedFileCount = 0;

            foreach($notStagedData['files'] as $index => $fileStates)
            {
                foreach($fileStates as $fileState => $files)
                {
                    $notStagedFileCount += count($files);
                }
            }
            $reposAsText = implode(", ", $repos);
            echo "$notStagedFileCount files not staged in $notStagedRepoCount repos ($reposAsText)\n";
        }
    }

    private function printUntrackedFiles()
    {
        $untrackedData = $this->getFilesByRepoGroup(self::REPO_PART_UNTRACKED);
        if(!empty($untrackedData))
        {
            $repos = $untrackedData['repos'];
            $untrackedRepoCount = count($repos);
            $untrackedFileCount = 0;

            foreach($untrackedData['files'] as $index => $fileStates)
            {
                foreach($fileStates as $fileState => $files)
                {
                    $untrackedFileCount += count($files);
                }
            }
            $reposAsText = implode(", ", $repos);
            echo "$untrackedFileCount untracked files in $untrackedRepoCount repos ($reposAsText)\n";
        }
    }

    private function getFilesByRepoGroup($groupName)
    {
        $data = array();
        if(!empty($this->reposWithFiles))
        {
            $repos = array();
            $files = array();
            foreach($this->reposWithFiles as $repo => $repoGroups)
            {
                $repos[] = $repo;
                foreach($repoGroups as $repoGroup => $fileStates)
                {
                    if($repoGroup == $groupName)
                    {
                        $files[] = $fileStates;
                    }
                }
            }
            $data = array(
                'files' => $files,
                'repos' => $repos
            );
        }
        return $data;
    }

    private function lintPHPFiles()
    {
        $badPHPFiles = array();

        foreach($this->reposWithFiles as $repoGroups)
        {
            foreach($repoGroups as $repoGroup => $fileStates)
            {
                if($repoGroup == self::REPO_PART_NOT_STAGED || $repoGroup == self::REPO_PART_STAGED)
                {
                    foreach($fileStates as $state => $files)
                    {
                        foreach($files as $file)
                        {
                            if($this->hasPHPSyntaxError($file))
                            {
                                $badPHPFiles[] = $file;
                            }
                        }
                    }
                }
            }
        }
        if(!empty($badPHPFiles))
        {
            echo "\n\033[1;31mThe following PHP files does not pass linting:\n";
            echo "\033[0;31m".implode("\n", $badPHPFiles)."\033[0m\n";
        }
    }

    protected function hasPHPSyntaxError ($fileName)
    {
        $lint = `php -l $fileName`;
        return stripos($lint, "No syntax errors detected") === false;
    }
}

class Repo
{
    public $path;
    public $name;

    protected $status = "";

    public function __construct($path)
    {
        $this->path = $path;
        $this->determineName($path);
    }

    public function getStatus()
    {
        if(!$this->status)
        {
            $this->status = shell_exec("cd {$this->path} && script -q /dev/null git status | cat");
        }
        return $this->status;
    }

    public function isRoot()
    {
        return $this->name == "main-repo";
    }

    private function determineName($path)
    {
        if($path == './')
        {
            $this->name = "main-repo";
        }
        else
        {
            $this->name = substr($path, strrpos(substr($path, 0, -1), "/")+1, -1);
        }
    }
}

$status = new GitRecursiveStatus(".");
$status->run();

