<?php

declare(strict_types=1);

namespace IvanCraft623\ThrowableBlocks;

use IvanCraft623\ThrowableBlocks\command\ThrowableBlocksCommand;
use IvanCraft623\ThrowableBlocks\entity\ThrownBlock;

use pocketmine\data\SavedDataLoadingException;
use pocketmine\data\bedrock\EntityLegacyIds;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\plugin\PluginBase;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;

use pocketmine\event\Listener;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\player\PlayerItemUseEvent;

class ThrowableBlocks extends PluginBase  implements Listener {
	use SingletonTrait;

	private string $prefix = "§7[§aThrowableBlocks§7] §r";

	private ?Config $config = null;

	private bool $throwableBlocks = true;

	public function onLoad(): void {
		self::setInstance($this);
		$this->loadConfig();

	}

	public function onEnable(): void {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getCommandMap()->register('ThrowableBlocks', new ThrowableBlocksCommand($this));
		EntityFactory::getInstance()->register(ThrownBlock::class, function(World $world, CompoundTag $nbt): ThrownBlock {
			$itemTag = $nbt->getCompoundTag("Item");
			if ($itemTag === null) {
				throw new SavedDataLoadingException("Expected \"Item\" NBT tag not found");
			}

			$item = Item::nbtDeserialize($itemTag);
			if ($item->isNull() or !$item->canBePlaced()) {
				throw new SavedDataLoadingException("Item is invalid");
			}
			return new ThrownBlock(EntityDataHelper::parseLocation($nbt, $world), null, $item, $nbt);
		}, ['Thrown Block', 'ThrownBlock'], EntityLegacyIds::ITEM);
	}

	public function onDisable(): void {
		$this->config->set("enable", $this->throwableBlocks);
		$this->config->save();
	}

	public function getPrefix(): string {
		return $this->prefix;
	}

	private function loadConfig(): void {
		$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML, ["enable" => $this->throwableBlocks]);
		$this->throwableBlocks = $this->config->get("enable", $this->throwableBlocks);
	}

	public function setThrowableBlocks(bool $bool): void {
		$this->throwableBlocks = $bool;
	}

	public function throwableBlocks(): bool {
		return $this->throwableBlocks;
	}

	# Listerner

	/**
	 * @priority MONITOR
	 */
	public function onItemUse(PlayerItemUseEvent $event): void {
		$player = $event->getPlayer();
		if ($this->throwableBlocks && !$player->isSpectator()) {
			$item = clone $event->getItem();
			$item->setCount(1);
			if ($item->canBePlaced()) {

				$location = $player->getLocation();
				$directionVector = $player->getDirectionVector();

				$projectile = new ThrownBlock(Location::fromObject($player->getEyePos(), $player->getWorld(), $location->yaw, $location->pitch), $player, $item);
				$projectile->setMotion($directionVector->multiply(1.5));

				$projectileEv = new ProjectileLaunchEvent($projectile);
				$projectileEv->call();

				if ($projectileEv->isCancelled()) {
					$projectile->flagForDespawn();
				}
				$projectile->spawnToAll();
				if ($player->hasFiniteResources()) {
					$event->getItem()->pop();
				}
			}
		}
	}
}
