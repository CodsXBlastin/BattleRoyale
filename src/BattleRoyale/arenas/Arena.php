<?php 
# I accidently compressed it. #
namespace BattleRoyale\arenas; 
use BattleRoyale\Main; 
use BattleRoyale\arenas\ArenaScheduler;
use pocketmine\Player; 
use pocketmine\event\Listener; 
use pocketmine\event\player\PlayerDeathEvent; 
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent; 
use pocketmine\event\player\PlayerKickEvent; 
use pocketmine\event\player\PlayerMoveEvent; 
use pocketmine\event\player\PlayerRespawnEvent; 
use pocketmine\event\player\PlayerQuitEvent; 
use pocketmine\event\block\BlockBreakEvent; 
use pocketmine\event\block\BlockPlaceEvent; 
use pocketmine\event\entity\EntityDamageEvent; 
use pocketmine\event\entity\EntityDamageByEntityEvent; 
use pocketmine\event\entity\ProjectileHitEvent; 
use pocketmine\event\server\DataPacketReceiveEvent; 
use pocketmine\network\mcpe\protocol\PlayerActionPacket; 
use pocketmine\block\Block; 
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance; 
use pocketmine\entity\Entity; 
use pocketmine\entity\projectile\Egg; 
use pocketmine\item\Item; 
use pocketmine\item\Arrow; 
use pocketmine\nbt\tag\CompoundTag; 
use pocketmine\nbt\tag\ListTag; 
use pocketmine\nbt\tag\ByteTag; 
use pocketmine\nbt\tag\DoubleTag; 
use pocketmine\nbt\tag\FloatTag; 
use pocketmine\level\Explosion; 
use pocketmine\level\Level; 
use pocketmine\level\Position; 
use pocketmine\level\sound\AnvilFallSound; 
use pocketmine\level\sound\BlazeShootSound; 
use pocketmine\math\Vector3; 
use pocketmine\utils\TextFormat; 
use onebone\economyapi\EconomyAPI; 
class Arena implements Listener { 
private $id; 
public $plugin; 
public $data; 
public $lobbyp = []; 
public $ingamep = [];
public $spec = []; 
public $magneticFieldX = 170; 
public $magneticFieldZ = 170; 
public $game = 0; 
public $arenaReset = 0; 
public $winners = []; 
public $deads = []; 
public $setup = false; 
public function __construct($id, Main $plugin) { 
$this->id = $id; 
$this->plugin = $plugin; 
$this->data = $plugin->arenas[$id]; 
$this->checkWorlds(); 
if(strtolower($this->data['arena']['time'] !== "true")){ 
$this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world'])->setTime(str_replace(['day', 'night'], [6000, 18000], $this->data['arena']['time'])); 
$this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world'])->stopTime(); } } public function enableScheduler(){ 
$this->plugin->getScheduler()->scheduleRepeatingTask(new ArenaScheduler($this), 20); 
} 
public function tapJoinSign(PlayerInteractEvent $e) { 
$b = $e->getBlock();
$p = $e->getPlayer(); if($p->hasPermission("bg.sign") || $p->isOp()){ 
if($b->x == $this->data["signs"]["join_sign_x"] && $b->y == $this->data["signs"]["join_sign_y"] && $b->z == $this->data["signs"]["join_sign_z"] && $b->level == $this->plugin->getServer()->getLevelByName($this->data["signs"]["join_sign_world"])){ 
if($this->getPlayerMode($p) === 0 || $this->getPlayerMode($p) === 1 || $this->getPlayerMode($p) === 2){ 
return; 
} 
$this->joinToArena($p); 
}
if($b->x == $this->data["signs"]["return_sign_x"] && $b->y == $this->data["signs"]["return_sign_y"] && $b->z == $this->data["signs"]["return_sign_z"] && $b->level == $this->plugin->getServer()->getLevelByName($this->data["arena"]["arena_world"])){
if($this->getPlayerMode($p) === 0 || $this->getPlayerMode($p) === 2){ 
$this->leaveArena($p); 
} 
} 
return; 
} 

} 
public function getPlayerMode($p) { 
if(isset($this->lobbyp[strtolower($p->getName())])){ 
return 0; 
} 
if(isset($this->ingamep[strtolower($p->getName())])){
return 1;
} 
if(isset($this->spec[strtolower($p->getName())])){ 
return 2; 
} 
return false; 
} 
public function messageArenaPlayers($msg) { 
$ingame = array_merge($this->lobbyp, $this->ingamep, $this->spec); 
foreach($ingame as $p){ 
$p->sendMessage($this->plugin->getPrefix().$msg); 
} 
}
public function joinToArena(Player $p) { 
if($this->setup === true){ 
$p->sendMessage($this->plugin->getPrefix().$this->plugin->getMsg('arena_in_setup')); 
return; 
} 
if(count($this->lobbyp) >= $this->getMaxPlayers()){ 
$p->sendMessage($this->plugin->getPrefix().$this->plugin->getMsg('game_full')); 
return;
}
if($this->game === 1){
return; 
}
if(!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['arena_world'])){
$this->plugin->getServer()->generateLevel($this->data['arena']['arena_world']); 
}
if(!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['arena_world'])){ 
$this->plugin->getServer()->loadLevel($this->data['arena']['arena_world']); 
} 
$this->saveInv($p); $p->teleport(new Position($this->data['arena']['lobby_position_x'], $this->data['arena']['lobby_position_y'], $this->data['arena']['lobby_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['lobby_position_world']))); 
$p->sendMessage($this->plugin->getPrefix().$this->plugin->getMsg('join'));
$this->lobbyp[strtolower($p->getName())] = $p; 
$p->addTitle(TextFormat::GREEN . "BattleRoyale", "", 20, 40, 20); 
$p->removeAllEffects(); 
$vars = ['%1'];
$replace = [$p->getName()]; 
$this->messageArenaPlayers(str_replace($vars, $replace, $this->plugin->getMsg('join_others')));
return;
$p->sendMessage($this->plugin->getPrefix().$this->plugin->getMsg('has_not_permission'));  
} 
public function leaveArena(Player $p) { 
if($this->getPlayerMode($p) == 0){ 
unset($this->lobbyp[strtolower($p->getName())]); 
$p->teleport(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world']))); 
$p->setSpawn(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world']))); 
} 
if($this->getPlayerMode($p) == 1){ 
unset($this->ingamep[strtolower($p->getName())]);
$p->teleport(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world']))); 
$p->setSpawn(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world']))); 
$this->messageArenaPlayers(str_replace("%1", $p->getName(), $this->plugin->getMsg('leave_others'))); $this->checkAlive(); 
}
if($this->getPlayerMode($p) == 2){
unset($this->spec[strtolower($p->getName())]); 
$p->teleport(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world']))); 
$p->setSpawn(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world']))); 
} 
if(isset($this->plugin->players[strtolower($p->getName())]['arena'])){ 
unset($this->plugin->players[strtolower($p->getName())]['arena']); 
}
if(!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['leave_position_world'])){ $this->plugin->getServer()->generateLevel($this->data['arena']['leave_position_world']); 
} 
if(!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['leave_position_world'])){ 
$this->plugin->getServer()->loadLevel($this->data['arena']['leave_position_world']); 
} 
$p->sendMessage($this->plugin->getPrefix().$this->plugin->getMsg('leave')); 
$this->loadInv($p); $p->setGamemode(0); $p->removeAllEffects(); 
} 
public function startGame(){ 
$this->game = 1; 
foreach($this->lobbyp as $p){ 
$p->setGamemode(0); 
$level = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']); $level->setBlockIdAt($this->data['arena']['join_position_x'], $this->data['arena']['join_position_y'] - 1, $this->data['arena']['join_position_z'], 57); 
$level->setBlockIdAt($this->data['arena']['join_position_x'] + 1, $this->data['arena']['join_position_y'] - 1, $this->data['arena']['join_position_z'], 57); 
$level->setBlockIdAt($this->data['arena']['join_position_x'] - 1, $this->data['arena']['join_position_y'] - 1, $this->data['arena']['join_position_z'], 57); 
$level->setBlockIdAt($this->data['arena']['join_position_x'], $this->data['arena']['join_position_y'] - 1, $this->data['arena']['join_position_z'] + 1, 57); 
$level->setBlockIdAt($this->data['arena']['join_position_x'], $this->data['arena']['join_position_y'] - 1, $this->data['arena']['join_position_z'] - 1, 57); 
$p->teleport(new Position($this->data['arena']['join_position_x'] + 0.5, $this->data['arena']['join_position_y'], $this->data['arena']['join_position_z'] + 0.5, $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']))); 
$p->setSpawn(new Position($this->data['arena']['join_position_x'] + 0.5, $this->data['arena']['join_position_y'], $this->data['arena']['join_position_z'] + 0.5, $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']))); 
$p->setImmobile(true);
} 
$this->messageArenaPlayers($this->plugin->getMsg('start_game')); 
} 
public function setIngame() { 
foreach($this->lobbyp as $p){ 
unset($this->lobbyp[strtolower($p->getName())]); 
$this->ingamep[strtolower($p->getName())] = $p; 
$p->getInventory()->clearAll(); 
$p->setImmobile(false); 
$p->getArmorInventory()->setChestplate(Item::get(444, 0, 1)); 
$axe = Item::get(271, 0, 1); 
$p->getInventory()->addItem($axe); 
} 
} 
public function removeSpawnBlocks() { 
$level = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']); 
$level->setBlockIdAt($this->data['arena']['join_position_x'], $this->data['arena']['join_position_y'] - 1, $this->data['arena']['join_position_z'], 0); 
$level->setBlockIdAt($this->data['arena']['join_position_x'] + 1, $this->data['arena']['join_position_y'] - 1, $this->data['arena']['join_position_z'], 0); 
$level->setBlockIdAt($this->data['arena']['join_position_x'] - 1, $this->data['arena']['join_position_y'] - 1, $this->data['arena']['join_position_z'], 0); 
$level->setBlockIdAt($this->data['arena']['join_position_x'], $this->data['arena']['join_position_y'] - 1, $this->data['arena']['join_position_z'] + 1, 0); 
$level->setBlockIdAt($this->data['arena']['join_position_x'], $this->data['arena']['join_position_y'] - 1, $this->data['arena']['join_position_z'] - 1, 0);
} 
public function onPacketReceive(DataPacketReceiveEvent $e) { 
$pk = $e->getPacket(); 
$p = $e->getPlayer();
if($this->getPlayerMode($p) === 1){
if($pk instanceof PlayerActionPacket){ 
switch ($pk->action) { 
case PlayerActionPacket::ACTION_STOP_GLIDE : 
$p->getArmorInventory()->setChestplate(Item::get(0, 0, 1)); 
return true; 
} 
}
} 
} 
public function checkAlive() {
if(count($this->ingamep) <= 1){ 
if(count($this->ingamep) === 1){ 
foreach($this->ingamep as $p){
$p->addTitle("You won on this game!", "", 20, 40, 20); 
foreach($this->plugin->getServer()->getOnlinePlayers() as $player) { 
$winnerName = $p->getName(); 
$player->sendMessage(TextFormat::BOLD . TextFormat::AQUA . $winnerName . TextFormat::GREEN . " has won the game in " . TextFormat::YELLOW . "BattleRoyale!"); 
} 
} 
$this->stopGame();
} 
} 
}
public function stopGame() {
$this->arenaReset = 1; 
} 
public function reloadmap() {
if($this->plugin->getServer()->isLevelLoaded($this->data['arena']['arena_world'])){
$this->plugin->getServer()->unloadLevel($this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world'])); 	
} 
$zip = new \ZipArchive; 
$zip->open($this->plugin->getDataFolder() . 'arenasmap/' . $this->data['arena']['arena_world'] . '.zip'); 
$zip->extractTo($this->plugin->getServer()->getDataPath() . 'worlds'); 
$zip->close();
unset($zip); 
$this->plugin->getServer()->loadLevel($this->data['arena']['arena_world']);
return true;
}
public function unsetAllPlayers() { 
foreach($this->ingamep as $p){ 
$p->removeAllEffects(); 
$p->getInventory()->clearAll(); 
$p->getArmorInventory()->clearAll(); 
$this->loadInv($p);
unset($this->ingamep[strtolower($p->getName())]); 
$p->teleport(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world']))); 
$p->setSpawn(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world']))); 
$p->setHealth(20); 
$p->setFood(20);
} 
foreach($this->lobbyp as $p){
$p->removeAllEffects(); 
$p->getInventory()->clearAll();
$p->getArmorInventory()->clearAll(); 
$this->loadInv($p); 
unset($this->lobbyp[strtolower($p->getName())]);
$p->teleport(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world'])));
$p->setSpawn(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world'])));
} 
foreach($this->spec as $p){ 
$p->removeAllEffects();
$p->setAllowFlight(false);
$p->getInventory()->clearAll(); 
$p->getArmorInventory()->clearAll();
$this->loadInv($p); 
unset($this->spec[strtolower($p->getName())]); 
$p->teleport(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world'])));
$p->setSpawn(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world']))); 
$p->setGamemode(0); 
$p->setHealth(20); 
$p->setFood(20); 
} 
} 
public function onHand(PlayerItemHeldEvent $e) { 
$p = $e->getPlayer(); 
$item = $e->getItem(); 
if($p instanceof Player){
if($this->getPlayerMode($p) === 1){
if($item->getId() == 293 && $item->getCustomName() == TextFormat:: AQUA . "AWM"){ 
$p->getArmorInventory()->setHelmet(Item::get(86, 0, 1)); 
} else { 
$p->getArmorInventory()->setHelmet(Item::get(0, 0, 1)); 
} 
} 
if($this->getPlayerMode($p) === 2){ 
if($item->getId() == 355 && $item->getCustomName() == TextFormat:: AQUA . "Exit the game!"){
$this->leaveArena($p); 
} 
if($item->getId() == 345 && $item->getCustomName() == TextFormat:: AQUA . "Teleporter"){ 
$this->openTeleporter($p);
} 
} 
} 
}
public function openTeleporter($p) {
$api = $this->plugin->getServer()->getPluginManager()->getPlugin("FormAPI");
$form = $api->createSimpleForm(function (Player $p, $data = null){ 
$result = $data; 
if($result === null){ 
return true; 
} 
$c = 0; 
foreach($this->ingamep as $ingame){
if($result == $c){ 
$target = $ingame->getPlayer();
if($target instanceof Player){ 
$p->teleport($target);
}
} 
$c++; 
} 
}); 
$form->setTitle(TextFormat::BOLD . "Teleporter"); 
$form->setContent(""); 
foreach($this->ingamep as $ingame){ 
$form->addButton($ingame->getName());
} 
$form->sendToPlayer($p); 
} 
public function onShoot(PlayerInteractEvent $e){ 
$item = $e->getItem();
$block = $e->getBlock();
$p = $e->getPlayer(); 
if($p instanceof Player){ 
if($this->getPlayerMode($p) === 1){
if($item->getId() == 332){ 
$e->setCancelled(true); 
} 
if($item->getId() == 284 && $item->getCustomName() == TextFormat:: AQUA . "AK47"){ 
$e->setCancelled(); 
$item = Item::get(332, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "AK47 Ammo"); 
if($p->getInventory()->contains($itemName)){ 
$nbt = new CompoundTag("", [ "Pos" => new ListTag("Pos", [ new DoubleTag("", $p->x), new DoubleTag("", $p->y + $p->getEyeHeight()), new DoubleTag("", $p->z) ]), "Motion" => new ListTag("Motion", [ new DoubleTag("", -sin($p->yaw / 180 * M_PI) * cos($p->pitch / 180 * M_PI)), new DoubleTag("", -sin($p->pitch / 180 * M_PI)), new DoubleTag("", cos($p->yaw / 180 * M_PI) * cos($p->pitch / 180 * M_PI)) ]), "Rotation" => new ListTag("Rotation", [ new FloatTag("", $p->yaw), new FloatTag("", $p->pitch) ]), ]); 
$f = 8; 
$snowball = Entity::createEntity("Arrow", $p->getLevel(), $nbt, $p); $snowball->setMotion($snowball->getMotion()->multiply($f)); 
$snowball->getLevel()->addSound(new BlazeShootSound(new Vector3($p->x, $p->y, $p->z, $p->getLevel()))); 
$item = Item::get(332, 0, 1); $itemName = $item->setCustomName(TextFormat::AQUA . "AK47 Ammo"); 
$p->getInventory()->removeItem($itemName); 
}else{
$item = Item::get(284, 0, 1);
$itemName = $item->setCustomName(TextFormat::AQUA . "AK47"); 
$p->getInventory()->removeItem($itemName); 
} 
} 
if($item->getId() == 256 && $item->getCustomName() == TextFormat:: AQUA . "Shotgun"){ 
$e->setCancelled(); 
$item = Item::get(332, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "Shotgun Ammo"); 
if($p->getInventory()->contains($itemName)){
$nbt = new CompoundTag("", [ "Pos" => new ListTag("Pos", [ new DoubleTag("", $p->x), new DoubleTag("", $p->y + $p->getEyeHeight()), new DoubleTag("", $p->z) ]), "Motion" => new ListTag("Motion", [ new DoubleTag("", -sin($p->yaw / 180 * M_PI) * cos($p->pitch / 180 * M_PI)), new DoubleTag("", -sin($p->pitch / 180 * M_PI)), new DoubleTag("", cos($p->yaw / 180 * M_PI) * cos($p->pitch / 180 * M_PI)) ]), "Rotation" => new ListTag("Rotation", [ new FloatTag("", $p->yaw), new FloatTag("", $p->pitch) ]), ]); 
$f = 8; 
$snowball = Entity::createEntity("Arrow", $p->getLevel(), $nbt, $p); 
$snowball->setMotion($snowball->getMotion()->multiply($f)); 
$snowball->getLevel()->addSound(new BlazeShootSound(new Vector3($p->x, $p->y, $p->z, $p->getLevel()))); 
$item = Item::get(332, 0, 1); $itemName = $item->setCustomName(TextFormat::AQUA . "Shotgun Ammo"); 
$p->getInventory()->removeItem($itemName); 
}else{ 
$item = Item::get(256, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "Shotgun");
$p->getInventory()->removeItem($itemName);
} 
} 
if($item->getId() == 293 && $item->getCustomName() == TextFormat:: AQUA . "AWM"){ 
$e->setCancelled();
$item = Item::get(332, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "AWM Ammo");
if($p->getInventory()->contains($itemName)){
$nbt = new CompoundTag("", [ "Pos" => new ListTag("Pos", [ new DoubleTag("", $p->x), new DoubleTag("", $p->y + $p->getEyeHeight()), new DoubleTag("", $p->z) ]), "Motion" => new ListTag("Motion", [ new DoubleTag("", -sin($p->yaw / 180 * M_PI) * cos($p->pitch / 180 * M_PI)), new DoubleTag("", -sin($p->pitch / 180 * M_PI)), new DoubleTag("", cos($p->yaw / 180 * M_PI) * cos($p->pitch / 180 * M_PI)) ]), "Rotation" => new ListTag("Rotation", [ new FloatTag("", $p->yaw), new FloatTag("", $p->pitch) ]), ]);
$f = 8; 
$snowball = Entity::createEntity("Arrow", $p->getLevel(), $nbt, $p); 
$snowball->setMotion($snowball->getMotion()->multiply($f)); 
$snowball->getLevel()->addSound(new BlazeShootSound(new Vector3($p->x, $p->y, $p->z, $p->getLevel()))); 
$item = Item::get(332, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "AWM Ammo"); 
$p->getInventory()->removeItem($itemName); 
}else{ 
$item = Item::get(293, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "AWM"); 
$p->getInventory()->removeItem($itemName); 
} 
} 
if($item->getId() == 260 && $item->getCustomName() == TextFormat:: AQUA . "Bandage"){ 
$e->setCancelled(); 
$item = Item::get(332, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "Bandage"); 
$p->getInventory()->removeItem($itemName);
$health = $p->getHealth() + 2; $p->setHealth($health);
} 
} 
} 
}
public function onUsePotionKits(PlayerInteractEvent $e) { 
$item = $e->getItem(); 
$p = $e->getPlayer(); 
if($p instanceof Player){ 
if($this->getPlayerMode($p) === 1){
if($item->getId() == 260 && $item->getCustomName() == TextFormat:: AQUA . "Bandage"){
$e->setCancelled(); $item = Item::get(260, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "Bandage"); 
$p->getInventory()->removeItem($itemName);
$health = $p->getHealth() + 2; $p->setHealth($health); 
} 
if($item->getId() == 322 && $item->getCustomName() == TextFormat:: AQUA . "Health Kit"){
$e->setCancelled(); 
$item = Item::get(322, 0, 1);
$itemName = $item->setCustomName(TextFormat::AQUA . "Health Kit");
$p->getInventory()->removeItem($itemName);
$p->setHealth(20); 
}
if($item->getId() == 32 && $item->getCustomName() == TextFormat:: AQUA . "Camouflage"){ 
$e->setCancelled(); 
$item = Item::get(32, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "Camouflage"); 
$p->getInventory()->removeItem($itemName); 
$effect = new EffectInstance(Effect::getEffect(14), 20 * 10, 1); 
$p->addEffect($effect);
}
}
} 
} 
public function treasureChest(PlayerInteractEvent $e) { 
$block = $e->getBlock();
$p = $e->getPlayer(); 
if($p instanceof Player){ 
if($this->getPlayerMode($p) === 1){
if($block->getId() == 54){ 
$e->setCancelled(true); 
$level = $p->getLevel(); 
$level->setBlockIdAt($block->x, $block->y, $block->z, 0); 
$level->addSound(new AnvilFallSound(new Vector3($p->x, $p->y, $p->z, $p->getLevel()))); 
$this->dropGuns($p, $block, $level); 
$this->dropTrapTools($p, $block, $level);
$this->dropPotionKits($p, $block, $level);
$this->dropExplosives($p, $block, $level); 
} 
} 
} 
}
public function dropGuns($p, $block, $level) {
switch(mt_rand(1, 4)){ 
case 1: 
$item = Item::get(284, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "AK47"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName); 
$item = Item::get(332, 0, 30); $itemName = $item->setCustomName(TextFormat::AQUA . "AK47 Ammo"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName);
break; 
case 2: 
$item = Item::get(256, 0, 1);
$itemName = $item->setCustomName(TextFormat::AQUA . "Shotgun"); $level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName); $item = Item::get(332, 0, 7); 
$itemName = $item->setCustomName(TextFormat::AQUA . "Shotgun Ammo"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName); 
break; 
case 3:
$item = Item::get(293, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "AWM"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName);
$item = Item::get(332, 0, 5); 
$itemName = $item->setCustomName(TextFormat::AQUA . "AWM Ammo"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName);
break; 
case 4: 
$item = Item::get(261, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "Crossbow"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName);
$item = Item::get(262, 0, 3); 
$itemName = $item->setCustomName(TextFormat::AQUA . "Crossbow Arrow"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName); 
break; 
} 
} 
public function dropTrapTools($p, $block, $level) {
switch(mt_rand(1, 4)){ 
case 1: 
$item = Item::get(41, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "Launchpad"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName); 
break; 
case 2:
$item = Item::get(444, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "Glider"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName); 
break; 
} 
} 
public function dropPotionKits($p, $block, $level) {
switch(mt_rand(1, 7)){ 
case 1: 
$item = Item::get(260, 0, 3);
$itemName = $item->setCustomName(TextFormat::AQUA . "Bandage");
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName); 
break; 
case 2: 
$item = Item::get(322, 0, 1);
$itemName = $item->setCustomName(TextFormat::AQUA . "Health Kit"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName); 
break; 
case 3: 
$item = Item::get(32, 0, 1);
$itemName = $item->setCustomName(TextFormat::AQUA . "Camouflage"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName); 
break; 
case 4: 
$item = Item::get(373, 5, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "Slurp"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName);
break; 
} 
} 
public function dropExplosives($p, $block, $level) { 
switch(mt_rand(1, 3)){ 
case 1: 
$item = Item::get(344, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "Grenade"); 
$level->dropItem(new Vector3($block->x, $block->y, $block->z), $itemName); 
break; 
} 
}
public function onBuildBlocks(PlayerInteractEvent $e) { 
$p = $e->getPlayer(); 
$item = $e->getItem(); 
if($p instanceof Player){ 
if($this->getPlayerMode($p) === 1){ 
if($item->getId() == 5 && $item->getCustomName() == TextFormat:: AQUA . "[ Wall Builder ]"){ 
$e->setCancelled(); 
$item = Item::get(5, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "[ Wall Builder ]"); 
$p->getInventory()->removeItem($itemName); 
$level = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']); $yaw = $p->yaw;
if($yaw >= 225 && $yaw <= 315){
$x = $p->x + 3; 
$level->setBlockIdAt($x, $p->y, $p->z, 5); 
$level->setBlockIdAt($x, $p->y, $p->z + 1, 5); 
$level->setBlockIdAt($x, $p->y, $p->z - 1, 5);
$level->setBlockIdAt($x, $p->y, $p->z + 2, 5); 
$level->setBlockIdAt($x, $p->y, $p->z - 2, 5); 
$level->setBlockIdAt($x, $p->y + 1, $p->z, 5); 
$level->setBlockIdAt($x, $p->y + 1, $p->z + 1, 5); 
$level->setBlockIdAt($x, $p->y + 1, $p->z - 1, 5); 
$level->setBlockIdAt($x, $p->y + 1, $p->z + 2, 5); 
$level->setBlockIdAt($x, $p->y + 1, $p->z - 2, 5);
$level->setBlockIdAt($x, $p->y + 2, $p->z, 5); 
$level->setBlockIdAt($x, $p->y + 2, $p->z + 1, 5); 
$level->setBlockIdAt($x, $p->y + 2, $p->z - 1, 5); 
$level->setBlockIdAt($x, $p->y + 2, $p->z + 2, 5); 
$level->setBlockIdAt($x, $p->y + 2, $p->z - 2, 5);
} 
if($yaw >= 46 && $yaw <= 135){ 
$x = $p->x - 3; $level->setBlockIdAt($x, $p->y, $p->z, 5); 
$level->setBlockIdAt($x, $p->y, $p->z + 1, 5); 
$level->setBlockIdAt($x, $p->y, $p->z - 1, 5); 
$level->setBlockIdAt($x, $p->y, $p->z + 2, 5);
$level->setBlockIdAt($x, $p->y, $p->z - 2, 5); 
$level->setBlockIdAt($x, $p->y + 1, $p->z, 5); 
$level->setBlockIdAt($x, $p->y + 1, $p->z + 1, 5);
$level->setBlockIdAt($x, $p->y + 1, $p->z - 1, 5); 
$level->setBlockIdAt($x, $p->y + 1, $p->z + 2, 5); 
$level->setBlockIdAt($x, $p->y + 1, $p->z - 2, 5); 
$level->setBlockIdAt($x, $p->y + 2, $p->z, 5); 
$level->setBlockIdAt($x, $p->y + 2, $p->z + 1, 5); 
$level->setBlockIdAt($x, $p->y + 2, $p->z - 1, 5); 
$level->setBlockIdAt($x, $p->y + 2, $p->z + 2, 5); 
$level->setBlockIdAt($x, $p->y + 2, $p->z - 2, 5);
} 
if(($yaw >= 0 && $yaw <= 45) || ($yaw >= 316 && $yaw <= 360)){
$z = $p->z + 3; 
$level->setBlockIdAt($p->x, $p->y, $z, 5); 
$level->setBlockIdAt($p->x + 1, $p->y, $z, 5); 
$level->setBlockIdAt($p->x - 1, $p->y, $z, 5); 
$level->setBlockIdAt($p->x + 2, $p->y, $z, 5); 
$level->setBlockIdAt($p->x - 2, $p->y, $z, 5); 
$level->setBlockIdAt($p->x, $p->y + 1, $z, 5); 
$level->setBlockIdAt($p->x + 1, $p->y + 1, $z, 5); 
$level->setBlockIdAt($p->x - 1, $p->y + 1, $z, 5);
$level->setBlockIdAt($p->x + 2, $p->y + 1, $z, 5); 
$level->setBlockIdAt($p->x - 2, $p->y + 1, $z, 5); 
$level->setBlockIdAt($p->x, $p->y + 2, $z, 5); 
$level->setBlockIdAt($p->x + 1, $p->y + 2, $z, 5); 
$level->setBlockIdAt($p->x - 1, $p->y + 2, $z, 5); 
$level->setBlockIdAt($p->x + 2, $p->y + 2, $z, 5); 
$level->setBlockIdAt($p->x - 2, $p->y + 2, $z, 5); 
} 
if($yaw >= 136 && $yaw <= 225){ 
$z = $p->z - 3; 
$level->setBlockIdAt($p->x, $p->y, $z, 5); 
$level->setBlockIdAt($p->x + 1, $p->y, $z, 5); 
$level->setBlockIdAt($p->x - 1, $p->y, $z, 5); 
$level->setBlockIdAt($p->x + 2, $p->y, $z, 5); 
$level->setBlockIdAt($p->x - 2, $p->y, $z, 5); 
$level->setBlockIdAt($p->x, $p->y + 1, $z, 5); 
$level->setBlockIdAt($p->x + 1, $p->y + 1, $z, 5); 
$level->setBlockIdAt($p->x - 1, $p->y + 1, $z, 5); 
$level->setBlockIdAt($p->x + 2, $p->y + 1, $z, 5); 
$level->setBlockIdAt($p->x - 2, $p->y + 1, $z, 5); 
$level->setBlockIdAt($p->x, $p->y + 2, $z, 5);
$level->setBlockIdAt($p->x + 1, $p->y + 2, $z, 5); 
$level->setBlockIdAt($p->x - 1, $p->y + 2, $z, 5);
$level->setBlockIdAt($p->x + 2, $p->y + 2, $z, 5); 
$level->setBlockIdAt($p->x - 2, $p->y + 2, $z, 5); 
} 
} 
if($item->getId() == 53 && $item->getCustomName() == TextFormat:: AQUA . "[ Stairs Builder ]"){
$e->setCancelled();
$item = Item::get(53, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "[ Stairs Builder ]"); 
$p->getInventory()->removeItem($itemName);
$level = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']); 
$yaw = $p->yaw; if($yaw >= 225 && $yaw <= 315){ 
$x = $p->x + 1;
$level->setBlockIdAt($x, $p->y, $p->z, 53);
$level->setBlockIdAt($x, $p->y, $p->z + 1, 53); 
$level->setBlockIdAt($x, $p->y, $p->z - 1, 53); 
$level->setBlockIdAt($x + 1, $p->y + 1, $p->z, 53); 
$level->setBlockIdAt($x + 1, $p->y + 1, $p->z + 1, 53);
$level->setBlockIdAt($x + 1, $p->y + 1, $p->z - 1, 53);
$level->setBlockIdAt($x + 2, $p->y + 2, $p->z, 53); 
$level->setBlockIdAt($x + 2, $p->y + 2, $p->z + 1, 53); 
$level->setBlockIdAt($x + 2, $p->y + 2, $p->z - 1, 53); 
} 
if($yaw >= 46 && $yaw <= 135){
$x = $p->x - 1; 
$level->setBlockIdAt($x, $p->y, $p->z, 53); 
$level->setBlockIdAt($x, $p->y, $p->z + 1, 53); 
$level->setBlockIdAt($x, $p->y, $p->z - 1, 53); 
$level->setBlockDataAt($x, $p->y, $p->z, 1); 
$level->setBlockDataAt($x, $p->y, $p->z + 1, 1); 
$level->setBlockDataAt($x, $p->y, $p->z - 1, 1); 
$level->setBlockIdAt($x - 1, $p->y + 1, $p->z, 53);
$level->setBlockIdAt($x - 1, $p->y + 1, $p->z + 1, 53); 
$level->setBlockIdAt($x - 1, $p->y + 1, $p->z - 1, 53); 
$level->setBlockDataAt($x - 1, $p->y + 1, $p->z, 1); 
$level->setBlockDataAt($x - 1, $p->y + 1, $p->z + 1, 1);
$level->setBlockDataAt($x - 1, $p->y + 1, $p->z - 1, 1); 
$level->setBlockIdAt($x - 2, $p->y + 2, $p->z, 53);
$level->setBlockIdAt($x - 2, $p->y + 2, $p->z + 1, 53); 
$level->setBlockIdAt($x - 2, $p->y + 2, $p->z - 1, 53); 
$level->setBlockDataAt($x - 2, $p->y + 2, $p->z, 1);
$level->setBlockDataAt($x - 2, $p->y + 2, $p->z + 1, 1); 
$level->setBlockDataAt($x - 2, $p->y + 2, $p->z - 1, 1); 
} 
if(($yaw >= 0 && $yaw <= 45) || ($yaw >= 316 && $yaw <= 360)){
$z = $p->z + 1; 
$level->setBlockIdAt($p->x, $p->y, $z, 53); 
$level->setBlockIdAt($p->x + 1, $p->y, $z, 53); 
$level->setBlockIdAt($p->x - 1, $p->y, $z, 53);
$level->setBlockDataAt($p->x, $p->y, $z, 2);
$level->setBlockDataAt($p->x + 1, $p->y, $z, 2); 
$level->setBlockDataAt($p->x - 1, $p->y, $z, 2); 
$level->setBlockIdAt($p->x, $p->y + 1, $z + 1, 53); 
$level->setBlockIdAt($p->x + 1, $p->y + 1, $z + 1, 53); 
$level->setBlockIdAt($p->x - 1, $p->y + 1, $z + 1, 53); 
$level->setBlockDataAt($p->x, $p->y + 1, $z + 1, 2); 
$level->setBlockDataAt($p->x + 1, $p->y + 1, $z + 1, 2); 
$level->setBlockDataAt($p->x - 1, $p->y + 1, $z + 1, 2); 
$level->setBlockIdAt($p->x, $p->y + 2, $z + 2, 53); 
$level->setBlockIdAt($p->x + 1, $p->y + 2, $z + 2, 53);
$level->setBlockIdAt($p->x - 1, $p->y + 2, $z + 2, 53); 
$level->setBlockDataAt($p->x, $p->y + 2, $z + 2, 2); 
$level->setBlockDataAt($p->x + 1, $p->y + 2, $z + 2, 2); 
$level->setBlockDataAt($p->x - 1, $p->y + 2, $z + 2, 2); 
} 
if($yaw >= 136 && $yaw <= 225){ 
$z = $p->z - 1; $level->setBlockIdAt($p->x, $p->y, $z, 53); 
$level->setBlockIdAt($p->x + 1, $p->y, $z, 53); 
$level->setBlockIdAt($p->x - 1, $p->y, $z, 53); 
$level->setBlockDataAt($p->x, $p->y, $z, 3); 
$level->setBlockDataAt($p->x + 1, $p->y, $z, 3); 
$level->setBlockDataAt($p->x - 1, $p->y, $z, 3);
$level->setBlockIdAt($p->x, $p->y + 1, $z - 1, 53); 
$level->setBlockIdAt($p->x + 1, $p->y + 1, $z - 1, 53);
$level->setBlockIdAt($p->x - 1, $p->y + 1, $z - 1, 53);
$level->setBlockDataAt($p->x, $p->y + 1, $z - 1, 3); 
$level->setBlockDataAt($p->x + 1, $p->y + 1, $z - 1, 3); 
$level->setBlockDataAt($p->x - 1, $p->y + 1, $z - 1, 3); 
$level->setBlockIdAt($p->x, $p->y + 2, $z - 2, 53); 
$level->setBlockIdAt($p->x + 1, $p->y + 2, $z - 2, 53); 
$level->setBlockIdAt($p->x - 1, $p->y + 2, $z - 2, 53); 
$level->setBlockDataAt($p->x, $p->y + 2, $z - 2, 3);
$level->setBlockDataAt($p->x + 1, $p->y + 2, $z - 2, 3);
$level->setBlockDataAt($p->x - 1, $p->y + 2, $z - 2, 3);
} 
}
}
}
}
public function onRespawn(PlayerRespawnEvent $e) { 
$p = $e->getPlayer(); 
if($this->getPlayerMode($p) === 0){
return true; 
}
if($this->getPlayerMode($p) === 2){ 
$p->setGamemode(3); 
$tp = Item::get(345, 0, 1); 
$tpName = $tp->setCustomName(TextFormat::AQUA . "Teleporter"); 
$p->getInventory()->setItem(4, $tpName); 
$compass = Item::get(355, 0, 1); 
$compassName = $compass->setCustomName(TextFormat::AQUA . "Exit the Game"); 
$p->getInventory()->setItem(8, $compassName); 
return true; 
} 
}
public function onHit(EntityDamageEvent $e) { 
if($e->getEntity() instanceof Player){ 
if($e instanceof EntityDamageByEntityEvent){ 
$p1 = $e->getDamager(); 
$p2 = $e->getEntity();
if($this->getPlayerMode($p2) === 1 && $e->getCause() === EntityDamageEvent::CAUSE_PROJECTILE){ 
$item = $p1->getInventory()->getItemInHand(); 
if($item->getId() == 284 && $item->getCustomName() == TextFormat:: AQUA . "AK47"){ 
//$e->setDamage(5); 
} 
if($item->getId() == 256 && $item->getCustomName() == TextFormat:: AQUA . "Shotgun"){ 
//$e->setDamage(7); 
} 
if($item->getId() == 293 && $item->getCustomName() == TextFormat:: AQUA . "AWM"){ 
//$e->setDamage(10); 
} 
}
if($this->getPlayerMode($p1) === 0 && $this->getPlayerMode($p2) === 0){ 
$e->setCancelled(true); 
} 
if($this->getPlayerMode($p1) === 1 && $this->getPlayerMode($p2) === 2){ 
$e->setCancelled(true); 
} 
if($this->getPlayerMode($p1) === 2 && $this->getPlayerMode($p2) === 1){ 
$e->setCancelled(true); 
} 
if($this->getPlayerMode($p1) === 2 && $this->getPlayerMode($p2) === 2){ 
$e->setCancelled(true); 
} 
} 
} 
} 
public function onDeath(PlayerDeathEvent $e) { 
$p = $e->getEntity(); 
if($p instanceof Player){ 
if($this->getPlayerMode($p) === 0 || $this->getPlayerMode($p) === 2){ 
$e->setDeathMessage(""); 
$e->setDrops([]); } 
if($this->getPlayerMode($p) === 1){ 
$e->setDeathMessage(""); 
if(count($this->ingamep) == 2){
$e->setDrops([]); 
} 
unset($this->ingamep[strtolower($p->getName())]); 
$this->spec[strtolower($p->getName())] = $p; 
$ingame = array_merge($this->lobbyp, $this->ingamep, $this->spec); 
foreach($ingame as $pl){ 
$pl->sendMessage($this->plugin->getPrefix().str_replace(['%2', '%1'], [count($this->ingamep), $p->getName()], $this->plugin->getMsg('death'))); 
} 
$this->checkAlive(); 
} 
} 
} 
public function onMove(PlayerMoveEvent $e) { 
$p = $e->getPlayer(); 
if($this->getPlayerMode($p) === 1 ){ 
$block = $p->getLevel()->getBlock($p->floor()->subtract(0, 1));
if ($block->getId() == 41){ 
$p->setMotion(new Vector3($p->getMotion()->x, 2, $p->getMotion()->z)); 
} 
if($p->getX() > $this->data['arena']['join_position_x'] + $this->magneticFieldX){ 
$cactus = new EntityDamageByEntityEvent($p, $p, EntityDamageEvent::CAUSE_CONTACT, 1); 
$p->attack($cactus); 
} 
if($p->getX() < $this->data['arena']['join_position_x'] - $this->magneticFieldX){ 
$cactus = new EntityDamageByEntityEvent($p, $p, EntityDamageEvent::CAUSE_CONTACT, 1);
$p->attack($cactus); } if($p->getZ() > $this->data['arena']['join_position_z'] + $this->magneticFieldZ){
$cactus = new EntityDamageByEntityEvent($p, $p, EntityDamageEvent::CAUSE_CONTACT, 1); 
$p->attack($cactus); 
} 
if($p->getZ() < $this->data['arena']['join_position_z'] - $this->magneticFieldZ){ 
$cactus = new EntityDamageByEntityEvent($p, $p, EntityDamageEvent::CAUSE_CONTACT, 1); 
$p->attack($cactus); 
} 
} 
} 
public function onBlockBreak(BlockBreakEvent $e) {
$p = $e->getPlayer(); 
$block = $e->getBlock(); 
if($this->getPlayerMode($p) === 1 ){
$e->setCancelled(true); 
if($block->getId() == 17){ 
$level = $p->getLevel(); 
$level->setBlockIdAt($block->x, $block->y, $block->z, 0);
$item = Item::get(5, 0, 1); $itemName = $item->setCustomName(TextFormat::AQUA . "[ Wall Builder ]"); 
$p->getInventory()->addItem($itemName); $item = Item::get(53, 0, 1); 
$itemName = $item->setCustomName(TextFormat::AQUA . "[ Stairs Builder ]"); 
$p->getInventory()->addItem($itemName);
} 
} 
} 
public function onBlockPlace(BlockPlaceEvent $e) { 
$p = $e->getPlayer(); 
$block = $e->getBlock();
if($this->getPlayerMode($p) === 1){ 
if($block->getId() == 41){
} else { 
$e->setCancelled(true); 
}
} 
} 
public function onQuit(PlayerQuitEvent $e) { 
if($this->getPlayerMode($e->getPlayer()) !== false){ 
$this->leaveArena($e->getPlayer()); 
} 
} 
public function onKick(PlayerKickEvent $e) {
if($this->getPlayerMode($e->getPlayer()) !== false){ 
$this->leaveArena($e->getPlayer()); 
} 
} 
public function saveInv(Player $p) { 
$p->getInventory()->clearAll(); 
} 
public function loadInv(Player $p) { 
if(!$p->isOnline()){ 
return;
} 
$p->getInventory()->clearAll(); 
} 
public function getMaxPlayers() { 
return $this->data['arena']['max_players']; 
} 
public function getMinPlayers() { 
return $this->data['arena']['min_players']; 
} 
public function kickPlayer($p, $reason = "") {
$players = array_merge($this->ingamep, $this->lobbyp, $this->spec, $this->zombie); 
$players[strtolower($p)]->sendMessage(str_replace("%1", $reason, $this->plugin->getMsg('kick_from_game')));
$this->leaveArena($players[strtolower($p)]);
} 
public function getStatus() { 
if($this->game === 0) return TextFormat::BLUE . "[ Join ]"; 
if($this->game === 1) return TextFormat::RED . "[ Running ]"; 
} 
public function checkWorlds() { 
if(!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['arena_world'])){ 
$this->plugin->getServer()->generateLevel($this->data['arena']['arena_world']); 
} 
if(!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['arena_world'])){ 
$this->plugin->getServer()->loadLevel($this->data['arena']['arena_world']); 
} 
if(!$this->plugin->getServer()->isLevelGenerated($this->data['signs']['join_sign_world'])){ 
$this->plugin->getServer()->generateLevel($this->data['signs']['join_sign_world']); 
} 
if(!$this->plugin->getServer()->isLevelLoaded($this->data['signs']['join_sign_world'])){ 
$this->plugin->getServer()->loadLevel($this->data['signs']['join_sign_world']); 
} 
if(!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['leave_position_world'])){ 
$this->plugin->getServer()->generateLevel($this->data['arena']['leave_position_world']); 
} 
if(!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['leave_position_world'])){ 
$this->plugin->getServer()->loadLevel($this->data['arena']['leave_position_world']); 
} 
} 
}