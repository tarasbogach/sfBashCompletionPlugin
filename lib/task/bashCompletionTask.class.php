<?php

class bashCompletionTask extends sfBaseTask {

  protected function configure(){

    $this->addOptions(array(
        new sfCommandOption('user', 'u', sfCommandOption::PARAMETER_NONE, 'do install for current user only?'),
        new sfCommandOption('system', 's', sfCommandOption::PARAMETER_NONE, 'do install systemwide?'),
        new sfCommandOption('force', 'f', sfCommandOption::PARAMETER_NONE, 'do force file rewriting?'),
        new sfCommandOption('cleanup', 'c', sfCommandOption::PARAMETER_NONE, 'cleanup all installed files'),
        new sfCommandOption('path', 'p', sfCommandOption::PARAMETER_REQUIRED, 'path to install bash_completion script', null),
    ));

    $this->namespace = 'bash';
    $this->name = 'completion';
    $this->briefDescription = 'Add bash completion for symfony tasks to your user/system';
    $this->detailedDescription = <<<EOF
The [bash:completion|INFO] install bash completion for symfony tasks.
Call it with:

Once after any new task is created:
  [./symfony bash:completion|INFO]

To install for current user only (~/.bashrc):
  [./symfony bash:completion --user|INFO]

To install systemwide
  [sudo ./symfony bash:completion --system|INFO]

To remove all installed files:
  [./symfony bash:completion --cleanup|INFO]
EOF;
  }

  protected function execute($arguments = array(), $options = array()) {
    $bashrcPath = $_SERVER['HOME'].'/.bashrc';
    $userCompletionPath = $options['path'] ? $options['path'] : $_SERVER['HOME'].'/.symfony_completion';
    $systemCompletionPath = $options['path'] ? $options['path'] : '/etc/bash_completion.d/symfony';
    $completionCode = '
if [ -f ~/.symfony_completion ]; then
  . ~/.symfony_completion
fi
';
    $completionFunction =
'_symfony()
{
  if [ -f data/bash_completion ] ; then
    source data/bash_completion
    _symfony_local
  fi
}
complete -F _symfony symfony';

    if($options['system']){
      if (file_exists($systemCompletionPath) && !$options['force']) {
        $this->logBlock('File "'.$systemCompletionPath.'" already exists! If you realy want to rewrite it use --force (-f) option', 'ERROR');
      } else {
        if (file_put_contents($systemCompletionPath, $completionFunction)) {
          $this->logSection('+file', $systemCompletionPath);
          $this->logSection('Attention!', 'You need to restart terminal to complete this task');
        } else {
          $this->logBlock('File "'.$systemCompletionPath.'" is not writable! Try to run this task with sudo or check --path option', 'ERROR');
        }
      }
    } elseif($options['user']){

      if (file_put_contents($userCompletionPath, $completionFunction)) {
        $this->logSection('+file', $userCompletionPath);
      } else {
        $this->logBlock('File "'.$userCompletionPath.'" is not writable! Try to run this task with sudo or check --path option', 'ERROR');
        return;
      }

      if(strstr(file_get_contents($bashrcPath), $completionCode)){
        $this->logBlock('File "'.$bashrcPath.'" has already installed completion script. Not touching.', 'INFO');
      } elseif (file_put_contents($bashrcPath, $completionCode, FILE_APPEND)) {
        $this->logSection('modified', $bashrcPath);
      } else {
        $this->logBlock('File "'.$bashrcPath.'" is not writable! Aborting.', 'ERROR');
        return;
      }

      $this->logSection('Attention!', 'You need to restart terminal to complete this task');

    } elseif($options['cleanup']){
      if(file_exists($userCompletionPath)){
        $fs = new sfFilesystem($this->dispatcher, $this->formatter);
        $fs->remove($userCompletionPath);
      } else {
        $this->logBlock('File "'.$userCompletionPath.'" does not exists.', 'INFO');
      }

      if(is_writable($bashrcPath)){
        $bashrc = file_get_contents($bashrcPath);
        if(strstr($bashrc, $completionCode)){
          file_put_contents($bashrcPath, str_replace($completionCode, '', $bashrc));
          $this->logSection('modified', $bashrcPath);
        } else {
          $this->logBlock('File "'.$bashrcPath.'" already clean.', 'INFO');
        }
      } else {
        $this->logBlock('File "'.$bashrcPath.'" is not writable or does not exist!', 'ERROR');
      }
    } else {
      $tasks = $this->commandApplication->getTasks();
      foreach ($tasks as $name => &$task){
        $opts = array();
        foreach($task->getOptions() as $option){
          $opts[] = '--'.$option->getName().($option->isParameterRequired()?'=':'');
          if($option->getShortcut()){
            $opts[] = '-'.$option->getShortcut();
          }
        }
        $opts = implode(' ',$opts);
        $task = "\t\t\"$name\")\n\t\t\tCOMPREPLY=($(compgen -W \"$opts\" -- \$cur)) ;;";
      }
      $names = implode(' ',  array_keys($tasks));
      $opts = implode("\n", $tasks);
      $script =
'_symfony_local()
{
  local cur prev words cword
  _get_comp_words_by_ref -n : cur prev words cword

  if [[ cword -eq 1 ]] ; then
    COMPREPLY=($(compgen -W "%s" -- $cur))
    __ltrim_colon_completions "$cur"
    return 0
  fi
  if [[ ${cur:0:1} != "-" ]] ; then
    COMPREPLY=($(compgen -A file -- ${words[cword]}))
    return 0
  fi
  COMPREPLY=($(compgen -A file -- ${words[cword]}))
  case ${words[1]} in
%s
  esac
}
';
      $script = sprintf($script, $names, $opts);
      $path = $options['path'] ? $options['path'] : sfConfig::get('sf_data_dir').'/bash_completion';
      file_put_contents($path, $script);
      chown($path, get_current_user());
      $this->logSection('+file', $path);
    }
  }

}
