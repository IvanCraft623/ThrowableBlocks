<?php

declare(strict_types=1);

namespace IvanCraft623\ThrowableBlocks\entity;

use pocketmine\block\Block;
use pocketmine\block\Hopper;
use pocketmine\block\Lever;
use pocketmine\block\Skull;
use pocketmine\block\Torch;
use pocketmine\block\utils\LeverFacing;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Throwable;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\AddItemActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;

class ThrownBlock extends Throwable {

	public static function getNetworkTypeId() : string{ return EntityIds::ITEM; }

	protected Item $item;

	public function __construct(Location $location, ?Entity $thrower, Item $item, ?CompoundTag $nbt = null) {
		if($item->isNull()  or !$item->canBePlaced()) {
			throw new \InvalidArgumentException("Item is invalid");
		}
		$this->item = clone $item;
		parent::__construct($location, $thrower, $nbt);
	}

	public function saveNBT(): CompoundTag {
		$nbt = parent::saveNBT();
		$nbt->setTag("Item", $this->item->nbtSerialize());
		return $nbt;
	}

	public function getItem(): Item {
		return $this->item;
	}

	protected function sendSpawnPacket(Player $player): void {
		$player->getNetworkSession()->sendDataPacket(AddItemActorPacket::create(
			$this->getId(), //TODO: entity unique ID
			$this->getId(),
			ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($this->getItem())),
			$this->location->asVector3(),
			$this->getMotion(),
			$this->getAllNetworkData(),
			false
		));
	}

	public function getOffsetPosition(Vector3 $vector3): Vector3 {
		return $vector3->add(0, 1 / 8, 0);
	}

	public function canCollideWith(Entity $entity) : bool{
		return false;
	}

	protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult) : void{
		$this->flagForDespawn();
		$world = $this->location->getWorld();
		$block = $this->item->getBlock();
		$face = $hitResult->getHitFace();
		$facing = $face;
		$position = $blockHit->getSide($face)->getPosition();
		if ($block instanceof Hopper) {
			if ($face !== Facing::DOWN) {
				$facing = Facing::opposite($face);
			}
		}
		if ($block instanceof Lever) {
			$facing = LeverFacing::DOWN_AXIS_X();
			foreach (LeverFacing::getAll() as $faces) { // Hack >:D
				if ($faces->getFacing() === $face) {
					$facing = $faces;
					break;
				}
			}
		}
		if ($block instanceof Skull || $block instanceof Torch) {
			if ($face === Facing::DOWN) {
				$facing = Facing::opposite($face);
			}
		}
		if ($block instanceof Hopper ||
			$block instanceof Lever ||
			$block instanceof Skull ||
			$block instanceof Torch) {
			$block->setFacing($facing);
		}
		$world->setBlock($position, $block);

	}
}