<?php 
namespace BattleRoyale; 
use pocketmine\plugin\PluginBase; 
use pocketmine\command\Command; 
use pocketmine\command\CommandSender; 
use pocketmine\utils\Config; 
use pocketmine\utils\TextFormat; 
use pocketmine\Player; 
use pocketmine\event\Listener; 
use pocketmine\event\player\PlayerInteractEvent; 
use pocketmine\item\Item; 
use pocketmine\event\block\BlockBreakEvent; 
use pocketmine\event\block\BlockPlaceEvent; 
use pocketmine\level\Position; 
use pocketmine\event\player\PlayerJoinEvent; 
use pocketmine\event\player\PlayerChatEvent; 
use pocketmine\event\player\PlayerQuitEvent; 
use pocketmine\event\player\PlayerKickEvent; 
use pocketmine\plugin\Plugin; 
use BattleRoyale\arenas\Arena; 
use BattleRoyale\ConfigManager;
class Main extends PluginBase implements Listener { 
public $cfg; 
public $msg; 
public $arenas = []; 
public $ins = []; 
public $selectors = []; 
public $inv = []; 
public $setters = []; 
public $economy; 
public function onEnable(): void{ 
$this->initConfig(); 
$this->registerEconomy(); 
$this->checkArenas(); 
$this->getServer()->getPluginManager()->registerEvents($this, $this); 
if(!$this->getServer()->isLevelGenerated($this->cfg->getNested('lobby.world'))){ 
$this->getServer()->generateLevel($this->cfg->getNested('lobby.world')); 
} 
$this->getLogger()->info(TextFormat::GREEN."[BattleRoyale] Enable!"); 
} 
public function onDisable(): void{ 
$this->getLogger()->info(TextFormat::RED."[BattleRoyale] Disable!"); 
}
public function setArenasData(Config $arena, $name) { 
$this->arenas[$name] = $arena->getAll(); 
$this->arenas[$name]['enable'] = true; 
$game = new Arena($name, $this); 
$game->enableScheduler(); 
$this->ins[$name] = $game; 
$this->getServer()->getPluginManager()->registerEvents($game, $this); 
}
public function initConfig() { 
if(!file_exists($this->getDataFolder())){ 
@mkdir($this->getDataFolder()); 
} 
if(!is_file($this->getDataFolder()."config.yml")){ 
$this->saveResource("config.yml"); 
} 
$this->cfg = new Config($this->getDataFolder()."config.yml", Config::YAML); 
if(!file_exists($this->getDataFolder()."arenas/")){ 
@mkdir($this->getDataFolder()."arenas/"); 
$this->saveResource("arenas/default.yml"); 
} 
if(!file_exists($this->getDataFolder()."languages/")){ 
@mkdir($this->getDataFolder()."languages/"); 
} 
if(!is_file($this->getDataFolder()."languages/English.yml")){ 
$this->saveResource("languages/English.yml"); 
} 
if(!is_file($this->getDataFolder()."languages/{$this->cfg->get('Language')}.yml")){ 
$this->msg = new Config($this->getDataFolder()."languages/English.yml", Config::YAML); 
$this->getServer()->getLogger()->info("Selected plugin language to English"); 
} else { 
$this->msg = new Config($this->getDataFolder()."languages/{$this->cfg->get('Language')}.yml", Config::YAML); 
$this->getServer()->getLogger()->info("Selected plugin language {$this->cfg->get('Language')}"); 
} 
}
public function checkArenas() { 
$this->getLogger()->info("checking arena files..."); 
foreach(glob($this->getDataFolder()."arenas/*.yml") as $file){ 
$arena = new Config($file, Config::YAML); 
if(strtolower($arena->get("enabled")) === "false"){ 
$this->arenas[basename($file, ".yml")] = $arena->getAll(); 
$this->arenas[basename($file, ".yml")]['enable'] = false; 
} else { 
if($this->checkFile($arena) === true){ 
$fname = basename($file); 
$this->setArenasData($arena, basename($file, ".yml")); 
$this->getLogger()->info("$fname - ".TextFormat::GREEN."Checking data has sucessful!"); 
} else { 
$this->arenas[basename($file, ".yml")] = $arena->getAll(); 
$this->arenas[basename($file, ".yml")]['enable'] = false; 
$fname = basename($file, ".yml"); $this->getLogger()->error("Arena \"$fname\" is not set properly"); 
} 
} 
} 
}
public function checkFile(Config $arena) { 
if(!(is_numeric($arena->getNested("signs.join_sign_x")) && is_numeric($arena->getNested("signs.join_sign_y")) && is_numeric($arena->getNested("signs.join_sign_z")) && is_string($arena->getNested("signs.join_sign_world")) && is_string($arena->getNested("signs.status_line_1")) && is_string($arena->getNested("signs.status_line_2")) && is_string($arena->getNested("signs.status_line_3")) && is_string($arena->getNested("signs.status_line_4")) && is_numeric($arena->getNested("signs.return_sign_x")) && is_numeric($arena->getNested("signs.return_sign_y")) && is_numeric($arena->getNested("signs.return_sign_z")) && is_string($arena->getNested("arena.arena_world")) && is_numeric($arena->getNested("arena.join_position_x")) && is_numeric($arena->getNested("arena.join_position_y")) && is_numeric($arena->getNested("arena.join_position_z")) && is_numeric($arena->getNested("arena.lobby_position_x")) && is_numeric($arena->getNested("arena.lobby_position_y")) && is_numeric($arena->getNested("arena.lobby_position_z")) && is_string($arena->getNested("arena.lobby_position_world")) && is_numeric($arena->getNested("arena.spec_spawn_x")) && is_numeric($arena->getNested("arena.spec_spawn_y")) && is_numeric($arena->getNested("arena.spec_spawn_z")) && is_numeric($arena->getNested("arena.leave_position_x")) && is_numeric($arena->getNested("arena.leave_position_y")) && is_numeric($arena->getNested("arena.leave_position_z")) && is_string($arena->getNested("arena.leave_position_world")) && is_numeric($arena->getNested("arena.max_game_time")) && is_numeric($arena->getNested("arena.max_players")) && is_numeric($arena->getNested("arena.min_players")) && is_numeric($arena->getNested("arena.starting_time")) && is_numeric($arena->getNested("arena.floor_y")) && is_string($arena->getNested("arena.finish_msg_levels")) && !is_string($arena->getNested("arena.money_reward")))){ 
return false; 
} 
if(!((strtolower($arena->getNested("signs.enable_status")) == "true" || strtolower($arena->getNested("signs.enable_status")) == "false") && (strtolower($arena->getNested("arena.spectator_mode")) == "true" || strtolower($arena->getNested("arena.spectator_mode")) == "false") && (strtolower($arena->getNested("arena.time")) == "true" || strtolower($arena->getNested("arena.time")) == "day" || strtolower($arena->getNested("arena.time")) == "night" || is_numeric(strtolower($arena->getNested("arena.time")))) && (strtolower($arena->get("enabled")) == "true" || strtolower($arena->get("enabled")) == "false"))){ 
return false; 
}
return true; 
}
public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool {
if(strtolower($cmd->getName()) == "br"){
if(isset($args[0])){ 
if($sender instanceof Player){ 
switch(strtolower($args[0])){ 
case "lobby": 
if(!$sender->hasPermission('br.command.lobby')){ 

break; 
} 
if($this->getPlayerArena($sender) !== false){ 
$this->getPlayerArena($sender)->leaveArena($sender); 
break; 
} 
$sender->teleport(new Position($this->cfg->getNested('lobby.x'), $this->cfg->getNested('lobby.y'), $this->cfg->getNested('lobby.z'), $this->getServer()->getLevelByName($this->cfg->getNested('lobby.world')))); 
$sender->sendMessage($this->getPrefix().$this->getMsg('send_to_main_world')); 
break; 
case "set": 
if(!$sender->hasPermission('br.command.set')){ 

break; 
} 
if(!isset($args[1]) || isset($args[2])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('set_help')); 
break; 
} 
if(!$this->arenaExist($args[1])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('arena_doesnt_exist')); 
break; 
} 
if($this->isArenaSet($args[1])){ 
$a = $this->ins[$args[1]]; 
if($a->game !== 0 || count(array_merge($a->ingamep, $a->lobbyp, $a->spec)) > 0){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('arena_running')); 
break; 
} 
$a->setup = true; 
} 
$this->setters[strtolower($sender->getName())]['arena'] = $args[1]; 
$sender->sendMessage($this->getPrefix().$this->getMsg('enable_setup_mode')); 
break; 
case "help": 
if(!$sender->hasPermission("br.command.help")){ 

break; 
} 
$msg = "---BattleRoyale Help---"; 
if($sender->hasPermission('br.command.lobby')) $msg .= $this->getMsg('lobby'); 
if($sender->hasPermission('br.command.leave')) $msg .= $this->getMsg('onleave'); 
if($sender->hasPermission('br.command.join')) $msg .= $this->getMsg('onjoin'); 
if($sender->hasPermission('br.command.start')) $msg .= $this->getMsg('start'); 
if($sender->hasPermission('br.command.stop')) $msg .= $this->getMsg('stop'); 
if($sender->hasPermission('br.command.kick')) $msg .= $this->getMsg('kick'); 
if($sender->hasPermission('br.command.set')) $msg .= $this->getMsg('set'); 
if($sender->hasPermission('br.command.delete')) $msg .= $this->getMsg('delete'); 
if($sender->hasPermission('br.command.create')) $msg .= $this->getMsg('create'); 
$sender->sendMessage($msg); 
break; 
case "create": 
if(!$sender->hasPermission('br.command.create')){ 

break; 
} 
if(!isset($args[1]) || isset($args[2])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('create_help')); 
break; 
} 
if($this->arenaExist($args[1])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('arena_already_exist')); 
break; 
} 
$a = new Config($this->getDataFolder()."arenas/$args[1].yml", Config::YAML); 
file_put_contents($this->getDataFolder()."arenas/$args[1].yml", $this->getResource('arenas/default.yml')); 
$this->arenas[$args[1]] = $a->getAll(); 
$sender->sendMessage($this->getPrefix().$this->getMsg('arena_create')); 
$name = $args[1]; 
$this->zipper($sender, $name); 
break; 
case "delete": 
if(!$sender->hasPermission('br.command.delete')){ 
$sender->sendMessage($this->getMsg ('has_not_permission')); 
break; 
} 
if(!isset($args[1]) || isset($args[2])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('delete_help')); 
break; 
} 
if(!$this->arenaExist($args[1])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('arena_doesnt_exist')); 
break; 
} 
unlink($this-getDataFolder()."arenas/$args[1].yml"); 
unset($this->arenas[$args[1]]); 
$sender->sendMessage($this->getPrefix().$this->getMsg('arena_delete')); 
break; 
case "join": 
if(!isset($args[1]) || isset($args[2])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('join_help')); 
break; 
} 
if(!$this->arenaExist($args[1])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('arena_doesnt_exist')); 
break; 
} 
if($this->arenas[$args[1]]['enable'] === false){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('arena_doesnt_exist')); 
break; 
} 
$this->ins[$args[1]]->joinToArena($sender); 
break; 
case "leave": 
if(!$sender->hasPermission('br.command.leave')){ 
$sender->sendMessage($this->getMsg ('has_not_permission')); 
break; 
} 
if(isset($args[1])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('leave_help')); 
break; 
} 
if($this->getPlayerArena($sender) === false){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('use_cmd_in_game')); 
break; 
} 
$this->getPlayerArena($sender)->leaveArena($sender); 
break; 
case "start": 
if(!$sender->hasPermission('br.command.start')){ 
$sender->sendMessage($this->plugin->getMsg('has_not_permission')); 
break; 
}
if(isset($args[2])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('start_help')); 
break; 
} 
if(isset($args[1])){ 
if(!isset($this->ins[$args[1]])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('arena_doesnt_exist')); 
break; 
} 
$this->ins[$args[1]]->startGame(); 
break; 
} 
if($this->getPlayerArena($sender) === false){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('start_help')); 
break; 
} 
$this->getPlayerArena($sender)->startGame(); 
break; 
case "stop": 
if(!$sender->hasPermission('br.command.stop')){ 
$sender->sendMessage($this->plugin->getMsg('has_not_permission')); 
break; 
} 
if(isset($args[2])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('stop_help')); 
break; 
} 
if(isset($args[1])){ 
if(!isset($this->ins[$args[1]])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('arena_doesnt_exist')); 
break; 
} 
$this->ins[$args[1]]->stopGame(); 
break; 
} 
if($this->getPlayerArena($sender) === false){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('stop_help')); 
break; 
} 
$this->getPlayerArena($sender)->stopGame(); 
break; 
case "kick": 
if(!$sender->hasPermission('br.command.kick')){ 
$sender->sendMessage($this->getMsg('has_not_permission'));
break; 
} 
if(!isset($args[2]) || isset($args[4])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('kick_help')); 
break; 
} 
if(!isset(array_merge($this->ins[$args[1]]->ingamep, $this->ins[$args[1]]->lobbyp, $this->ins[$args[1]]->spec, $this->ins[$args[1]]->zombie)[strtolower($args[2])])){ 
$sender->sendMessage($this->getPrefix().$this->getMsg('player_not_exist')); 
break; 
} 
if(!isset($args[3])){ $args[3] = ""; 
} 
$this->ins[$args[1]]->kickPlayer($args[2], $args[3]); 
break; 
case "setlobby": 
if(!$sender->hasPermission('br.command.setlobby')){

break; 
} 
if(isset($args[1])){
$sender->sendMessage($this->getPrefix().$this->getMsg('setlobby_help')); 
break; 
} 
$this->setters[strtolower($sender->getName())]['type'] = "mainlobby";
$sender->sendMessage($this->getPrefix().$this->getMsg('break_block')); 
break; 
default: 
$sender->sendMessage($this->getPrefix().$this->getMsg('help')); 
} 
return true; 
} 
$sender->sendMessage('Run a command in-game server!'); 
return true; 
} 
$sender->sendMessage($this->getPrefix().$this->getMsg('help')); 
} 
return true; 
} 
public function zipper($sender, $name) { 
$path = realpath($sender->getServer()->getDataPath() . 'worlds/' . $name); 
$zip = new \ZipArchive; 
@mkdir($this->getDataFolder() . 'arenasmap/', 0755);
$zip->open($this->getDataFolder() . 'arenasmap/' . $name . '.zip', $zip::CREATE | $zip::OVERWRITE); 
$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator(($path)), \RecursiveIteratorIterator::LEAVES_ONLY ); 
                                  
