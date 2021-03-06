<?php

namespace RubtsovAV\YandexWordstatParser\Browser\ReactPhantomJs;

use Evenement\EventEmitter;
use React\ChildProcess\Process as ReactProcess;
use React\EventLoop\LoopInterface as EventLoopInterface;

class Process extends EventEmitter
{
	const SCRIPT = 'scripts/index.js';

	protected $process;

	public function __construct($phantomJs, $options = [])
	{
		$command = $this->buildCommand($phantomJs, $options);
		$workingDirectory = realpath(__DIR__ . '/scripts');
		$this->process = new ReactProcess($command, $workingDirectory);
	}

	protected function buildCommand($phantomJs, $options = [])
	{
		$cmd = escapeshellcmd($phantomJs);
		$script = escapeshellarg(__DIR__ . '/' . static::SCRIPT);
		$optionsString = $this->buildOptionsString($options);
		return "exec $cmd $optionsString $script";
	}

	protected function buildOptionsString($options = [])
	{
		$optionsString = [];
		foreach ($options as $name => $value) {
			switch (gettype($value)) {
				case 'NULL':
					$optionsString[] = escapeshellarg($name);
					break;

				case 'boolean':
					$optionsString[] = escapeshellarg($name) . '=' . ($value ? 'true' : 'false');
					break;
				
				default:
					$optionsString[] = escapeshellarg($name) . '=' . escapeshellarg($value);
					break;
			}
		}
		return implode(' ', $optionsString);
	}

	public function start(EventLoopInterface $loop)
	{
		$this->process->start($loop);

		$this->process->on('exit', function ($code) {
			$this->emit('exit', [$code]);
		});

		$this->process->stderr->on('data', function ($data) {
			$this->emit('error', [$data]);
		});

		$parser = new MessageParser();
        $parser->on('message', function(Message $message) {
        	$this->emit('message', [$message]);
        });
        $listener = [$parser, 'feed'];
		$this->process->stdout->on('data', $listener);
	}

	public function stop() 
	{
		$this->process->terminate();
		
		$message = new Message('terminate');
		$this->sendMessage($message);
	}

	public function sendMessage(Message $message)
	{
		$this->process->stdin->write($message->encode());
	}
}