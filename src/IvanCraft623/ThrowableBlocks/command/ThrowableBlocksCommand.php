<?php

declare(strict_types=1);

namespace IvanCraft623\ThrowableBlocks\command;

use IvanCraft623\ThrowableBlocks\ThrowableBlocks;

use pocketmine\Server;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;

final class ThrowableBlocksCommand extends Command implements PluginOwned {

	private ThrowableBlocks $plugin;

	public function __construct(ThrowableBlocks $plugin) {
		parent::__construct('throwableblocks', 'Enable or disable ThrowableBlocks.');
		$this->plugin = $plugin;
		$this->setPermission("throwableblocks.command");
	}

	public function getOwningPlugin(): ThrowableBlocks {
		return $this->plugin;
	}

	public function execute(CommandSender $sender, string $label, array $args) {
		if (!$this->checkPermission($sender)) {
			return;
		}
		if (!isset($args[0])) {
			$sender->sendMessage($this->plugin->getPrefix() . "§cUsage: /".$label." <on|off>");
			return;
		}
		$bool = filter_var($args[0], FILTER_VALIDATE_BOOLEAN);
		$this->plugin->setThrowableBlocks($bool);
		$sender->sendMessage($this->plugin->getPrefix() . "§bThrowable blocks mechanic: " . ($bool ? "§aon" : "§coff"));
	}

	public function checkPermission(CommandSender $sender): bool {
		if (!$this->testPermission($sender)) {
			$sender->sendMessage($this->plugin->getPrefix() . "§cYou do not have permission to use this command!");
			return false;
		}
		return true;
	}
}