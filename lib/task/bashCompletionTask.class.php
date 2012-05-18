<?php

class bashCompletionTask extends sfBaseTask {

	protected function configure() {

		$this->addOptions(array(
				new sfCommandOption('system', 's', sfCommandOption::PARAMETER_NONE, 'do install systemwide?'),
				new sfCommandOption('force', 'f', sfCommandOption::PARAMETER_NONE, 'do force file rewriting?'),
				new sfCommandOption('path', 'p', sfCommandOption::PARAMETER_REQUIRED, 'path to install bash_completion script', ''),
		));

		$this->namespace = 'bash';
		$this->name = 'completion';
		$this->briefDescription = 'Add bash completion for symfony tasks to your system';
		$this->detailedDescription = <<<EOF
The [bash:completion|INFO] install bash completion for symfony tasks.
Call it with:

  [sudo ./symfony bash:completion --system|INFO] once after plugin installation
  [sudo ./symfony bash:completion|INFO] once after plugin installation and once after any new task is created
EOF;
	}

	protected function execute($arguments = array(), $options = array()) {
		if($options['system']){
			$path = $options['path'] ? $options['path'] : '/etc/bash_completion.d/symfony';
			if (file_exists($path) && !$options['force']) {
				$this->logBlock('File "'.$options['path'].'" is already exist! If you realy whant to rewrite it use --force (-f) option', 'ERROR');
			} else {
				$script =
<<<'BASH'
_symfony()
{
	if [ -f data/bash_completion ] ; then
		source data/bash_completion
		COMPREPLY=( $(_sumfony_local) )
	fi
}
complete -F _symfony symfony
BASH;
				if (file_put_contents($path,$script)) {
					$this->logSection('+file', $path);
					$this->logSection('Attention!', 'You need to restart terminal to complete this task');
				} else {
					$this->logBlock("File $path is not writable! Try to run this task with sudo or check --path option", 'ERROR');
				}
			}
		}else{
			$tasks = $this->commandApplication->getTasks();
			foreach ($tasks as $name=>&$task){
				$opts = array();
				foreach($task->getOptions() as $option){
					$opts[] = '--'.$option->getName().($option->isParameterRequired()?'=':'');
					if($option->getShortcut()){
						$opts[] = '-'.$option->getShortcut();
					}
				}
				$opts = implode(' ',$opts);
				$name = strtr($name,array(':'=>'\:'));
				$task = "\t\t\"$name\")\n\t\t\tcompgen -W \"$opts\" -- \$cur ;;";
			}
			$names = strtr(implode(' ',  array_keys($tasks)),array(':'=>'\\\\\:'));
			$opts = implode("\n", $tasks);
			$script = 
<<<'BASH'
_sumfony_local()
{
	local cur
	cur=${COMP_WORDS[COMP_CWORD]}
	cur=${cur//:/\\:}
	if [[ COMP_CWORD -eq 1 ]] ; then
		compgen -W "%s" -- $cur
		return 0
	fi
	if [[ ${cur:0:1} != "-" ]] ; then
		compgen -A file -- ${COMP_WORDS[COMP_CWORD]}
		return 0
	fi
	case ${COMP_WORDS[1]} in
%s
	esac
}
BASH;
			$script = sprintf($script,$names,$opts);
			$path = $options['path'] ? $options['path'] : sfConfig::get('sf_data_dir').'/bash_completion';
			file_put_contents($path, $script);
			chown($path,get_current_user());
			$this->logSection('+file', $path);
		}
	}

}
