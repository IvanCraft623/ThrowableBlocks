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
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerItemUseEvent;

class ThrowableBlocks extends PluginBase  implements Listener {
	use SingletonTrait;

	private string $prefix = "§7[§aThrowableBlocks§7] §r";

	private ?Config $config = null;

	private bool $throwableBlocks = true;

	/*
	 * playerId => itemId
	 * @var array<int, int>
	 */
	private array $ignorePlayers = [];

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

	public function onClickAir(PlayerItemUseEvent $event): void {
		if ($this->throwableBlocks) {
			$item = $event->getItem();
			if (isset($this->ignorePlayers[$player->getId()]) && $this->ignorePlayers[$player->getId()] === $item->getId()) {
				unset($this->ignorePlayers[$player->getId()]);
			} elseif (!$player->isSpectator() && !$item->isNull() && $item->canBePlaced()) {
				$throwItem = $item->pop();
				$location = $player->getLocation();
				$directionVector = $event->getDirectionVector();

				$projectile = new ThrownBlock(Location::fromObject($player->getEyePos(), $player->getWorld(), $location->yaw, $location->pitch), $player, $throwItem);
				$projectile->setMotion($directionVector->multiply(1.5));

				$projectileEv = new ProjectileLaunchEvent($projectile);
				$projectileEv->call();

				if ($projectileEv->isCancelled()) {
					$projectile->flagForDespawn();
				}
				$projectile->spawnToAll();
				if ($player->hasFiniteResources()) {
					$player->getInventory()->setItemInHand($item);
				}
			}
		}
	}

	/**
	 * @priority MONITOR
	 * @ignoreCancelled
	 */
	public function onBlockPlace(BlockPlaceEvent $event): void {
		$this->ignorePlayers[$event->getPlayer()->getId()] = $event->getItem()->getId();
	}
}
