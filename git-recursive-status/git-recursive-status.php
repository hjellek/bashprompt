<?php
$repos = array();
exec('find . -type d -name .git | sed -e "s/\.git//"', $repos);

$statuses = array();
foreach ($repos as $index => $repo) {
    $status = shell_exec("cd $repo && script -q /dev/null git status | cat");
    $repoName = substr($repo, strrpos(substr($repo, 0, -1), "/")+1, -1);
    $statuses[] = $status;
    if (false == strpos($status, 'nothing to commit')) {
        if($index != 0)
        {
            echo "\033[1;33m$repoName\033[0m  ($repo)\n" . str_repeat('-', strlen($repoName)) . "\n";
        }
        echo "$status\n";
    }
}
$modifiedFiles = array();
$untrackedFiles = array();
$stagedFiles = array();

foreach($statuses as $index => $status)
{
    $matches = array();
    $tmpModifiedCount = preg_match_all('/modified/', $status, $matches);
    if($tmpModifiedCount)
    {
        $modifiedFiles[$index] = $tmpModifiedCount;
    }

    $untrackedMatches = array();
    $tmp = preg_match_all('/Untracked files:(.*)/ims', $status, $untrackedMatches);

    if($tmp && isset($untrackedMatches[1]))
    {
        $content = preg_replace("/\x1B\[([0-9]{1,2}(;[0-9]{1,2})?)?[m|K]/i", "", $untrackedMatches[1][0]);

        $tmpMatches = array();
        preg_match_all('/^#\t([a-z0-9-_.\/]+)/ims', $content, $tmpMatches);
        if(isset($tmpMatches[1]))
        {
            $tmpUntrackedCount = count($tmpMatches[1]);
            if($tmpUntrackedCount)
            {
                $untrackedFiles[$index] = $tmpUntrackedCount;
            }
        }
    }
}

if(!empty($modifiedFiles))
{
    $modifiedCount = array_sum($modifiedFiles);
    $modifiedRepos = count($modifiedFiles);
    echo "$modifiedCount modified files in $modifiedRepos repos\n";
}
if(!empty($untrackedFiles))
{
    $untrackedCount = array_sum($untrackedFiles);
    $untrackedRepos = count($untrackedFiles);
    echo "$untrackedCount untracked files in $untrackedRepos repos\n";
}