foreach ($files as $datos) { 
if (!$datos->isDir()) { 
$relativePath = $name . '/' . substr($datos, strlen($path) + 1); 
$zip->addFile($datos, $relativePath); 
} 
} 
$zip->close(); 
$sender->getServer()->loadLevel($name); 
unset($zip, $path, $files); 
}  
public function arenaExist($name) { 
if(isset($this->arenas[$name])){ 
return true; 
} 
return false;
} 
public function getMsg($key) { 
$msg = $this->msg; 
return str_replace("&", "§", $msg->get($key)); 
} 
public function onBlockTouch(PlayerInteractEvent $e) { 
$p = $e->getPlayer(); 
$b = $e->getBlock(); 
if(isset($this->selectors[strtolower($p->getName())])){ 
$p->sendMessage(TextFormat::BLUE."X: ".TextFormat::GREEN.$b->x.TextFormat::BLUE." Y: ".TextFormat::GREEN.$b->y.TextFormat::BLUE." Z: ".TextFormat::GREEN.$b->z);
} 
} 
public function getPrefix() { 
return str_replace("&", "§", $this->cfg->get('9Prefix')); 
}
public function loadInvs() { 
foreach($this->getServer()->getOnlinePlayers() as $p){
if(isset($this->inv[strtolower($p->getName())])){ 
foreach($this->inv as $slot => $i){ 
list($id, $dmg, $count) = explode(":", $i); 
$item = Item::get($id, $dmg, $count); 
$p->getInventory()->setItem($slot, $item); 
unset($this->plugin->inv[strtolower($p->getName())]); 
} 
} 
} 
} 
public function onBlockBreak(BlockBreakEvent $e) {
$p = $e->getPlayer();
if(isset($this->setters[strtolower($p->getName())]['arena']) && isset($this->setters[strtolower($p->getName())]['type'])){
$e->setCancelled(true); 
$b = $e->getBlock(); 
$arena = new ConfigManager($this->setters[strtolower($p->getName())]['arena'], $this);
if($this->setters[strtolower($p->getName())]['type'] == "setjoinsign"){
$arena->setJoinSign($b->x, $b->y, $b->z, $b->level->getName());
$p->sendMessage($this->getPrefix().$this->getMsg('joinsign'));
unset($this->setters[strtolower($p->getName())]['type']); 
return; 
}
if($this->setters[strtolower($p->getName())]['type'] == "setreturnsign"){ 
$arena->setReturnSign($b->x, $b->y, $b->z); 
$p->sendMessage($this->getPrefix().$this->getMsg('returnsign'));
unset($this->setters[strtolower($p->getName())]['type']); 
return; 
} 
if($this->setters[strtolower($p->getName())]['type'] == "setjoinpos"){ 
$arena->setJoinPos($b->x, $b->y, $b->z); 
$arena->setArenaWorld($b->level->getName()); 
$p->sendMessage($this->getPrefix().$this->getMsg('startpos')); 
unset($this->setters[strtolower($p->getName())]['type']); 
return; 
} 
if($this->setters[strtolower($p->getName())]['type'] == "setlobbypos"){ 
$arena->setLobbyPos($b->x, $b->y, $b->z, $b->level->getName()); 
$p->sendMessage($this->getPrefix().$this->getMsg('lobbypos')); 
unset($this->setters[strtolower($p->getName())]['type']);  
return;
}
if($this->setters[strtolower($p->getName())]['type'] == "setspecspawn"){ 
$arena->setSpecSpawn($b->x, $b->y, $b->z);
$p->sendMessage($this->getPrefix().$this->getMsg('spectatorspawn')); 
unset($this->setters[strtolower($p->getName())]['type']); 
return;
}
if($this->setters[strtolower($p->getName())]['type'] == "setleavepos"){ 
$arena->setLeavePos($b->x, $b->y, $b->z, $b->level->getName());
$p->sendMessage($this->getPrefix().$this->getMsg('leavepos')); 
unset($this->setters[strtolower($p->getName())]['type']); 
return;
}
if($this->setters[strtolower($p->getName())]['type'] == "mainlobby"){
$this->cfg->setNested("lobby.x", $b->x);
$this->cfg->setNested("lobby.y", $b->y); 
$this->cfg->setNested("lobby.z", $b->z); 
$this->cfg->setNested("lobby.world", $b->level->getName()); 
$p->sendMessage($this->getPrefix().$this->getMsg('mainlobby'));
unset($this->setters[strtolower($p->getName())]['type']);
return;
}
}
}
public function onChat(PlayerChatEvent $e) { 
$p = $e->getPlayer(); 
$msg = strtolower(trim($e->getMessage())); 
if(isset($this->setters[strtolower($p->getName())]['arena'])){ 
$e->setCancelled(true); 
$arena = new ConfigManager($this->setters[strtolower($p->getName())]['arena'], $this); 
switch($msg){
case 'joinsign': 
$this->setters[strtolower($p->getName())]['type'] = 'setjoinsign'; 
$p->sendMessage($this->getPrefix().$this->getMsg('break_sign')); 
return; 
case 'returnsign': 
$this->setters[strtolower($p->getName())]['type'] = 'setreturnsign'; 
$p->sendMessage($this->getPrefix().$this->getMsg('break_sign'));
return; 
case 'startpos': 
$this->setters[strtolower($p->getName())]['type'] = 'setjoinpos'; 
$p->sendMessage($this->getPrefix().$this->getMsg('break_block')); 
return; 
case 'lobbypos': 
$this->setters[strtolower($p->getName())]['type'] = 'setlobbypos';
$p->sendMessage($this->getPrefix().$this->getMsg('break_block')); 
return; 
case 'spectatorspawn': 
$this->setters[strtolower($p->getName())]['type'] = 'setspecspawn'; 
$p->sendMessage($this->getPrefix().$this->getMsg('break_block')); 
return; 
case 'leavepos': 
$this->setters[strtolower($p->getName())]['type'] = 'setleavepos'; 
$p->sendMessage($this->getPrefix().$this->getMsg('break_block')); 
return;
case 'done': 
$p->sendMessage($this->getPrefix().$this->getMsg('disable_setup_mode')); 
$this->reloadArena($this->setters[strtolower($p->getName())]['arena']); 
unset($this->setters[strtolower($p->getName())]); 
return;
}
$args = explode(' ', $msg);
if(count($args) >= 1 && count($args) <= 2){
if($args[0] === 'help'){ 
$help1 = $this->getMsg('help_joinsign') . $this->getMsg('help_returnsign') . $this->getMsg('help_startpos') . $this->getMsg('help_lobbypos') . $this->getMsg('help_corners') . $this->getMsg('help_spectatorspawn') . $this->getMsg('help_leavepos'); 
$help2 = $this->getMsg('help_allowstatus') . $this->getMsg('help_world') . $this->getMsg('help_statusline'); 
$help3 = $this->getMsg('help_allowspectator') . $this->getMsg('help_signupdatetime') . $this->getMsg('help_maxtime') . $this->getMsg('help_maxplayers') . $this->getMsg('help_minplayers');
$helparray = [$help1, $help2, $help3]; 
if(isset($args[1])){
if(intval($args[1]) >= 1 && intval($args[1]) <= 3){ 
$help = "--- Setup Help $args[1]/3 ---"; 
$help .= $helparray[intval(intval($args[1]) - 1)];
$p->sendMessage($help); 
return; 
} 
$p->sendMessage($this->getPrefix()."Usage: help {page 1-3}"); 
return; 
} 
$p->sendMessage("--- Setup Help 1/3 ---".$help1); 
return; 
}
} 
switch(trim(strtolower($args[0]))){ 
} 
if(count(explode(' ', $msg)) >= 3 && strpos($msg, 'statusline') !== 0){ 
$p->sendMessage($this->getPrefix().$this->getMsg('invalid_arguments')); 
return; 
}
if(substr($msg, 0, 10) === 'statusline'){
if(!strlen(substr($msg, 13)) >= 1 || !intval(substr($msg, 11, 1)) >= 1 || !intval(substr($msg, 11, 1) <= 4)){ 
$p->sendMessage($this->getPrefix().$this->getMsg('statusline_help'));
return;
}
$arena->setStatusLine($args[1], substr($msg, 13));
$p->sendMessage($this->getPrefix().$this->getMsg('statusline')); 
return;
}
elseif(strpos($msg, 'enable') === 0){
if(substr($msg, 7) === 'true' || substr($msg, 7) === 'false'){ 
$arena->setEnable(substr($msg, 7)); 
$p->sendMessage($this->getPrefix().$this->getMsg('enable')); 
return;
}
$p->sendMessage($this->getPrefix().$this->getMsg('enable_help'));
return;
} elseif(strpos($msg, 'allowstatus') === 0){ 
if(substr($msg, 12) === 'true' || substr($msg, 12) === 'false'){ 
$arena->setStatus(substr($msg, 12));
$p->sendMessage($this->getPrefix().$this->getMsg('allowstatus')); 
return; 
} 
$p->sendMessage($this->getPrefix().$this->getMsg('allowstatus_help')); 
} elseif(strpos($msg, 'signupdatetime') === 0){
if(!is_numeric(substr($msg, 15))){
$p->sendMessage($this->getPrefix().$this->getMsg('signupdatetime'));
return; 
} 
$arena->setUpdateTime(substr($msg, 15)); 
$p->sendMessage($this->getPrefix().$this->getMsg('signupdatetime')); 
} elseif(strpos($msg, 'allowspectator') === 0){
if(substr($msg, 15) === 'true' || substr($msg, 15) === 'false'){ 
$arena->setSpectator(substr($msg, 15)); 
$p->sendMessage($this->getPrefix().$this->getMsg('allowspectator'));
return; 
} 
$p->sendMessage($this->getPrefix().$this->getMsg('allowspectator_help')); 
} elseif(strpos($msg, 'maxtime') === 0){ 
if(!is_numeric(substr($msg, 8))){ 
$p->sendMessage($this->getPrefix().$this->getMsg('maxtime_help')); 
return; 
} 
$arena->setMaxTime(substr($msg, 8)); 
$p->sendMessage($this->getPrefix().$this->getMsg('maxtime')); 
} elseif(strpos($msg, 'maxplayers') === 0){ 
if(!is_numeric(substr($msg, 11))){ 
$p->sendMessage($this->getPrefix().$this->getMsg('maxplayers_help'));
return;
} 
$arena->setMaxPlayers(substr($msg, 11)); 
$p->sendMessage($this->getPrefix().$this->getMsg('maxplayers'));
} elseif(strpos($msg, 'minplayers') === 0){
if(!is_numeric(substr($msg, 11))){ 
$p->sendMessage($this->getPrefix().$this->getMsg('minplayers_help'));
return; 
} 
$arena->setMinPlayers(substr($msg, 11)); 
$p->sendMessage($this->getPrefix().$this->getMsg('minplayers'));
} elseif(strpos($msg, 'starttime') === 0){ 
if(!is_numeric(substr($msg, 10))){ 
$p->sendMessage($this->getPrefix().$this->getMsg('starttime_help')); 
return; 
} 
$arena->setStartTime(substr($msg, 10));
$p->sendMessage($this->getPrefix().$this->getMsg('starttime')); 
} elseif(strpos($msg, 'time') === 0){ if(substr($msg, 5) === 'true' || substr($msg, 5) === 'day' || substr($msg, 5) === 'night' || is_numeric(substr($msg, 5))){ 
$arena->setTime(substr($msg, 5)); 
$p->sendMessage($this->getPrefix().$this->getMsg('time')); 
return;
} 
$p->sendMessage($this->getPrefix().$this->getMsg('time_help'));
} else { 
$p->sendMessage($this->getPrefix().$this->getMsg('invalid_arguments')); 
} 
}
}
public function onQuit(PlayerQuitEvent $e) { 
$p = $e->getPlayer(); 
$this->unsetPlayers($p); 
} 
public function onKick(PlayerKickEvent $e) { 
$p = $e->getPlayer(); 
$this->unsetPlayers($p); 
} 
public function unsetPlayers(Player $p) {
if(isset($this->selectors[strtolower($p->getName())])){ 
unset($this->selectors[strtolower($p->getName())]);
}
if(isset($this->setters[strtolower($p->getName())])){
$this->reloadArena($this->setters[strtolower($p->getName())]['arena']);
if($this->isArenaSet($this->setters[strtolower($p->getName())]['arena'])){ 
$a = new Arena($this->setters[strtolower($p->getName())]['arena'], $this); 
$a->setup = false; 
} 
unset($this->setters[strtolower($p->getName())]);
} 
} 
public function reloadArena($name) { 
$arena = new Config($this->getDataFolder()."arenas/$name.yml"); 
if(isset($this->ins[$name])) $this->ins[$name]->setup = false; 
if(!$this->checkFile($arena) || $arena->get('enabled') === "false"){ 
$this->arenas[$name] = $arena->getAll(); 
$this->arenas[$name]['enable'] = 'false'; 
return; 
} 
$this->arenas[$name] = $arena->getAll(); 
//$this->ins[$name]->data = $this->arenas[$name]; if run its you have error!
} 
public function getPlayerArena(Player $p) { 
foreach($this->ins as $arena){ 
$players = array_merge($arena->ingamep, $arena->lobbyp, $arena->spec); 
if(isset($players[strtolower($p->getName())])){ 
return $arena; 
}
} 
return false; 
} 
public function isArenaSet($name) { 
if(isset($this->ins[$name]))
return true; 
return false; 
} 
public function registerEconomy() { 
$economy = ["EconomyAPI"]; 
foreach($economy as $plugin){
$ins = $this->getServer()->getPluginManager()->getPlugin($plugin);
if($ins instanceof Plugin && $ins->isEnabled()){ 
$this->economy = $ins; 
$this->getServer()->getLogger()->info("Please install the plugin $plugin!"); 
return; 
} 
} 
$this->economy = null;
}
}


